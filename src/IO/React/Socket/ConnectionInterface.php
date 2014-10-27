<?php

namespace Hathoora\Jaal\IO\React\Socket;

Interface ConnectionInterface extends \React\Socket\ConnectionInterface
{
    // client is allowed to connect
    public function isAllowed();
}