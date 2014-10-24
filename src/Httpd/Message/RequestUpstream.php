<?php

namespace Hathoora\Jaal\Httpd\Message;

use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Upstream\Httpd\Pool;

class RequestUpstream extends Request implements RequestUpstreamInterface
{
    private $jStartMTime;         // request start micro time
    private $jExecTime;           // request execution time im milliseconds

    /**
     * @var Request
     */
    private $clientRequest;

    /**
     * @param Pool $pool
     * @param RequestInterface $request
     */
    public function __construct(Pool $pool, RequestInterface $request)
    {
        $this->setStartTime();
        $this->pool = $pool;
        $this->setClientRequest($request);

        parent::__construct($request->getMethod(), $request->getUrl());

        $this->pool->prepareClientToProxyRequestHeaders($this);

        Logger::getInstance()->debug($this->getClientRequest()->getClientSocket()->getRemoteAddress() . ' ' .
            $this->getClientRequest()->getMethod() . ' ' . $this->getClientRequest()->getUrl() . ' >> UPSTREAM >> ' .
            $this->getUrl());
    }

    /**
     * @param Request $request
     */
    public function setClientRequest(Request $request)
    {
        $this->clientRequest = $request;
    }

    /**
     * @return Request
     */
    public function getClientRequest()
    {
        return $this->clientRequest;
    }

    /**
     * Communicates with upstream server and return response to client
     *
     * @void
     */
    public function send()
    {
        $this->pool->getUpstreamSocket($this->clientRequest)->then(
            function (RequestUpstreamInterface $request) {
                Logger::getInstance()->debug('Writing to upstream...');

                $stream = $request->getUpstreamStream();
                $stream->write($this->getRawHeaders() . "\r\n\r\n");


                echo "\n---------------------Header---------------------\n";
                echo htmlspecialchars($this->getRawHeaders());
                echo "\n---------------------/Header---------------------\n";


                $consumed = $bodyLength = 0;
                $bodyBuffer = $compression = '';
                $hasError = false;
                $stream->on('data', function ($data) use (
                    $request,
                    $stream,
                    &$bodyLength,
                    &$consumed,
                    &$bodyBuffer,
                    &$hasError
                ) {


                    #echo "\n---------------------Before Data---------------------\n";
                    #echo $data . "\n\n";
                    #echo "\n---------------------/Data---------------------\n";
                    $bodyBuffer .= $data;

                    if (!$bodyLength) {
                        // @TODO no need to parse entire message, just look for content-length
                        $response = \Hathoora\Jaal\Httpd\Message\Response::fromMessage($data);
                        if ($response) {
                            $bodyLength = (int)(string)$response->getHeader('Content-Length');

                            if ($response->hasHeader('Content-Encoding')) {
                                $data = $response->getBody();
                            }
                        } else {
                            $hasError = true;
                        }
                    }

                    $consumed += strlen($data);

                    //echo "---> $bodyLength <-> $consumed \n";
                    if ($consumed >= $bodyLength) {
                        $response = \Hathoora\Jaal\Httpd\Message\Response::fromMessage($bodyBuffer);
                        $this->pool->prepareProxyToClientHeaders($this, $response);
                        $request->setExecutionTime();

                        $request->getClientRequest()->getClientSocket()
                            ->write($response->getRawHeaders() . $response->getBody());

                        echo "\n---------------------Data---------------------\n";
                        echo $bodyBuffer . "\n\n";
                        echo "\n---------------------/Data---------------------\n";

                        //$request->getClientRequest()->getClientSocket()->end();
                        //$stream->end();
                    }
                    /*
                     *
                    // KEEP ALIVE
                    $request->setExecutionTime();

                    $response->addHeader('Jaal-Exectime', $request->getExecutionTime());
                    $request->setResponse($response);

                    $request->getClientRequest()->getClientSocket()
                            ->write($response->getRawHeaders() . $response->getBody());

                    */
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
            echo sprintf('%6X', $offset) . ' : ' . implode(' ',
                    str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width;
        }
    }
}