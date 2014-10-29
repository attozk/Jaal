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
use Hathoora\Jaal\Util\Time;
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

                    $client->on('data', function ($data) use ($client) {
                        $this->handleData($client, $data);
                    });
                },
                function ($error) use ($client) {
                    $this->handleClose($client);
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
        if (!$this->inboundIOManager->getProp($client, 'request')) {

            /** @var $request \Hathoora\Jaal\Daemons\Http\Client\RequestInterface */
            $request = Parser::getClientRequest($data);
            $request->setStartTime()
                ->setStream($client);

            Logger::getInstance()->log(-50, $request->getHost() . ' ' . $request->getMethod() . ' ' . $request->getUrl() . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

            $client->resource = $request->getUrl();

            if ($client->hits > 1) {
                Logger::getInstance()->log(-99,
                    $client->getRemoteAddress() . ' <' . $client->id . '> keep alive, hits: ' . $client->hits . ', idle: ' . Time::millitimeDiff($this->inboundIOManager->getProp($client, 'lastActivity')) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                        'lightCyan'));
            }

            $client->hits++;
            $this->inboundIOManager->setProp($client, 'request', $request);

        } else {
            /** @var $request \Hathoora\Jaal\Daemons\Http\Client\RequestInterface */
            $request = $this->inboundIOManager->getProp($client, 'request');
            $request->handleData($client, $data);
        }

        if ($request->isValid() === true) {
            $this->handleRequest($request);
        }
    }

    /**
     * @emit client.request:PORT
     * @emit client.request.HOST:PORT
     * @param ClientRequestInterface $request
     */
    protected function handleRequest(ClientRequestInterface $request)
    {
        Logger::getInstance()->log(-99, $request->getHost() . ' ' . $request->getMethod() . ' ' . $request->getUrl() . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

        $emitVhostKey = 'client.request.' . $request->getHost() . ':' . $request->getPort();

        if (count($this->listeners($emitVhostKey))) {
            $this->emit($emitVhostKey, [$request]);
        } else {
            $this->emit('client.request' . ':' . $request->getPort(), [$request]);
        }
    }

    /**
     * @param array $arrVhostConfig
     * @param ClientRequestInterface $request
     */
    public function proxy($arrVhostConfig, ClientRequestInterface $request)
    {
        Logger::getInstance()->log(-50, $request->getHost() . ' ' . $request->getMethod() . ' ' . $request->getUrl() . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

        $vhost = VhostFactory::create($arrVhostConfig, $request->getScheme(), $request->getHost(), $request->getPort());

        if (!$this->inboundIOManager->getProp($request->getStream(), 'upstreamRequest')) {
            $upstreamRequest = new UpstreamRequest($vhost, $request);
            $upstreamRequest->setBody($request->getBody())->setState(ClientRequestInterface::STATE_PENDING)->send();
        } // @todo
        else if ($upstreamRequest = $this->inboundIOManager->getProp($request->getStream(), 'upstreamRequest')) {

        }
    }

    /**
     * get pretty stats
     */
    public function stats()
    {
        return array('inbound' => $this->inboundIOManager->stats(),
            'outbound' => $this->outboundIOManager->stats()
        );
    }
}