<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

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
    public function handleInboundData($data)
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
                $methodEOM = 'length';
                // don't include headers when calculating size of message
                $body = $parsed['body'];
                $this->body = $body;

                $httpMethod = strtoupper($parsed['method']);
                $this->method = $httpMethod;
                $this->protocolVersion = $parsed['protocol_version'];

                $this->setScheme($parsed['request_url']['scheme'])
                     ->setHost($parsed['request_url']['host'])
                     ->setPath($parsed['request_url']['path'])
                     ->setPort($parsed['request_url']['port'])
                     ->setQuery($parsed['request_url']['query']);

                if (isset($parsed['headers']['content-length'])) {
                    $contentLength = $parsed['headers']['content-length'];
                }

                // POST|PUT must have content length if body is present
                if (($httpMethod == 'PUT' || $httpMethod == 'POST') && $contentLength == 0 && $body) {
                    $errorCode = 401;
                }
            }
            // we are unable to parse this request, its bad..
            else {
                $errorCode = 402;
            }
        }
        // we already have detected methodEOM, now body is the same as $data (i.e. it doesn't include headers)
        else {
            $body = $data;

            // if upstream attached, then send write away
            if ($this->upstreamRequest) {
                $this->upstreamRequest->send($data);
            } // keep buffering
            else {
                $buffer .= $data;
            }
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
     * Reply to client's request using $stream
     *
     * @param string $code to overwrite response's status code
     * @param string $message to overwrite response's body
     * @param bool $streamEnd to end the stream after reply (overwrite keep-alive settings)
     * @return mixed
     */
    public function reply($code = '', $message = '', $streamEnd = false)
    {
        $this->setState(self::STATE_DONE);
        $responseCreated = FALSE;

        if (!$this->response) {
            $this->response = new Response($code);
            $this->response->setReasonPhrase($message);
            $responseCreated = TRUE;
        }

        $this->prepareResponseHeaders();

        if ($code && $responseCreated === FALSE) {
            $this->response->setStatusCode($code);
            $this->response->setReasonPhrase($message);
        }

        $this->stream->write($this->response->getRawHeaders() . "\r\n" . $this->response->getBody());
        $this->cleanup($streamEnd);
    }

    /**
     * Cleanups internal registry
     * @param bool $streamEnd to end the stream after reply (overwrite keep-alive settings)
     */
    private function cleanup($streamEnd = false)
    {
        Logger::getInstance()
              ->log(-99,
                    'REPLY (' . $this->state . ') ' . Logger::getInstance()->color($this->getUrl(), 'red') .
                    ' using stream: ' . Logger::getInstance()->color($this->stream->id, 'green'));

        Jaal::getInstance()->getDaemon('httpd')->inboundIOManager->removeProp($this->stream, 'request');

        if ($streamEnd ||
            (!Jaal::getInstance()->config->get('httpd.keepalive.max') && !Jaal::getInstance()->config->get('httpd.keepalive.max')))
        {
            $this->stream->end();
        }

        //unset($this);
    }

    /**
     * Prepare response headers (based on config) to send to client
     */
    private function prepareResponseHeaders()
    {
        $keepAliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
        $keepAliveMax     = Jaal::getInstance()->config->get('httpd.keepalive.max');

        #if ($this->response->getProtocolVersion() != $this->getProtocolVersion()) {
        #    $this->response->setProtocolVersion($this->getProtocolVersion());
        #}

        if ($this->getHeader('connection') != 'close' && $keepAliveTimeout && $keepAliveMax) {
            $this->response->addHeader('Connection', 'keep-alive');
            $this->response->addHeader('Keep-Alive', 'timeout=' . $keepAliveTimeout . ', max=' . $keepAliveMax);
        } else {
            $this->response->addHeader('Connection', 'close');
        }

        $this->response->addHeader('Server', Jaal::name);
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
}
