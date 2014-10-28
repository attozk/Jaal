<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\Daemons\Http\Message\StatusCode;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\Util\Time;

Class Request extends \Hathoora\Jaal\Daemons\Http\Message\Request implements RequestInterface
{
    /**
     * @var ResponseInterface
     */
    protected $response;
    protected $stream;

    public function __construct($method, $url, $headers = array())
    {
        parent::__construct($method, $url, $headers);
    }


    /**
     * Sets connection stream to client or proxy
     *
     * @param ConnectionInterface $stream
     * @return self
     */
    public function setStream(ConnectionInterface $stream)
    {
        $this->stream = $stream;

        return $this;
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

    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;

        return $this;
    }

    public function getResponse() {
        return $this->response;
    }

    private function prepareResponseHeaders()
    {

    }

    public function send()
    {
        $this->prepareResponseHeaders();
        $this->setStartTime('Finished');

        $this->stream->write($this->response->getRawHeaders() . $this->response->getBody());
        $this->stream->end();

    }

    public function error($code, $description = '')
    {
        $this->setStartTime('Finished');

        $this->prepareResponseHeaders();
        $this->response->setStatusCode($code);

        if ($description)
            $this->response->setReasonPhrase($code);

        $this->stream->write($this->response->getRawHeaders() . $this->response->getBody());
        $this->stream->end();
    }
}
