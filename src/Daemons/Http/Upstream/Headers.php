<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

class Headers
{
    /**
     * List of headers that we can pass from client to upstream server
     *
     * @var array
     */
    public static $arrAllowedUpstreamHeaders = [
            'accept'            => 1,
            'accept-language'   => 1,
            'accept-charset'    => 1,
            'accept-encoding'   => 1,
            'cache-control'     => 1,
            'cookie'            => 1,
            'content-length'    => 1,
            'content-type'      => 1,
            'host'              => 1,
            'if-match'          => 1,
            'if-modified-Since' => 1,
            'user-agent'        => 1
        ];
}