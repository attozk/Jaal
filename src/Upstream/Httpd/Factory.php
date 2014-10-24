<?php

namespace Hathoora\Jaal\Upstream\Httpd;

use Hathoora\Jaal\Httpd\Message\RequestInterface;
use Hathoora\Jaal\Jaal;

class Factory
{
    /**
     * @param RequestInterface $request
     * @param $arrConfig
     * @return static
     */
    public static function create(RequestInterface $request, $arrConfig)
    {
        /** @var $httpd \Hathoora\Jaal\Httpd\Server */
        $httpd = Jaal::getInstance()->getService('httpd');

        /** @var $upstream \Hathoora\Jaal\Upstream\UpstreamManager */
        $upstream = $httpd->upstreamManager;

        $uniqueName = $request->getScheme() . ':' . $request->getHost() . ':' . $request->getPort();

        if (!isset($upstream->arrPools[$uniqueName])) {
            $pool = new Pool($httpd->upstreamManager, $arrConfig);
            $upstream->arrPools[$uniqueName] = $pool;
        } else {
            $pool = $upstream->arrPools[$uniqueName];
        }

        $httpd->proxy($pool, $request);
    }
}