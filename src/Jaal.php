<?php

namespace Hathoora\Jaal;

use Hathoora\Jaal\Daemons\Http\Httpd;
use Hathoora\Jaal\Daemons\Admin\WAMP;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\WampServer;
use Ratchet\WebSocket\WsServer;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
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
    protected $confDPath;

    /**
     * @var \Dflydev\DotAccessConfiguration\Configuration
     */
    public $config;

    /** @var  \Hathoora\Jaal\Daemons\Http\Server */
    protected $httpd;

    /** @var  \Hathoora\Jaal\Daemons\Monitoring\WAMP */
    protected $admin;

    private function __construct()
    {}

    public function setup(LoopInterface $loop, Resolver $dns)
    {
        $this->loop = $loop;
        $this->dns = $dns;
        $this->configFilePath = realpath(__DIR__ .'/../conf.yml');
        $this->confDPath = realpath(__DIR__ .'/../conf.d/');
        $this->initConfig();
        $this->initDaemons();
        $this->initConfD();
    }

    public function initConfig()
    {
        $configBuilder = new YamlFileConfigurationBuilder(array($this->configFilePath));
        $this->config = $configBuilder->build();
    }

    public function initDaemons()
    {
        if ($this->config->get('httpd') && ($port = $this->config->get('httpd.port')) && ($ip = $this->config->get('httpd.listen')))
        {
            Logger::getInstance()->info('HTTPD listening on ' . $ip .':' . $port);
            $socket = new SocketServer($this->loop);
            $this->httpd = new Httpd($this->loop, $socket, $this->dns);
            $this->httpd->listen($port, $ip);

            #$this->loop->addPeriodicTimer(5, function () {
            #    print_r($this->httpd->stats());
            #});
        }

        if ($this->config->get('admin') && ($port = $this->config->get('admin.port')) && ($ip = $this->config->get('admin.listen'))) {

            $this->admin = new WAMP($this->loop);
            Logger::getInstance()->info('Admin WAMP Server listening on ' . $ip . ':' . $port);

            $socket = new SocketServer($this->loop);
            $socket->listen($port, $ip);
            new IoServer(
                new HttpServer(
                    new WsServer(
                        new WampServer(
                            $this->admin
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
        $directory = new \RecursiveDirectoryIterator($this->confDPath);
        $recIterator = new \RecursiveIteratorIterator($directory);
        $regex = new \RegexIterator($recIterator, '/^.+\.php$/i');

        $httpd = $this->getDaemon('httpd');
        foreach($regex as $item) {

            $filePath = $item->getPathname();
            Logger::getInstance()->debug('HTTPD config file loaded: '. $filePath);

            include($filePath);
        }
    }

    public function getDaemon($name)
    {
        $service = null;

        if (isset($this->$name) && is_object($this->$name))
            $service = $this->$name;

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
