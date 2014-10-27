<?php

namespace Hathoora\Jaal\Daemons\Http;

use Hathoora\Jaal\ClientsManager;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Hathoora\Jaal\Daemons\Http\Message\Parser\Parser;
use Hathoora\Jaal\Daemons\Http\Message\RequestFactory;
use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\RequestUpstream;
use Hathoora\Jaal\Logger;
use Hathoora\Jaal\Upstream\Http\Pool;
use Hathoora\Jaal\Upstream\UpstreamManager;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;

/** @event connection */
class Httpd extends EventEmitter implements HttpdInterface
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * @var \React\Socket\Server
     */
    protected $socket;

    /**
     * @var \React\Dns\Resolver
     */
    protected $dns;

    /**
     * @var ClientsManager
     */
    public $clientsManager;

    /**
     * @var \Hathoora\Jaal\Upstream\UpstreamManager
     */
    public $upstreamManager;

    private $arrConfig = array(
        'maxConnectionsPerIP' => 10,             // maximum concurrent connections per IP
    );

    /**
     * @param LoopInterface $loop
     * @param SocketServer $socket
     * @param Resolver $dns
     */
    public function __construct(LoopInterface $loop, SocketServer $socket, Resolver $dns)
    {
        $this->loop = $loop;
        $this->socket = $socket;
        $this->dns = $dns;
        $this->clientsManager = new ClientsManager($this->loop, 'http');
        $this->upstreamManager = new UpstreamManager($this->loop, $dns, 'http');
        $this->listen();
        $this->init();
    }

    /**
     * Start listening
     *
     * @param int $port
     * @param string $host
     */
    public function listen($port = 80, $host = '127.0.0.1')
    {
        $this->socket->listen($port, $host);
    }

    private function init()
    {
        $this->socket->on('connection', function (ConnectionInterface $client) {
            $this->clientsManager->add($client);

            $client->isAllowed($client)->then(
                function ($client) {

                    $client->on('close', function (ConnectionInterface $client) {
                        $this->handleClose($client);
                    });

                    $client->on('error', function (ConnectionInterface $client) {
                        $this->handleError($client);
                    });

                    $client->on('data', function ($data) use ($client) {
                        $this->handleData($data, $client);
                    });
                },
                // @TODO error handle, emit somthing here?
                function ($error) use ($client) {
                    $this->clientsManager->end($client);
                }
            );
        });
    }

    /**
     * @param $data
     * @param ConnectionInterface $client
     */
    protected function handleData($data, ConnectionInterface $client)
    {
        /** @var $request \Hathoora\Jaal\Daemons\Http\Message\Request */
        $request = RequestFactory::getInstance()->fromMessage($data);
        $request->setClientSocket($client);
        $this->handleRequest($request);
    }

    /**
     * @emit client.request:PORT
     * @emit client.request.HOST:PORT
     * @param RequestInterface $request
     */
    protected function handleRequest(RequestInterface $request)
    {
        $emitVhostKey = 'client.request.' . $request->getHost() . ':' . $request->getPort();

        if (count($this->listeners($emitVhostKey))) {
            $this->emit($emitVhostKey, [$request]);
        } else {
            $this->emit('client.request' . ':' . $request->getPort(), [$request]);
        }
    }

    /**
     * @emit client.error
     * @param ConnectionInterface $client
     */
    protected function handleError(ConnectionInterface $client)
    {
        $this->emit('client.error', [$client]);
    }

    /**
     * @emit client.close
     * @param ConnectionInterface $client
     */
    protected function handleClose(ConnectionInterface $client)
    {
        $this->emit('client.close', [$client]);
        $this->clientsManager->end($client);
    }

    /**
     * @param Pool $pool
     * @param RequestInterface $request
     */
    public function proxy(Pool $pool, RequestInterface $request)
    {
        $requestUpstream = new RequestUpstream($pool, $request);
        $requestUpstream->send();
    }

    /**
     * get pretty stats
     */
    public function stats()
    {
        print_r($this->clientsManager->count());
    }
}