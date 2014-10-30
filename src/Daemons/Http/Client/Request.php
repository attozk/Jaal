<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;

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

    /**
     * Read data from stream until reached end of message
     *
     * @param $data
     */
    public function handleData(ConnectionInterface $stream, $data)
    {
        $this->body .= $data;

        $EOM = $this->getEOMType();

        if ($EOM == 'length') {

        }
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
        $keepAliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
        $keepAliveMax = Jaal::getInstance()->config->get('httpd.keepalive.max');

        if ($keepAliveTimeout && $keepAliveMax) {
            $this->response->addHeader('Connection', 'keep-alive');
            $this->response->addHeader('Keep-Alive', 'timeout=' . $keepAliveTimeout . ', max=' . $keepAliveMax);
        } else {
            $this->response->addHeader('Connection', 'close');
        }
    }

    public function reply()
    {
        $this->prepareResponseHeaders();
        $this->setState(self::STATE_DONE);
        $this->stream->write($this->response->getRawHeaders() . $this->response->getBody());
        $this->end();
    }

    public function error($code, $description = '')
    {
        $this->setState(self::STATE_ERROR);

        if (!$this->response)
            $this->response = new Response($code);

        $this->prepareResponseHeaders();
        $this->response->setStatusCode($code);

        if ($description)
            $this->response->setReasonPhrase($code);

        $this->stream->write($this->response->getRawHeaders() . $this->response->getBody());
        $this->end();
    }

    private function end()
    {
        Logger::getInstance()->log(-99, 'REPLY ('. $this->state .') ' . Logger::getInstance()->color($this->getUrl(), 'red') . ' using stream: '. Logger::getInstance()->color($this->stream->id, 'green'));


        if (!Jaal::getInstance()->config->get('httpd.keepalive.max') && !Jaal::getInstance()->config->get('httpd.keepalive.max')) {
            $this->stream->end();
        }

        Jaal::getInstance()->getDaemon('httpd')->inboundIOManager->removeProp($this->stream, 'request');
    }
}
