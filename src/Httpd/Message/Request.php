<?php

namespace Hathoora\Jaal\Httpd\Message;

use Guzzle\Http\Message\Request as GuzzleRequest;
use Hathoora\Jaal\Util\Time;
Use React\Socket\ConnectionInterface;

class Request extends GuzzleRequest implements RequestInterface
{
    private $jStartMTime;         // request start millitime
    private $jExecTime;           // request execution time im milliseconds
    private $jUniqueId;           // unique id for request

    /** @var \React\Socket\ConnectionInterface */
    protected $clientSocket;

    /**
     * The response which we send back to the client
     * @var Response
     */
    private $responseJaal;

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

    public function setJaalResponse(Response $response)
    {
        $this->responseJaal = $response;
    }

    public function getJaalResponse() {
        return $this->responseJaal;
    }

    public function setStartTime($microtime = null)
    {
        if (!$microtime)
            $microtime = microtime();

        $this->jStartMTime = Time::millitime($microtime);

        return $this;
    }

    public function setExecutionTime()
    {
        if (!$this->jExecTime) {
            $end = Time::millitime(microtime());
            $this->jExecTime = ($end - $this->jStartMTime);
        }

        return $this;
    }

    public function getExecutionTime()
    {
        return $this->jExecTime;
    }


    public function send()
    {
        $this->getClientSocket()->write($this->responseJaal->getRawHeaders() . $this->responseJaal->getBody());
        $this->getClientSocket()->end();
    }
}