<?php

namespace Hathoora\Jaal\Daemons\Http\Vhost;

use Hathoora\Jaal\Daemons\Http\Httpd;

class Factory
{
    /**
     * Stores vhosts
     *
     * @var array
     */
    public static $arrVhosts;

    /**
     * Creates a Vhost factory
     *
     * @param $arrConfig
     * @param $scheme
     * @param $host
     * @param $port
     * @return Vhost
     */
    public static function create(Httpd $httpd, $arrConfig, $scheme, $host, $port)
    {
        $uniqueName = $scheme . ':' . $host . ':' . $port;

        if (!isset(self::$arrVhosts[$uniqueName])) {
            $vhost = new Vhost($httpd, $arrConfig);
            self::$arrVhosts[$uniqueName] = $vhost;
        } else {
            $vhost = self::$arrVhosts[$uniqueName];
        }

        return $vhost;
    }
}