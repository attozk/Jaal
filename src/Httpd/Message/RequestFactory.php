<?php

namespace Attozk\Jaal\Httpd\Message;

use Guzzle\Http\Message\RequestFactory as GuzzleRequestFactory;

class RequestFactory extends GuzzleRequestFactory
{
    /** @var string Class to instantiate for requests with no body */
    protected $requestClass = 'Attozk\\Jaal\\Httpd\\Message\\Request';
}