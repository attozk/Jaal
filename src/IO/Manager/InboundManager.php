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
class InboundManager
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * http, ftp etc..
     */
    protected $protocol;

    /**
     * @var array storing ConnectionInterface and properties
     */
    protected $clients;

    public function __construct($loop, $protocol)
    {
        $this->loop = $loop;
        $this->protocol = $protocol;
        $this->clients = array();
    }

    public function add(ConnectionInterface $client)
    {
        $id = $client->id;
        if (!isset($this->clients[$id])) {
            $this->clients[$id] = array(
                'stream' => $client
            );


            Logger::getInstance()->log(-99, $client->getRemoteAddress() . ' <' . $id .'> has been added to Inbound Manager '. Logger::getInstance()->color('[' . __METHOD__ .']', 'lightCyan'));

            $client->on('data', function () use ($client) {
                $this->setProp($client, 'lastActivity', Time::millitime());
            });

            $client->on('close', function (ConnectionInterface $client) {
                $this->remove($client);
            });
        }

        return $this;
    }

    public function get(ConnectionInterface $client)
    {
        $id = $client->id;
        if (isset($this->clients[$id])) {
            return $this->clients[$id];
        }
    }

    public function remove(ConnectionInterface $client)
    {
        $id = $client->id;
        if (isset($this->clients[$id])) {

            Logger::getInstance()->log(-99, $client->getRemoteAddress() . ' <' . $id .'> has been removed from Inbound Manager after staying connected for ' . Time::millitimeDiff($client->militime)  .' ms '. Logger::getInstance()->color('[' . __METHOD__ .']', 'lightCyan'));
            unset($this->clients[$id]);
        }

        return $this;
    }

    /**
     * @param ConnectionInterface $client
     * @param $property
     * @param $value
     */
    public function setProp(ConnectionInterface $client, $property, $value)
    {
        $this->add($client);
        $id = $client->id;
        $this->clients[$id][$property] = $value;
    }

    /**
     * @param ConnectionInterface $client
     * @param $property
     */
    public function getProp(ConnectionInterface $client, $property)
    {
        if (($arr = $this->get($client)) && isset($arr[$property])) {
            return $arr[$property];
        }
    }

    /**
     * @param ConnectionInterface $client
     * @param $property
     */
    public function removeProp(ConnectionInterface $client, $property)
    {
        if (isset($this->clients[$client->id]) && isset($this->clients[$client->id][$property])) {
            unset($this->clients[$client->id][$property]);
        }
    }
}
