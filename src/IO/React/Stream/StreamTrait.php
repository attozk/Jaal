<?php

namespace Hathoora\Jaal\IO\React\Stream;

use Hathoora\Jaal\Util\Time;

trait StreamTrait
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
     * @var int millitime for last activity
     */
    public $lastActivity;

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

    public function write($data)
    {
        $this->lastActivity = Time::millitime();
        parent::write($data);
    }

    public function handleData($stream)
    {
        $this->lastActivity = Time::millitime();
        parent::handleData($stream);
    }

    public function getRemoteAddress()
    {
        if (empty($this->remoteAddress))
        {
            $this->remoteAddress = $this->parseAddress(stream_socket_get_name($this->stream, true));
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
     *
     * @return $this
     */
    public function setMeta($key, $value)
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     *
     * @return null
     */
    public function getMeta($key)
    {
        return (isset($this->meta[$key]) ? $this->meta[$key] : null);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function appendMeta($key, $value)
    {
        $this->meta[$key] .= $value;

        return $this;
    }

    /**
     * @param $key
     *
     * @return $this
     */
    public function removeMeta($key)
    {
        if (isset($this->meta[$key]))
            $this->meta[$key] = null;

        return $this;
    }
}