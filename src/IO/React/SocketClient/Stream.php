<?php

namespace Hathoora\Jaal\IO\React\SocketClient;

use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;

class Stream extends \React\Stream\Stream {

    public $militime;   // milli time at connect
    public $id;
    public $remoteId;
    public $remoteAddress;

    public function __construct($stream, LoopInterface $loop) {
        parent::__construct($stream, $loop);
        $this->militime = Time::millitime();
        $this->id = stream_socket_get_name($stream, false);
        $this->remoteId = stream_socket_get_name($stream, true);
    }

    public function getRemoteAddress()
    {
        if (empty($this->remoteAddress)) {
            $this->remoteAddress = $this->parseAddress(stream_socket_get_name($this->stream, true));
        }

        return $this->remoteAddress;
    }

    private function parseAddress($address)
    {
        return trim(substr($address, 0, strrpos($address, ':')), '[]');
    }
}