<?php

namespace Hathoora\Jaal\IO\Manager;

/**
 * Class Inbound for managing inbound connections
 *
 * @package Hathoora\Jaal\IO\Manager
 */
class InboundManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * http, ftp etc..
     */
    protected $protocol;

    /**
     * @var SplObjectStorage for storing ConnectionInterface
     */
    protected $clients;

    public function __construct($loop, $protocol)
    {
        $this->loop = $loop;
        $this->protocol = $protocol;
        $this->clients = new SplObjectStorage();
    }

    public function add(ConnectionInterface $client)
    {
        $this->clients->attach($client);
    }

    public function remove(ConnectionInterface $client)
    {
        $this->clients->detach($client);
    }

    public function end(ConnectionInterface $client)
    {
        $this->clients->detach($client);
        $client->end();
    }

    public function count()
    {
        return $this->clients->count();
    }
}
