<?php

namespace Hathoora\Jaal\Upstream;

use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
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

    /**
     * Array of pools
     */
    public $arrPools;

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