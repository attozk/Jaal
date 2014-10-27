<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
use Hathoora\Jaal\Jaal;

class Factory
{
    /**
     * Stores vhosts
     *
     * @var array
     */
    public static $arrVhosts;

    /**
     * @param $arrConfig
     * @param $scheme
     * @param $host
     * @param $port
     * @return Vhost
     */
    public static function create($arrConfig, $scheme, $host, $port)
    {
        $uniqueName = $scheme . ':' . $host . ':' . $port;
        $httpd = Jaal::getInstance()->getDaemon('httpd');
        $outboundIOManager = $httpd->outboundIOManager;

        if (!isset(self::$arrVhosts[$uniqueName])) {
            $vhost = new Vhost($arrConfig);
            self::$arrVhosts[$uniqueName] = $vhost;
        } else {
            $vhost = self::$arrVhosts[$uniqueName];
        }

        return $vhost;
    }
}