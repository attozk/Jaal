<?php

namespace Hathoora\Jaal;

use Hathoora\Jaal\Daemons\Http\Httpd;
use Hathoora\Jaal\Daemons\Admin\WAMP;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\Wamp\WampServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Factory as DnsFactory;
use Dflydev\DotAccessConfiguration\YamlFileConfigurationBuilder;

class Jaal
{
    const name = 'Jaal/0.1';

    /**
     * @var Jaal
     */
    protected static $instance;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    public $loop;

    /**
     * @var \React\Dns\Resolver\Resolver
     */
    public $dns;

    /**
     * Path to config file
     *
     * @var string
     */
    protected $configFilePath;

    /**
     * Path to conf.d
     *
     * @var
     */
    protected $confDPath;

    /**
     * @var \Dflydev\DotAccessConfiguration\Configuration
     */
    public $config;

    /** @var  \Hathoora\Jaal\Daemons\Http\Httpd */
    protected $httpd;

    /** @var  \Hathoora\Jaal\Daemons\Admin\WAMP */
    protected $admin;

    private function __construct()
    {
    }

    public static function execute($docopt)
    {
        $loop = LoopFactory::create();
        self::getInstance()->setup($loop);
        ini_set('memory_limit', '1024M');
        $loop->run();
    }

    public function setup(LoopInterface $loop)
    {
        $this->loop           = $loop;
        $this->configFilePath = realpath(__DIR__ . '/../conf.yml');
        $this->confDPath      = realpath(__DIR__ . '/../conf.d/');
        $this->initConfig();

        $dnsResolverFactory = new DnsFactory();
        $this->dns = $dnsResolverFactory->createCached($this->config->get('jaal.resolver'), $loop);

        $this->initDaemons();
        $this->initConfD();
    }

    public function initConfig()
    {
        $configBuilder = new YamlFileConfigurationBuilder([$this->configFilePath]);
        $this->config  = $configBuilder->build();
    }

    public function initDaemons()
    {
        Logger::getInstance()->log(100, 'Event Library: ' . get_class($this->loop));
        if ($this->config->get('httpd') && ($port = $this->config->get('httpd.port')) && ($ip = $this->config->get('httpd.listen'))) {
            Logger::getInstance()->log(100, 'HTTPD listening on ' . $ip . ':' . $port);
            $socket = new SocketServer($this->loop);
            $this->httpd = new Httpd($this->loop, $socket, $this->dns);
            $this->httpd->listen($port, $ip);

            $this->loop->addPeriodicTimer(
                20,
                function ()
                {

                    #echo 'Size of HTTPD: ' .  round(strlen(serialize($this->httpd) / 1024 / 1024, 2)) . " MB \n";
                    #echo 'Size of Inbound IO: ' .  round(strlen(serialize($this->httpd->inboundIOManager) / 1024 / 1024, 2)) . " MB \n";
                    #echo 'Size of Outbound IO: ' .  round(strlen(serialize($this->httpd->outboundIOManager) / 1024 / 1024, 2)) . " MB \n";
                    print_r($this->httpd->stats());

                    echo date('Y-m-d H:i:s') . '----------------------------GC----------------------------' . "\n" .
                         'Memory: ' . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n" .
                         'Peak Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 2) . "MB \n" . "\n\n";
                });
        }

        if ($this->config->get('admin') && ($port = $this->config->get('admin.port')) && ($ip = $this->config->get('admin.listen'))) {

            $this->admin = new WAMP($this->loop);
            Logger::getInstance()->log(100, 'Admin WAMP Server listening on ' . $ip . ':' . $port);

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
     *
     * @TODO make this async
     */
    public function initConfD()
    {
        $directory   = new \RecursiveDirectoryIterator($this->confDPath);
        $recIterator = new \RecursiveIteratorIterator($directory);
        $regex       = new \RegexIterator($recIterator, '/^.+\.php$/i');

        $httpd = $this->getDaemon('httpd');
        foreach ($regex as $item) {

            $filePath = $item->getPathname();
            Logger::getInstance()->debug('Config file loaded: ' . $filePath);

            include($filePath);
        }
    }

    /**
     * @param $name
     * @return null|Httpd
     */
    public function getDaemon($name)
    {
        $service = NULL;

        if (isset($this->$name) && is_object($this->$name)) {
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
