<?php

namespace Attozk\Roxy\Http\Message;

use Guzzle\Http\Message\Request as GuzzleRequest;
use React\Promise\Deferred;
Use React\Socket\ConnectionInterface;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;

class Request extends GuzzleRequest implements RequestInterface
{
    private $roxyStartMTime;         // request start micro time
    private $roxyExecTime;           // request execution time im milliseconds

    /** @var \React\Socket\ConnectionInterface */
    protected $clientSocket;

    /** @var \React\SocketClient\ConnectorInterface */
    protected $upstreamSocket;

    /** @var \React\Stream\Stream */
    protected $upstreamStream;

    public function setClientSocket(ConnectionInterface $clientSocket)
    {
        $this->clientSocket = $clientSocket;

        return $this;
    }

    public function getClientSocket()
    {
        return $this->clientSocket;
    }

    public function setUpstreamSocket(ConnectorInterface $connector)
    {
        $this->upstreamSocket = $connector;

        return $this;
    }

    /**
     * @param $host
     * @param $port
     */
    public function connectUpstreamSocket($host, $port)
    {
        $deferred = new Deferred();
        $this->upstreamSocket->create($host, $port)->then(
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

    public function setStartTime($microtime = null)
    {
        if (!$microtime)
            $microtime = microtime();

        $this->roxyStartMTime = $microtime;

        return $this;
    }

    public function setExecutionTime()
    {
        if (!$this->roxyExecTime) {
            list($a_dec, $a_sec) = explode(" ", $this->roxyStartMTime);
            list($b_dec, $b_sec) = explode(" ", microtime());
                $this->roxyExecTime = ($b_sec - $a_sec + $b_dec - $a_dec);
        }

        return $this;
    }

    public function getExecutionTime()
    {
        return $this->roxyExecTime;
    }
}