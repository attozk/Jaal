<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

use Guzzle\Http\Message\RequestFactory as GuzzleRequestFactory;

class RequestFactory extends GuzzleRequestFactory
{
    /** @var string Class to instantiate for requests with no body */
    protected $requestClass = 'Hathoora\\Jaal\\Daemons\\Http\\Message\\Request';

    /** @var string Class to instantiate for requests with a body */
    //protected $entityEnclosingRequestClass = 'Hathoora\\Jaal\\Daemons\Http\\Message\\Request';
}
