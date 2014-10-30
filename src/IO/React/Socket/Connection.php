<?php

namespace Hathoora\Jaal\IO\React\Socket;

use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

Class Connection extends \React\Socket\Connection implements ConnectionInterface
{
    public $militime;   // milli time at connect
    public $id;
    public $remoteId;
    public $remoteAddress;
    public $hits;
    public $resource; // a unique resource identifier, for http its URL

    public function __construct($stream, LoopInterface $loop)
    {
        $this->militime = Time::millitime();
        parent::__construct($stream, $loop);
        $this->id = stream_socket_get_name($this->stream, true);
        $this->remoteId = stream_socket_get_name($this->stream, false);
        $this->hits = 0;
    }

    public function getRemoteAddress()
    {
        if (empty($this->remoteAddress)) {
            $this->remoteAddress = parent::getRemoteAddress();
        }

        return $this->remoteAddress;
    }

    /**
     * @internal param ConnectionInterface $client
     * @return \React\Promise\Promise
     */
    public function isAllowed()
    {
        // @TODO check for max & blacklisted IPs here..
        $deferred = new Deferred();
        $promise = $deferred->promise();

        $deferred->resolve($this);

        return $promise;
    }
}