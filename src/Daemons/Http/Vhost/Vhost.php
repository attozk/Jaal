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
    protected $config;

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

}