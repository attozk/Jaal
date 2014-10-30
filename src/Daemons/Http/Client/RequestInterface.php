<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;

Interface RequestInterface extends \Hathoora\Jaal\Daemons\Http\Message\RequestInterface
{
    public function __construct($method, $url, $headers = array());

    /**
     * Sets connection stream to client or proxy
     *
     * @param ConnectionInterface $stream
     * @return self
     */
    public function setStream(ConnectionInterface $stream);

    /**
     * Returns connection stream socket
     *
     * @return ConnectionInterface
     */
    public function getStream();

    public function handleData(ConnectionInterface $stream, $data);

    public function setResponse(ResponseInterface $response);

    public function getResponse();

    public function reply();

    public function error($code, $description = '');
}