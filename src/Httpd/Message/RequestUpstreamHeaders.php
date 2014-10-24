<?php

namespace Hathoora\Jaal\Httpd\Message;

class RequestUpstreamHeaders
{
    // allowed headers that we can pass from client to proxy
    // keep it lower case
    public static $arrClientToProxyRequestHeaders = array(
        'accept' => 1,
        'accept-language' => 1,
        'accept-charset' => 1,
        'accept-encoding' => 1,
        'cache-control' => 1,
        'cookie' => 1,
        'content-length' => 1,
        'content-type' => 1,
        'host' => 1,
        'if-match' => 1,
        'if-modified-Since' => 1,
        'user-agent' => 1
    );
}