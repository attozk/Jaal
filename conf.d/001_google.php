<?php

$httpd->on('client.request:800', function ($request) use ($httpd) {

    $arrVhostConfig = array(
        // nginx inspred
        'http_version' => '1.1',
        'proxy_hide_header' => array(
            'X-Powered-By',
            'Server'
        ),
        'add_header' => array(),
        'proxy_set_header' => array(
            'HOST' => 'www.118118.com'
        ),
        'upstreams' => array(
            // nginx inspired @http://nginx.org/en/docs/http/ngx_http_upstream_module.html#health_check
            'keepalive' => 0,
            'timeout' => 10,

            //'strategy' => 'round-robin|sticky|least_conn|etc...',
            'servers' => array(
                'server1' => array(
                    'ip' => '192.168.1.44', 'port' => 80,
                    'ip' => '10.137.8.219', 'port' => 9200,
                    'weight' => 5,
                )
            )
        )
    );

    $httpd->proxy($arrVhostConfig, $request);

});
