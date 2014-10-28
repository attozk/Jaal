<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;

Interface RequestInterface
{
    /**
     * Sets a unique request id
     *
     * @return self
     */
    public function setRequestId();

    /**
     * Gets the unique request id
     *
     * @return mixed
     */
    public function getRequestId();

    public function removeHeader($header);

    public function getScheme();

    public function getPort();

    public function getHost();

    public function getPath();

    public function getQuery();

    /**
     * Sets connection stream to client or proxy
     *
     * @param ConnectionInterface|ConnectorInterface $stream
     * @return self
     */
    public function setStream($stream);

    /**
     * Returns the type of stream
     *
     * @return string inbound|outbound
     */
    public function getStreamType();

    /**
     * Returns connection stream socket
     *
     * @return ConnectionInterface
     */
    public function getStream();

    /**
     * Sets start time of request in miliseconds
     *
     * @param null|int $miliseconds
     * @return self
     */
    public function setStartTime($miliseconds = null);

    /**
     * Sets execution time of request in miliseconds
     *
     * @return self
     */
    public function setExecutionTime();

    /**
     * Gets execution time of request in miliseconds
     */
    public function getExecutionTime();

    public function setResponse(ResponseInterface $response);

    public function send();
}