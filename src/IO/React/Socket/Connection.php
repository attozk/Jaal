<?php

namespace Hathoora\Jaal\IO\React\Socket;

use Hathoora\Jaal\IO\React\Stream\StreamTrait;
use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;

Class Connection extends \React\Socket\Connection implements ConnectionInterface
{
    use StreamTrait;

    public function __construct($stream, LoopInterface $loop)
    {
        parent::__construct($stream, $loop);
        $this->millitime = Time::millitime();
        $this->id       = stream_socket_get_name($this->stream, TRUE);
        $this->remoteId = stream_socket_get_name($this->stream, FALSE);
        $this->hits     = 0;
    }
}