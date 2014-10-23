<?php

namespace Attozk\Jaal\Upstream;

use Attozk\Jaal\Httpd\Message\RequestInterface;
use Attozk\Jaal\Httpd\Message\RequestUpstreamHeaders;
use Attozk\Jaal\Httpd\Message\RequestUpstreamInterface;
use Attozk\Jaal\Logger;
use Dflydev\DotAccessConfiguration\Configuration;

class PoolHttpd extends Pool
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

        // the end product of all header's merging
        $arrRequestHeaders = $arrProxySetHeaders;
        $arrResponseHeaders = $arrAddHeaders;

        $arrConfig['headers']['proxy_request'] = $arrRequestHeaders;
        $arrConfig['headers']['proxy_response'] = $arrResponseHeaders;

        $this->config = new Configuration($arrConfig);
    }

    /**
     * @param RequestUpstreamInterface $request
     */
    public function prepareUpstreamRequestHeaders(RequestUpstreamInterface &$request)
    {
        if ($version = $request->pool->config->get('http_version')) {
            $request->setProtocolVersion($version);
        }

        // setting new proxy request headers
        $arrProxyRequestHeaders = $request->pool->config->get('headers.proxy_request');
        foreach ($arrProxyRequestHeaders as $header => $value) {
            Logger::getInstance()->debug('Setting proxy_set_header headers: ' . $header . ' => ' . $value);
            $request->setHeader($header, $value);
        }

        // copy headers from original request to upstream request
        $arrClientHeaders = $request->getClientRequest()->getHeaders();
        foreach ($arrClientHeaders as $header => $value) {
            $header = strtolower($header);

            if (isset(RequestUpstreamHeaders::$arrClientToProxyRequestHeaders[$header]) && !$request->hasHeader($header)) {
                $request->setHeader($header, $value);
                Logger::getInstance()->debug('Setting client_to_proxy headers: ' . $header . ' => ' . $value);
            }
        }
    }

    /**
     * @param RequestInterface $request
     * @return mixed
     */
    public function getServer(RequestInterface $request)
    {
        $arrUpstreams = $this->config->get('upstreams');

        return array_pop($arrUpstreams['servers']);
    }

    /**
     * @param RequestInterface $request
     * @param $arrConfig
     * @return static
     */
    public static function factory(RequestInterface $request, $arrConfig)
    {
        $pool = null;

        $uniqueName = 'httpd.' . $request->getScheme() . ':' . $request->getHost() . ':' . $request->getPort();
        if (!isset(UpstreamManager::$arrPools[$uniqueName])) {
            $pool = new static($arrConfig);
            UpstreamManager::$arrPools[$uniqueName] = $pool;
        } else {
            $pool = UpstreamManager::$arrPools[$uniqueName];
        }

        return $pool;
    }
}