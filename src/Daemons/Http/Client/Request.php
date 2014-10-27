<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;
use Hathoora\Jaal\Util\Time;

Class Request extends \http\Client\Request implements RequestInterface
{
    private $id;
    private $stream;
    private $streamType;  // inbound|outbound
    private $militime;
    private $took;        // took milli seconds to execute

    /**
     * Create a new client request message to be enqueued and sent by http\Client.
     **/
    public function __construct($meth = NULL, $url = NULL, array $headers = NULL, \http\Message\Body $body = NULL)
    {
        parent::_construct($meth, $url, $headers, $body);
    }

    /**
     * Sets a unique request id
     *
     * @return self
     */
    public function setRequestId()
    {
        $this->id = uniqid();
    }

    /**
     * Gets the unique request id
     *
     * @return mixed
     */
    public function getRequestId()
    {
        return $this->id;
    }

    /**
     * Sets connection stream to client or proxy
     *
     * @param ConnectionInterface|ConnectorInterface $stream
     * @return self
     */
    public function setStream($stream)
    {
        $this->stream = $stream;

        if ($this->stream instanceof ConnectorInterface)
            $this->streamType = 'inbound';
        else if ($this->stream instanceof ConnectorInterface)
            $this->streamType = 'outbound';
    }

    /**
     * Returns the type of stream
     *
     * @return string client|upstream
     */
    public function getStreamType()
    {
        return $this->streamType;
    }

    /**
     * Returns connection stream socket
     *
     * @return ConnectionInterface
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Sets start time of request in miliseconds
     *
     * @param null|int $miliseconds
     * @return self
     */
    public function setStartTime($miliseconds = null)
    {
        if ($miliseconds)
            $miliseconds = Time::millitime();

        $this->militime = $miliseconds;
    }

    /**
     * Sets execution time of request in miliseconds
     *
     * @return self
     */
    public function setExecutionTime()
    {
        if (!$this->took)
        {
            //$this->took = Time::
        }
    }

    /**
     * Gets execution time of request in miliseconds
     */
    public function getExecutionTime()
    {
        return $this->took;
    }
}
