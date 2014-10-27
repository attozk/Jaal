<?php

namespace Hathoora\Jaal\IO\React\Socket;

Class Server extends \React\Socket\Server
{
    public $loop;

    public function __construct($loop)
    {
        parent::__construct($loop);

        $this->loop = $loop;
    }

    public function createConnection($socket)
    {
        return new Connection($socket, $this->loop);
    }
}