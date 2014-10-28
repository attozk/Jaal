<?php

namespace Hathoora\Jaal\Daemons\Http;

use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\Request as UpstreamRequest;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Factory as VhostFactory;
Use Hathoora\Jaal\IO\Manager\InboundManager;
use Hathoora\Jaal\IO\Manager\OutboundManager;
Use Hathoora\Jaal\IO\Manager\outoundManager;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Hathoora\Jaal\Logger;
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
     * @var InboundManager
     */
    public $inboundIOManager;

    /**
     * @var OutboundManager
     */
    public $outboundIOManager;

    /**
     * @var array stores requests
     */
    private $arrRequests = array();

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
        $this->inboundIOManager = new InboundManager($this->loop, 'http');
        $this->outboundIOManager = new OutboundManager($this->loop, $dns, 'http');
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
            $this->inboundIOManager->add($client);

            $client->isAllowed($client)->then(
                function ($client) {

                    $client->on('close', function (ConnectionInterface $client) {
                        $this->handleClose($client);
                    });

                    $client->on('error', function (ConnectionInterface $client) {
                        $this->handleError($client);
                    });

                    $client->on('data', function ($data) use ($client) {
                        $this->handleData($client, $data);
                    });
                },
                // @TODO error handle, emit somthing here?
                function ($error) use ($client) {
                    $this->inboundIOManager->end($client);
                }
            );
        });
    }

    /**
     * @param ConnectionInterface $client
     * @param $data
     */
    protected function handleData(ConnectionInterface $client, $data)
    {
        /** @var $request \Hathoora\Jaal\Daemons\Http\Client\RequestInterface */
        $request = Parser::getClientRequest($data);
        $request->setStartTime()
                ->setStream($client)
                ->setState('Ready');

        Logger::getInstance()->debug($request->getMethod() . ' ' . $request->getUrl());
        $this->handleRequest($request);
    }

    /**
     * @emit client.request:PORT
     * @emit client.request.HOST:PORT
     * @param ClientRequestInterface $request
     */
    protected function handleRequest(ClientRequestInterface $request)
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
        $this->inboundIOManager->end($client);
    }

    /**
     * @param array $arrVhostConfig
     * @param ClientRequestInterface $request
     */
    public function proxy($arrVhostConfig, ClientRequestInterface $request)
    {
        $vhost = VhostFactory::create($arrVhostConfig, $request->getScheme(), $request->getHost(), $request->getPort());
        (new UpstreamRequest($vhost, $request))->setBody($request->getBody())->setState('Pending')->send();
    }

    /**
     * get pretty stats
     */
    public function stats()
    {
        print_r($this->inboundIOManager->count());
    }
}