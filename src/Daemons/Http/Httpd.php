<?php

namespace Hathoora\Jaal\Daemons\Http;

use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\Request as UpstreamRequest;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Factory as VhostFactory;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
Use Hathoora\Jaal\IO\Manager\InboundManager;
use Hathoora\Jaal\IO\Manager\OutboundManager;
Use Hathoora\Jaal\IO\Manager\outoundManager;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\Logger;
use Evenement\EventEmitter;
use Hathoora\Jaal\Util\Time;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\Promise\Deferred;

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
     * This takes care of incoming connections, timeouts, stats and so on
     *
     * @var InboundManager
     */
    public $inboundIOManager;

    /**
     * This takes care of outgoing connections, timeouts, stats and so on
     *
     * @var OutboundManager
     */
    public $outboundIOManager;

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
        $this->handleClientConnection();
    }

    /**
     * Start listening to HTTP requests from clients
     *
     * @param int $port
     * @param string $host
     */
    public function listen($port = 80, $host = '127.0.0.1')
    {
        $this->socket->listen($port, $host);
    }

    /**
     * This method handles client connections
     */
    private function handleClientConnection()
    {
        $this->socket->on('connection', function (ConnectionInterface $client) {

            $this->inboundIOManager->add($client);

            $client->isAllowed($client)->then(

                function ($client) {
                    $client->on('data', function ($data) use ($client) {
                        $this->handleClientRequestData($client, $data);
                    });
                }
            );
        });
    }

    /**
     * Handles incoming data from client's request and makes sense of it
     *
     * @param ConnectionInterface $client
     * @param $data
     */
    protected function handleClientRequestData(ConnectionInterface $client, $data)
    {
        $request = null;

        if (!$this->inboundIOManager->getProp($client, 'request')) {

            /** @var $request ClientRequestInterface */
            $request = Parser::getClientRequest($data);
            $request->setStartTime()
                ->setStream($client);

            Logger::getInstance()->log(-50,
                'REQUEST ' . $request->getMethod() . ' ' . Logger::getInstance()->color($request->getUrl(),
                    'red') . ' using stream: ' . Logger::getInstance()->color($client->id,
                    'green') . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'yellow'));

            $client->resource = $request->getUrl();
            $client->hits++;
            $this->inboundIOManager->stats['hits']++;

            if ($client->hits > 1) {
                Logger::getInstance()->log(-99,
                    Logger::getInstance()->color($client->id, 'green') . ' keep-alive requested ' . $request->getUrl() . ', hits: ' . $client->hits . ', idle: ' . Time::millitimeDiff($this->inboundIOManager->getProp($client,
                        'lastActivity')) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                        'lightCyan'));
            }

            $this->inboundIOManager->setProp($client, 'request', $request);

        } else {
            /** @var $request ClientRequestInterface */
            if ($request = $request = $this->inboundIOManager->getProp($client, 'request')) {
                $request->handleIncomingData($client, $data);
            }
        }


        if ($request && $request->isValid() === true) {
            $this->emitClientRequestHandler($request);
        } else {
            Logger::getInstance()->log('ERROR', 'Unable to handle client request.');
            $client->end();
        }
    }

    /**
     * Similar to handleClientRequestData, this method handle's response data from upstream and makes sense of it
     *
     * @param Stream $stream
     * @param $data
     */
    protected function handleUpstreamRequestData(Stream $stream, $data)
    {
        /** @var $request \Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface */
        if ($request = $this->outboundIOManager->getProp($stream, 'request')) {
            $request->handleUpstreamOutputData($stream, $data);
        } else {
            die('handleUpstreamRequestData error');
        }
    }


    /**
     * After handling incoming client's request data, this method notifies to take action
     *
     * @param ClientRequestInterface $request
     * @emit client.request:HOST:PORT
     * @emit client.request:PORT        emitted if no listeners for above event are listening
     * @return string
     */
    public function emitClientRequestHandler(ClientRequestInterface $request)
    {
        $event = 'client.request:';
        $emitEvent = $event . $request->getHost() . ':' . $request->getPort();

        if (count($this->listeners($emitEvent))) {
            Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
            $this->emit($emitEvent, [$request]);
        } else {
            $emitName = $event . $request->getPort();
            Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
            $this->emit($emitName, [$request]);
        }

        return $emitName;
    }

    /**
     * After receiving client's request response and about to reply back to client, this function notifies to take any action.
     *
     * @param ClientRequestInterface $request
     * @param $code int
     * @param $description string
     * @emit client.response:HOST:PORT
     * @emit client.response:PORT        emitted if no listeners for above event are listening
     * @return string
     */
    public function emitClientResponseHandler(ClientRequestInterface $request, $code, $description)
    {
        $event = 'upstream.response:';
        $emitEvent = $event . $request->getHost() . ':' . $request->getPort();

        if (count($this->listeners($emitEvent))) {
            Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
            $this->emit($emitEvent, [$request]);
        } else {
            $emitName = $event . $request->getPort();
            Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
            $this->emit($emitName, [$request]);
        }

        return $emitName;
    }


    /**
     * After receiving upstream response and about to reply back to client, this function notifies to take any action.
     *
     * @param UpstreamRequestInterface $request
     * @param $code int
     * @param $description string
     * @emit upstream.response:HOST:PORT
     * @emit upstream.response:PORT        emitted if no listeners for above event are listening
     * @return string
     */
    public function emitUpstreamResponseHandler(UpstreamRequestInterface $request, $code, $description)
    {
        $event = 'upstream.response:';
        $emitEvent = $event . $request->getHost() . ':' . $request->getPort();

        if (count($this->listeners($emitEvent))) {
            Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
            $this->emit($emitEvent, [$request]);
        } else {
            $emitName = $event . $request->getPort();
            Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
            $this->emit($emitName, [$request]);
        }

        return $emitName;
    }

    /**
     * For proxying client's request to upstream
     *
     * @param $vhostConfig array|Vhost
     * @param ClientRequestInterface $clientRequest
     */
    public function proxy($vhostConfig, ClientRequestInterface $clientRequest)
    {
        Logger::getInstance()->log(-50,
            'PROXY ' . $clientRequest->getMethod() . ' ' . Logger::getInstance()->color($clientRequest->getUrl(),
                'red') . ' using stream: ' . Logger::getInstance()->color($clientRequest->getStream()->id,
                'green') . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                'yellow'));

        if (is_array($vhostConfig)) {
            $vhost = VhostFactory::create($vhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(), $clientRequest->getPort());
        } else if ($vhostConfig instanceof Vhost) {
            $vhost = $vhostConfig;
        }

        $arrUpstreamConfig = $vhost->getUpstreamConnectorConfig();

        $ip = $arrUpstreamConfig['ip'];
        $port = $arrUpstreamConfig['port'];
        $keepalive = $arrUpstreamConfig['keepalive'];
        $timeout = $arrUpstreamConfig['timeout'];

        $upstreamRequest = new UpstreamRequest($vhost, $clientRequest);
        $upstreamRequest->setBody($clientRequest->getBody());

        $this->outboundIOManager->buildConnector($ip, $port, $keepalive, $timeout)->then(

            function (Stream $stream) use ($upstreamRequest) {

                $stream->hits++;
                $stream->resource = $upstreamRequest->getUrl();
                $this->outboundIOManager->stats['hits']++;
                $this->outboundIOManager->setProp($stream, 'request', $upstreamRequest);

                $upstreamRequest->setStartTime()
                    ->setStream($stream)
                    ->setState(RequestInterface::STATE_CONNECTING)
                    ->send();

                $stream->on('data', function ($data) use ($stream) {
                    $this->handleUpstreamRequestData($stream, $data);
                });

            }, function ($error) use ($clientRequest) {

                $clientRequest->reply(500);
            }
        );
    }

    /**
     * get pretty stats
     */
    public function stats()
    {
        return array(
            'inbound' => $this->inboundIOManager->stats(),
            'outbound' => $this->outboundIOManager->stats()
        );
    }
}