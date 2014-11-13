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
    /*
    public function buildConnector($ip, $port, $keepalive, $timeout = 10, $upstreamPoolKey = NULL)
    {
        $stream = NULL;

        if (!$timeout) {
            $timeout = 10;
        }

        $connector = NULL;
        $deferred  = new Deferred();
        $promise   = $deferred->promise();
//        $id = $this->getUpstreamPoolMapping($upstreamPoolKey);
//
//        if ($keepalive && ($stream = $this->getStreamById($id)))
//        {
//
//            $status = $this->getProp($stream, 'status');
//            if ($status == 'connected') {
//
//                Logger::getInstance()->log(-99,
//                    $stream->getRemoteAddress() . ' <' . $id . '> keep alive, hits: ' . $stream->hits . ', idle: ' . Time::millitimeDiff($this->getProp($stream, 'lastActivity')) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
//                    'lightPurple'));
//
//                $deferred->resolve($stream);
//            } else {
//                $stream = null;
//                $this->removeUpstreamPoolMapping($upstreamPoolKey);
//            }
//        }

        if (!$stream) {

            $connector = new Connector($this->loop, $this->dns);
            $connector->create($ip, $port)->then(
                function (Stream $stream) use ($deferred, $upstreamPoolKey)
                {
                    //$this->addUpstreamPoolMapping($upstreamPoolKey, $stream);
                    $this->setProp($stream, 'status', 'connected');
                    $deferred->resolve($stream);
                },
                function ($error) use ($deferred, $upstreamPoolKey)
                {

                    //$this->removeUpstreamPoolMapping($upstreamPoolKey);
                    Logger::getInstance()->log('NOTICE', 'Unable to connect to remote server: ' . $upstreamPoolKey);

                    $deferred->reject();
                });
        }

        return $promise;
    }*/
}