<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Dflydev\DotAccessConfiguration\Configuration;
use Hathoora\Jaal\IO\Manager\OutboundManager;
use Hathoora\Jaal\Daemons\Http\Upstream\Request as UpstreamRequest;
use React\Promise\Deferred;
use React\Stream\Stream;

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

    /**
     * @var
     */
    public $arrUpstreamConnectors;

    /**
     * @param $arrConfig
     * @param OutboundManager $outboundIOManager
     */
    public function __construct($arrConfig, OutboundManager $outboundIOManager)
    {
        $this->outboundIOManager = $outboundIOManager;
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
     *
     * @return mixed
     */
    public function getAvailableUpstreamServer()
    {
        $arrUpstreams = $this->config->get('upstreams');

        return array_pop($arrUpstreams['servers']);
    }

    /**
     * @param UpstreamRequest $request
     * @return \React\Promise\Promise
     */
    public function getUpstreamSocket(UpstreamRequest $request)
    {
        $arrServer = $this->getAvailableUpstreamServer();
        $ip = $arrServer['ip'];
        $port = $arrServer['port'];
        $keepAlive = $this->config->get('upstreams.keepalive');
        $timeout = $this->config->get('upstreams.timeout');

        return $this->outboundIOManager->buildConnector($ip, $port, $keepAlive, $timeout);
    }

}