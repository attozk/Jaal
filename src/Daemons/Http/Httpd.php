<?php

namespace Hathoora\Jaal\Daemons\Http;

use Evenement\EventEmitter;
use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Client\Request as ClientRequest;
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







    #######################################################################################
    ##
    ##              Inbound Client Code
    ##  Following code is for requests coming to jaal server from clients (or browsers)
    ##
    /**
     * Helper method for adding a new request property and setup various elements
     * @param ConnectionInterface $client
     */
    protected function inboundIOManagerAddNewRequestProperty(ConnectionInterface $client)
    {
        $request = new ClientRequest();
        $request->setStartTime()
                ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING)
                ->setStream($client);

        $client->hits++;
        $this->inboundIOManager->add($client)
            ->newQueue($client, 'requests')
            ->setProp($client, 'request', $request)
            ->stats['hits']++;
    }

    /**
     * This method handles client connections
     */
    private function handleClientConnection()
    {
        $this->socket->on('connection', function (ConnectionInterface $client) {

            $this->inboundIOManagerAddNewRequestProperty($client);

            $client->isAllowed($client)->then(
                function ($client) {
                    $client->on('data', function ($data) use ($client) {

                        $this->handleClientInboundRequestData($client, $data);
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
    protected function handleClientInboundRequestData(ConnectionInterface $client, $data)
    {
        $errorCode = 0;

        /** @var $request ClientRequest */
        if ($request = $this->inboundIOManager->getProp($client, 'request')) {

            /**
             * $status values:
             * NULL     being processed
             * TRUE     when reached EOM
             * INT      when error code
             */
            $status = $request->handleInboundData($data);

            if (is_int($status) && $request->getState() == ClientRequestInterface::STATE_ERROR) {
                $errorCode = $status;
            }
            // Request is ready to emit and/or EOM
            else if ($request->getParsingAttr('packets') == 1 || $request->getStateParsing() == ClientRequestInterface::STATE_PARSING_EOM)
            {
                ## emit request readiness
                if ($request->getParsingAttr('packets') == 1) {
                    Logger::getInstance()->log(-50, "\n\n" . 'REQUEST ' . $request->getMethod() . ' ' . Logger::getInstance()->color($request->getUrl(), 'red') .
                                                    ' using stream: ' . Logger::getInstance()->color($client->id, 'green') . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

                    $fallback = function () use ($request) {
                        $request->reply(404, '', true);
                    };

                    $this->emitClientRequestHandler($request, $fallback);

                    // for admin stats
                    $client->resource = $request->getUrl();
                }

                ## we reached EOM, lets be prepared to parse a new request on the same channel
                if ($status === true) {
                    $this->inboundIOManagerAddNewRequestProperty($client);

                    /**
                        In order to remain persistent, all messages on a connection need to have a self-defined message length
                        (i.e., one not defined by closure of the connection), as described in Section 3.3. A server MUST read the entire
                        request message body or close the connection after sending its response, since otherwise the remaining data on a
                        persistent connection would be misinterpreted as the next request. Likewise, a client MUST read the entire
                        response message body if it intends to reuse the same connection for a subsequent request.
                    */
                    $this->inboundIOManager->getQueue($client, 'requests')
                                           ->enqueue($request);
                }
            }
        }
        else {
            $errorCode = 450;
        }

        // we need to close this connection manually even when keep-alive
        if ($errorCode) {
            $request->reply($errorCode, '', true);
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







    #######################################################################################
    ##
    ##              Outbound Upstream Code
    ##  Following code is for requests going from jaal server outside to upstream server/docroot
    ##
    /**
     * For proxy-ing client's request to upstream
     *
     * @param                        $vhostConfig array|Vhost
     * @param ClientRequestInterface $clientRequest
     */
    public function proxy($vhostConfig, ClientRequestInterface $clientRequest)
    {
        $vhost = NULL;
        Logger::getInstance()->log(-50, 'PROXY ' . $clientRequest->getMethod() . ' ' .Logger::getInstance()->color($clientRequest->getUrl(), 'red') .
            ' using stream: ' .Logger::getInstance()->color($clientRequest->getStream()->id, 'green') . ' ' .
            Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

        if (is_array($vhostConfig)) {
            $vhost = VhostFactory::create($vhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(), $clientRequest->getPort());
        }
        else if ($vhostConfig instanceof Vhost) {
            $vhost = $vhostConfig;
        }

        $arrUpstreamConfig = $vhost->getUpstreamConnectorConfig();

        $ip        = $arrUpstreamConfig['ip'];
        $port      = $arrUpstreamConfig['port'];
        $keepalive = $arrUpstreamConfig['keepalive'];
        $timeout   = $arrUpstreamConfig['timeout'];

        $this->outboundIOManager->buildConnector($ip, $port, $keepalive, $timeout)->then(
            function (Stream $stream) use ($vhost, $clientRequest) {

                $this->outboundIOManagerAddNewRequestProperty($stream, $vhost, $clientRequest);

                $stream->on('data', function ($data) use ($stream) {
                    $this->handleUpstreamInboundRequestData($stream, $data);
                });
            },
            function ($error) use ($clientRequest) {

                // we need to close this connection manually even when keep-alive
                $clientRequest->reply(500, '', true);
            }
        );
    }

    /**
     * Helper method for adding a new request property and setup various elements
     *
     * @param Stream $stream
     * @param Vhost $vhost
     * @param ClientRequestInterface $clientRequest
     */
    protected function outboundIOManagerAddNewRequestProperty(Stream $stream, $vhost, $clientRequest)
    {
        $upstreamRequest = new UpstreamRequest($vhost, $clientRequest);
        $upstreamRequest->setStartTime()
                        ->setStream($stream)
                        ->setState(UpstreamRequestInterface::STATE_CONNECTED)
                        ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING)
                        ->send();

        $stream->hits++;
        $this->outboundIOManager->add($stream)
            ->setProp($stream, 'request', $upstreamRequest)
            ->stats['hits']++;
    }

    /**
     * Similar to handleClientInboundRequestData, this method handle's response data from upstream and makes sense of it
     *
     * @param Stream $stream
     * @param        $data
     */
    protected function handleUpstreamInboundRequestData(Stream $stream, $data)
    {
        $errorCode = 0;

        /** @var $request UpstreamRequestInterface */
        if ($request = $this->inboundIOManager->getProp($stream, 'request')) {

            /**
             * $status values:
             * NULL     being processed
             * TRUE     when reached EOM
             * INT      when error code
             */
            $status = $request->handleInboundData($data);

            if (is_int($status) && $request->getState() == UpstreamRequestInterface::STATE_ERROR) {
                $errorCode = $status;
            }
            // response is ready (has reached EOM)
            else if ($status === true && $request->getStateParsing() == UpstreamRequestInterface::STATE_PARSING_EOM)
            {
                ## we reached EOM, lets be prepared to parse a new request on the same channel
                if ($status === true) {


                    echo "UPSTREAM EOM \n";

                    //$this->inboundIOManagerAddNewRequestProperty($client);
                }
            }
        }
        else {
            $errorCode = 450;
        }

        // we need to close this connection manually even when keep-alive
        if ($errorCode) {
            $request->reply($errorCode, '', true);
        }
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



//    /**
//     * After receiving client's request response and about to reply back to client, this function notifies to take any
//     * action.
//
//     *
//     * @param ClientRequestInterface $request
//     * @param callable         $fallbackCallback when no listeners found, use this callback
//     * @emit response:HOST:PORT
//     */
//    public function emitClientResponseHandler(ClientRequestInterface $request, callable $fallbackCallback = null)
//    {
//        $emitEvent = 'response.' . $request->getHost() . ':' . $request->getPort();
//        Logger::getInstance()->log(-99, 'EMIT ' . $emitEvent . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
//        $this->emit($emitEvent, [$request], $fallbackCallback);
//    }

}