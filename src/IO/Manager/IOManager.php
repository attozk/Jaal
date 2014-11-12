<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Util\Time;

abstract class IOManager
{
    protected $streams = [];

    public $stats = [
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
     * @param \Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\Socket\ConnectionInterface|\Hathoora\Jaal\IO\React\SocketClient\Stream $stream
     *
     * @return $this
     */
    public function add($stream)
    {
        $id = $stream->id;
        if (!isset($this->streams[$id]))
        {
            $this->streams[$id] = [
                'stream' => $stream
            ];

            $this->stats['streams']++;
            $this->stats['concurrency'][$id] = [
                'address'  => &$stream->remoteId,
                'resource' => &$stream->resource,
                'hits'     => &$stream->hits
            ];

            $stream->on('data', function ($data) use ($stream)
            {
                $this->stats['bytes'] += strlen($data);
            });

            $stream->on('close', function ($stream)
            {
                unset($this->stats['concurrency'][$stream->id]);
                $this->remove($stream);
            });
        }

        return $this;
    }

    /**
     * @param \Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\Socket\ConnectionInterface|\Hathoora\Jaal\IO\React\SocketClient\Stream $stream
     *
     * @return null|array
     */
    public function get($stream)
    {
        $value = null;
        $id    = $stream->id;

        if (isset($this->streams[$id]))
        {
            $value = $this->streams[$id];
        }

        return $value;
    }

    /**
     * @param $id
     *
     * @return null|\Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\SocketClient\Stream
     */
    public function getStreamById($id)
    {
        $value = null;

        if (isset($this->streams[$id]))
        {
            $value = $this->streams[$id]['stream'];
        }

        return $value;
    }

    /**
     * @param \Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\Socket\ConnectionInterface|\Hathoora\Jaal\IO\React\SocketClient\Stream $stream
     *
     * @return $this
     */
    public function remove($stream)
    {
        $id = $stream->id;
        if (isset($this->streams[$id]))
        {
            unset($this->streams[$id]);
        }

        return $this;
    }

    /**
     * @param \Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\Socket\ConnectionInterface|\Hathoora\Jaal\IO\React\SocketClient\Stream $stream
     * @param                                                                                                                                          $propertyName
     *
     * @return SplQueue
     */
    public function addQueue($stream, $propertyName)
    {
        $id = $stream->id;
        $key = 'queues:' . $propertyName;

        if (!isset($this->streams[$id]) || !isset($this->streams[$id][$key]))
        {
            $this->streams[$id][$key] = new \SplQueue();
        }

        /** \SplQueue() */

        return $this->streams[$id][$key];
    }

    /**
     * @param \Hathoora\Jaal\IO\React\Socket\Connection|\Hathoora\Jaal\IO\React\Socket\ConnectionInterface|\Hathoora\Jaal\IO\React\SocketClient\Stream $stream
     * @param                                                                                                                                          $propertyName
     *
     * @return null|\SplQueue
     */
    public function getQueue($stream, $propertyName)
    {
        $value = null;
        $id    = $stream->id;
        $key   = 'queues:' . $propertyName;

        if (isset($this->streams[$id]) && isset($this->streams[$id][$key]))
            $value = $this->streams[$id][$key];

        return $value;
    }

    /**
     * Sets property for a stream that manager needs to keep track of
     *
     * @param        $stream
     * @param        $property
     * @param        $value
     *
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
     *
     * @return null|mixed
     */
    public function getProp($stream, $property)
    {
        $value = null;

        if (($arr = $this->get($stream)) && isset($arr[$property]))
        {
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
     *
     * @return self
     */
    public function appendProp($stream, $property, $data)
    {
        $value = null;
        $arr   = $this->get($stream);

        if (isset($arr[$property]))
        {
            $value .= $data;
        }
        else
        {
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
        if (isset($this->streams[$stream->id]) && isset($this->streams[$stream->id][$property]))
        {
            unset($this->streams[$stream->id][$property]);
        }
    }

    public function stats()
    {
        return $this->stats;
    }
}