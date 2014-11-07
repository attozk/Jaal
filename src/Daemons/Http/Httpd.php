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
use Hathoora\Jaal\Jaal;
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
     * Helper method for adding a new request property and setup various elements
     * @param ConnectionInterface $client
     */
    protected function inboundIOManagerAddNewRequestProperty(ConnectionInterface $client)
    {
        $request = new ClientRequest($this);
        $request->setStartTime()
                ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING)
                ->setStream($client);

        $this->inboundIOManager->add($client)
            ->setProp($client, 'request', $request);


//        if (!$this->inboundIOManager->getProp($client, 'timerTimeout'))
//        {
//            $timeout = Jaal::getInstance()->config->get('httpd.timeout');
//
//            $timerTimeout = $this->loop->addPeriodicTimer($timeout, function () use ($client) {
//
//                Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id, 'green') .
//                    ' connection timeout from Inbound Manager, hits: ' . $stream->hits .
//                    ', connection time: ' . Time::millitimeDiff($stream->millitime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
//            }
//        });
//        $this->setProp($stream, 'timerTimeout', $timerTimeout);
//
//        }
//
//        $keepaliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');
//
//
//
//        if ($timeout && $keepaliveTimeout && ($keepaliveTimeout * 1.5) < $timeout) {
//            $timerKeepaliveTimeout = $this->loop->addPeriodicTimer($keepaliveTimeout, function () use ($stream) {
//                if ($request = $this->getProp($stream, 'request')) {
//                    if (($timerKeepaliveTimeout = $this->getProp($stream, 'timerKeepaliveTimeout')) &&
//                        $timerKeepaliveTimeout instanceof TimerInterface)
//                    {
//                        $this->loop->cancelTimer($timerKeepaliveTimeout);
//                        $this->removeProp($stream, 'timerKeepaliveTimeout');
//                    }
//
//                    Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id, 'green') . ' keep-alive timeout from Inbound Manager, hits: ' .
//                        $stream->hits . ', connection time: ' . Time::millitimeDiff($stream->millitime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
//
//
//                    $stream->end();
//                }
//            });
//            $this->setProp($stream, 'timerKeepaliveTimeout', $timerKeepaliveTimeout);
//        }
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
            $requestParsingIsDone = FALSE;

            if (is_int($status) && $request->getState() == ClientRequestInterface::STATE_ERROR)
            {
                $errorCode = $status;
                $requestParsingIsDone = true;
            }
            // Request is ready to emit and/or EOM
            else if ($request->getParsingAttr('packets') == 1 || $request->getStateParsing() == ClientRequestInterface::STATE_PARSING_EOM)
            {
                ## emit request readiness
                if ($request->getParsingAttr('packets') == 1) {

                    // for admin stats
                    $client->hits++;
                    $client->resource = $request->getUrl();
                    $this->inboundIOManager->stats['hits']++;

                    // I think we need to add requests to UPSTREAM's QUEUE instead..
//                    $this->inboundIOManager->getQueue($client, 'requests')
//                                           ->enqueue($request);

                    Logger::getInstance()->log(
                        -50,
                        'REQUEST ' . $request->getMethod() . ' ' .
                        Logger::getInstance()->color($request->getUrl(), 'red') .
                                                    ' using stream: ' . Logger::getInstance()->color($client->id, 'green') . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));
                    //echo "IN QUEUE: ". $this->inboundIOManager->getQueue($client, 'requests')->count() . "\n";


                    $fallback = function () use ($request) {
                        $request->reply(404, '', true);
                    };

                    $this->emitClientRequestHandler($request, $fallback);
                }

                ## we reached EOM, lets be prepared to parse a new request on the same channel
                if ($status === true) {
                    $requestParsingIsDone = true;
                }
            }

            if ($requestParsingIsDone) {
                $this->handleClientInboundRequestParsingDone($request);
            }
        }
        else {
            $errorCode = 450;
        }

        // we need to close this connection manually even when keep-alive
        if ($errorCode) {
            $request->error($errorCode, '', true);
        }
    }

    /**
     * Actions to take when we client's request has been parsed
     *
     * @param ClientRequestInterface $request
     */
    public function handleClientInboundRequestParsingDone(ClientRequestInterface $request)
    {
        // remove old processed requests..
        $this->inboundIOManager->removeProp($request->getStream(), 'request');
        $this->inboundIOManagerAddNewRequestProperty($request->getStream());
    }

    /**
     * Actions to take when we client has received all the data
     *
     * @param ClientRequestInterface $request
     * @param bool $closeStream
     */
    public function handleClientInboundRequestDone(ClientRequestInterface $request, $closeStream = false)
    {
        // if we already have a request that errored out before reaching parsing end, then lets remove it
        if (($currentInboundUnParsedRequest = $this->inboundIOManager->getProp($request->getStream(), 'request')) && $currentInboundUnParsedRequest->id == $request->id) {
            $this->handleClientInboundRequestParsingDone($request);
        }


        if (
            $closeStream ||
            (!Jaal::getInstance()->config->get('httpd.keepalive.max') && !Jaal::getInstance()
                    ->config->get('httpd.keepalive.max')) ||
            $request->getProtocolVersion() == '1.0'
        ) {
            $request->getStream()->end();
        } // keep connection alive

        $request->cleanup();
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
            $vhost = VhostFactory::create($this, $vhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(), $clientRequest->getPort());
        }
        else if ($vhostConfig instanceof Vhost) {
            $vhost = $vhostConfig;
        }

        $newConnectorCB = function ($stream) use ($vhost, $clientRequest) {
            $upstreamRequest = $this->outboundIOManagerAddNewRequestProperty($stream, $vhost, $clientRequest);
            $upstreamRequest->send();

            $stream->on('data', function ($data) use ($stream) {
                $this->handleUpstreamInboundRequestData($stream, $data);
            });
        };

        $reuseConnectorCB = function ($stream) use ($vhost, $clientRequest) {
            $this->outboundIOManagerAddNewRequestProperty($stream, $vhost, $clientRequest);
        };

        $errorConnectorCB = function ($error) use ($clientRequest) {
            $clientRequest->error(500, '', TRUE);
        };

        $vhost->connectToUpstreamServer($clientRequest, $newConnectorCB, $reuseConnectorCB, $errorConnectorCB);
    }

    /**
     * Helper method for adding a new request property and setup various elements
     *
     * @param Stream $stream
     * @param Vhost $vhost
     * @param ClientRequestInterface $clientRequest
     * @return UpstreamRequestInterface
     */
    protected function outboundIOManagerAddNewRequestProperty(Stream $stream, $vhost, ClientRequestInterface $clientRequest)
    {
        $upstreamRequest = new UpstreamRequest($this, $vhost, $clientRequest);
        $upstreamRequest->setStartTime()
            ->setStream($stream)
            ->setState(UpstreamRequestInterface::STATE_CONNECTED)
            ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING);

        $stream->hits++;
        $stream->resource = $upstreamRequest->getUrl();
        $this->outboundIOManager->add($stream)
            ->setProp($stream, 'request', $upstreamRequest)
            ->stats['hits']++;

        $vhost->getQueueRequests()->enqueue($upstreamRequest);

        return $upstreamRequest;
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
        if ($request = $this->outboundIOManager->getProp($stream, 'request'))
        {

            /**
             * $status values:
             * NULL     being processed
             * TRUE     when reached EOM
             * INT      when error code
             */
            $status = $request->handleInboundData($data);
            $requestIsDone = FALSE;

            if (is_int($status) && $request->getState() == UpstreamRequestInterface::STATE_ERROR) {
                $errorCode = $status;
                $requestIsDone = TRUE;
            }
            // response is ready (has reached EOM)
            else if ($status === true && $request->getStateParsing() == UpstreamRequestInterface::STATE_PARSING_EOM)
            {
                ## we reached EOM, lets be prepared to parse a new request on the same channel
                if ($status === true) {

                    $requestIsDone = TRUE;
                    Logger::getInstance()->log(
                        -50,
                        'REPLIED ' . $request->getMethod() . ' ' . Logger::getInstance()
                                                                         ->color($request->getUrl(), 'red') .
                        ' using stream: ' . Logger::getInstance()->color($stream->id, 'green')
                        . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'yellow'));

                }
            }

            if ($requestIsDone)
            {
                $this->handleUpstreamInboundRequestDone($request);
            }
        }
        else {
            $errorCode = 450;
        }

        // we need to close this connection manually even when keep-alive
        if ($errorCode) {
            #echo "\n\n\n\n---------------------ERROR\n";
            #echo __FUNCTION__ . " -----> $errorCode ($data)\n";
            #echo "\n---------------------ERROR\n\n\n\n";
            // $request->reply($errorCode, '', true);
        }
    }

    /**
     * Actions to take when we got the reply from upstream server
     *
     * @param UpstreamRequestInterface $request
     * @param bool $closeStream
     */
    public function handleUpstreamInboundRequestDone(UpstreamRequestInterface $request, $closeStream = false)
    {
        // remove old processed requests..
        $this->outboundIOManager->removeProp($request->getStream(), 'request');
        $request->getStream()->resource = '';

        $queue = $request->getVhost()->getQueueRequests();
        $numQueuedRequests = $queue->count();

        if ($numQueuedRequests && ($queuedRequest = $queue->dequeue())) {
            /** @var UpstreamRequestInterface */
            $queuedRequest->send();
        }

        // close upstream connection?
        else if (
            (!$numQueuedRequests &&
                (
                    $request->getClientRequest()->getProtocolVersion() == '1.0' ||
                    (!$request->getVhost()->config->get('upstreams.keepalive.max') && !$request->getVhost()->config->get('upstreams.keepalive.max'))
                )
            ) || $closeStream
        ) {
            $request->getStream()->end();
        }

        $request->cleanup();
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