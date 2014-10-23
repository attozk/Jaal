<?php

namespace Attozk\Jaal\Httpd\Message;

use Guzzle\Http\Message\Request as GuzzleRequest;
Use React\Socket\ConnectionInterface;

class Request extends GuzzleRequest implements RequestInterface
{
    private $jStartMTime;         // request start micro time
    private $jExecTime;           // request execution time im milliseconds
    private $jUniqueId;           // unique id for request

    /** @var \React\Socket\ConnectionInterface */
    protected $clientSocket;

    /**
     * @param string $method
     * @param \Guzzle\Http\Url|string $url
     * @param array $headers
     */
    public function __construct($method, $url, $headers = array())
    {
        parent::__construct($method, $url, $headers);
        $this->setStartTime();
        $this->setRequestId();
        $this->getRequestId();
    }

    public function setRequestId()
    {
        $this->jUniqueId = uniqid();
    }

    public function getRequestId()
    {
        return $this->jUniqueId;;
    }

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

        $this->jStartMTime = $microtime;

        return $this;
    }

    public function setExecutionTime()
    {
        if (!$this->jExecTime) {
            list($a_dec, $a_sec) = explode(" ", $this->jStartMTime);
            list($b_dec, $b_sec) = explode(" ", microtime());
                $this->jExecTime = ($b_sec - $a_sec + $b_dec - $a_dec);
        }

        return $this;
    }

    public function getExecutionTime()
    {
        return $this->jExecTime;
    }
}