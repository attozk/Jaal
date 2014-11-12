<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Evenement\EventEmitterTrait;
use Hathoora\Jaal\Daemons\Http\Httpd;
use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\IO\Manager\InboundManager;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;

/**
 * Class Request for handling a request from client and responding back
 *
 * @emit    inbound.buffering [$this, $buffer] when request is being read including up to eom
 * @emit    inbound.eom [$this] messaged parsed and no more incoming data from client
 * @emit    inbound.error [$this, $code] when there is an error parsing request
 * @emit    upstream.ready [$this] stream is ready to be handled by upstream
 * @emit    response.headers [$this] when response headers are ready
 * @emit    outbound.buffering [$this, $buffer] when response is being sent to client including up to eom
 * @emit    outbound.eom [$this] entire response has been sent to client
 * @emit    done [$this, $closeStream] when response has been sent back to client
 *
 * @package Hathoora\Jaal\Daemons\Http\Client
 */
Class Request extends \Hathoora\Jaal\Daemons\Http\Message\Request implements RequestInterface
{
    use EventEmitterTrait;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var UpstreamRequestInterface
     */
    protected $upstreamRequest;

    /**
     * @var ConnectionInterface|\Hathoora\Jaal\IO\React\Socket\Connection
     */
    protected $stream;

    public function __construct($method = null, $url = null, $headers = [])
    {
        parent::__construct($method, $url, $headers);
    }

    /**
     * Sets connection stream to client or proxy
     *
     * @param ConnectionInterface $stream
     *
     * @return self
     */
    public function setStream(ConnectionInterface $stream)
    {
        $this->stream = $stream;

        return $this;
    }

    /**
     * Returns connection stream socket
     *
     * @return ConnectionInterface
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Reads incoming data to parse it into a message
     *
     * @param $data
     *
     * @emit inbound.buffering [$this, $buffer] when request is being read including up to eom
     * @emit inbound.eom [$this] messaged parsed and no more incoming data from client
     * @emit inbound.error [$this, $code] when there is an error parsing request
     */
    public function onInboundData($data)
    {
        /**
         * Status value
         * NULL    being processed
         * TRUE    when message has reached EOM
         * INT     when error code
         */
        $status        = null;
        $hasReachedEOM = null;
        $consumed      =& $this->parsingAttrs['consumed'];
        $methodEOM     =& $this->parsingAttrs['methodEOM'];
        $contentLength =& $this->parsingAttrs['contentLength'];
        $packets       =& $this->parsingAttrs['packets'];
        $buffer        =& $this->parsingAttrs['buffer'];
        $errorCode     =& $this->parsingAttrs['errorCode'];
        $packets++;

        if (!$errorCode)
        {
            Logger::getInstance()->log(-100, '----------- Request Read: ' . $this->id . ' -----------' . "\n" .
                                             $data . "\n" .
                                             '----------- /Request Read: ' . $this->id . ' -----------' . "\n");

            //        echo "---------------------------\n";
            //        echo preg_replace_callback("/(\n|\r)/", function ($match) {
            //                return ($match[1] == "\n" ? '\n' . "\n" : '\r');
            //            },
            //            $data);
            //        echo "---------------------------\n";

            if ($this->stateParsing != self::STATE_PARSING_PROCESSING)
                $this->stateParsing = self::STATE_PARSING_PROCESSING;

            // start of message
            if (!$methodEOM)
            {
                if (($parsed = Parser::parseRequest($data)) && isset($parsed['method']) && isset($parsed['headers']) &&
                    isset($parsed['request_url']) && isset($parsed['request_url']['host'])
                )
                {
                    // always assume length to be EOM
                    $methodEOM             = 'length';
                    $body                  = $parsed['body'];
                    $httpMethod            = strtoupper($parsed['method']);
                    $this->body            = $body;
                    $this->method          = $httpMethod;
                    $this->protocolVersion = $parsed['protocol_version'];
                    $this->setScheme($parsed['request_url']['scheme'])
                         ->addHeaders($parsed['headers'])
                         ->setHost($parsed['request_url']['host'])
                         ->setPath($parsed['request_url']['path'])
                         ->setPort($parsed['request_url']['port'])
                         ->setQuery($parsed['request_url']['query']);

                    $contentLength = $this->getSize();

                    // POST|PUT must have content length if body is present
                    if (($httpMethod == 'PUT' || $httpMethod == 'POST') && $contentLength == 0 && $body)
                        $errorCode = 401;
                }
                // we are unable to parse this request, its bad..
                else
                    $errorCode = 402;
            }
            // we already have detected methodEOM, now body is the same as $data (i.e. it doesn't include headers)
            else
            {
                $body = $data;
                $buffer .= $data;
            }

            if (!$errorCode)
            {
                if ($methodEOM == 'length')
                {
                    $consumed += strlen($body);
                    if ($consumed > $consumed)
                        $errorCode = 405;
                    else if ($consumed == $contentLength)
                        $hasReachedEOM = true;
                }

                if ($hasReachedEOM)
                {
                    $this->stateParsing = self::STATE_PARSING_EOM;
                    $status             = $hasReachedEOM;
                }
            }
            else if ($errorCode)
            {
                $this->stateParsing = self::STATE_PARSING_ERROR;
                $this->state        = self::STATE_ERROR;
                $status             = $errorCode;
            }

            if (is_int($status))
                $this->emit('inbound.error', [$this, $errorCode]);
            else
            {
                $this->emit('inbound.buffering', [$this, $buffer]);
                if ($status === true)
                    $this->emit('inbound.eom', [$this]);

                $this->upstreamReadiness();
            }
        }
    }

    /**
     * At this point we emit request's readiness to be handled by proxy server. This behavior may need to
     * be revisited because as a comparison Nginx would keep client_body_buffer_size in memory if the size is
     * greater than that it would write the request's data to local disk.
     *
     * @emit upstream.ready [$this] stream is ready to be handled by upstream
     */
    protected function upstreamReadiness()
    {
        if ($this->getParsingAttr('packets') == 1)
            $this->emit('upstream.ready', [$this]);
    }

    /**
     * Set the upstream request
     *
     * @param UpstreamRequestInterface $request
     *
     * @return self
     */
    public function setUpstreamRequest(UpstreamRequestInterface $request)
    {
        $this->upstreamRequest = $request;

        return $this;
    }

    /**
     * Get the upstream request
     *
     * @return UpstreamRequestInterface
     */
    public function getUpstreamRequest()
    {
        return $this->upstreamRequest;
    }

    /**
     * Set response to this request
     *
     * @param ResponseInterface $response
     *
     * @return self
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get response of this request
     *
     * @return ResponseInterface $response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Reply to client
     *
     * Typically upstream's response is send to client as a series of data
     *
     * @param $buffer
     *
     * @return self
     */
    public function onOutboundData($buffer)
    {
        static $consumed;

        $message               = null;

        if ($this->headersSent == false)
        {
            $this->prepareResponseHeaders();
            $message           = $this->response->getRawHeaders() . "\r\n" . $buffer;
            $this->headersSent = true;
            $this->state       = self::STATE_SENDING;
        }
        else
            $message = $buffer;

        Logger::getInstance()->log(-100, '----------- Request Replying: ' . $this->id . ' -----------' . "\n" .
                                         $message . "\n" .
                                         '----------- /Request Replying: ' . $this->id . ' -----------' . "\n");

        $this->stream->write($message);

        // figure out if all the message data has been sent to the client?
        if ($this->upstreamRequest && $this->upstreamRequest->getStateParsing() == self::STATE_PARSING_EOM)
        {
            $this->setExecutionTime()
                 ->setState(self::STATE_EOM)
                 ->hasBeenReplied();
        }

        return $this;
    }

    /**
     * Respond to client as error
     *
     * @param string $code
     * @param string $message     if any
     * @param bool   $closeStream to end the stream after reply (overwrite keep-alive settings)
     *
     * @return self
     */
    public function error($code, $message = '', $closeStream = false)
    {
        $this->response = new Response($code);
        $this->setExecutionTime();
        $this->prepareResponseHeaders();
        $this->response->setStatusCode($code);
        $this->response->setReasonPhrase($message);

        $this->stream->write($this->response->getRawHeaders() . "\r\n" . $this->response->getBody());
        Logger::getInstance()->log(-99, 'ERROR (' . $this->state . ') ' .
                                        Logger::getInstance()->color($this->getUrl(), 'red') . ' using stream: ' .
                                        Logger::getInstance()->color($this->stream->id, 'green'));
        $this->hasBeenReplied($closeStream);

        return $this;
    }

    /**
     * When the message has been replied to the client, this function needs to be called
     *
     * @param bool $closeStream to end the stream after reply (overwrite keep-alive settings)
     *
     * @emit done [$this, $closeStream] when response has been sent back to client
     */
    public function hasBeenReplied($closeStream = false)
    {
        $this->setState(self::STATE_DONE);
        $this->setExecutionTime();
        $this->emit('done', [$this, $closeStream]);
    }

    /**
     * Prepare response headers to send to client
     *
     * @emit response.headers [$this] when response headers are ready
     */
    private function prepareResponseHeaders()
    {
        $keepAliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
        $keepAliveMax     = Jaal::getInstance()->config->get('httpd.keepalive.max');

        if ($this->response->getProtocolVersion() != $this->protocolVersion)
            $this->response->setProtocolVersion($this->protocolVersion);

        if ($this->upstreamRequest)
        {
            $arrHeaders = $this->upstreamRequest->getVhost()->config->get('headers.toClient');

            foreach ($arrHeaders as $header => $value)
            {
                if ($value === '')
                    $this->response->removeHeader($header);
                else
                    $this->response->addHeader($header, $value);
            }
        }

        if ($this->protocolVersion == '1.1' && $this->getHeader('connection') != 'close' && $keepAliveTimeout && $keepAliveMax
        )
        {
            $this->response->addHeader('Connection', 'keep-alive');
            $this->response->addHeader('Keep-Alive', 'timeout=' . $keepAliveTimeout . ', max=' . $keepAliveMax);
        }
        $this->response->addHeader('Server', Jaal::name);

        $this->emit('response.headers', [$this]);
    }

    /**
     * Cleanups internal registry
     */
    public function cleanup()
    {
        unset($this->upstreamRequest); // TOOD: not sure if we need this, profiler later
        $this->removeAllListeners();
        unset($this);
    }
}
