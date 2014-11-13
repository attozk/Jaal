<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;

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
     * @return ConnectionInterface|\Hathoora\Jaal\IO\React\Socket\Connection
     */
    public function getStream();

    /**
     * Reads incoming data from client to parse it into a message
     *
     * @param $data
     * @return void
     */
    public function onInboundData($data);

    /**
     * Set the upstream request
     *
     * @param UpstreamRequestInterface $request
     * @return self
     */
    public function setUpstreamRequest(UpstreamRequestInterface $request);

    /**
     * Get the upstream request
     *
     * @return UpstreamRequestInterface
     */
    public function getUpstreamRequest();

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
     * Reply to client
     *
     * Typically upstream's response is send to client as a series of data
     *
     * @param $buffer
     * @return self
     */
    public function onOutboundData($buffer);

    /**
     * Respond to client as error
     *
     * @param string $code
     * @param string $message   if any
     * @param bool $closeStream to end the stream after reply (overwrite keep-alive settings)
     * @return self
     */
    public function error($code, $message = '', $closeStream = FALSE);

    public function cleanup();
}