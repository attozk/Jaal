<?php

namespace Hathoora\Jaal\Upstream\Httpd;

use Guzzle\Http\Message\AbstractMessage;
use Hathoora\Jaal\Httpd\Message\RequestInterface;
use Hathoora\Jaal\Httpd\Message\RequestUpstream;
use Hathoora\Jaal\Httpd\Message\RequestUpstreamHeaders;
use Hathoora\Jaal\Httpd\Message\RequestUpstreamInterface;
use Hathoora\Jaal\Logger;
use Dflydev\DotAccessConfiguration\Configuration;
use React\SocketClient\ConnectorInterface;
use Hathoora\Jaal\Upstream\UpstreamManager;
Use Hathoora\Jaal\Upstream\Pool as PoolBase;

class Pool extends PoolBase
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

        // keep alive?
        if (isset($arrConfig['upstreams']) && !empty($arrConfig['upstreams']['keepalive'])) {
            $arrProxySetHeaders['Connection'] = 'Keep-Alive';
        }

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
    public function prepareClientToProxyRequestHeaders(RequestUpstreamInterface &$request)
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
     * @param RequestUpstream $request
     * @param AbstractMessage $response
     */
    public function prepareProxyToClientHeaders(RequestUpstream &$request, AbstractMessage &$response)
    {
        $arrProxyResponseHideHeaders = $request->pool->config->get('proxy_hide_header');
        foreach ($arrProxyResponseHideHeaders as $header) {
            $response->removeHeader($header);
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
     * @return mixed
     */
    public function getUpstreamSocket(RequestInterface $request)
    {
        $deferred = new Deferred();
        $arrServer = $this->getServer($this->clientRequest);
        $promise = $deferred->promise();



        $this->upstreamSocket->create($arrServer['ip'], $arrServer['port'])->then(
            function ($stream) use ($deferred, $arrServer) {

                Logger::getInstance()->debug('Upstream connected to ' . $arrServer['ip'] . ':' . $arrServer['port']);
                $this->setUpstreamStream($stream);
                $deferred->resolve($this);
            },
            // @TODO handle error
            function () use ($deferred, $arrServer) {
                $deferred->reject();

                Logger::getInstance()->debug('Upstream connection error to ' . $arrServer['ip'] . ':' . $arrServer['port']);
            }
        );

        return $promise;
    }
}