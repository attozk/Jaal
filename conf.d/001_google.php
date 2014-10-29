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
            'HOST' => 'HOST'
        ),
        'upstreams' => array(
            /*'keepalive' => array(
                'timeout' => 10,
                'max' => 100
            ),*/

            'servers' => array(
                'server1' => array(
                    #'ip' => '192.168.1.44', 'port' => 80,
                    'ip' => '10.137.8.219', 'port' => 9200,
                    'weight' => 5,
                )
            )
        )
    );

    $httpd->proxy($arrVhostConfig, $request);

});
