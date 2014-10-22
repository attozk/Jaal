<?php
include __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);

$connector = new React\SocketClient\Connector($loop, $dns);

$connector->create('www.google.com', 80)->then(function (React\Stream\Stream $stream) {
    $stream->write("GET / HTTP/1.0\r\nHost: www.google.com\r\n\r\n");
    $stream->on('data', function($data) {
       echo $data;
    });
});

$loop->run();

/*
$elasticaClient = new \Elastica\Client(array(
            'host' => '127.0.0.1',
            'port' => 111,
            'transport' => 'Null'
        ));
$elasticaIndex = $elasticaClient->getIndex('index_name')->getType('type');

$elasticQuery = new \Elastica\Query();
$termQueryBroker = new \Elastica\Query\Term(array('broker' => 'hello'));
$boolQuery = new \Elastica\Query\Bool();
$boolQuery->addMust($termQueryBroker);

$multiMatchQuery = new \Elastica\Query\QueryString();
$multiMatchQuery->setQuery('query')
                ->setFields(array('name^3', 'number^3', 'description'));
$boolQuery->addMust($multiMatchQuery);

$termQueryYear = new \Elastica\Query\Range('date_submitted',
    array(
        'gte' => '2014-01-01',
        'lte' => '2014-12-31'));
$boolQuery->addMust($termQueryYear);
$elasticQuery->setQuery($boolQuery);

print_r(\Elastica\JSON::stringify($elasticQuery->toArray()));


$elasticaResultSet = $elasticaIndex->search($elasticQuery);

print_r($elasticaResultSet);
*/