<?php

namespace Hathoora\Jaal\Httpd\Message;

use React\Socket\ConnectionInterface;
Use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;

interface RequestInterface extends GuzzleRequestInterface
{
    public function setRequestId();

    public function getRequestId();

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

    public function setJaalResponse(Response $response);

    public function getJaalResponse();

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