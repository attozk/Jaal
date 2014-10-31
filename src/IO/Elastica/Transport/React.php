<?php

namespace Hathoora\Jaal\IO\Elastica\Transport;

use Elastica\JSON;
use Elastica\Response;
use Elastica\Transport\Http;
use Hathoora\Jaal\IO\Elastica\Request;
use Hathoora\Jaal\Jaal;
use React\Promise\Deferred;
use React\SocketClient\Connector;
use React\Stream\Stream;

class React extends Http
{
    /**
     * Http scheme
     *
     * @var string Http scheme
     */

    protected $_scheme = 'http';

    /**
     * Curl resource to reuse
     *
     * @var resource Curl resource to reuse
     */
    protected static $_curlConnection = NULL;

    /**
     * @param Request $request
     * @param array   $params
     * @return \React\Promise\Promise
     */
    public function execute(Request $request, array $params)
    {
        $deferred = new Deferred();

        $content    = NULL;
        $headers    = [];
        $connection = $this->getConnection();

        // If url is set, url is taken. Otherwise port, host and path
        $url = $connection->hasConfig('url') ? $connection->getConfig('url') : '';

        if (!empty($url)) {
            $baseUri = $url;
        } else {
            $baseUri = $this->_scheme . '://' . $connection->getHost() . ':' . $connection->getPort() . '/' .
                       $connection->getPath();
        }

        $baseUri .= $request->getPath();

        $query = $request->getQuery();

        if (!empty($query)) {
            $baseUri .= '?' . http_build_query($query);
        }

        $headersConfig = $connection->hasConfig('headers') ? $connection->getConfig('headers') : [];

        if (!empty($headersConfig)) {
            while (list($header, $headerValue) = each($headersConfig)) {
                array_push($headers, $header . ': ' . $headerValue);
            }
        }

        $data       = $request->getData();
        $httpMethod = $request->getMethod();

        if (!empty($data) || '0' === $data) {
            if ($this->hasParam('postWithRequestBody') && $this->getParam('postWithRequestBody') == TRUE) {
                $httpMethod = Request::POST;
            }

            if (is_array($data)) {
                $content = JSON::stringify($data, 'JSON_ELASTICSEARCH');
            } else {
                $content = $data;
            }

            // Escaping of / not necessary. Causes problems in base64 encoding of files
            $content = str_replace('\/', '/', $content);
        }

        $this->buildConnector($request->getConnection()->getParam('host'), $request->getConnection()->getParam('port'))
             ->then(
                 function (Stream $stream) use ($deferred, $httpMethod, $baseUri, $headers, $content) {

                     $protocolVersion = '1.1';
                     $resource        = implode('?',
                         [parse_url($baseUri, PHP_URL_PATH), parse_url($baseUri, PHP_URL_QUERY)]);
                     $hello           = trim($httpMethod . ' ' . $resource) . ' HTTP/' . $protocolVersion . "\r\n" .
                                        implode("\r\n", $headers) . "\r\n\r\n" . $content;
                     $stream->write($hello);

                     echo "-------------Hello--------------------------\n";
                     echo $hello . "\n";
                     echo "-------------/Hello--------------------------\n";

                     $stream->on('data', function ($data) use ($deferred) {

                         new Response($data);
                     });
                 }, function ($error) use ($deferred) {
                     $deferred->reject($error);
                 });

        //$httpMethod
        #//curl_setopt($conn, CURLOPT_NOBODY, $httpMethod == 'HEAD');

        #//curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $httpMethod);

        // Checks if error exists
        #curl_exec($conn);
        #$responseString = ob_get_clean();

        /*

         *
         * $response = new Response($responseString, curl_getinfo($this->_getConnection(), CURLINFO_HTTP_CODE));


        if ($response->hasError()) {
            #throw new ResponseException($request, $response);
        }

        if ($response->hasFailedShards()) {
            #throw new PartialShardFailureException($request, $response);
        }
        */

        return $deferred->promise();
    }

    /**
     * @param $ip
     * @param $port
     * @return null|\React\Promise\FulfilledPromise|\React\Promise\RejectedPromise
     */
    protected function buildConnector($ip, $port)
    {

        $connector = new Connector(Jaal::getInstance()->loop, Jaal::getInstance()->dns);

        return $connector->create($ip, $port);
    }
}