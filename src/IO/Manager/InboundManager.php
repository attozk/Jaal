<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Util\Time;
use React\EventLoop\Timer\TimerInterface;

/**
 * Class Inbound for managing inbound connections
 *
 * @package Hathoora\Jaal\IO\Manager
 */
class InboundManager extends IOManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * http, ftp etc..
     */
    protected $protocol;

    public function __construct($loop, $protocol)
    {
        $this->loop = $loop;
        $this->protocol = $protocol;
    }

    public function add($stream)
    {
        if (!isset($this->streams[$stream->id]))
        {
            Logger::getInstance()->log(-99, sprintf('%-25s' . $stream->id . "\n" .
                                                    "\t" . '[local: ' . $stream->id . ',  remote: ' . $stream->remoteId . ', connect-time: ' . Time::millitimeDiff($stream->millitime) . 'ms, ' .
                                                    'hits: ' . $stream->hits . ', resource: ' . $stream->resource . ']', 'InboundIOManager-New'));
            parent::add($stream);
        }

        return $this;
    }

    public function remove($stream)
    {

        $id = $stream->id;
        if (isset($this->streams[$id]))
        {
            Logger::getInstance()->log(-99, sprintf('%-25s' . $stream->id . "\n" .
                                                    "\t" . '[local: ' . $stream->id . ',  remote: ' . $stream->remoteId . ', connect-time: ' . Time::millitimeDiff($stream->millitime) . 'ms, ' .
                                                    'idle-time: ' . Time::millitimeDiff($stream->lastActivity) . 'ms, ' .
                                                    'hits: ' . $stream->hits . ', resource: ' . $stream->resource . ']', 'InboundIOManager-Remove'));

        }

        return parent::remove($stream);
    }
}