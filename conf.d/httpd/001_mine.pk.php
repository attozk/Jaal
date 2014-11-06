<?php

$httpd->on('request.mine.pk:81',
function (\Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request) use ($httpd) {

    $arrVhostConfig = [
        // nginx inspred
        'http_version'      => '1.1',
        'proxy_hide_header' => [
            'X-Powered-By',
            'Server'
        ],
        'add_header'        => [],
        'proxy_hide_header' => [
            'accept-encoding'
        ],
        'proxy_set_header'  => [
            'HOST' => 'mine.pk',
        ],
        'upstreams'         => [
            /*'keepalive' => array(
                'timeout' => 10,
                'max' => 100
            ),*/

            'servers' => [
                'server1' => [
                    'ip' => '192.168.1.44',
                    'port'   => 80,
                    'weight' => 5,
                ]
            ]
        ]
    ];

    //$request->reply(200);

    $httpd->proxy($arrVhostConfig, $request);
});
