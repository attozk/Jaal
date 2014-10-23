<?php

$httpd->on('client.request:80', function(\Attozk\Jaal\Httpd\Message\Request $request) use($httpd) {

    $pool = new \Attozk\Jaal\Upstream\Pool();
    $arrOptions = array(
        // nginx inspred
        'http_version' => '1.1',
        'proxy_hide_header' => array(
            'X-Powered-By'
        ),
        'add_header' => array(
        ),
        'proxy_set_header' => array(
            'HOST' => 'mine.pk'
        )
    );

    /** @var $httpd \Attozk\Jaal\Httpd\Server */
    $httpd->proxy($pool, $request, $arrOptions);
});
