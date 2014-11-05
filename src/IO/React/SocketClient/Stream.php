<?php

namespace Hathoora\Jaal\IO\React\SocketClient;

use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;

class Stream extends \React\Stream\Stream
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
        parent::__construct($stream, $loop);
        $this->millitime = Time::millitime();
        $this->id = stream_socket_get_name($stream, FALSE);
        $this->remoteId = stream_socket_get_name($stream, TRUE);
        $this->hits = 0;
    }

    public function getRemoteAddress()
    {
        if (empty($this->remoteAddress)) {
            $this->remoteAddress = $this->parseAddress(stream_socket_get_name($this->stream, TRUE));
        }

        return $this->remoteAddress;
    }

    private function parseAddress($address)
    {
        return trim(substr($address, 0, strrpos($address, ':')), '[]');
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
     * @return null
     */
    public function getMeta($key)
    {
        return (isset($this->meta[$key]) ? $this->meta[$key] : NULL);
    }
}