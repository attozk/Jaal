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

        if ($this->protocol == 'http') {
            $stream->setMeta('http_message_consumed', 0);
            $stream->setMeta('http_message_methodEOM', '');
            $stream->setMeta('http_message_contentLength', 0);
            $stream->setMeta('http_message_buffer', 0);
            $stream->setMeta('http_message_packets', 0);
            $stream->setMeta('http_message_error_code', 0);

            $this->newQueue($stream, 'requests');
        }

        //if ($notAdded) {
        //
        //    /* @TODO revisit timeouts */
        //    if ($this->protocol == 'http') {
        //        $timeout = Jaal::getInstance()->config->get('httpd.timeout');
        //        $keepaliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
        //
        //        $timerTimeout = $this->loop->addPeriodicTimer($timeout, function () use ($stream) {
        //            if ($request = $this->getProp($stream, 'request')) {
        //
        //                if (($timerTimeout = $this->getProp($stream, 'timerTimeout')) && $timerTimeout instanceof TimerInterface) {
        //                     $this->loop->cancelTimer($timerTimeout);
        //                    $this->removeProp($stream, 'timerTimeout');
        //                }
        //
        //                Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id, 'green') .
        //                                                ' connection timeout from Inbound Manager, hits: ' . $stream->hits .
        //                                                ', connection time: ' . Time::millitimeDiff($stream->millitime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
        //
        //                $request->reply(408);
        //            }
        //        });
        //        $this->setProp($stream, 'timerTimeout', $timerTimeout);
        //
        //        if ($timeout && $keepaliveTimeout && ($keepaliveTimeout * 1.5) < $timeout) {
        //            $timerKeepaliveTimeout = $this->loop->addPeriodicTimer($keepaliveTimeout, function () use ($stream) {
        //                    if ($request = $this->getProp($stream, 'request')) {
        //                        if (($timerKeepaliveTimeout = $this->getProp($stream, 'timerKeepaliveTimeout')) &&
        //                            $timerKeepaliveTimeout instanceof TimerInterface)
        //                        {
        //                            $this->loop->cancelTimer($timerKeepaliveTimeout);
        //                            $this->removeProp($stream, 'timerKeepaliveTimeout');
        //                        }
        //
        //                        Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id, 'green') . ' keep-alive timeout from Inbound Manager, hits: ' .
        //                                                        $stream->hits . ', connection time: ' . Time::millitimeDiff($stream->millitime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
        //
        //
        //                        $stream->end();
        //                    }
        //                });
        //            $this->setProp($stream, 'timerKeepaliveTimeout', $timerKeepaliveTimeout);
        //        }
        //    }
        //}

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
