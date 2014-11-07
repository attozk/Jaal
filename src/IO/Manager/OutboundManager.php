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

            Logger::getInstance()->log(-99,
                                       Logger::getInstance()->color($stream->remoteId, 'purple') . ' / ' .
                                       Logger::getInstance()->color($stream->id, 'green') .
                                       ' has been added to Outbound Manager, hits: ' .
                                       $stream->hits . ', connection time: ' . Time::millitimeDiff($stream->millitime) .
                                       ' ms ' .
                                       Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));
        }

        return parent::add($stream);
    }

    public function remove($stream)
    {
        $id = $stream->id;

        if (isset($this->streams[$id])) {

            Logger::getInstance()->log(-99,
                                       Logger::getInstance()->color($stream->remoteId, 'purple') . ' / ' .
                                       Logger::getInstance()->color($stream->id, 'green') .
                                       ' has been removed from Outbound Manager, hits: ' .
                                       $stream->hits . ', connection time: ' . Time::millitimeDiff($stream->millitime) .
                                       ' ms ' .
                                       Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));
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