<?php

namespace Hathoora\Jaal\IO\React\Socket;

Interface ConnectionInterface extends \React\Socket\ConnectionInterface
{
    /**
     * @return \React\Promise\Promise
     */
    public function isAllowed();
}