<?php

namespace Attozk\Roxy\Http;

use React\Promise\Deferred;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;


class UpstreamManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    private $pool2ConnectorMapping = array();

    public function __construct($loop, $dns)
    {
        $this->loop = $loop;
        $this->dns = $dns;
    }

    public function factory($host, $port, $secure = false)
    {
        // @TODO secure key/support
        $key = $host .':'. $port;

        #if (!isset($this->pool2ConnectorMapping[$key])) {
            $connector = new Connector($this->loop, $this->dns);
            $this->pool2ConnectorMapping[$key] = array('connector' => $connector);
        #}
        // @TODO explore possibility of reusing same connector
        #else {
        #    echo "ALREADY connector... \n";
        #    $connector = $this->pool2ConnectorMapping[$key]['connector'];
        #}

        return $connector;
    }
}