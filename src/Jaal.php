<?php

namespace Attozk\Jaal;

use Attozk\Jaal\Httpd\Server as Httpd;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Socket\Server as SocketServer;
use Dflydev\DotAccessConfiguration\YamlFileConfigurationBuilder;

class Jaal
{
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

    protected $httpd;

    public function __construct(LoopInterface $loop, Resolver $dns)
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
            $socket = new SocketServer($this->loop);
            $this->httpd = new Httpd($this->loop, $socket, $this->dns);
            $this->httpd->listen($port, $ip);
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

        foreach($regex as $item) {
            $httpd = $this->getService('httpd');
            include($item->getPathname());
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

}
