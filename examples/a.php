<?php
include __DIR__ . '/../vendor/autoload.php';


$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$jaal = new \Attozk\Jaal\Jaal($loop, $dns);

$loop->run();