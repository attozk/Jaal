<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Dflydev\DotAccessConfiguration\Configuration;

Class Vhost
{
    /**
     * // nginx inspired @http://nginx.org/en/docs/http/ngx_http_upstream_module.html#health_check
     * array (
     * 'keepalive' => 10,
     *
     * //'strategy' => 'round-robin|sticky|least_conn|etc...',
     * 'servers' => array(
     * 'weight:name' => array(
     * 'ip' => '192.168.1.44',
     * 'port' => 80,
     * 'weight' => 5,
     * 'max_fails' => 5,
     * 'fail_timeout' => 10,
     * 'max_conns' => 100,
     * )
     * )*/

    /**
     * @var \Dflydev\DotAccessConfiguration\Configuration
     */
    public $config;

    public function __construct($arrConfig)
    {
        $this->config = new Configuration($arrConfig);
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
     *
     * @return mixed
     */
    public function getAvailableUpstreamServer()
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
        $arrServer = $this->getAvailableUpstreamServer();

        return $this->upstreamSocketFactory($arrServer, $requestUpstream);
    }

}