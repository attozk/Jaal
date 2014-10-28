<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;

Class Request extends \Hathoora\Jaal\Daemons\Http\Client\Request implements RequestInterface
{
    /**
     * @var Vhost
     */
    protected $vhost;

    /**
     * @var ClientRequestInterface
     */
    protected $clientRequest;

    public function __construct(Vhost $vhost, ClientRequestInterface $clientRequest)
    {
        parent::__construct($clientRequest->getRequestMethod(), $clientRequest->getRequestUrl(), $clientRequest->getHeaders(), $clientRequest->getBody());
        $this->vhost = $vhost;
        $this->clientRequest = $clientRequest;
        $this->prepareHeaders();
    }

    public function getClientRequest()
    {
        return $this->clientRequest;
    }

    /**
     * Prepares headers for the request which would be sent to upstream (from Jaal server)
     */
    public function prepareHeaders()
    {
        if ($version = $this->vhost->config->get('http_version')) {
            $this->setHttpVersion($version);
        }

        // setting new proxy request headers
        $arrHeaders = $this->vhost->config->get('headers.server_to_upstream_request');
        foreach ($arrHeaders as $header => $value) {
            $this->setHeader($header, $value);
        }

        // copy headers from original (client) request to request we will make to upstream
        $arrClientHeaders = $this->clientRequest->getHeaders();
        foreach ($arrClientHeaders as $header => $value) {
            $header = strtolower($header);

            #if (isset(RequestUpstreamHeaders::$arrClientToProxyRequestHeaders[$header]) && !$this->hasHeader($header)) {
                $this->setHeader($header, $value);
            #}
        }
    }

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
}
