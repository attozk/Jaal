<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\IO\React\SocketClient\Stream;

Class Request extends \Hathoora\Jaal\Daemons\Http\Message\Request implements RequestInterface
{
    /**
     * @var Vhost
     */
    protected $vhost;

    /**
     * @var ClientRequestInterface
     */
    protected $clientRequest;

    /**
     * @var Stream
     */
    protected $stream;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * This is true when headers has been sent to upstream server
     *
     * @var bool
     */
    protected $headersSent = false;

    /**
     * @param Vhost                  $vhost
     * @param ClientRequestInterface $clientRequest
     */
    public function __construct(Vhost $vhost, ClientRequestInterface $clientRequest)
    {
        parent::__construct($clientRequest->getMethod(), $clientRequest->getUrl(), $clientRequest->getHeaders());
        $this->setBody($clientRequest->getBody());
        $this->vhost         = $vhost;
        $this->clientRequest = $clientRequest;
        $this->clientRequest->setUpstreamRequest($this);
        $this->prepareHeaders();
        $this->setState(ClientRequestInterface::STATE_PENDING);
    }

    /**
     * Prepares headers for the request which would be sent to upstream (from Jaal server)
     */
    protected function prepareHeaders()
    {
        if ($version = $this->vhost->config->get('http_version')) {
            $this->setProtocolVersion($version);
        }

        // setting new proxy request headers
        $arrHeaders = $this->vhost->config->get('headers.server_to_upstream_request');

        foreach ($arrHeaders as $header => $value) {
            $this->addHeader($header, $value);
        }

        // copy headers from original (client) request to request we will make to upstream
        $arrClientHeaders = $this->clientRequest->getHeaders();
        foreach ($arrClientHeaders as $header => $value) {
            $header = strtolower($header);

            if (isset(Headers::$arrAllowedUpstreamHeaders[$header]) && !$this->hasHeader($header)) {
                $this->addHeader($header, $value);
            }
        }

        // @TODO added for testing only..
        $this->removeHeader('accept-encoding');
    }

    /**
     * Return  client's request
     *
     * @return ClientRequestInterface
     */
    public function getClientRequest()
    {
        return $this->clientRequest;
    }

    /**
     * Set outbound stream
     *
     * @param Stream $stream
     * @return self
     */
    public function setStream(Stream $stream)
    {
        $this->stream = $stream;

        return $this;
    }

    /**
     * Returns connection stream socket
     *
     * @return Stream
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Set upstream response
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
     * Get upstream response
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Send's the request to upstream server
     * @param null $buffer
     */
    public function send($buffer = null)
    {
        $message = null;

        if ($buffer && $this->headersSent === false) {
            $write = $this->getRawHeaders() . "\r\n\r\n" . $this->clientRequest->getBody();
            $this->headersSent = true;
            $this->setState(self::STATE_SENDING);
        }
        else if ($buffer)
        {
            $write = $buffer;
        }

        if ($message) {
            Logger::getInstance()->log(-100, "\n" . '----------- Request Write: ' . $this->id . ' -----------' . "\n" .
                $write .
                "\n" . '----------- /Request Write: ' . $this->id . ' -----------' . "\n");

            $this->stream->write($message);
        }

        // clear buffer when client request has reached EOM
        if ($this->clientRequest->getStateParsing() == ClientRequestInterface::STATE_PARSING_EOM)
        {
            $this->clientRequest->setParsingAttr('buffer', '');
            $this->setState(self::STATE_RETRIEVING);
        }
    }

    /**
     * Reads incoming data from upstream to make sense
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
            if (($parsed = Parser::parseResponse($data)) && isset($parsed['code']) && isset($parsed['headers']))
            {
                // don't include headers when calculating size of message
                $body = $parsed['body'];

                if (isset($parsed['headers']['content-length'])) {
                    $contentLength    = $parsed['headers']['content-length'];
                    $methodEOM = 'length';
                }
                else if (isset($parsed['headers']['transfer-encoding']) && preg_match('/chunked/i', $parsed['headers']['transfer-encoding'])) {
                    $methodEOM = 'chunked';
                } else {
                    $errorCode = 400;
                }

                $this->response = new Response($parsed['code'], $parsed['headers']);
                $this->response->setReasonPhrase($parsed['reason_phrase'])
                               ->setBody($body);
            }
            // we are unable to parse this request, its bad..
            else {
                $errorCode = 402;
            }
        }
        // we already have detected methodEOM, now body is the same as $data (i.e. it doesn't include headers)
        else {
            $body = $data;

            // reply back to client right away?
            #if ($this->upstreamRequest) {
            #    $this->upstreamRequest->send($data);
            #} // keep buffering
            #else {
            $buffer .= $data;
            #}
        }

        if (!$errorCode) {

            if ($methodEOM == 'chunked')
            {
                /*
                for ($res = ''; !empty($str); $str = trim($str)) {
                    $pos = strpos($str, "\r\n");
                    $len = hexdec(substr($str, 0, $pos));
                    $res.= substr($str, $pos + 2, $len);
                    $str = substr($str, $pos + 2 + $len);
                }
                return $res;

                if ($chunk_length === false) {
                    $data = trim(fgets($fp, 128));
                    $chunk_length = hexdec($data);
                } else if ($chunk_length > 0) {
                    $read_length = $chunk_length > $readBlockSize ? $readBlockSize : $chunk_length;
                    $chunk_length -= $read_length;
                    $data = fread($fp, $read_length);
                    fwrite($wfp, $data);
                    if ($chunk_length <= 0) {
                        fseek($fp, 2, SEEK_CUR);
                        $chunk_length = false;
                    }
                } else {
                     break;
                }
                */
            }
            else if ($methodEOM == 'length')
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
                $this->state = self::STATE_EOM;
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

