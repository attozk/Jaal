<?php

namespace Hathoora\Jaal\IO\React\SocketClient;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;

class Connector extends \React\SocketClient\Connector
{
    private $loop;

    public function __construct(LoopInterface $loop, Resolver $resolver)
    {
        parent::__construct($loop, $resolver);
        $this->loop = $loop;
    }

    public function handleConnectedSocket($socket)
    {
        return new Stream($socket, $this->loop);
    }
}