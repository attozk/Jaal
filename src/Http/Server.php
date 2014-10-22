<?php

namespace Attozk\Roxy\Http;

use Attozk\Roxy\Http\Message\RequestFactory;
use Attozk\Roxy\Http\Message\RequestInterface;
use Attozk\Roxy\Http\Message\Response;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Server as TCPServer;
use React\Dns\Resolver;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use SplObjectStorage;

/** @event connection */
class Server extends EventEmitter implements ServerInterface
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
    protected $clientsManager;

    /**
     * @var UpstreamManager;
     */
    protected $upstreamManager;

    private $arrConfig = array(
        'maxConnectionsPerIP' => 10             // maximum concurrent connections per IP
    );

    /**
     * @param LoopInterface $loop
     * @param TCPServer $socket
     * @param Resolver $dns
     */
    public function __construct(LoopInterface $loop, TCPServer $socket, /*Resolver*/ $dns)
    {
        $this->loop = $loop;
        $this->socket = $socket;
        $this->dns = $dns;
        $this->clientsManager = new ClientsManager($this->loop);
        $this->upstreamManager = new UpstreamManager($this->loop, $dns);
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
        $this->socket->on('connection', function(ConnectionInterface $client) {

            $microtime = microtime();

            $this->clientsManager->isAllowed($client)->then(
                function ($client) use ($microtime) {

                    $client->on('close', function (ConnectionInterface $client) {
                        $this->handleClose($client);
                    });

                    $client->on('error', function (ConnectionInterface $client) {
                        $this->handleError($client);
                    });

                    $client->on('data', function ($data) use ($client, $microtime) {

                        /** @var $request \Attozk\Roxy\Http\Message\Request */
                        $request = RequestFactory::getInstance()->fromMessage($data);
                        $request->setStartTime($microtime)->setClientSocket($client);

                        $this->handleRequest($request);
                    });

                },
                // @TODO error handle
                function ($error) {

                }
            );

        });
    }

    /**
     * @param $pool
     * @param RequestInterface $request
     * @return null|\React\Promise\FulfilledPromise|\React\Promise\RejectedPromise
     */
    public function getUpstream($pool, RequestInterface $request)
    {
        // @todo CHECK FOR POOL
        $host = 'WWW.google.com';
        $port = 80;

        $connector = new Connector($this->loop, $this->dns);
        $request->setUpstreamSocket($connector);

        return $request->connectUpstreamSocket($host, $port);
    }

    /**
     * @emit client.request
     * @emit client.request.HOST:PORT
     * @param RequestInterface $request
     */
    protected function handleRequest(RequestInterface $request)
    {
        $this->emit('client.request', [$request]);

        // @todo emit only when have a listener, otherwise default to client.request emit
        $this->emit('client.request.' . $request->getHost() . ':'. $request->getPort() , [$request]);
    }

    /**
     * @emit client.close
     * @param ConnectionInterface $client
     */
    protected function handleClose(ConnectionInterface $client)
    {
        $this->emit('client.close', [$client]);
        $this->clientsManager->remove($client);
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
     * get pretty stats
     */
    public function stats()
    {
        // @todo
        //print_r($this->stats);
    }
}