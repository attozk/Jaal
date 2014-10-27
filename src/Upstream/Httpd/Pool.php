<?php

namespace Hathoora\Jaal\Upstream\Http;

use Guzzle\Http\Message\AbstractMessage;
use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\RequestUpstream;
use Hathoora\Jaal\Daemons\Http\Message\RequestUpstreamHeaders;
use Hathoora\Jaal\Daemons\Http\Message\RequestUpstreamInterface;
use Hathoora\Jaal\Logger;
use Dflydev\DotAccessConfiguration\Configuration;
use React\Promise\Deferred;
use React\SocketClient\ConnectorInterface;
use Hathoora\Jaal\Upstream\UpstreamManager;
Use Hathoora\Jaal\Upstream\Pool as PoolBase;
use React\Stream\Stream;

class Pool extends PoolBase
{


    /**
     * Array of \React\SocketClient\ConnectorInterface
     */
    private $upstreamConnectors = array();

    /** @var  \Hathoora\Jaal\Upstream\UpstreamManager */
    private $upstreamManager;

    public function __construct(UpstreamManager $upstreamManager, $arrConfig)
    {
        $this->upstreamManager = $upstreamManager;
        $this->init($arrConfig);
    }

    public function init($arrConfig)
    {
        // additional headers passed to proxy (in addition to client's headers)
        $arrProxySetHeaders = isset($arrConfig['proxy_set_header']) && is_array($arrConfig['proxy_set_header']) ? $arrConfig['proxy_set_header'] : array();
        $arrProxySetHeaders['Connection'] = '';

        // add headers to response (i..e sent to the client)
        $arrAddHeaders = isset($arrConfig['add_header']) && is_array($arrConfig['add_header']) ? $arrConfig['add_header'] : array();

        // headers not passed from proxy server to client
        $arrProxyHideHeaders = isset($arrConfig['proxy_hide_header']) && is_array($arrConfig['proxy_hide_header']) ? $arrConfig['proxy_hide_header'] : array();
        foreach ($arrProxyHideHeaders as $header) {
            $arrAddHeaders[$header] = false;
        }

        // keep alive?
        if (isset($arrConfig['upstreams']) && !empty($arrConfig['upstreams']['keepalive'])) {
            $arrProxySetHeaders['Connection'] = 'Keep-Alive';
        }

        // the end product of all header's merging
        $arrRequestHeaders = $arrProxySetHeaders;
        $arrResponseHeaders = $arrAddHeaders;

        $arrConfig['headers']['server_to_upstream_request'] = $arrRequestHeaders;
        $arrConfig['headers']['upstream_to_client_response'] = $arrResponseHeaders;

        $this->config = new Configuration($arrConfig);
    }

    /**
     * Return a server based on load/health etc..
     * @return mixed
     */
    public function getServer()
    {
        $arrUpstreams = $this->config->get('upstreams');

        return array_pop($arrUpstreams['servers']);
    }

    public function upstreamSocketFactory($arrServer, RequestUpstream $requestUpstream)
    {
        $ip = $arrServer['ip'];
        $port = $arrServer['port'];
        $failTimeout = $arrServer['fail_timeout'];
        $MaxFails = $arrServer['max_fails'];

        $key = $ip . ':' . $port;
        $connector = null;
        $deferred = new Deferred();
        $promise = $deferred->promise();

        if (isset($this->upstreamConnectors[$key])) {

            // stream is connected?
            if ($this->upstreamConnectors[$key]['status'] == 'connected') {
                $deferred->resolve($this->upstreamConnectors[$key]['stream']);
            }
        }

        // reuse existing stream...
        if (!isset($this->upstreamConnectors[$key]) || (isset($this->upstreamConnectors[$key]) && $this->upstreamConnectors[$key]['status'] != 'connected')) {

            $connector = $this->upstreamManager->buildConnector();

            if (!isset($this->upstreamConnectors[$key])) {

                $this->upstreamConnectors[$key] = array(
                    'connector' => $connector,
                    'start' => null,
                    'status' => 'pending',
                    'connectCount' => 0
                );
            }

            // @TODO keep track of timeout and implement fail_timeout
            $connector->create($ip, $port)->then(function (Stream $stream) use ($deferred, $key, $requestUpstream) {
                    $this->upstreamConnectors[$key]['start'] = time();
                    $this->upstreamConnectors[$key]['stream'] = $stream;
                    $this->upstreamConnectors[$key]['status'] = 'connected';
                    $this->upstreamConnectors[$key]['connectCount']++;

                    Logger::getInstance()->debug('Upstream connected for ' . $key);

                    $stream->on('close', function () use ($key) {
                        Logger::getInstance()->debug('Upstream closed for ' . $key);
                        $this->upstreamConnectors[$key]['status'] = 'disconnected';
                    });

                    $deferred->resolve($stream);
                },
                function ($error) use ($deferred, $key, $requestUpstream) {
                    Logger::getInstance()->debug('Upstream closed for ' . $key);
                    $this->upstreamConnectors[$key]['status'] = 'error';

                    // close client?
                    //$requestUpstream->getClientRequest()->send();

                    $deferred->reject();
                });
        }

        return $promise;
    }

    public function getUpstreamSocket(RequestUpstream $requestUpstream)
    {
        $arrServer = $this->getServer();

        return $this->upstreamSocketFactory($arrServer, $requestUpstream);
    }
}