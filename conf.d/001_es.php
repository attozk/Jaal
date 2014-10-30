<?php

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
#print_r($esQueryArray);
#print_r(\Elastica\JSON::stringify($esQueryArray));
    echo "\n";

    $path = '/' . $elasticaIndex->getIndex()->getName() . "/" . $elasticaIndex->getName() . '/_mapping';
#$baseUri .= $request->getPath();

    $request->setPath($path);

    $httpd->proxy($arrVhostConfig, $request);

    /*
    #$query = $request->getQuery();
    //print_r($elasticaIndex->getMapping()) ;
    echo "\n\n";

    $request->error(450);

    return;
    */

#$elasticaResultSet = $elasticaIndex->search($elasticQuery);

#print_r($elasticaResultSet);

#$httpd->proxy($arrVhostConfig, $request);
    $httpd->proxy($arrVhostConfig, $request);

});
