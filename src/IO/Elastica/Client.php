<?php

namespace Hathoora\Jaal\IO\Elastica;

use Elastica\Connection\ConnectionPool;
use Elastica\Connection\Strategy\StrategyFactory;
use React\Promise\Deferred;

class Client extends \Elastica\Client
{
    /**
     * Inits the client connections
     */
    protected function _initConnections()
    {
        $connections = array();

        foreach ($this->getConfig('connections') as $connection) {
            $connections[] = Connection::create($this->_prepareConnectionParams($connection));
        }

        if (isset($this->_config['servers'])) {
            foreach ($this->getConfig('servers') as $server) {
                $connections[] = Connection::create($this->_prepareConnectionParams($server));
            }
        }

        // If no connections set, create default connection
        if (empty($connections)) {
            $connections[] = Connection::create($this->_prepareConnectionParams($this->getConfig()));
        }

        if (!isset($this->_config['connectionStrategy'])) {
            if ($this->getConfig('roundRobin') === true) {
                $this->setConfigValue('connectionStrategy', 'RoundRobin');
            } else {
                $this->setConfigValue('connectionStrategy', 'Simple');
            }
        }

        $strategy = StrategyFactory::create($this->getConfig('connectionStrategy'));

        $this->_connectionPool = new ConnectionPool($connections, $strategy, $this->_callback);
    }

    /**
     * Returns the index for the given connection
     *
     * @param  string $name Index name to create connection to
     * @return \Elastica\Index Index for the given name
     */
    public function getIndex($name)
    {
        return new Index($this, $name);
    }

    /**
     * Makes calls to the elasticsearch server based on this index
     *
     * It's possible to make any REST query directly over this method
     *
     * @param  string $path Path to call
     * @param  string $method Rest method to use (GET, POST, DELETE, PUT)
     * @param  array $data OPTIONAL Arguments as array
     * @param  array $query OPTIONAL Query params
     * @return \React\Promise\Promise
     */
    public function request($path, $method = Request::GET, $data = array(), array $query = array())
    {
        $deferred = new Deferred();
        $connection = $this->getConnection();

        $request = new Request($path, $method, $data, $query, $connection);
        $this->_log($request);

        $request->send()->then(
            function ($response) use ($request, $deferred) {

                $this->_lastRequest = $request;
                $this->_lastResponse = $response;

                $deferred->resolve();
            },
            function () use ($deferred) {
                $deferred->reject();
            }
        );

        return $deferred->promise();
    }
}
