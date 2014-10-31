<?php

namespace Hathoora\Jaal\IO\Elastica;

use Hathoora\Jaal\IO\Elastica\Transport\React;

class Connection extends \Elastica\Connection
{
    public function getTransport()
    {
        return new React();
    }

    public static function create($params = [])
    {
        $connection = NULL;

        if ($params instanceof Connection) {
            $connection = $params;
        } elseif (is_array($params)) {
            $connection = new Connection($params);
        }

        return $connection;
    }
}