<?php

namespace Hathoora\Jaal\Upstream\Http;

use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
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
        /** @var $Http \Hathoora\Jaal\Daemons\Http\Server */
        $Http = Jaal::getInstance()->getService('Http');

        /** @var $upstream \Hathoora\Jaal\Upstream\UpstreamManager */
        $upstream = $Http->upstreamManager;

        $uniqueName = $request->getScheme() . ':' . $request->getHost() . ':' . $request->getPort();

        if (!isset($upstream->arrPools[$uniqueName])) {
            $pool = new Pool($Http->upstreamManager, $arrConfig);
            $upstream->arrPools[$uniqueName] = $pool;
        } else {
            $pool = $upstream->arrPools[$uniqueName];
        }

        $Http->proxy($pool, $request);
    }
}