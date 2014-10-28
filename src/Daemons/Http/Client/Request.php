<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\SocketClient\ConnectorInterface;
use Hathoora\Jaal\Util\Time;

Class Request extends \http\Client\Request implements RequestInterface
{
    protected $id;
    protected $stream;
    protected $streamType;  // inbound|outbound
    protected $militime;
    protected $took;        // took milli seconds to execute
    protected $urlParts = array();

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Create a new client request message to be enqueued and sent by http\Client.
     **/
    public function __construct($meth = null, $url = null, array $headers = null, \http\Message\Body $body = null)
    {
        parent::__construct($meth, $url, $headers, $body);

        $this->urlParts = parse_url($url);

        $this->setRequestId();
    }

    /**
     * Sets a unique request id
     *
     * @return self
     */
    public function setRequestId()
    {
        $this->id = uniqid('Request_');
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

    public function removeHeader($header)
    {
        if (isset($this->headers[$header])) {
            unset($this->headers[$header]);
        }
    }

    public function getScheme()
    {
        return isset($this->urlParts['scheme']) ? $this->urlParts['scheme'] : null;
    }

    public function getPort()
    {
        return isset($this->urlParts['port']) ? $this->urlParts['port'] : null;
    }

    public function getHost()
    {
        return isset($this->urlParts['host']) ? $this->urlParts['host'] : null;
    }

    public function getPath()
    {
        return isset($this->urlParts['path']) ? $this->urlParts['path'] : null;
    }

    public function getQuery()
    {
        return isset($this->urlParts['query']) ? $this->urlParts['query'] : null;
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

        if ($this->stream instanceof ConnectorInterface) {
            $this->streamType = 'inbound';
        } else {
            if ($this->stream instanceof ConnectorInterface) {
                $this->streamType = 'outbound';
            }
        }
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
        if ($miliseconds) {
            $miliseconds = Time::millitime();
        }

        $this->militime = $miliseconds;
    }

    /**
     * Sets execution time of request in miliseconds
     *
     * @return self
     */
    public function setExecutionTime()
    {
        if (!$this->took) {
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

    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function send()
    {
        //$this->stream->write($this->responseJaal->getRawHeaders() . $this->responseJaal->getBody());
        $this->stream->end();
    }
}
