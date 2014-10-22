<?php

namespace Attozk\Roxy\Http\Message;

use React\Socket\ConnectionInterface;
Use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;

interface RequestUpstreamInterface extends GuzzleRequestInterface
{
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

    public function connectUpstreamSocket();

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
}