<?php

namespace Hathoora\Jaal\IO\Manager;

use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Util\Time;

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
        if (!isset($this->streams[$stream->id])) {
            Logger::getInstance()->log(-99, $stream->getRemoteAddress() . ' <' . $stream->id . '> has been added to Inbound Manager ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
        }

        return parent::add($stream);
    }

    public function remove($stream)
    {

        $id = $stream->id;
        if (isset($this->streams[$id])) {
            Logger::getInstance()->log(-99, $stream->getRemoteAddress() . ' <' . $id . '> has been removed from Inbound Manager after staying connected for ' . Time::millitimeDiff($stream->militime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
        }

        return parent::remove($stream);
    }
}
