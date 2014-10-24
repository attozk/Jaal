<?php
include __DIR__ . '/../vendor/autoload.php';


$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$jaal = \Hathoora\Jaal\Jaal::getInstance();
$jaal->setup($loop, $dns);

$loop->run();