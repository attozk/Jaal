<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Dflydev\DotAccessConfiguration\Configuration;

Class Vhost
{
    /**
     * // nginx inspired @http://nginx.org/en/docs/http/ngx_http_upstream_module.html#health_check
     * array (
     * 'keepalive' => 10,
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
     * @param $arrConfig
     */
    public function __construct($arrConfig)
    {
        $this->init($arrConfig);
    }

    /**
     * Sets configs, sets defaults and so on
     *
     * @param $arrConfig
     */
    public function init($arrConfig)
    {
        // additional headers passed to proxy (in addition to client's headers)
        $arrProxySetHeaders = isset($arrConfig['proxy_set_header']) &&
                              is_array($arrConfig['proxy_set_header']) ? $arrConfig['proxy_set_header'] : [];

        // add headers to response (i..e sent to the client)
        $arrAddHeaders
            = isset($arrConfig['add_header']) && is_array($arrConfig['add_header']) ? $arrConfig['add_header'] : [];

        // headers not passed from proxy server to client
        $arrProxyHideHeaders = isset($arrConfig['proxy_hide_header']) &&
                               is_array($arrConfig['proxy_hide_header']) ? $arrConfig['proxy_hide_header'] : [];
        foreach ($arrProxyHideHeaders as $header) {
            $arrAddHeaders[$header] = FALSE;
        }

        // keep alive?
        if (isset($arrConfig['upstreams']) && isset($arrConfig['upstreams']['keepalive']) &&
            !empty($arrConfig['upstreams']['keepalive']['timeout']) &&
            isset($arrConfig['upstreams']['keepalive']['max'])
        ) {
            $arrProxySetHeaders['Connection'] = 'Keep-Alive';
            $arrProxySetHeaders['Keep-Alive']
                                              =
                'timeout=' . $arrConfig['upstreams']['keepalive']['timeout'] . ', max=' .
                $arrConfig['upstreams']['keepalive']['max'];
        } else {
            $arrProxySetHeaders['Connection'] = 'Close';
        }

        // the end product of all header's merging
        $arrRequestHeaders  = $arrProxySetHeaders;
        $arrResponseHeaders = $arrAddHeaders;

        $arrConfig['headers']['server_to_upstream_request']  = $arrRequestHeaders;
        $arrConfig['headers']['upstream_to_client_response'] = $arrResponseHeaders;

        $this->config = new Configuration($arrConfig);
    }

    /**
     * Return a server
     *
     * @return array of server
     */
    public function getAvailableUpstreamServer()
    {
        $arrUpstreams = $this->config->get('upstreams');

        return array_pop($arrUpstreams['servers']);
    }

    /**
     * Return upstream configs that can be used to create a connector
     *
     * @return array
     */
    public function getUpstreamConnectorConfig()
    {
        $arrServer = $this->getAvailableUpstreamServer();
        $ip        = $arrServer['ip'];
        $port      = $arrServer['port'];

        $keepalive = $this->config->get('upstreams.keepalive.timeout');

        if ($keepalive) {
            $keepalive .= ':' . $this->config->get('upstreams.keepalive.max');
        }

        $timeout = $this->config->get('upstreams.timeout');

        return [
            'ip'        => $ip,
            'port'      => $port,
            'keepalive' => $keepalive ? $keepalive : '',
            'timeout'   => $timeout ? $timeout : ''
        ];
    }
}