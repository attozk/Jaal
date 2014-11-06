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
        $notAdded = FALSE;
        if (!isset($this->streams[$stream->id])) {
            $notAdded = TRUE;
            Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id,
                                                                         'green') . ' / ' . $stream->remoteId .
                                            ' has been added to Inbound Manager, hits: ' .
                                            $stream->hits . ', connection time: ' .
                                            Time::millitimeDiff($stream->millitime) . ' ms ' .
                                            Logger::getInstance()->color('[' . __METHOD__ . ']',
                                                                         'lightCyan'));
            parent::add($stream);
        }

        return $this;
    }

    public function remove($stream)
    {

        $id = $stream->id;
        if (isset($this->streams[$id])) {
            Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id,
                                                                         'green') . ' / ' . $stream->remoteId .
                                            ' has been removed from Inbound Manager, hits: ' .
                                            $stream->hits . ', connection time: ' .
                                            Time::millitimeDiff($stream->millitime) . ' ms ' .
                                            Logger::getInstance()->color('[' . __METHOD__ . ']',
                                                                         'lightCyan'));
        }

        return parent::remove($stream);
    }
}
