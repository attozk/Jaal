<?php
include __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$socket = new React\Socket\Server($loop);
$http = new Attozk\Roxy\Http\Server($loop, $socket, $dns);
$http->listen(8800);


$http->on('http.client.request.localhost:8800', function(\Attozk\Roxy\Http\Message\Request $request) use($http) {

    echo $request->getClientSocket()->getRemoteAddress() . " is inside http.client.request.localhost:8800 vhost  \n";
    $arrOptions = array();
    $http->proxy('SomePool', $request, $arrOptions);
});


$loop->run();