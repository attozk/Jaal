<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Util\Time;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Hathoora\Jaal\IO\React\SocketClient\Connector;
use Hathoora\Jaal\IO\React\SocketClient\Stream;

/**
 * Class Outbound for managing outbound connections
 *
 * @package Hathoora\Jaal\IO\Manager
 */
class OutboundManager extends IOManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * http, ftp etc..
     */
    private $protocol;

    protected $streams;

    /**
     * @param LoopInterface $loop
     * @param Resolver      $dns
     * @param               $protocol
     */
    public function __construct(LoopInterface $loop, Resolver $dns, $protocol)
    {
        $this->loop    = $loop;
        $this->dns     = $dns;
        $this->protocol = $protocol;
        $this->streams = [];
    }

    /**
     * @param $ip
     * @param $port
     *
     * @return \React\Promise\Promise
     */
    public function buildConnector($ip, $port)
    {
        $deferred  = new Deferred();
        $connector = new Connector($this->loop, $this->dns);
        $connector->create($ip, $port)->then(
            function (Stream $stream) use ($deferred)
            {
                $this->add($stream);
                $deferred->resolve($stream);
            },
            function ($error) use ($deferred)
            {
                $deferred->reject();
            });

        return $deferred->promise();
    }
}