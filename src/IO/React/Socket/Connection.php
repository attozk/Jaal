<?php

namespace Hathoora\Jaal\IO\React\Socket;

use Hathoora\Jaal\IO\React\Stream\Stream;
use React\EventLoop\LoopInterface;

Class Connection extends Stream implements ConnectionInterface
{
    public function __construct($stream, LoopInterface $loop)
    {
        parent::__construct($stream, $loop);
        $this->id       = stream_socket_get_name($stream, true);
        $this->remoteId = stream_socket_get_name($stream, false);
    }

    public function handleData($stream)
    {
        // Socket is raw, not using fread as it's interceptable by filters
        // See issues #192, #209, and #240
        $data = stream_socket_recvfrom($stream, $this->bufferSize);
        if ('' !== $data && false !== $data)
        {
            $this->emit('data', [$data, $this]);
        }

        if ('' === $data || false === $data || !is_resource($stream) || feof($stream))
        {
            $this->end();
        }
    }

    public function handleClose()
    {
        if (is_resource($this->stream))
        {
            // http://chat.stackoverflow.com/transcript/message/7727858#7727858
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            stream_set_blocking($this->stream, false);
            fclose($this->stream);
        }
    }
}