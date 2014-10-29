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
     */
    public function __construct($arrConfig)
    {
        $this->init($arrConfig);
    }

    public function init($arrConfig)
    {
        // additional headers passed to proxy (in addition to client's headers)
        $arrProxySetHeaders = isset($arrConfig['proxy_set_header']) && is_array($arrConfig['proxy_set_header']) ? $arrConfig['proxy_set_header'] : array();

        // add headers to response (i..e sent to the client)
        $arrAddHeaders = isset($arrConfig['add_header']) && is_array($arrConfig['add_header']) ? $arrConfig['add_header'] : array();

        // headers not passed from proxy server to client
        $arrProxyHideHeaders = isset($arrConfig['proxy_hide_header']) && is_array($arrConfig['proxy_hide_header']) ? $arrConfig['proxy_hide_header'] : array();
        foreach ($arrProxyHideHeaders as $header) {
            $arrAddHeaders[$header] = false;
        }

        // keep alive?
        if (isset($arrConfig['upstreams']) && isset($arrConfig['upstreams']['keepalive']) && !empty($arrConfig['upstreams']['keepalive']['timeout']) && isset($arrConfig['upstreams']['keepalive']['max'])) {
            $arrProxySetHeaders['Connection'] = 'Keep-Alive';
            $arrProxySetHeaders['Keep-Alive'] = 'timeout=' . $arrConfig['upstreams']['keepalive']['timeout'] . ', max=' . $arrConfig['upstreams']['keepalive']['max'];
        } else {
            $arrProxySetHeaders['Connection'] = 'Close';
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

    public function getUpstreamConnectorConfig()
    {
        $arrServer = $this->getAvailableUpstreamServer();
        $ip = $arrServer['ip'];
        $port = $arrServer['port'];
        $keepalive = $this->config->get('upstreams.keepalive.timeout');
        if ($keepalive)
            $keepalive .= ':' . $this->config->get('upstreams.keepalive.max');

        $timeout = $this->config->get('upstreams.timeout');

        return array(
            'ip' => $ip,
            'port' => $port,
            'keepalive' => $keepalive ? $keepalive : '',
            'timeout' => $timeout ? $timeout : '');
    }

}