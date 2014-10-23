<?php

namespace Attozk\Jaal\Upstream;

use Attozk\Jaal\Httpd\Message\RequestInterface;
use React\Promise\Deferred;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;


class UpstreamManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * http, ftp etc..
     */
    private $protocol;

    public function __construct($loop, $dns, $protocol)
    {
        $this->loop = $loop;
        $this->dns = $dns;
        $this->protocol = $protocol;
    }

    public function buildConnector()
    {
        $connector = new Connector($this->loop, $this->dns);

        return $connector;
    }
}