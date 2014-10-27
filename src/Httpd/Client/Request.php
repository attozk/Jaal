<?php

namespace Hathoora\Jaal\Httpd\Client;

Class Request extends \http\Client\Request implements RequestInterface
{
    /**
     * Create a new client request message to be enqueued and sent by http\Client.
     **/
    public function __construct($meth = NULL, $url = NULL, array $headers = NULL, \http\Message\Body $body = NULL)
    {
        parent::_construct($meth, $url, $headers, $body);
    }
}
