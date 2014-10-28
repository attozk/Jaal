<?php

namespace Hathoora\Jaal\Daemons\Http\Upstream;

use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;

Interface RequestInterface extends \Hathoora\Jaal\Daemons\Http\Client\RequestInterface
{
    public function __construct(Vhost $vhost, ClientRequestInterface $clientRequest);

    public function getClientRequest();
}