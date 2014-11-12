<?php

$httpd->on('request.mine.pk:81', function (\Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request) use ($httpd)
{
    $arrVhostConfig = [
        'httpVersion' => '1.1',    // default

        'headers'   => [
            /*
                Adds the specified field to a response header
             */
            'add' => [
                'Date'  => '{{now}}',                // default
                'Server' => '{{jaal.name}}',       // default
                'Hello' => 'World'
            ]
        ],

        'proxy'     => [
            'headers' => [
                /*
                    upstream response headers that we do not want to pass to client.
                    this directive sets additional fields that will not be passed.
                */
                'hide' => [
                    'Server',   // default
                    'Date',     // default
                    'X-Powered-By',
                    'accept-encoding'
                ],
                /*
                    Disables processing of certain response header fields from the proxied server. The following fields
                    can be ignored: â€œExpiresâ€�, â€œCache-Controlâ€�, â€œSet-Cookieâ€�, and â€œVaryâ€�
                 */
                'ignore' => [

                ],
                /*
                    Allows redefining or appending fields to the request header passed to the proxied server. The value can
                    contain text, variables, and their combinations.
                */
                'set'  => [
                    'host'       => '{{request.host}}',       // default
                    'Connection' => 'Close',             // default,
                    'host'       => 'mine.pk',
                ]
            ]
        ],

        'upstreams' => [
            //            'keepalive' => array(
            //                'timeout' => 10,
            //                'max' => 100
            //            ),

            'servers' => [
                'server1' => [
                    'ip' => '192.168.1.44',
                    'port'   => 80,
                    'weight' => 5,



                ]
            ]
        ]
    ];

    $request->on('upstream.ready', function ($request) use ($arrVhostConfig, $httpd)
    {
        $httpd->onProxy($arrVhostConfig, $request);
    });
});
