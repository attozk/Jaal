<?php

namespace Hathoora\Jaal;

use React\Promise\Deferred;
use React\Socket\ConnectionInterface;

class ClientsManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * http, ftp etc..
     */
    private $protocol;

    /**
     * @var array
     */
    public static $clients = array();

    public function __construct($loop, $protocol)
    {
        $this->loop = $loop;
        $this->protocol = $protocol;
    }

    public function remove(ConnectionInterface $client)
    {
        //$this->clients->detach($client);

        return $this;
    }

    /**
     * @param ConnectionInterface $client
     * @return \React\Promise\Promise
     */
    public function isAllowed(ConnectionInterface $client)
    {

        // @TODO check for max & blacklisted IPs here..
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $deferred->resolve($client);

        return $promise;
    }


}