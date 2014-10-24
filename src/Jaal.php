<?php

namespace Hathoora\Jaal;

use Hathoora\Jaal\Httpd\Server as Httpd;
use Hathoora\Jaal\Monitoring\Monitoring;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\WampServer;
use Ratchet\WebSocket\WsServer;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Socket\Server as SocketServer;
use Dflydev\DotAccessConfiguration\YamlFileConfigurationBuilder;

class Jaal
{
    /**
     * @var Jaal
     */
    protected static $instance;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * @var \React\Dns\Resolver\Resolver
     */
    protected $dns;

    /**
     * Path to config file
     *
     * @var string
     */
    protected $configFilePath;

    /**
     * Path to conf.d
     * @var
     */
    protected $confDDirPath;

    /**
     * @var \Dflydev\DotAccessConfiguration\Configuration
     */
    protected $config;

    /** @var  \Hathoora\Jaal\Httpd\Server */
    protected $httpd;

    /** @var  \Hathoora\Jaal\Monitoring\Monitoring */
    protected $monitoring;

    private function __construct()
    {}

    public function setup(LoopInterface $loop, Resolver $dns)
    {
        $this->loop = $loop;
        $this->dns = $dns;
        $this->configFilePath = realpath(__DIR__ .'/../conf.yml');
        $this->confDDirPath = realpath(__DIR__ .'/../conf.d/');
        $this->initConfig();
        $this->initServices();
        $this->initConfD();
    }

    public function initConfig()
    {
        $configBuilder = new YamlFileConfigurationBuilder(array($this->configFilePath));
        $this->config = $configBuilder->build();
    }

    public function initServices()
    {
        if ($this->config->get('httpd') && ($port = $this->config->get('httpd.port')) && ($ip = $this->config->get('httpd.listen')))
        {
            Logger::getInstance()->debug('HTTP listening on ' . $ip .':' . $port);
            $socket = new SocketServer($this->loop);
            $this->httpd = new Httpd($this->loop, $socket, $this->dns);
            $this->httpd->listen($port, $ip);
        }

        if ($this->config->get('monitoring') && ($port = $this->config->get('monitoring.port')) && ($ip = $this->config->get('monitoring.listen'))) {

            $this->monitoring = new Monitoring($this->loop);

            $socket = new SocketServer($this->loop);
            $socket->listen($port, $ip);
            new IoServer(
                new HttpServer(
                    new WsServer(
                        new WampServer(
                            $this->monitoring
                        )
                    )
                ),
                $socket
            );
        }
    }

    /**
     * Load confD
     * @TODO make this async
     */
    public function initConfD()
    {
        $directory = new \RecursiveDirectoryIterator($this->confDDirPath);
        $recIterator = new \RecursiveIteratorIterator($directory);
        $regex = new \RegexIterator($recIterator, '/^.+\.php$/i');

        $httpd = $this->getService('httpd');
        foreach($regex as $item) {

            $filePath = $item->getPathname();
            Logger::getInstance()->debug('Included conf.d >>> '. $filePath);

            include($filePath);
        }
    }

    /**
     * @param $name http for now
     */
    public function getService($name)
    {
        $service = null;

        if ($name == 'httpd') {
            if (isset($this->$name) && is_object($this->$name))
                $service = $this->$name;
        }

        return $service;
    }

    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new self;
        }

        return static::$instance;
    }

}
