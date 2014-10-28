<?php

namespace Hathoora\Jaal\IO\Manager;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use SplObjectStorage;

/**
 * Class Outbound for managing outbound connections
 *
 * @package Hathoora\Jaal\IO\Manager
 */
class OutboundManager
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
     * @var SplObjectStorage for storing ConnectorInterface
     */
    protected $connectors;

    public function __construct(LoopInterface $loop, Resolver $dns, $protocol)
    {
        $this->loop = $loop;
        $this->dns = $dns;
        $this->protocol = $protocol;
        $this->connectors = new SplObjectStorage();
    }

    public function add(ConnectorInterface $connector)
    {
        $this->connectors->attach($connector);
    }

    public function remove(ConnectorInterface $connector)
    {
        $this->connectors->detach($connector);
    }

    public function end(ConnectorInterface $connector)
    {
        $this->connectors->detach($connector);
    }

    public function count()
    {
        return $this->connectors->count();
    }

    public function buildConnector()
    {
        $connector = new Connector($this->loop, $this->dns);

        return $connector;
    }
}