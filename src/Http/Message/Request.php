<?php

namespace Attozk\Roxy\Http\Message;

use Guzzle\Http\Message\Request as GuzzleRequest;
Use React\Socket\ConnectionInterface;

class Request extends GuzzleRequest implements RequestInterface
{
    private $roxyStartMTime;         // request start micro time
    private $roxyExecTime;           // request execution time im milliseconds

    /** @var \React\Socket\ConnectionInterface */
    protected $clientSocket;


    public function setClientSocket(ConnectionInterface $clientSocket)
    {
        $this->clientSocket = $clientSocket;

        return $this;
    }

    public function getClientSocket()
    {
        return $this->clientSocket;
    }

    public function setStartTime($microtime = null)
    {
        if (!$microtime)
            $microtime = microtime();

        $this->roxyStartMTime = $microtime;

        return $this;
    }

    public function setExecutionTime()
    {
        if (!$this->roxyExecTime) {
            list($a_dec, $a_sec) = explode(" ", $this->roxyStartMTime);
            list($b_dec, $b_sec) = explode(" ", microtime());
                $this->roxyExecTime = ($b_sec - $a_sec + $b_dec - $a_dec);
        }

        return $this;
    }

    public function getExecutionTime()
    {
        return $this->roxyExecTime;
    }
}