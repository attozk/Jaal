<?php

namespace Hathoora\Jaal\IO\React\Socket;

use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

Class Connection extends \React\Socket\Connection implements ConnectionInterface
{
    /**
     * @var string unique identifier
     */
    public $id;

    /**
     * @var string unique remote identifier
     */
    public $remoteId;

    /**
     * @var int millitime at connect
     */
    public $millitime;

    /**
     * @var string remote address
     */
    public $remoteAddress;

    /**
     * @var int activity
     */
    public $hits;

    /**
     * @var string a resource e.g. for HTTP it is the URL
     */
    public $resource;

    /**
     * @var array for storing other meta data (per daemon)
     */
    protected $meta;

    public function __construct($stream, LoopInterface $loop)
    {
        $this->millitime = Time::millitime();
        parent::__construct($stream, $loop);
        $this->id       = stream_socket_get_name($this->stream, TRUE);
        $this->remoteId = stream_socket_get_name($this->stream, FALSE);
        $this->hits     = 0;
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
        $promise  = $deferred->promise();

        $deferred->resolve($this);

        return $promise;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setMeta($key, $value)
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function appendMeta($key, $value)
    {
        $this->meta[$key] .= $value;

        return $this;
    }

    /**
     * @param $key
     * @return null
     */
    public function getMeta($key)
    {
        return (isset($this->meta[$key]) ? $this->meta[$key] : NULL);
    }
}