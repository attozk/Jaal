<?php

namespace Attozk\Jaal\Httpd;

use Attozk\Jaal\ClientsManager;
use Attozk\Jaal\Httpd\Message\RequestFactory;
use Attozk\Jaal\Httpd\Message\RequestInterface;
use Attozk\Jaal\Httpd\Message\RequestUpstream;
use Attozk\Jaal\Httpd\Message\Response;
use Attozk\Jaal\Logger;
use Attozk\Jaal\Upstream\PoolHttpd;
use Attozk\Jaal\Upstream\UpstreamManager;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Server as TCPServer;
use React\Dns\Resolver\Resolver;

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
        'maxConnectionsPerIP' => 10,             // maximum concurrent connections per IP
    );

    /**
     * @param LoopInterface $loop
     * @param TCPServer $socket
     * @param Resolver $dns
     */
    public function __construct(LoopInterface $loop, TCPServer $socket, Resolver $dns)
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

            Logger::getInstance()->debug($client->getRemoteAddress() . ' has connected.');
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

                        /** @var $request \Attozk\Jaal\Httpd\Message\Request */
                        $request = RequestFactory::getInstance()->fromMessage($data);
                        $request->setClientSocket($client);

                        $this->handleRequest($request);
                    });

                },
                // @TODO error handle
                function ($error) use ($client) {
                    $client->end();
                }
            );

        });
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
        $this->clientsManager->remove($client);
        Logger::getInstance()->debug($client->getRemoteAddress() . ' has closed.');
    }

    /**
     * @emit client.request:PORT
     * @emit client.request.HOST:PORT
     * @param RequestInterface $request
     */
    protected function handleRequest(RequestInterface $request)
    {
        $this->emit('client.request' . ':' . $request->getPort(), [$request]);

        // @todo emit only when have a listener, otherwise default to client.request emit
        $this->emit('client.request.' . $request->getHost() . ':' . $request->getPort(), [$request]);

        Logger::getInstance()->debug($request->getClientSocket()->getRemoteAddress() . ' has requested for ' . $request->getMethod() . ' ' . $request->getUrl());
    }

    /**
     * @param PoolHttpd $pool
     * @param RequestInterface $request
     */
    public function proxy(PoolHttpd $pool, RequestInterface $request)
    {
        Logger::getInstance()->debug($request->getClientSocket()->getRemoteAddress() . ' ' . $request->getMethod() . ' ' . $request->getUrl() . ' >> UPSTREAM');

        $connector = $this->upstreamManager->buildConnector();
        $requestUpstream = new RequestUpstream($pool, $request, $connector);
        $requestUpstream->send();
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