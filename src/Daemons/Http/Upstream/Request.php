<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use React\Stream\Stream;

Class Request extends \Hathoora\Jaal\Daemons\Http\Message\Request implements RequestInterface
{
    protected $stream;
    protected $response;

    /**
     * @var Vhost
     */
    protected $vhost;

    private $handleUpstreamDataAtts = array(
        'consumed' => 0,
        'length' => 0,
        'buffer' => '',
        'methodEOM' => '',
        'segments' => 0,
        'hasError' => false
    );

    /**
     * @var ClientRequestInterface
     */
    protected $clientRequest;

    public function __construct(Vhost $vhost, ClientRequestInterface $clientRequest)
    {
        parent::__construct($clientRequest->getMethod(), $clientRequest->getUrl(), $clientRequest->getHeaders());
        $this->setBody($clientRequest->getBody());
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
    protected function prepareHeaders()
    {
        if ($version = $this->vhost->config->get('http_version')) {
            $this->setProtocolVersion($version);
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

            if (isset(Headers::$arrAllowedUpstreamHeaders[$header]) && !$this->hasHeader($header)) {
                $this->setHeader($header, $value);
            }
        }
    }

    protected function prepareClientResponseHeader()
    {
        $arrHeaders = $this->vhost->config->get('headers.upstream_to_client_response');

        foreach ($arrHeaders as $header => $value) {
            if ($value === false) {
                $this->getClientRequest()->getResponse()->removeHeader($header);
            } else {
                $this->getClientRequest()->getResponse()->addHeader($header, $value);
            }
        }

        $this->getClientRequest()->setHeader('Exec-Time', $this->getClientRequest()->getExecutionTime());
        $this->getClientRequest()->setHeader('X-Exec-Time', $this->getExecutionTime());
        $this->getClientRequest()->setExecutionTime();
    }

    public function send()
    {
        $this->setState(self::STATE_CONNECTING);

        $this->vhost->getUpstreamSocket($this)->then(
            function (Stream $stream) {

                $this->setStream($stream);
                $this->setState(self::STATE_RETRIEVING);

                $hello = $this->getRawHeaders() . "\r\n\r\n" . $this->getBody();

                Logger::getInstance()->log(-100, "\n" . '----------- Request Write: ' . $this->id . ' -----------' . "\n" .
                    $hello .
                    "\n" . '----------- /Request Write: ' . $this->id . ' -----------' . "\n");

                $stream->write($hello);

                $stream->on('data', function ($data) {
                    $this->handleUpstreamData($data);
                });
            },
            // @TODO handle error
            function ($error) {
                echo "Unable to connec... \n";
            }
        );
    }

    /**
     * Handles upstream data
     */
    private function handleUpstreamData($data)
    {
        $this->handleUpstreamDataAtts['segments']++;

        $isEOM = false;
        $response = null;

        $this->handleUpstreamDataAtts['buffer'] .= $data;

        if (!$this->handleUpstreamDataAtts['methodEOM']) {

            Logger::getInstance()->log(-100, "\n" . '----------- Request Read: ' . $this->id . ' -----------' . "\n" .
                $data .
                "\n" . '----------- /Request Read: ' . $this->id . ' -----------' . "\n");

            // @TODO no need to parse entire message, just look for content-length

            if (strlen($data))
                $response = Parser::getResponse($data);

            if ($response) {
                if ($response->hasHeader('Content-Length')) {
                    $this->handleUpstreamDataAtts['length'] = $response->getHeader('Content-Length');
                    $this->handleUpstreamDataAtts['methodEOM'] = 'length';
                } else if ($response->hasHeader('Transfer-Encoding') && ($header = $response->getHeader('Transfer-Encoding')) && $header == 'chunked') {
                    $this->handleUpstreamDataAtts['methodEOM'] = 'chunk';
                } else
                    $this->handleUpstreamDataAtts['hasError'] = 'No Content-Length or Transfer-Encoding';

                // remove header from body as we keep track of bodylength
                $data = $response->getBody();
            } else {
                $this->handleUpstreamDataAtts['hasError'] = 'Bad Request';
            }
        }

        if (!$this->handleUpstreamDataAtts['hasError']) {            // check of end of message in chunk mode
            if ($this->handleUpstreamDataAtts['methodEOM'] == 'chunk' && $data = "") {
                $isEOM = true;
            } else if ($this->handleUpstreamDataAtts['methodEOM'] == 'length') {

                $this->handleUpstreamDataAtts['consumed'] += strlen($data);

                if ($this->handleUpstreamDataAtts['consumed'] >= $this->handleUpstreamDataAtts['length']) {
                    $isEOM = true;
                }
            }

            #echo "Request #". $this->id . " (". $this->getUrl() .") EOM: {$this->handleUpstreamDataAtts['methodEOM']}, Segment: {$this->handleUpstreamDataAtts['segments']}, Content-Length: {$this->handleUpstreamDataAtts['length']}, Consumed: {$this->handleUpstreamDataAtts['consumed']} \n";


            if ($isEOM) {

                if (!$response)
                    $this->response = Parser::getResponse($this->handleUpstreamDataAtts['buffer']);
                else
                    $this->response = $response;

                $this->response->setMethod($this->getMethod());
                $this->setExecutionTime();

                $this->clientRequest->setResponse(clone $this->response);
                $this->prepareClientResponseHeader();
                $this->clientRequest->send();
                $this->setState(self::STATE_DONE);
                $this->stream->end();
                $this->end();
            }
        } else {
            $this->getClientRequest()->getStream()->end();
            $this->end();
        }
    }

    private function end()
    {
        Jaal::getInstance()->getDaemon('httpd')->inboundIOManager->removeProp($this->clientRequest->getStream(), 'upstreamRequest');
    }

    /**
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
     * @return ConnectorInterface
     */
    public function getStream()
    {
        return $this->stream;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }
}