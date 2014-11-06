<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Httpd;
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
     * @type Httpd
     */
    protected $httpd;

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
     * @param Httpd $httpd
     * @param Vhost                  $vhost
     * @param ClientRequestInterface $clientRequest
     */
    public function __construct(Httpd $httpd, Vhost $vhost, ClientRequestInterface $clientRequest)
    {
        $this->httpd = $httpd;
        parent::__construct($clientRequest->getMethod(), $clientRequest->getUrl(), $clientRequest->getHeaders());
        $this->setBody($clientRequest->getBody());
        $this->vhost         = $vhost;
        $this->clientRequest = $clientRequest;
        $this->clientRequest->setUpstreamRequest($this);
        $this->prepareHeaders();
        $this->setState(ClientRequestInterface::STATE_PENDING);
    }

    /**
     * Return vhost
     *
     * @return Vhost
     */
    public function getVhost()
    {
        return $this->vhost;
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
     * Send's the request to upstream server
     * @param null $buffer
     */
    public function send($buffer = null)
    {
        $message = null;

        if ($this->headersSent === FALSE)
        {
            $message = $this->getRawHeaders() . "\r\n\r\n" . $this->clientRequest->getBody();
            $this->headersSent = true;
            $this->setState(self::STATE_SENDING);
        }
        else
        {
            $message = $buffer;
        }

        if ($message) {
            Logger::getInstance()->log(-100, "\n" . '----------- Request Write: ' . $this->id . ' -----------' . "\n" .
                                             $message .
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
        $errorCode     =& $this->parsingAttrs['errorCode'];
        $packets++;

        if ($errorCode)
            return $errorCode;

        Logger::getInstance()
              ->log(
                  -100,
                  "\n" . '----------- Upstream Read: ' . $this->id . ' -----------' . "\n" .
                  $data .
                  "\n" . '----------- /Upstream Read: ' . $this->id . ' -----------' . "\n");

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
                }
                // http://stackoverflow.com/a/11375745/394870
                else if ($this->protocolVersion == '1.0')
                {
                    $methodEOM = '1.0';

                    $this->stream->on(
                        'close',
                        function ($stream)
                        {

                            $this->stateParsing = self::STATE_PARSING_EOM;
                            $this->state        = self::STATE_EOM;
                            $this->setExecutionTime();
                            $this->clientRequest->setExecutionTime()
                                                ->setState(self::STATE_EOM);

                            $this->httpd->handleUpstreamInboundRequestDone($this);
                        });
                }
                else
                {
                    $errorCode = 400;
                }

                $response = new Response($parsed['code'], $parsed['headers']);
                $response->setProtocolVersion($parsed['protocol']);
                $response->setReasonPhrase($parsed['reason_phrase']);

                $this->clientRequest->setResponse($response);
            }
            // we are unable to parse this request, its bad..
            else {
                $errorCode = 402;
            }
        }
        // we already have detected methodEOM, now body is the same as $data (i.e. it doesn't include headers)
        else {
            $body = $data;
        }

        if (!$errorCode) {

            $this->clientRequest->reply($body);

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
                $this->setExecutionTime();
                $this->clientRequest->setExecutionTime()
                                    ->setState(self::STATE_EOM);
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
     * Upstream reply is client's request response
     *
     * @param int $code
     * @param null $message
     */
    public function error($code, $message = NULL)
    {
        //        $this->cleanup();
        //        $this->clientRequest->error($code, $message);
    }

    /**
     * Cleanups internal registry
     */
    public function cleanup()
    {
        $this->clientRequest->cleanup();
        unset($this->clientRequest);

        //        if (!$this->vhost->config->get('upstreams.keepalive.max') && !$this->vhost->config->get('upstreams.keepalive.max'))
        //        {
        //            $this->stream->end();
        //        }
    }
}
