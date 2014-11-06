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

    protected $upstreamPoolMapping;

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
        $this->streams = $this->upstreamPoolMapping = [];
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

    public function addUpstreamPoolMapping($key, Stream $stream)
    {
        $this->upstreamPoolMapping[$key] = $stream->id;
    }

    public function getUpstreamPoolMapping($key)
    {
        if (isset($this->upstreamPoolMapping[$key]))
        {
            return $this->upstreamPoolMapping[$key];
        }
    }

    public function removeUpstreamPoolMapping($key)
    {
        if (isset($this->upstreamPoolMapping[$key]))
        {
            unset($this->upstreamPoolMapping[$key]);
        }
    }

    public function buildConnector($ip, $port, $keepalive, $timeout = 10, $upstreamPoolKey = NULL)
    {
        $stream = NULL;

        if (!$timeout) {
            $timeout = 10;
        }

        $connector = NULL;
        $deferred  = new Deferred();
        $promise   = $deferred->promise();
        $id = $this->getUpstreamPoolMapping($upstreamPoolKey);

        if ($keepalive && ($stream = $this->getStreamById($id)))
        {

            $status = $this->getProp($stream, 'status');
            if ($status == 'connected') {

                Logger::getInstance()->log(-99,
                    $stream->getRemoteAddress() . ' <' . $id . '> keep alive, hits: ' . $stream->hits . ', idle: ' . Time::millitimeDiff($this->getProp($stream, 'lastActivity')) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));

                $deferred->resolve($stream);
            } else {
                $stream = null;
                $this->removeUpstreamPoolMapping($upstreamPoolKey);
            }
        }

        if (!$stream) {

            $connector = new Connector($this->loop, $this->dns);
            $connector->create($ip, $port)->then(
                function (Stream $stream) use ($deferred, $upstreamPoolKey)
                {
                    $this->addUpstreamPoolMapping($upstreamPoolKey, $stream);
                    $this->setProp($stream, 'status', 'connected');
                    $deferred->resolve($stream);
                },
                function ($error) use ($deferred, $upstreamPoolKey)
                {

                    $this->removeUpstreamPoolMapping($upstreamPoolKey);
                    Logger::getInstance()->log('NOTICE', 'Unable to connect to remote server: ' . $upstreamPoolKey);

                    $deferred->reject();
                });
        }

        return $promise;
    }
}