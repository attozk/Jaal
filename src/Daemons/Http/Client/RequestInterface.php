<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;

Interface RequestInterface extends \Hathoora\Jaal\Daemons\Http\Message\RequestInterface
{
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

    /**
     * Reads incoming data (when more than buffer) to parse it into a message
     *
     * @param ConnectionInterface $stream
     * @param $data
     * @return void
     */
    public function handleIncomingData(ConnectionInterface $stream, $data);

    /**
     * Set response to this request
     *
     * @param ResponseInterface $response
     * @return self
     */
    public function setResponse(ResponseInterface $response);

    /**
     * Get response of this request
     *
     * @return ResponseInterface $response
     */
    public function getResponse();

    /**
     * Reply to client's request using $stream
     *
     * @param null $code to overwrite request response
     * @param null $message
     * @return mixed
     */
    public function reply($code = null, $message = null);
}