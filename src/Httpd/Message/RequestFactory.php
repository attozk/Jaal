<?php

namespace Hathoora\Jaal\Httpd\Message;

use Guzzle\Http\Message\RequestFactory as GuzzleRequestFactory;

class RequestFactory extends GuzzleRequestFactory
{
    /** @var string Class to instantiate for requests with no body */
    protected $requestClass = 'Hathoora\\Jaal\\Httpd\\Message\\Request';
}