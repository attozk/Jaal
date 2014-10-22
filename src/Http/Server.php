<?php

namespace Attozk\Roxy\Http;

use Attozk\Roxy\ClientsManager;
use Attozk\Roxy\Http\Message\RequestFactory;
use Attozk\Roxy\Http\Message\RequestInterface;
use Attozk\Roxy\Http\Message\RequestUpstream;
use Attozk\Roxy\Http\Message\Response;
use Attozk\Roxy\UpstreamManager;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Server as TCPServer;
use React\Dns\Resolver;

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
    public function __construct(LoopInterface $loop, TCPServer $socket, /*Resolver*/ $dns)
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
        $this->socket->on('connection', function(ConnectionInterface $client) {

            echo $client->getRemoteAddress() . " has connected \n";

            $microtime = microtime();
            $this->clientsManager->isAllowed($client)->then(
                function ($client) use ($microtime) {

                    echo $client->getRemoteAddress() . " is allowed to connect \n";

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
                function ($error) use($client) {
                    echo $client->getRemoteAddress() . " is not allowed to connect \n";

                    $client->end();
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

    }

    public function proxy($pool, RequestInterface $request, $arrOptions)
    {
        $connector = $this->upstreamManager->buildConnector();
        $requestUpstream = new RequestUpstream($request, $connector, $arrOptions);
        $requestUpstream->send();
    }

    /**
     * @emit http.client.request
     * @emit http.client.request.HOST:PORT
     * @param RequestInterface $request
     */
    protected function handleRequest(RequestInterface $request)
    {
        $this->emit('http.client.request', [$request]);

        // @todo emit only when have a listener, otherwise default to client.request emit
        $this->emit('http.client.request.' . $request->getHost() . ':'. $request->getPort() , [$request]);
    }

    /**
     * @emit client.close
     * @param ConnectionInterface $client
     */
    protected function handleClose(ConnectionInterface $client)
    {
        echo $client->getRemoteAddress() . " has closed \n";
        $this->emit('http.client.close', [$client]);
        $this->clientsManager->remove($client);
    }

    /**
     * @emit client.error
     * @param ConnectionInterface $client
     */
    protected function handleError(ConnectionInterface $client)
    {
        echo $client->getRemoteAddress() . " has ended \n";

        $this->emit('http.client.error', [$client]);
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