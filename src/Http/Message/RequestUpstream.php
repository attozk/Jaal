<?php

namespace Attozk\Roxy\Http\Message;

use React\Promise\Deferred;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;

class RequestUpstream extends Request implements RequestUpstreamInterface
{
    private $roxyStartMTime;         // request start micro time
    private $roxyExecTime;           // request execution time im milliseconds

    /** @var  Request */
    private $clientRequest;

    /** @var \React\SocketClient\ConnectorInterface */
    protected $upstreamSocket;

    /** @var \React\Stream\Stream */
    protected $upstreamStream;

    /**
     * @param RequestInterface $request
     * @param ConnectorInterface $connector
     * @param array $arrOptions
     */
    public function __construct(RequestInterface $request, ConnectorInterface $connector, $arrOptions = array())
    {
        $this->clientRequest = $request;
        $this->upstreamSocket = $connector;
        $arrHeaders = $request->getHeaders();
        $this->setStartTime();

        parent::__construct($request->getMethod(), $request->getUrl(), $arrHeaders);
    }

    public function setUpstreamSocket(ConnectorInterface $connector)
    {
        $this->upstreamSocket = $connector;

        return $this;
    }

    public function setUpstreamStream(Stream $stream)
    {
        $this->upstreamStream = $stream;
    }

    public function getUpstreamStream()
    {
        return $this->upstreamStream;
    }

    /**
     * Returns upstream socket
     *
     * @return ConnectorInterface $connector
     */
    public function getUpstreamSocket()
    {
        return $this->$upstreamSocket;
    }

    /**
     * Opens upstream server
     * @return \React\Promise\Promise
     */
    public function connectUpstreamSocket()
    {
        $deferred = new Deferred();
        $this->upstreamSocket->create($this->getHost(), $this->getPort())->then(
            function($stream) use($deferred) {
                $this->setUpstreamStream($stream);
                $deferred->resolve($this);
            },
            // @TODO handle error
            function() use($deferred)
            {
                $deferred->reject();
            }
        );

        return $deferred->promise();
    }

    /**
     * Communicates with upstream server and return response to client
     *
     * @void
     */
    public function send()
    {
        $this->connectUpstreamSocket()->then(
            function(RequestUpstreamInterface $request)
            {
                $stream = $request->getUpstreamStream();
                $stream->write("GET / HTTP/1.1\r\nHost: www.domain.com\r\n\r\n");

                $stream->on('data', function($data) use($request, $stream) {

                    // KEEP ALIVE
                    $request->setExecutionTime();
                    $response = \Attozk\Roxy\Http\Message\Response::fromMessage($data);
                    $response->addHeader('Roxy-Exectime', $request->getExecutionTime());
                    $request->setResponse($response);

                    $request->getClientSocket()->write($response->getRawHeaders() . $response->getBody());
                });
            },
            // @TODO handle error
            function($error) {
                echo "Unable to connec... \n";
            }
        );
    }
}