<?php

namespace Hathoora\Jaal\IO\React\SocketClient;

use Hathoora\Jaal\Util\Time;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;

class Connector extends \React\SocketClient\Connector implements ConnectorInterface
{
    public $militime;   // milli time at connect

    public function __construct(LoopInterface $loop, Resolver $resolver)
    {
        $this->militime = Time::millitime();
        parent::__construct($loop, $resolver);
    }
}