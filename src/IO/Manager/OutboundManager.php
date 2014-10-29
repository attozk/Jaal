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
class OutboundManager
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

    protected $stats;

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
        $this->stats = array(
            'connections' => 0,
            'data' => 0,
        );
    }

    public function add(Stream $stream)
    {
        $id = $stream->id;
        if (!isset($this->streams[$id])) {
            $this->streams[$id] = array(
                'stream' => $stream
            );

            Logger::getInstance()->log(-99,
                $stream->getRemoteAddress() . ' <' . $id . '> has been added to Outbound Manager ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));

            $stream->on('data', function () use ($stream) {
                $this->setProp($stream, 'lastActivity', Time::millitime());
            });

            $stream->on('close', function () use ($stream) {
                $key = $stream->remoteId;
                $this->removeIp2StreamMapping($key);
            });
        }

        return $this;
    }

    public function get(Stream $stream)
    {
        $id = $stream->id;
        if (isset($this->streams[$id])) {
            return $this->streams[$id];
        }
    }

    public function getSteamById($id)
    {

        if (isset($this->streams[$id])) {
            return $this->streams[$id]['stream'];
        }
    }

    public function remove(Stream $stream)
    {
        $id = $stream->id;
        if (isset($this->streams[$id])) {

            Logger::getInstance()->log(-99,
                $stream->getRemoteAddress() . ' <' . $id . '> has been removed from Outbound Manager after staying connected for ' . Time::millitimeDiff($stream->militime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));

            unset($this->streams[$id]);
        }

        return $this;
    }

    /**
     * @param Stream $stream
     * @param $property
     * @param $value
     */
    public function setProp(Stream $stream, $property, $value)
    {
        $this->add($stream);
        $id = $stream->id;
        $this->streams[$id][$property] = $value;
    }

    /**
     * @param Stream $stream
     * @param $property
     */
    public function getProp(Stream $stream, $property)
    {
        if (($arr = $this->get($stream)) && isset($arr[$property])) {
            return $arr[$property];
        }
    }

    public function removeProp(Stream $stream, $property)
    {
        if (isset($this->streams[$stream->id]) && isset($this->streams[$stream->id][$property])) {
            unset($this->streams[$stream->id][$property]);
        }
    }

    public function addIp2StreamMapping($key, Stream $stream)
    {
        $this->ips2StreamMapping[$key] = $stream->id;
        $this->add($stream);
    }

    public function removeIp2StreamMapping($key)
    {
        if (isset($this->ips2StreamMapping[$key])) {
            $id = $this->ips2StreamMapping[$key];

            unset($this->ips2StreamMapping[$key]);

            if (isset($this->streams[$id])) {
                unset($this->streams[$id]);
            }
        }
    }

    /**
     * @param $ip
     * @param $port
     * @param $keepAlive format is -> Seconds:Number of requests
     * @return \React\Promise\Promise
     */
    public function buildConnector($ip, $port, $keepAlive, $timeout = 10)
    {
        $key = $ip . ':' . $port;
        $id = isset($this->ips2StreamMapping[$key]) ? $this->ips2StreamMapping[$key] : null;

        if (!$timeout) {
            $timeout = 10;
        }

        $connector = null;
        $deferred = new Deferred();
        $promise = $deferred->promise();

        if ($stream = $this->getSteamById($id)) {

            $status = $this->getProp($stream, 'status');
            if ($status == 'connected') {

                $hits = $this->getProp($stream, 'hits') + 1;
                $this->setProp($stream, 'hits', $hits);

                Logger::getInstance()->log(-99,
                $stream->getRemoteAddress() . ' <' . $id . '> keep alive, hits: ' . $hits . ', idle: '. Time::millitimeDiff($this->getProp($stream, 'lastActivity')) .' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'lightPurple'));

                $deferred->resolve($stream);
            } else {
                $stream = null;
                $this->removeIp2StreamMapping($key);
            }
        }

        if (!$stream) {

            $connector = new Connector($this->loop, $this->dns);
            $connector->create($ip, $port)->then(function (Stream $stream) use ($deferred, $key) {

                    $this->addIp2StreamMapping($key, $stream);
                    $this->setProp($stream, 'status', 'connected');
                    $this->setProp($stream, 'hits', 1);
                    $deferred->resolve($stream);
                },
                function ($error) use ($deferred, $key) {

                    $deferred->reject();
                    $this->removeIp2StreamMapping($key);

                    Logger::getInstance()->log('NOTICE', 'Unable to connect to remote server: ' . $key);
                });
        }

        return $promise;
    }

}