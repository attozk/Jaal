<?php

namespace Hathoora\Jaal\IO\React\Socket;

Interface ConnectionInterface extends \React\Socket\ConnectionInterface
{
    /**
     * @return \React\Promise\Promise
     */
    public function isAllowed();

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setMeta($key, $value);

    /**
     * @param $key
     * @return null
     */
    public function getMeta($key);
}