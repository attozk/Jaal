<?php

namespace Hathoora\Jaal\IO\React\Socket;

use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

Class Connection extends \React\Socket\Connection implements ConnectionInterface
{
    public $militime;   // milli time at connect

    public function __construct($stream, LoopInterface $loop)
    {
        parent::__construct($stream, $loop);

        $this->militime = Time::millitime();
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