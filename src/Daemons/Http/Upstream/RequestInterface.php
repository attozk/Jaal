<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use React\Stream\Stream;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;

Interface RequestInterface extends \Hathoora\Jaal\Daemons\Http\Message\RequestInterface
{
    public function __construct(Vhost $vhost, ClientRequestInterface $clientRequest);

    public function getClientRequest();

    /**
     * @return self
     */
    public function setStream(Stream $stream);

    /**
     * Returns connection stream socket
     *
     * @return ConnectorInterface
     */
    public function getStream();

    public function setResponse(Response $response);

    public function send();
}