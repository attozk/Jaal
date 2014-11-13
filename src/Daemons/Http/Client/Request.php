<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Daemons\Http\Httpd;
use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\IO\Manager\InboundManager;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;

Class Request extends \Hathoora\Jaal\Daemons\Http\Message\Request implements RequestInterface
{
    /**
     * @type Httpd
     */
    protected $httpd;

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

    public function __construct(Httpd $httpd, $method = NULL, $url = NULL, $headers = [])
    {
        $this->httpd = $httpd;
        parent::__construct($method, $url, $headers);
    }

    /**
     * Sets connection stream to client or proxy
     *
     * @param ConnectionInterface $stream
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
     * Reads incoming data (when more than buffer) to parse it into a message
     *
     * @param $data
     * @return null|bool|int
     *      NULL    being processed
     *      TRUE    when message has reached EOM
     *      INT     when error code
     */
    public function onInboundData($data)
    {
        $hasReachedEOM = $status = null;
        $consumed      =& $this->parsingAttrs['consumed'];
        $methodEOM     =& $this->parsingAttrs['methodEOM'];
        $contentLength =& $this->parsingAttrs['contentLength'];
        $packets       =& $this->parsingAttrs['packets'];
        $buffer        =& $this->parsingAttrs['buffer'];
        $errorCode     =& $this->parsingAttrs['errorCode'];
        $packets++;

        if ($errorCode)
            return $errorCode;

        Logger::getInstance()->log(-100, "\n" . '----------- Request Read: ' . $this->id . ' -----------' . "\n" .
            $data .
            "\n" . '----------- /Request Read: ' . $this->id . ' -----------' . "\n");


//        echo "---------------------------\n";
//        echo preg_replace_callback("/(\n|\r)/", function ($match) {
//                return ($match[1] == "\n" ? '\n' . "\n" : '\r');
//            },
//            $data);
//        echo "---------------------------\n";


        if ($this->stateParsing != self::STATE_PARSING_PROCESSING) {
            $this->stateParsing = self::STATE_PARSING_PROCESSING;
        }

        // start of message
        if (!$methodEOM) {
            if (($parsed = Parser::parseRequest($data)) && isset($parsed['method']) && isset($parsed['headers']) &&
                isset($parsed['request_url']) && isset($parsed['request_url']['host']))
            {
                // always assume length to be EOM
                $methodEOM    = 'length';
                $body         = $parsed['body'];
                $httpMethod   = strtoupper($parsed['method']);
                $this->body   = $body;
                $this->method = $httpMethod;
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
            else {
                $errorCode = 402;
            }
        }
        // we already have detected methodEOM, now body is the same as $data (i.e. it doesn't include headers)
        else {
            $body = $data;
            $buffer .= $data;
        }

        if (!$errorCode) {

            if ($methodEOM == 'length')
            {
                $consumed += strlen($body);

                if ($consumed > $consumed) {
                    $errorCode = 405;
                }
                else if ($consumed == $contentLength) {
                    $hasReachedEOM = true;
                }
            }

            //echo "CONSUMED $consumed out of $contentLength \n";

            if ($hasReachedEOM) {
                $this->stateParsing = self::STATE_PARSING_EOM;
                $status = $hasReachedEOM;
            }
        }
        else if ($errorCode) {
            $this->stateParsing = self::STATE_PARSING_ERROR;
            $this->state = self::STATE_ERROR;
            $status = $errorCode;
        }

        return $status;
    }

    /**
     * Set the upstream request
     *
     * @param UpstreamRequestInterface $request
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
     * @return self
     */
    public function onOutboundData($buffer)
    {
        $message = NULL;

        if ($this->headersSent == FALSE)
        {
            $this->prepareResponseHeaders();
            $message           = $this->response->getRawHeaders() . "\r\n" . $buffer;
            $this->headersSent = TRUE;
            $this->state       = self::STATE_SENDING;

            Logger::getInstance()
                  ->log(
                      -99,
                      'REPLYING (' . $this->state . ') ' . Logger::getInstance()->color(
                          $this->getUrl(),
                          'red') .
                      ' using stream: ' . Logger::getInstance()->color($this->stream->id, 'green'));
        }
        else
            $message = $buffer;

        Logger::getInstance()->log(-100, "\n" . '----------- Request Replying: ' . $this->id . ' -----------' . "\n" .
            $message .
            "\n" . '----------- /Request Replying: ' . $this->id . ' -----------' . "\n");

        $this->stream->write($message);

        return $this;
    }

    /**
     * Respond to client as error
     *
     * @param string $code
     * @param string $message if any
     * @param bool $closeStream to end the stream after reply (overwrite keep-alive settings)
     * @return self
     */
    public function error($code, $message = '', $closeStream = FALSE)
    {
        $this->response = new Response($code);
        $this->setExecutionTime();
        $this->prepareResponseHeaders();
        $this->response->setStatusCode($code);
        $this->response->setReasonPhrase($message);
        $this->stream->write($this->response->getRawHeaders() . "\r\n" . $this->response->getBody());
        $this->hasBeenReplied($closeStream);

        return $this;
    }

    /**
     * When the message has been replied to the client, this function needs to be called
     *
     * @param bool $closeStream to end the stream after reply (overwrite keep-alive settings)
     */
    public function hasBeenReplied($closeStream = FALSE)
    {
        $this->setState(self::STATE_DONE);
        $this->httpd->onClientRequestDone($this, $closeStream);
    }


    /**
     * Prepare response headers (based on config) to send to client
     */
    private function prepareResponseHeaders()
    {
        $keepAliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
        $keepAliveMax     = Jaal::getInstance()->config->get('httpd.keepalive.max');

        if ($this->response->getProtocolVersion() != $this->protocolVersion)
        {
            $this->response->setProtocolVersion($this->protocolVersion);
        }

        if ($this->upstreamRequest)
        {
            $arrHeaders = $this->upstreamRequest->getVhost()->config->get('headers.toClient');

            foreach ($arrHeaders as $header => $value)
            {
                if ($value === '')
                {
                    $this->response->removeHeader($header);
                }
                else
                {
                    $this->response->addHeader($header, $value);
                }
            }
        }

        if ($this->protocolVersion == '1.1' && $this->getHeader('connection') != 'close' && $keepAliveTimeout &&
            $keepAliveMax
        )
        {
            $this->response->addHeader('Connection', 'keep-alive');
            $this->response->addHeader('Keep-Alive', 'timeout=' . $keepAliveTimeout . ', max=' . $keepAliveMax);
        } else {
            $this->response->addHeader('Connection', 'close');
        }
    }

    /**
     * Cleanups internal registry
     * @param bool $streamEnd to end the stream after reply (overwrite keep-alive settings)
     */
    public function cleanup($streamEnd = FALSE)
    {
        unset($this->upstreamRequest);
    }
}
