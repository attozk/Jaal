<?php

namespace Attozk\Jaal\Upstream;

use Attozk\Jaal\Httpd\Message\RequestInterface;

class Pool implements PoolInterface
{
    private $arrConfig = array(
        // nginx inspired @http://nginx.org/en/docs/http/ngx_http_upstream_module.html#health_check
        'keepalive' => 10,

        //'strategy' => 'round-robin|sticky|least_conn|etc...',
        'servers' => array(
            'server1' => array(
                'ip' => '192.168.1.44',
                'port' => 80,
                'weight' => 5,

                'max_fails' => 5,
                'fail_timeout' => 10,
                'max_conns' => 100,
            )
        )
    );

    public function getServer(RequestInterface $request)
    {
        return $this->arrConfig['servers']['server1'];
    }
}