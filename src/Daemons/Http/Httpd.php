<?php

namespace Hathoora\Jaal\Daemons\Http;

use Evenement\EventEmitter;
use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\Request as UpstreamRequest;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Factory as VhostFactory;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
Use Hathoora\Jaal\IO\Manager\InboundManager;
use Hathoora\Jaal\IO\Manager\OutboundManager;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\Logger;
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
     * @var \React\Dns\Resolver\Resolver
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
     * @param SocketServer  $socket
     * @param Resolver      $dns
     */
    public function __construct(LoopInterface $loop, SocketServer $socket, Resolver $dns)
    {
        $this->loop              = $loop;
        $this->socket            = $socket;
        $this->dns               = $dns;
        $this->inboundIOManager  = new InboundManager($this->loop, 'http');
        $this->outboundIOManager = new OutboundManager($this->loop, $dns, 'http');
        $this->handleClientConnection();
    }

    /**
     * Start listening to HTTP requests from clients
     *
     * @param int    $port
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

            $this->inboundIOManager->add($client)->newQueue($client, 'requests');

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
     * @param ConnectionInterface|\Hathoora\Jaal\IO\React\Socket\Connection $client
     * @param                                                               $data
     */
    protected function handleClientRequestData(ConnectionInterface $client, $data)
    {
        $request = NULL;

        //In order to remain persistent, all messages on a connection need to have a self-defined message length
        //(i.e., one not defined by closure of the connection), as described in Section 3.3. A server MUST read the entire
        //request message body or close the connection after sending its response, since otherwise the remaining data on a
        //persistent connection would be misinterpreted as the next request. Likewise, a client MUST read the entire
        //response message body if it intends to reuse the same connection for a subsequent request.

        $request = Parser::getClientRequest($data);

        $request = Parser::getClientRequest($data);

        if (Parser::hasReachedEOM($this, $client, $data)) {


            $this->inboundIOManager->add($client)->newQueue($client, 'requests');
        }


        if (!$this->inboundIOManager->getProp($client, 'request')) {

            $request = Parser::getClientRequest($data);
            $request->setStartTime()
                    ->setStream($client);

            Logger::getInstance()
                  ->log(-50,
                        "\n\n" . 'REQUEST ' . $request->getMethod() . ' ' .
                        Logger::getInstance()->color($request->getUrl(), 'red') .
                        ' using stream: ' . Logger::getInstance()->color($client->id, 'green') . ' ' .
                        Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

            $client->resource = $request->getUrl();
            $client->hits++;
            $this->inboundIOManager->stats['hits']++;

            if ($client->hits > 1) {
                Logger::getInstance()->log(-99,
                                           Logger::getInstance()->color($client->id, 'green') .
                                           ' keep-alive requested ' . $request->getUrl() .
                                           ', hits: ' . $client->hits . ', idle: ' .
                                           Time::millitimeDiff($this->inboundIOManager->getProp($client,
                                                                                                'lastActivity')) .
                                           ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                                                                                 'lightCyan'));
            }

            $this->inboundIOManager->setProp($client, 'request', $request);
        } else {
            /** @var $request ClientRequestInterface */
            if ($request = $this->inboundIOManager->getProp($client, 'request')) {
                $request->handleIncomingData($client, $data);
            }
        }

        if ($request && $request->isValid() === TRUE) {

            $fallback = function () use ($request) {
                $request->reply(404);
                $request->getStream()->end();
            };
            $this->emitClientRequestHandler($request, $fallback);
        } else {
            Logger::getInstance()->log('ERROR', 'Unable to handle client request.');
            $client->end();
        }
    }

    /**
     * Similar to handleClientRequestData, this method handle's response data from upstream and makes sense of it
     *
     * @param Stream $stream
     * @param        $data
     */
    protected function handleUpstreamRequestData(Stream $stream, $data)
    {
        /** @var $request \Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface */
        if ($request = $this->outboundIOManager->getProp($stream, 'request')) {
            $request->handleUpstreamOutputData($stream, $data);
        } else {
            echo('handleUpstreamRequestData error' . "\n---->" . $data . "<--" . strlen($data) . "---\n\n");
        }
    }

    /**
     * After handling incoming client's request data, this method notifies to take action

     *
     * @param ClientRequestInterface $request
     * @param callable         $fallbackCallback when no listeners found, use this callback
     * @emit request.HOST:PORT
     */
    public function emitClientRequestHandler(ClientRequestInterface $request, callable $fallbackCallback = NULL)
    {
        $emitEvent = 'request.' . $request->getHost() . ':' . $request->getPort();

        Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));

        $this->emit($emitEvent, [$request], $fallbackCallback);
    }

    /**
     * After receiving client's request response and about to reply back to client, this function notifies to take any
     * action.

     *
     * @param ClientRequestInterface $request
     * @param callable         $fallbackCallback when no listeners found, use this callback
     * @emit response:HOST:PORT
     */
    public function emitClientResponseHandler(ClientRequestInterface $request, callable $fallbackCallback = null)
    {
        $emitEvent = 'response.' . $request->getHost() . ':' . $request->getPort();

        Logger::getInstance()
              ->log(-99,
                    'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));

        $this->emit($emitEvent, [$request], $fallbackCallback);
    }

    /**
     * For proxy-ing client's request to upstream
     *
     * @param                        $vhostConfig array|Vhost
     * @param ClientRequestInterface $clientRequest
     */
    public function proxy($vhostConfig, ClientRequestInterface $clientRequest)
    {
        $vhost = NULL;
        Logger::getInstance()->log(-50,
                                   'PROXY ' . $clientRequest->getMethod() . ' ' .
                                   Logger::getInstance()->color($clientRequest->getUrl(),
                                                                'red') . ' using stream: ' .
                                   Logger::getInstance()->color($clientRequest->getStream()->id,
                                                                'green') . ' ' .
                                   Logger::getInstance()->color('[' . __METHOD__ . ']',
                                                                'yellow'));

        if (is_array($vhostConfig)) {
            $vhost = VhostFactory::create($vhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(), $clientRequest->getPort());
        } else if ($vhostConfig instanceof Vhost) {
            $vhost = $vhostConfig;
        }

        $arrUpstreamConfig = $vhost->getUpstreamConnectorConfig();

        $ip        = $arrUpstreamConfig['ip'];
        $port      = $arrUpstreamConfig['port'];
        $keepalive = $arrUpstreamConfig['keepalive'];
        $timeout   = $arrUpstreamConfig['timeout'];

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
            },
            function ($error) use ($clientRequest) {

                $clientRequest->reply(500);
            }
        );
    }

    /**
     * get pretty stats
     */
    public function stats()
    {
        return [
            'inbound'  => $this->inboundIOManager->stats(),
            'outbound' => $this->outboundIOManager->stats()
        ];
    }
}