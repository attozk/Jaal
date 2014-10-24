<?php

namespace Hathoora\Jaal\Httpd\Message;

use React\Socket\ConnectionInterface;
Use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;

interface RequestUpstreamInterface extends GuzzleRequestInterface
{
    public function setRequestId();

    public function getRequestId();

    public function setClientRequest(Request $request);

    public function getClientRequest();
}