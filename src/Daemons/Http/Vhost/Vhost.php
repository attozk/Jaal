<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Dflydev\DotAccessConfiguration\Configuration;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Httpd;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;
use Hathoora\Jaal\Util\Time;
use React\Promise\Deferred;

/**
 * Class Vhost
 *
 * @emit    ready [$upstreamRequest] ready to process upstream
 *
 * @package Hathoora\Jaal\Daemons\Http\Vhost
 */
Class Vhost
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var \Dflydev\DotAccessConfiguration\Configuration
     */
    public $config;

    /**
     * @var Httpd
     */
    protected $httpd;

    /**
     * @var \SplQueue
     */
    public $serverPool;

    /**
     * @param $arrConfig
     */
    public function __construct(Httpd $httpd, $arrConfig)
    {
        $this->id    = uniqid();
        $this->httpd = $httpd;
        $this->init($arrConfig);
        $this->serverPool = [
            'requests' => new \SplQueue(),      // stores UpstreamRequest
            'pools'    => [/*
                 $ip:port => array(
                    'hits' => 0,
                    'streamIds' => array(
                        $stream->id  = array()
                        ...
                    )
                 ),
                 $ip:port => array(
                    'streamIds' => array(
                        $stream->id  = array()
                        ...
                    )
                 ),
                 ....
                 */
            ]
        ];
    }

    /**
     * Sets configs, sets defaults and so on
     *
     * @param $arrConfig
     */
    public function init($arrConfig)
    {
        // add headers to response (i.e. sent to the client)
        $toClient                         = [];
        $serverToProxy = [];

        if (isset($arrConfig['headers']['add']) && is_array($arrConfig['headers']['add']))
        {
            foreach ($arrConfig['headers']['add'] as $header => $value)
            {
                $header = trim(strtolower($header));
                $value                    = trim($value);
                $toClient[$header] = $value;
            }
        }

        if (isset($arrConfig['proxy']) && isset($arrConfig['proxy']['headers']))
        {
            // additional headers passed to proxy (in addition to client's headers)
            if (isset($arrConfig['proxy']['headers']['set']) && is_array($arrConfig['proxy']['headers']['set']))
            {
                foreach ($arrConfig['proxy']['headers']['set'] as $header => $value)
                {
                    $header = trim(strtolower($header));
                    $value                = trim($value);
                    $serverToProxy[$header] = $value;
                }
            }

            // headers not passed from proxy server to client
            if (isset($arrConfig['proxy']['headers']['hide']) && is_array($arrConfig['proxy']['headers']['hide']))
            {
                foreach ($arrConfig['proxy']['headers']['hide'] as $header)
                {
                    $header = trim(strtolower($header));

                    if (!isset($toClient[$header]))
                        $toClient[$header] = false;
                }
            }
        }

        // keep alive?
        if (isset($arrConfig['upstreams']) && isset($arrConfig['upstreams']['keepalive']) && !empty($arrConfig['upstreams']['keepalive']['timeout']) &&
            isset($arrConfig['upstreams']['keepalive']['max'])
        )
        {
            $arrProxySetHeaders['Connection'] = 'Keep-Alive';
            $arrProxySetHeaders['Keep-Alive'] = 'timeout=' . $arrConfig['upstreams']['keepalive']['timeout'] . ',
            max=' .
                $arrConfig['upstreams']['keepalive']['max'];
        }
        else
        {
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
     * @param ClientRequestInterface $clientRequest
     *
     * @return array
     */
    public function getNextServerConfig(ClientRequestInterface $clientRequest)
    {
        $arrServer = $this->getAvailableUpstreamServer();
        $ip        = $arrServer['ip'];
        $port      = $arrServer['port'];

        $keepalive = $this->config->get('upstreams.keepalive.timeout');

        if ($keepalive)
        {
            $keepalive .= ':' . $this->config->get('upstreams.keepalive.max');
        }

        $timeout = $this->config->get('upstreams.timeout');
        $poolKey = $ip . ':' . $port;

        return [
            'ip'        => $ip,
            'port'      => $port,
            'keepalive' => $keepalive ? $keepalive : '',
            'timeout' => $timeout ? $timeout : 10,
            'poolKey' => $poolKey
        ];
    }

    /**
     * Add a stream to server pool
     *
     * @param        $key
     * @param Stream $stream
     *
     * @return $this
     */
    public function addServerStream($key, Stream $stream)
    {
        $id = $stream->id;

        if (!isset($this->serverPool['pools'][$key]))
        {
            $this->serverPool['pools'][$key] = [
                'streamIds' => [
                    $id => ['id' => $id]
                ]
            ];
        }
        else if (!isset($this->serverPools['pools'][$key]['streamIds'][$id]))
        {
            $this->serverPools['pools'][$key]['streamIds'][$id] = ['id' => $id];
        }

        return $this;
    }

    /**
     * Removes a stream from pool
     *
     * @param        $key
     * @param Stream $stream
     *
     * @return $this
     */
    public function removeServerStream($key, Stream $stream = null)
    {
        if (isset($this->serverPool['pools'][$key]))
        {
            if ($stream)
            {
                $id = $stream->id;
                if (isset($this->serverPool['pools'][$key]['streamIds'][$id]))
                    unset($this->serverPool['pools'][$key]['streamIds'][$id]);
            }
            else
                unset($this->serverPool['pools'][$key]);
        }

        return $this;
    }

    /**
     * Gets a stream from pool
     *
     * @param        $key
     * @param Stream $stream
     *
     * @return null|array
     */
    public function getServerStream($key, Stream $stream = null)
    {
        $value = null;

        if (!empty($this->serverPool['pools'][$key]))
        {
            if ($stream)
            {
                $id = $stream->id;
                if (isset($this->serverPool['pools'][$key]['streamIds'][$id]))
                    $value = $this->serverPool['pools'][$key]['streamIds'][$id];
            }
            else
            {
                $rnd   = array_rand($this->serverPool['pools'][$key]['streamIds']);
                $value = $this->serverPool['pools'][$key]['streamIds'][$rnd];
            }
        }

        return $value;
    }

    /**
     * Add a stream to server pool
     *
     * @return \SplQueue
     */
    public function getQueueRequests()
    {
        return $this->serverPool['requests'];
    }

    /**
     * @param ClientRequestInterface $clientRequest
     *
     * @return \React\Promise\Promise
     */
    public function connectToUpstreamServer(ClientRequestInterface $clientRequest)
    {
        $deferred          = new Deferred();
        $arrUpstreamConfig = $this->getNextServerConfig($clientRequest);
        $ip                = $arrUpstreamConfig['ip'];
        $port              = $arrUpstreamConfig['port'];
        $keepalive         = $arrUpstreamConfig['keepalive'];
        $timeout           = $arrUpstreamConfig['timeout'];
        $poolKey           = $arrUpstreamConfig['poolKey'];

        $stream = null;

        //if ($keepalive && ($streamInfo = $this->getServerStream($poolKey)) && ($stream = $this->httpd->outboundIOManager->getStreamById($streamInfo['id'])))
        //{
        //    Logger::getInstance()->log(-99, sprintf('%-25s' . $poolKey . "\n" .
        //                                            "\t" . 'Client-' . $this->httpd->debugStream($clientRequest->getStream(), "\t") . "\n" .
        //                                            "\t" . 'Upstream-' . $this->httpd->debugStream($stream, "\t") . "\n" .
        //                                            "\t" . $this->httpd->debugVhost($this), 'VHOST-STREAM[KA=1]'));
        //

        //        $info = ['status' => 'keepalive', 'stream' => $stream];
        //        $deferred->resolve($info);
        //}

        if ($stream == null)
        {
            $this->httpd->outboundIOManager->buildConnector($ip, $port)->then(
                function (Stream $stream) use ($deferred, $poolKey, $clientRequest)
                {
                    //$this->addServerStream($poolKey, $stream);
                    //$stream->on('close', function ($stream) use ($poolKey)
                    //{
                    //    //$this->removeServerStream($poolKey, $stream);
                    //   // print_r($this->serverPool);
                    //});

                    $info = ['status' => 'new', 'stream' => $stream];
                    $deferred->resolve($info);

                    Logger::getInstance()->log(-99, sprintf('%-25s' . $poolKey . "\n" .
                                                            "\t" . 'Client-' . $this->httpd->debugStream($clientRequest->getStream(), "\t") . "\n" .
                                                            "\t" . 'Proxy-' . $this->httpd->debugStream($stream, "\t") . "\n" .
                                                            "\t" . $this->httpd->debugVhost($this), 'VHOST-STREAM-NEW'));
                },
                function ($error) use ($deferred)
                {
                    $deferred->resolve($error);
                }
            );
        }

        return $deferred->promise();
    }
}