//    /**
//     * Handles upstream output data which the server is reading
//     *
//     * @param                                             $data
//     * @return void
//     */
//    public function handleInboundData($data)
//    {
//
//
////        $consumed  =& $this->handleUpstreamDataAttrs['consumed'];
////        $length    =& $this->handleUpstreamDataAttrs['length'];
////        $methodEOM =& $this->handleUpstreamDataAttrs['methodEOM'];
////        $hasError  =& $this->handleUpstreamDataAttrs['hasError'];
////        $buffer    =& $this->handleUpstreamDataAttrs['buffer'];
////        $segments  =& $this->handleUpstreamDataAttrs['segments'];
////
////        $segments++;
////        $isEOM    = FALSE;
////        $response = NULL;
////        $buffer .= $data;
////
////        if (!$methodEOM) {
////
////            Logger::getInstance()
////                  ->log(-100, "\n" . '----------- Request Read: ' . $this->id . ' -----------' . "\n" .
////                              $data .
////                              "\n" . '----------- /Request Read: ' . $this->id . ' -----------' . "\n");
////
////            echo "---------------------------\n";
////            echo preg_replace_callback("/(\n|\r)/",
////            function ($match) {
////                return ($match[1] == "\n" ? '\n' . "\n" : '\r');
////            },
////                                       $data);
////            echo "---------------------------\n";
////
////            // @TODO no need to parse entire message, just look for content-length
////
////            if (strlen($data)) {
////                $response = Parser::getResponse($data);
////            }
////
////            if ($response) {
////                if ($response->hasHeader('Content-Length')) {
////                    $length    = $response->getHeader('Content-Length');
////                    $methodEOM = 'length';
////                } else if ($response->hasHeader('Transfer-Encoding') && ($header = $response->getHeader('Transfer-Encoding')) && $header == 'chunked') {
////                    $methodEOM = 'chunk';
////                } else {
////                    $hasError = 400;
////                }
////
////                // remove header from body as we keep track of body length
////                $data = $response->getBody();
////            } else {
////                $hasError = 401;
////            }
////        }
////
////        if (!$hasError) {
////            // @TODO check of end of message in chunk mode
////            if ($methodEOM == 'chunk' && $data = "") {
////                $isEOM = TRUE;
////            } else if ($methodEOM == 'length') {
////
////                $consumed += strlen($data);
////
////                if ($consumed >= $length) {
////                    $isEOM = TRUE;
////                }
////            }
////
////            if ($isEOM) {
////
////                if ($response) {
////                    $this->response = $response;
////                } else {
////                    $this->response = Parser::getResponse($buffer);
////                }
////
////                if ($this->response instanceof ResponseInterface) {
////                    $this->setExecutionTime();
////                    $this->response->setMethod($this->getMethod());
////                    $this->setState(self::STATE_DONE);
////                    $this->clientRequest->setResponse(clone $this->response);
////                    $this->reply();
////                } else {
////                    $hasError = 404;
////                }
////            }
////        }
////
////        if ($hasError) {
////            $this->setState(self::STATE_ERROR);
////            $this->reply(500);
////        }
//    }

    /**
     * Prepares client's response headers once upstream's response has been received
     */
    protected function prepareClientResponseHeader()
    {
        if ($this->clientRequest->getResponse()) {
            $arrHeaders = $this->vhost->config->get('headers.upstream_to_client_response');

            foreach ($arrHeaders as $header => $value) {
                if ($value === FALSE) {
                    $this->clientRequest->getResponse()->removeHeader($header);
                } else {
                    $this->clientRequest->getResponse()->addHeader($header, $value);
                }
            }

            $this->clientRequest->getResponse()->addHeader('Exec-Time', $this->clientRequest->getExecutionTime());
            $this->clientRequest->getResponse()->addHeader('X-Exec-Time', $this->getExecutionTime());
            $this->clientRequest->setExecutionTime();
        }
    }

    /**
     * Upstream reply is client's request response
     *
     * @param null $code to overwrite upstream response
     * @param null $message
     */
    public function reply($code = NULL, $message = NULL)
    {
        $this->prepareClientResponseHeader();
        $this->cleanup();
        $this->clientRequest->reply($code, $message);
    }

    /**
     * Cleanups internal registry
     */
    private function cleanup()
    {
        Logger::getInstance()
              ->log(-99,
                  'UPSTREAM RESPONSE (' . $this->state . ') ' . Logger::getInstance()->color($this->getUrl(), 'red') .
                  ' using remote stream: ' . Logger::getInstance()->color($this->stream->id, 'green'));
        Jaal::getInstance()->getDaemon('httpd')->outboundIOManager->removeProp($this->stream, 'request');

        if (!$this->vhost->config->get('upstreams.keepalive.max') &&
            !$this->vhost->config->get('upstreams.keepalive.max')
        ) {
            $this->stream->end();
        }
        //unset($this);
    }
}
