<?php

namespace Attozk\Roxy\Http\Message;

use Guzzle\Http\Message\RequestFactory as GuzzleRequestFactory;

class RequestFactory extends GuzzleRequestFactory
{
    /** @var string Class to instantiate for requests with no body */
    protected $requestClass = 'Attozk\\Roxy\\sHttp\\Message\\Request';
}