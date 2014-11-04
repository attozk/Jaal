<?php

namespace Hathoora\Jaal\Daemons\Http\Client;

use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Daemons\Http\Message\Response;
use Hathoora\Jaal\Daemons\Http\Message\ResponseInterface;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;

Class Request extends \Hathoora\Jaal\Daemons\Http\Message\Request implements RequestInterface
{
    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ConnectionInterface|\Hathoora\Jaal\IO\React\Socket\Connection
     */
    protected $stream;

    public function __construct($method, $url, $headers = [])
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
     * Reads incoming data (when more than buffer) to parse it into a message
     *
     * @param ConnectionInterface $stream
     * @param                     $data
     * @return void
     */
    public function handleIncomingData(ConnectionInterface $stream, $data)
    {
        $this->body .= $data;

        $EOM = $this->getEOMStrategy();

        if ($EOM == 'length') {
        }
    }

    /**
     * Set response to this request
     *
     * @param ResponseInterface $response
     * @return self
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get response of this request
     *
     * @return ResponseInterface $response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Reply to client's request using $stream
     *
     * @param string $code    to overwrite response's status code
     * @param string $message to overwrite response's body
     * @return mixed
     */
    public function reply($code = '', $message = '')
    {
        $this->setState(self::STATE_DONE);
        $responseCreated = FALSE;

        if (!$this->response) {
            $this->response = new Response($code);
            $this->response->setReasonPhrase($message);
            $responseCreated = TRUE;
        }

        $this->prepareResponseHeaders();

        if ($code && $responseCreated === FALSE) {
            $this->response->setStatusCode($code);
            $this->response->setReasonPhrase($message);
        }

        Jaal::getInstance()->getDaemon('httpd')->emitClientResponseHandler($this);

        // by default spit the message out
        $this->stream->write($this->response->getRawHeaders() . "\r\n" . $this->response->getBody());
        $this->cleanup();
    }

    /**
     * Cleanups internal registry
     */
    private function cleanup()
    {
        Logger::getInstance()
              ->log(-99,
                    'REPLY (' . $this->state . ') ' . Logger::getInstance()->color($this->getUrl(), 'red') .
                    ' using stream: ' . Logger::getInstance()->color($this->stream->id, 'green'));

        Jaal::getInstance()->getDaemon('httpd')->inboundIOManager->removeProp($this->stream, 'request');

        if (!Jaal::getInstance()->config->get('httpd.keepalive.max') &&
            !Jaal::getInstance()->config->get('httpd.keepalive.max')
        ) {
            $this->stream->end();
        }
        //unset($this);
    }

    /**
     * Prepare response headers (based on config) to send to client
     */
    private function prepareResponseHeaders()
    {
        $keepAliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
        $keepAliveMax     = Jaal::getInstance()->config->get('httpd.keepalive.max');

        if ($this->response->getProtocolVersion() != $this->getProtocolVersion()) {
            $this->response->getProtocolVersion() = $this->getProtocolVersion();
        }

        if ($this->getHeader('connection') != 'close' && $keepAliveTimeout && $keepAliveMax) {
            $this->response->addHeader('Connection', 'keep-alive');
            $this->response->addHeader('Keep-Alive', 'timeout=' . $keepAliveTimeout . ', max=' . $keepAliveMax);
        } else {
            $this->response->addHeader('Connection', 'close');
        }

        $this->response->addHeader('Server', Jaal::name);
    }
}
