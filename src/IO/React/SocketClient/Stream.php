<?php

namespace Hathoora\Jaal\IO\React\SocketClient;

use Hathoora\Jaal\IO\React\Stream\StreamTrait;
use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;

class Stream extends \React\Stream\Stream
{
    use StreamTrait;

    public function __construct($stream, LoopInterface $loop)
    {
        parent::__construct($stream, $loop);
        $this->millitime = Time::millitime();
        $this->id = stream_socket_get_name($stream, FALSE);
        $this->remoteId = stream_socket_get_name($stream, TRUE);
        $this->hits = 0;
    }
}