<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Util\Time;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Hathoora\Jaal\IO\React\SocketClient\Connector;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;

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

    public function add($stream)
    {
        if (!isset($this->streams[$stream->id])) {

            Logger::getInstance()->log(-99, sprintf('%-25s' . $stream->remoteId . "\n" .
                                                    "\t" . '[local: ' . $stream->id . ',  remote: ' . $stream->remoteId . ', connect-time: ' . Time::millitimeDiff($stream->millitime) . 'ms, ' .
                                                    'hits: ' . $stream->hits . ', resource: ' . $stream->resource . ']', 'OutboundIOManager-New'));
        }

        return parent::add($stream);
    }

    public function remove($stream)
    {
        $id = $stream->id;

        if (isset($this->streams[$id])) {
            Logger::getInstance()->log(-99, sprintf('%-25s' . $stream->remoteId . "\n" .
                                                    "\t" . '[local: ' . $stream->id . ',  remote: ' . $stream->remoteId . ', connect-time: ' . Time::millitimeDiff($stream->millitime) . 'ms, ' .
                                                    'idle-time: ' . Time::millitimeDiff($stream->lastActivity) . 'ms, ' .
                                                    'hits: ' . $stream->hits . ', resource: ' . $stream->resource . ']', 'OutboundIOManager-Remove'));
        }

        return parent::remove($stream);
    }

    /**
     * @param $ip
     * @param $port
     * @return \React\Promise\Promise
     */
    public function buildConnector($ip, $port)
    {
        $deferred = new Deferred();
        $connector = new Connector($this->loop, $this->dns);
        $connector->create($ip, $port)->then(
            function (Stream $stream) use ($deferred) {
                $this->add($stream);
                $deferred->resolve($stream);
            },
            function ($error) use ($deferred) {
                $deferred->reject();
            });

        return $deferred->promise();
    }
}