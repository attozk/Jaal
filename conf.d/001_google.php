<?php

$httpd->on('client.request:800', function (\Hathoora\Jaal\Httpd\Message\Request $request) use ($httpd) {

     \Hathoora\Jaal\Upstream\Httpd\Factory::create($request, $arrOptions = array(
        // nginx inspred
        'http_version' => '1.1',
        'proxy_hide_header' => array(
            'X-Powered-By',
            'Server'
        ),
        'add_header' => array(),
        'proxy_set_header' => array(
            'HOST' => 'mine.pk'
        ),
        'upstreams' => array(
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
        )
    ));
});
