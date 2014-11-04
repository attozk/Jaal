<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Util\Time;

abstract class IOManager
{
    protected $streams = '';

    public $stats
        = [
            'streams'     => 0, // number of connections
            'hits'        => 0,    // number of requests
            'bytes'       => 0,
            'concurrency' => [/*
             $id => array(
                'ip' => $ip,
                'resource' => $resource
             )
             */
            ]
        ];

    /**
     * @param \Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\SocketClient\Stream $stream
     * @return $this
     */
    public function add($stream)
    {
        $id = $stream->id;
        if (!isset($this->streams[$id])) {

            $this->streams[$id] = [
                'stream' => $stream,
                'lastActivity' => Time::millitime()
            ];

            $this->stats['streams']++;
            $this->stats['concurrency'][$id] = [
                'address'  => &$stream->remoteId,
                'resource' => &$stream->resource,
                'hits'     => &$stream->hits
            ];

            $stream->on('data', function ($data) use ($stream) {
                $this->stats['bytes'] = strlen($data);
                $this->setProp($stream, 'lastActivity', Time::millitime());
            });

            $stream->on('close', function ($stream) {
                unset($this->stats['concurrency'][$stream->id]);
                $this->remove($stream);
            });
        }

        return $this;
    }

    public function get($stream)
    {
        $value = NULL;
        $id    = $stream->id;

        if (isset($this->streams[$id])) {
            $value = $this->streams[$id];
        }

        return $value;
    }

    public function getStreamById($id)
    {
        $value = NULL;

        if (isset($this->streams[$id])) {
            $value = $this->streams[$id]['stream'];
        }

        return $value;
    }

    public function remove($stream)
    {
        $id = $stream->id;
        if (isset($this->streams[$id])) {
            unset($this->streams[$id]);
        }

        return $this;
    }

    /**
     * Sets property for a stream that manager needs to keep track of
     *
     * @param        $stream
     * @param $property
     * @param $value
     * @return self
     */
    public function setProp($stream, $property, $value)
    {
        $this->add($stream);
        $id                            = $stream->id;
        $this->streams[$id][$property] = $value;

        return $this;
    }

    /**
     * Gets property for a stream that manager needs to keep track of
     *
     * @param $stream
     * @param $property
     * @return null|mixed
     */
    public function getProp($stream, $property)
    {
        $value = NULL;

        if (($arr = $this->get($stream)) && isset($arr[$property])) {
            $value = $arr[$property];
        }

        return $value;
    }

    /**
     * Appents property for a stream that manager needs to keep track of
     *
     * @param        $stream
     * @param        $property
     * @param string $data
     * @return self
     */
    public function appendProp($stream, $property, $data)
    {
        $value = NULL;
        $arr   = $this->get($stream);

        if (isset($arr[$property])) {
            $value .= $data;
        } else {
            $value = $data;
        }

        return $this;
    }


    /**
     * Remove property of a stream
     *
     * @param $stream
     * @param $property
     */
    public function removeProp($stream, $property)
    {
        if (isset($this->streams[$stream->id]) && isset($this->streams[$stream->id][$property])) {
            unset($this->streams[$stream->id][$property]);
        }
    }

    public function stats()
    {
        return $this->stats;
    }
}