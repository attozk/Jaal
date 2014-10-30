<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\Util\Time;

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
        $this->setState(ClientRequestInterface::STATE_PENDING);
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
                $this->clientRequest->getResponse()->removeHeader($header);
            } else {
                $this->clientRequest->getResponse()->addHeader($header, $value);
            }
        }

        $this->clientRequest->setHeader('Exec-Time', $this->clientRequest->getExecutionTime());
        $this->clientRequest->setHeader('X-Exec-Time', $this->getExecutionTime());
        $this->clientRequest->setExecutionTime();
    }

    public function send()
    {
        $this->setState(self::STATE_RETRIEVING);

        $hello = $this->getRawHeaders() . "\r\n\r\n" . $this->getBody();

        Logger::getInstance()->log(-100, "\n" . '----------- Request Write: ' . $this->id . ' -----------' . "\n" .
            $hello .
            "\n" . '----------- /Request Write: ' . $this->id . ' -----------' . "\n");

        $this->stream->write($hello);
    }

    /**
     * Handles upstream data
     */
    public function handleData(Stream $stream, $data)
    {
        $request = $this;

        #if ($this->vhost->outboundIOManager->getProp($stream, 'request')) {

        #$request = $this->vhost->outboundIOManager->getProp($stream, 'request');
        #$request = $this;

            $consumed =& $request->handleUpstreamDataAtts['consumed'];
            $length =& $request->handleUpstreamDataAtts['length'];
            $methodEOM =& $request->handleUpstreamDataAtts['methodEOM'];
            $hasError =& $request->handleUpstreamDataAtts['hasError'];
            $buffer =& $request->handleUpstreamDataAtts['buffer'];
            $segments =& $request->handleUpstreamDataAtts['segments'];

            $segments++;
            $isEOM = false;
            $response = null;
            $buffer .= $data;

            if (!$methodEOM) {

                Logger::getInstance()->log(-100, "\n" . '----------- Request Read: ' . $request->id . ' -----------' . "\n" .
                    $data .
                    "\n" . '----------- /Request Read: ' . $request->id . ' -----------' . "\n");

                // @TODO no need to parse entire message, just look for content-length

                if (strlen($data))
                    $response = Parser::getResponse($data);

                if ($response) {
                    if ($response->hasHeader('Content-Length')) {
                        $length = $response->getHeader('Content-Length');
                        $methodEOM = 'length';
                    } else if ($response->hasHeader('Transfer-Encoding') && ($header = $response->getHeader('Transfer-Encoding')) && $header == 'chunked') {
                        $methodEOM = 'chunk';
                    } else
                        $hasError = 400;

                    // remove header from body as we keep track of bodylength
                    $data = $response->getBody();
                } else {
                    $hasError = 401;
                }
            }

            if (!$hasError) {
                // @TODO check of end of message in chunk mode
                if ($methodEOM == 'chunk' && $data = "") {
                    $isEOM = true;
                } else if ($methodEOM == 'length') {

                    $consumed += strlen($data);

                    if ($consumed >= $length) {
                        $isEOM = true;
                    }
                }

                if ($isEOM) {

                    if ($response)
                        $request->response = $response;
                    else
                        $request->response = Parser::getResponse($buffer);

                    if ($request->response instanceof ResponseInterface) {
                        $request->setExecutionTime();
                        $request->response->setMethod($this->getMethod());
                        $request->clientRequest->setResponse(clone $this->response);
                        $request->setState(self::STATE_DONE);
                        $request->prepareClientResponseHeader();
                        $request->end();
                        $request->clientRequest->reply();
                    } else
                        $hasError = 404;
                }
            }
        #} else {
        #    $hasError = 500;
        #}

        if ($hasError) {
            $request->setState(self::STATE_ERROR);
            $request->end();
            $request->clientRequest->error($hasError);
        }
    }

    public function reply()
    {

    }

    private function end()
    {
        Logger::getInstance()->log(-99, 'UPSTREAM RESPONSE ('. $this->state .') ' . Logger::getInstance()->color($this->getUrl(), 'red') . ' using remote stream: '. Logger::getInstance()->color($this->stream->id, 'green'));
        Jaal::getInstance()->getDaemon('httpd')->outboundIOManager->removeProp($this->stream, 'request');
        if (!$this->vhost->config->get('upstreams.keepalive.max') && !$this->vhost->config->get('upstreams.keepalive.max')) {
            $this->stream->end();
        }
    }

    public function error($code, $description = '')
    {

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