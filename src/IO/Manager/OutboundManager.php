<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Logger;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use SplObjectStorage;

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

    /**
     * @var SplObjectStorage for storing ConnectorInterface
     */
    protected $connectors;

    protected $arrKeepAliveConnections;

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
        $this->connectors = new SplObjectStorage();
    }

    public function add(ConnectorInterface $connector)
    {
        $this->connectors->attach($connector);
    }

    public function remove(ConnectorInterface $connector)
    {
        $this->connectors->detach($connector);
    }

    public function end(ConnectorInterface $connector)
    {
        $this->connectors->detach($connector);
    }

    public function count()
    {
        return $this->connectors->count();
    }

    public function buildConnector()
    {
        $connector = new Connector($this->loop, $this->dns);

        return $connector;
    }

    /**
     * @param $ip
     * @param $port
     * @param $keepAlive format is -> Seconds:Number of requests
     * @return \React\Promise\Promise
     */
    public function buildKeepAliveConnector($ip, $port, $keepAlive, $timeout = 10)
    {
        $key = $ip . ':' . $port;

        if (!$timeout)
            $timeout = 10;

        $connector = null;
        $deferred = new Deferred();
        $promise = $deferred->promise();

        if (isset($this->arrKeepAliveConnections[$key])) {

            if ($this->arrKeepAliveConnections[$key]['status'] == 'connected') {

                $this->arrKeepAliveConnections[$key]['hits']++;

                #Logger::getInstance()->log('DEBUG', 'Connector ('. $key .') is reused with hits: '. $this->arrKeepAliveConnections[$key]['hits']);
                $deferred->resolve($this->arrKeepAliveConnections[$key]['stream']);
            }
        }

        // reuse existing stream...
        if (!isset($this->arrKeepAliveConnections[$key]) || (isset($this->arrKeepAliveConnections[$key]) && $this->arrKeepAliveConnections[$key]['status'] != 'connected')) {

            $connector = $this->buildConnector();

            if (!isset($this->arrKeepAliveConnections[$key])) {

                $this->arrKeepAliveConnections[$key] = array(
                    'connector' => $connector,
                    'start' => null,
                    'status' => 'pending',
                    'connectCount' => 0,
                    'hits' => 0
                );
            }

            // @TODO keep track of timeout and implement

            $connector->create($ip, $port)->then(function (Stream $stream) use ($deferred, $key) {

                    $this->arrKeepAliveConnections[$key]['start'] = time();
                    $this->arrKeepAliveConnections[$key]['stream'] = $stream;
                    $this->arrKeepAliveConnections[$key]['status'] = 'connected';
                    $this->arrKeepAliveConnections[$key]['connectCount']++;
                    $this->arrKeepAliveConnections[$key]['hits']++;

                    Logger::getInstance()->log('DEBUG', 'Connector ('. $key .') has been connected');
                    $deferred->resolve($stream);

                    $stream->on('close', function () use ($key) {
                        unset($this->arrKeepAliveConnections[$key]);

                        Logger::getInstance()->log('DEBUG', 'Connector ('. $key .') has been disconnected.');
                    });


                },
                function ($error) use ($deferred, $key) {
                    unset($this->arrKeepAliveConnections[$key]);

                    $deferred->reject();
                    Logger::getInstance()->log('DEBUG', 'Connector ('. $key .') failed to connect.');
                });
        }

        return $promise;
    }

}