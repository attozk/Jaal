<?php
//
//$httpd->on('client.request:800', function (\Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request) use ($httpd) {
//
//    $conf = '
//http_version: 1.1
//proxy_hide_header:
//    X-Powered-By
//    Serve
//
//add_header:
//
//proxy_set_header:
//    HOST: HOST
//
//upstreams:
//    keepalive:
//        timeout: 10
//        max: 100
//
//servers:
//    server1:
//        #ip: 192.168.1.44
//        #port: 80
//        ip: 10.137.8.219
//        port: 9200
//        weight: 5';
//
//    $arrVhostConfig = array(
//        // nginx inspred
//        'http_version' => '1.1',
//        'proxy_hide_header' => array(
//            'X-Powered-By',
//            'Server'
//        ),
//        'add_header' => array(),
//        'proxy_set_header' => array(
//            'HOST' => 'HOST'
//        ),
//        'upstreams' => array(
//            /*'keepalive' => array(
//                'timeout' => 10,
//                'max' => 100
//            ),*/
//
//            'servers' => array(
//                'server1' => array(
//                    #'ip' => '192.168.1.44', 'port' => 80,
//                    'ip' => '10.137.8.219', 'port' => 9200,
//                    'weight' => 5,
//                )
//            )
//        )
//    );
//
//    $httpd->proxy($arrVhostConfig, $request);
//
//});
