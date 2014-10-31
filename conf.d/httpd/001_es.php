<?php
/** @var $httpd \Hathoora\Jaal\Daemons\Http\Httpd */
$httpd->on('client.response:www.es.com:800', function (\Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request) use ($httpd) {
    print_r(json_encode($request->getResponse()->getBody()));

    $request->reply();
});

$httpd->on('client.request:www.es.com:800', function (\Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request) use ($httpd) {


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
                    'ip' => '10.137.8.219', 'port' => 9200,
                    'weight' => 5,
                )
            )
        )
    );

    // ES data @ https://github.com/bly2k/files/blob/master/accounts.zip?raw=true
    $elasticaClient = new Elastica\Client();
    $elasticaIndex = $elasticaClient->getIndex('bank')->getType('account');


    $elasticQuery = new \Elastica\Query();
    $boolQuery = new \Elastica\Query\Bool();

    // search criteria #1
    $termQueryBroker = new \Elastica\Query\Term(array('firstname' => 'Opal'));

    // search criteria #2
    $multiMatchQuery = new \Elastica\Query\QueryString();
    $multiMatchQuery->setQuery('Lam*')
        ->setFields(array('firstname^3', 'lastname^3', 'employer'));

    $boolQuery->addMust($termQueryBroker)
        ->addMust($multiMatchQuery);

    $elasticQuery->setQuery($boolQuery);
    $esQueryArray = $elasticQuery->toArray();

    $path = '/' . $elasticaIndex->getIndex()->getName() . "/" . $elasticaIndex->getName() . '/_search';

    $request->setPath($path);
    $request->setBody(\Elastica\JSON::stringify($esQueryArray));

    $httpd->proxy($arrVhostConfig, $request);
});

