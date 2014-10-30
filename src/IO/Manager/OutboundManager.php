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

    protected $ips2StreamMapping;

    /**
     * @param LoopInterface $loop
     * @param Resolver $dns
     * @param $protocol
     */
    public function __construct(LoopInterface $loop, Resolver $dns, $protocol)
    {
        $this->loop = $loop;
        $this->dns = $dns;
        $this->protocol = $protocol;
        $this->streams = $this->ips2StreamMapping = array();
    }

    public function add($stream)
    {
        if (!isset($this->streams[$stream->id])) {

            Logger::getInstance()->log(-99,
                Logger::getInstance()->color($stream->remoteId, 'purple'). ' / '. Logger::getInstance()->color($stream->id, 'green') .' has been added to Outbound Manager, hits: ' . $stream->hits .', connection time: ' . Time::millitimeDiff($stream->militime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));
        }

        return parent::add($stream);
    }

    public function remove($stream)
    {
        $id = $stream->id;
        $key = $stream->remoteId;

        if (isset($this->streams[$id])) {

            Logger::getInstance()->log(-99,
                Logger::getInstance()->color($stream->remoteId, 'purple'). ' / '. Logger::getInstance()->color($stream->id, 'green') .' has been removed from Outbound Manager, hits: ' . $stream->hits .', connection time: ' . Time::millitimeDiff($stream->militime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));
        }

        return parent::remove($stream);
    }


    public function addIp2StreamMapping($key, Stream $stream)
    {
        $this->ips2StreamMapping[$key] = $stream->id;
    }

    public function removeIp2StreamMapping($key)
    {
        if (isset($this->ips2StreamMapping[$key])) {
            unset($this->ips2StreamMapping[$key]);
        }
    }

    public function buildConnector($ip, $port, $keepalive, $timeout = 10)
    {
        $stream = null;
        $key = $ip . ':' . $port;
        $id = isset($this->ips2StreamMapping[$key]) ? $this->ips2StreamMapping[$key] : null;

        if (!$timeout) {
            $timeout = 10;
        }

        $connector = null;
        $deferred = new Deferred();
        $promise = $deferred->promise();

        /*
        if ($keepalive && ($stream = $this->getStreamById($id))) {

            $status = $this->getProp($stream, 'status');
            if ($status == 'connected') {

                $stream->hits++;
                Logger::getInstance()->log(-99,
                    $stream->getRemoteAddress() . ' <' . $id . '> keep alive, hits: ' . $stream->hits . ', idle: ' . Time::millitimeDiff($this->getProp($stream, 'lastActivity')) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));

                $deferred->resolve($stream);
            } else {
                $stream = null;
                $this->removeIp2StreamMapping($key);
            }
        }*/

        if (!$stream) {

            $connector = new Connector($this->loop, $this->dns);
            $connector->create($ip, $port)->then(function (Stream $stream) use ($deferred, $key) {
                    //$this->addIp2StreamMapping($key, $stream);
                    $this->setProp($stream, 'status', 'connected');
                    $deferred->resolve($stream);
                },
                function ($error) use ($deferred, $key) {

                    //$this->removeIp2StreamMapping($key);
                    Logger::getInstance()->log('NOTICE', 'Unable to connect to remote server: ' . $key);

                    $deferred->reject();
                });
        }

        return $promise;
    }

}