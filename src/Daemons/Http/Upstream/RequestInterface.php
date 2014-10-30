<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;

Interface RequestInterface extends \Hathoora\Jaal\Daemons\Http\Message\RequestInterface
{
    /**
     * Return  client's request
     *
     * @return ClientRequestInterface
     */
    public function getClientRequest();

    /**
     * Set outbound stream
     *
     * @param Stream $stream
     * @return self
     */
    public function setStream(Stream $stream);

    /**
     * Returns connection stream socket
     *
     * @return ConnectorInterface
     */
    public function getStream();

    /**
     * @param Stream $stream
     * @param $data
     * @return mixed
     */
    public function handleUpstreamOutputData(Stream $stream, $data);

    /**
     * Set upstream response
     *
     * @param Response $response
     * @return self
     */
    public function setResponse(Response $response);

    /**
     * Send's the request to upstream server
     *
     * @return mixed
     */
    public function send();

    /**
     * Upstream reply is client's request response
     *
     * @param null $code to overwrite upstream's response
     * @param null $message
     * @return mixed
     */
    public function reply($code = null, $message = null);
}
