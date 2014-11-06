<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Dflydev\DotAccessConfiguration\Configuration;

Class Vhost
{
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
        // add headers to response (i.e. sent to the client)
        $toClient = [];
        $serverToProxy = [];

        if (isset($arrConfig['headers']['add']) && is_array($arrConfig['headers']['add'])) {
            foreach ($arrConfig['headers']['add'] as $header => $value) {
                $header = trim(strtolower($header));
                $value = trim($value);
                $toClient[$header] = $value;
            }
        }

        if (isset($arrConfig['proxy']) && isset($arrConfig['proxy']['headers'])) {
            // additional headers passed to proxy (in addition to client's headers)
            if (isset($arrConfig['proxy']['headers']['set']) && is_array($arrConfig['proxy']['headers']['set'])) {
                foreach ($arrConfig['proxy']['headers']['set'] as $header => $value) {
                    $header = trim(strtolower($header));
                    $value = trim($value);
                    $serverToProxy[$header] = $value;
                }
            }

            // headers not passed from proxy server to client
            if (isset($arrConfig['proxy']['headers']['hide']) && is_array($arrConfig['proxy']['headers']['hide'])) {
                foreach ($arrConfig['proxy']['headers']['hide'] as $header) {
                    $header = trim(strtolower($header));

                    if (!isset($toClient[$header]))
                        $toClient[$header] = false;
                }
            }
        }


        // keep alive?
        if (isset($arrConfig['upstreams']) && isset($arrConfig['upstreams']['keepalive']) && !empty($arrConfig['upstreams']['keepalive']['timeout']) &&
            isset($arrConfig['upstreams']['keepalive']['max'])
        ) {
            $arrProxySetHeaders['Connection'] = 'Keep-Alive';
            $arrProxySetHeaders['Keep-Alive'] = 'timeout=' . $arrConfig['upstreams']['keepalive']['timeout'] . ',
            max=' .
                $arrConfig['upstreams']['keepalive']['max'];
        } else {
            $arrProxySetHeaders['Connection'] = 'Close';
        }

        $arrConfig['headers']['serverToProxy'] = $serverToProxy;
        $arrConfig['headers']['toClient'] = $toClient;

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