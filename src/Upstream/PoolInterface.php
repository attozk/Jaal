<?php

namespace Attozk\Jaal\Upstream;

use Attozk\Jaal\Httpd\Message\RequestInterface;

interface PoolInterface
{
    /**
     * @param RequestInterface $request
     * @return array
     */
    public function getServer(RequestInterface $request);
}