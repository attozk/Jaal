<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Upstream\Http\Pool;
use React\Stream\Stream;

class RequestUpstream extends Request implements RequestUpstreamInterface
{
    private $jStartMTime;         // request start micro time
    private $jExecTime;           // request execution time im milliseconds

    /**
     * Client's request to Jaal server
     * @var Request
     */
    private $requestClient;

    /**
     * The response we got back from Upstream server as a result of Jaal's request
     * @var ResponseUpstream
     */
    private $responseUpstream;

    /**
     * @param Pool $pool
     * @param RequestInterface $request
     */
    public function __construct(Pool $pool, RequestInterface $request)
    {
        $this->setStartTime();
        $this->pool = $pool;
        $this->requestClient = $request;

        parent::__construct($request->getMethod(), $request->getUrl());

        $this->prepareHeaders();

        Logger::getInstance()->debug($this->getClientRequest()->getClientSocket()->getRemoteAddress() . ' ' .
            $this->getClientRequest()->getMethod() . ' ' . $this->getClientRequest()->getUrl() . ' >> UPSTREAM >> ' .
            $this->getUrl());
    }

    /**
     * @param Request $request
     */
    public function setClientRequest(Request $request)
    {
        $this->requestClient = $request;
    }

    /**
     * @return Request
     */
    public function getClientRequest()
    {
        return $this->requestClient;
    }

    public function setUpstreamResponse(ResponseUpstream $response)
    {
        $this->responseUpstream = $response;
    }

    public function getUpstreamResponse()
    {
        return $this->responseUpstream;
    }

    /**
     * Prepares headers for the request which would be sent to upstream (from Jaal server)
     */
    public function prepareHeaders()
    {
        if ($version = $this->pool->config->get('http_version')) {
            $this->setProtocolVersion($version);
        }

        // setting new proxy request headers
        $arrHeaders = $this->pool->config->get('headers.server_to_upstream_request');
        foreach ($arrHeaders as $header => $value) {
            //Logger::getInstance()->debug('Setting proxy_set_header headers: ' . $header . ' => ' . $value);
            $this->setHeader($header, $value);
        }

        // copy headers from original (client) request to request we will make to upstream
        $arrClientHeaders = $this->requestClient->getHeaders();
        foreach ($arrClientHeaders as $header => $value) {
            $header = strtolower($header);

            if (isset(RequestUpstreamHeaders::$arrClientToProxyRequestHeaders[$header]) && !$this->hasHeader($header)) {
                $this->setHeader($header, $value);
                //Logger::getInstance()->debug('Setting client_to_proxy headers: ' . $header . ' => ' . $value);
            }
        }
    }

    public function prepareClientResponseHeader()
    {
        $arrHeaders = $this->pool->config->get('headers.upstream_to_client_response');
        foreach ($arrHeaders as $header => $value) {
            if ($value === false)
                $this->getClientRequest()->getJaalResponse()->removeHeader($header);
            else
                $this->getClientRequest()->getJaalResponse()->addHeader($header, $value);
        }

        $this->getClientRequest()->getJaalResponse()->addHeader('Connection', 'close');
    }

    /**
     * Communicates with upstream server and return response to client
     *
     * @void
     */
    public function send()
    {
        $this->pool->getUpstreamSocket($this)->then(
            function (Stream $stream) {

                $stream->write($this->getRawHeaders() . "\r\n\r\n");

                $consumed = $bodyLength = 0;
                $bodyBuffer = $compression = $methodEOM = '';


                $stream->on('data', function ($data) use ($stream, &$bodyLength, &$consumed, &$bodyBuffer, &$methodEOM) {

                    $bodyBuffer .= $data;
                    $isEOM = $hasError = false;

                    #Logger::getInstance()->debug('-------------------Data-----------------' . "\n" .
                    #                            $data .
                    #                            //"HEX:\n" . $this->hex_dump($data) . "\n" .
                    #                            '------------------/Data-----------------' . "\n");

                    if (!$methodEOM) {
                        $responseUpstream = null;
                        // @TODO no need to parse entire message, just look for content-length
                        if (strlen($data))
                            $responseUpstream = ResponseUpstream::fromMessage($data);

                        if ($responseUpstream) {
                            if ($responseUpstream->hasHeader('Content-Length')) {
                                $bodyLength = (int)(string)$responseUpstream->getHeader('Content-Length');
                                $methodEOM = 'length';
                            } else if ($responseUpstream->hasHeader('Transfer-Encoding') && ($header = $responseUpstream->getHeader('Transfer-Encoding'))
                                && $header->hasValue('chunked')
                            ) {
                                $methodEOM = 'chunk';
                            } else
                                $hasError = 'No Content-Length or Transfer-Encoding';

                            // remove header from body as we keep track of bodylength
                            $data = $responseUpstream->getBody();
                        } else {
                            $hasError = true;
                        }
                    }

                    #Logger::getInstance()->debug('-------------------Data After-----------------' . "\n" .
                    #   $data .
                    #    //"HEX:\n" . $this->hex_dump($data) . "\n" .
                    #    '------------------/Data After-----------------' . "\n");

                    // check of end of message in chunk mode
                    if ($methodEOM == 'chunk' && $data = "") {
                        $isEOM = true;
                    } else if ($methodEOM == 'length') {

                        $consumed += strlen($data);

                        if ($consumed >= $bodyLength) {
                            $isEOM = true;
                        }
                        #echo "---> ". $this->getClientRequest()->getUrl() . " ---->  Lengh: $bodyLength <-> Consumed: $consumed \n";
                    }

                    #echo "$methodEOM ";
                    #echo var_dump($isEOM);
                    #echo "\n";

                    if ($isEOM) {
                        $this->setExecutionTime();
                        $this->responseUpstream = ResponseUpstream::fromMessage($bodyBuffer);
                        $responseClient = new Response($this->responseUpstream->getStatusCode(), $this->responseUpstream->getHeaders(), $this->responseUpstream->getBody());
                        $this->getClientRequest()->setJaalResponse($responseClient);
                        $this->getClientRequest()->setExecutionTime();
                        $this->getClientRequest()->getJaalResponse()->setHeader('ExecTime', $this->getClientRequest()->getExecutionTime());
                        $this->getClientRequest()->getJaalResponse()->setHeader('X-ExecTime', $this->getExecutionTime());
                        $this->prepareClientResponseHeader();
                        $this->getClientRequest()->send();
                    }
                });
            },
            // @TODO handle error
            function ($error) {
                echo "Unable to connec... \n";
            }
        );
    }

    function hex_dump($data, $newline = "\n")
    {
        static $from = '';
        static $to = '';

        static $width = 16; # number of bytes per line

        static $pad = '.'; # padding for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width * 2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        foreach ($hex as $i => $line) {
            echo sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width;
        }
    }
}