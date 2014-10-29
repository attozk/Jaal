<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Util\Time;

abstract class IOManager
{

    protected $streams = '';

    public $stats = array(
        'streams' => 0,
        'bytes' => 0,
        'concurrency' => array(/*
             $id => array(
                'ip' => $ip,
                'resouse' => $resource
             )
             */
        )
    );

    public function add($stream)
    {
        $id = $stream->id;
        if (!isset($this->streams[$id])) {
            $this->streams[$id] = array(
                'stream' => $stream,
                'lastActivity' => Time::millitime()
            );

            $this->stats['streams']++;
            $this->stats['concurrency'][$id] = array(
                'address' => $stream->remoteId,
                'resource' => & $stream->resource,
                'hits' => & $stream->hits
            );

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
        $id = $stream->id;
        if (isset($this->streams[$id])) {
            return $this->streams[$id];
        }
    }

    public function getStreamById($id)
    {
        if (isset($this->streams[$id]))
            return $this->streams[$id]['stream'];
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
     * @param $property
     * @param $value
     */
    public function setProp($stream, $property, $value)
    {
        $this->add($stream);
        $id = $stream->id;
        $this->streams[$id][$property] = $value;
    }

    public function getProp($stream, $property)
    {
        if (($arr = $this->get($stream)) && isset($arr[$property])) {
            return $arr[$property];
        }
    }

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