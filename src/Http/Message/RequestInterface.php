<?php

namespace Attozk\Roxy\Http\Message;

use React\Socket\ConnectionInterface;
Use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;

interface RequestInterface extends GuzzleRequestInterface
{
    /**
     * Set the client used to transport the request
     *
     * @param ConnectionInterface $clientSocket
     * @return self
     */
    public function setClientSocket(ConnectionInterface $clientSocket);

    /**
     * Gets the client used to transport the request
     *
     * @return ConnectionInterface $clientSocket
     */
    public function getClientSocket();

    /**
     * Sets upstream socket
     *
     * @param ConnectorInterface $connector
     * @return self
     */
    public function setUpstreamSocket(ConnectorInterface $connector);

    /**
     * Returns upstream socket
     *
     * @return ConnectorInterface $connector
     */
    public function getUpstreamSocket();

    /**
     * @param $host
     * @param $port
     */
    public function connectUpstreamSocket($host, $port);

    /**
     * Sets upstream stream
     *
     * @param Stream $stream
     * @return self
     */
    public function setUpstreamStream(Stream $stream);

    /**
     * Returns upstream stream
     *
     * @return Stream $stream
     */
    public function getUpstreamStream();

    /**
     * Sets microtime
     *
     * @param null $microtime
     * @return self
     */
    public function setStartTime($microtime = null);

    /**
     * Sets execution time of request
     *
     * @return self
     */
    public function setExecutionTime();

    /**
     * Gets execution time of request
     */
    public function getExecutionTime();
}