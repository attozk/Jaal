<?php

namespace Hathoora\Jaal\IO\React\SocketClient;

use Hathoora\Jaal\Util\Time;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;

class Connector extends \React\SocketClient\Connector implements ConnectorInterface
{
    public $loop;
    public $militime;   // milli time at connect
    public $stream;

    public function __construct(LoopInterface $loop, Resolver $resolver)
    {
        $this->militime = Time::millitime();
        $this->loop = $loop;
        parent::__construct($loop, $resolver);
    }

    public function handleConnectedSocket($socket)
    {
        $this->stream = new Stream($socket, $this->loop);

        return $this->stream;
    }
}