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
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;

/** @event connection */
class Httpd extends EventEmitter implements HttpdInterface
{
    /**
     * @var \Hathoora\Jaal\Jaal
     */
    protected $jaal;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * @var \React\Socket\Server
     */
    protected $socket;

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

    protected $debug = 0;

    /**
     * @param \Hathoora\Jaal\Jaal      $jaal
     * @param LoopInterface            $loop
     * @param SocketServer             $socket
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(Jaal $jaal, LoopInterface $loop, SocketServer $socket, LoggerInterface $logger)
    {
        $this->jaal              = $jaal;
        $this->loop              = $loop;
        $this->socket            = $socket;
        $this->logger            = $logger;
        $this->inboundIOManager  = new InboundManager($this->loop, 'http');
        $this->outboundIOManager = new OutboundManager($this->loop, $this->jaal->dns, 'http');
        $this->debug             = true;//$this->jaal->config->get('jaal.debug.level');
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
        $this->socket->on('connection', function (ConnectionInterface $client)
        {
            $this->onClientConnect($client);
        });
    }







    #######################################################################################
    ##
    ##              Inbound Client Code
    ##  Following code is for requests coming to jaal server from clients (or browsers)
    ##
    /**
     * Handles client's connection
     *
     * @param ConnectionInterface $client
     */
    protected function onClientConnect(ConnectionInterface $client)
    {
        $this->clientRequestFactory($client);
        $client->on('data', function ($data) use ($client)
        {
            $this->onClientRequestData($client, $data);
        });
    }

    /**
     * Helper method for adding a new request property and setup various elements
     * @param ConnectionInterface $client
     */
    protected function clientRequestFactory(ConnectionInterface $client)
    {
        $request = new ClientRequest($this);
        $request->setStartTime()
                ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING)
                ->setStream($client);

        $this->inboundIOManager->add($client)
            ->setProp($client, 'request', $request)
            ->newQueue($client, 'requests');
    }

    /**
     * Handles incoming data from client's request and makes sense of it
     *
     * @param ConnectionInterface|\Hathoora\Jaal\IO\React\Socket\Connection $client
     * @param                                                               $data
     */
    protected function onClientRequestData(ConnectionInterface $client, $data)
    {
        /** @var $request ClientRequest */
        if ($request = $this->inboundIOManager->getProp($client, 'request')) {

            /**
             * $status values:
             * NULL     being processed
             * TRUE     when reached EOM
             * INT      when error code
             */
            $status = $request->onInboundData($data);
            if (is_int($status) && $request->getState() == ClientRequestInterface::STATE_ERROR)
            {
                $request->error($status, '', true);
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
                    $this->inboundIOManager->getQueue($client, 'requests')
                                           ->enqueue($request);

                    if ($this->debug)
                        $this->logger->log(-50, sprintf('%-25s' . $this->debugClientRequest($request), 'REQUEST-NEW'));

                    $this->onClientRequestReadyForVhost($request);
                }

                // send the request to upstream
                if ($request->getUpstreamRequest())
                {
                    $request->getUpstreamRequest()->send($request->getParsingAttr('buffer'));
                    $request->setParsingAttr('buffer', '');
                }

                ## we reached EOM, lets be prepared to parse a new request on the same channel
                if ($status === true) {
                    $this->onClientRequestEOM($request);
                }
            }
        }
        // a client cannot send another request until it has finished sending the first one
        // @TODO: throw friendly (4XX) error?
        else
            $client->end();
    }

    /**
     * Actions to take when we client's request has been parsed
     *
     * @param ClientRequestInterface $request
     */
    public function onClientRequestEOM(ClientRequestInterface $request)
    {
        // remove old processed requests..
        $this->inboundIOManager->removeProp($request->getStream(), 'request');

        if ($request->getProtocolVersion() == '1.0' ||
            (!$this->jaal->config->get('httpd.keepalive.timeout') && $this->jaal->config->get('httpd.keepalive.max')) ||
            strcasecmp($request->getHeader('Connection'), 'keep-alive') != 0
        )
        {
            // keep alive negative negative logic is confusing at times...
        }
        else
            $this->clientRequestFactory($request->getStream());
    }

    /**
     * Actions to take when we client has received all the data
     *
     * @param ClientRequestInterface $request
     * @param bool $closeStream
     */
    public function onClientRequestDone(ClientRequestInterface $request, $closeStream = false)
    {
        // if we already have a request which errored out before reaching parsing end, then lets remove it
        if (($currentInboundUnParsedRequest = $this->inboundIOManager->getProp($request->getStream(), 'request')) &&
            $currentInboundUnParsedRequest->id == $request->id
        )
        {
            $this->onClientRequestEOM($request);
        }

        $keepAlive = true;

        if ($closeStream ||
            $request->getProtocolVersion() == '1.0' ||
            (!$this->jaal->config->get('httpd.keepalive.timeout') && $this->jaal->config->get('httpd.keepalive.max')) ||
            strcasecmp($request->getHeader('Connection'), 'keep-alive') != 0
        )
        {
            $keepAlive = false;
        }

        if ($this->debug)
            $this->logger->log(-50, sprintf('%-25s' . $this->debugClientRequest($request), 'REQUEST-DONE[KA=' . ($keepAlive ? '1' : 0) . ']'));

        if (!$keepAlive)
            $request->getStream()->end();

        $request->cleanup();
        unset($request);
    }

    /**
     * After handling incoming client's request data, this method notifies to take action
     *
     * @param ClientRequestInterface $request
     * @param callable         $fallbackCallback when no listeners found, use this callback
     * @emit request.HOST:PORT
     */
    public function onClientRequestReadyForVhost(ClientRequestInterface $request, callable $fallbackCallback = null)
    {
        $emitEvent = 'request.' . $request->getHost() . ':' . $request->getPort();
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
    public function onProxy($vhostConfig, ClientRequestInterface $clientRequest)
    {
        $vhost = NULL;

        if (is_array($vhostConfig)) {
            $vhost = VhostFactory::create($this, $vhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(), $clientRequest->getPort());
        }
        else if ($vhostConfig instanceof Vhost) {
            $vhost = $vhostConfig;
        }

        $vhost->connectToUpstreamServer($clientRequest)->then(
            function ($info) use ($vhost, $clientRequest)
            {
                $status = $info['status'];
                $stream = $info['stream'];

                $this->upstreamRequestFactory($stream, $vhost, $clientRequest);

                if ($status == 'new')
                {

                    $this->upstreamRequestProcess($vhost);

                    $stream->on('data', function ($data) use ($stream)
                    {
                        $this->onUpstreamRequestData($stream, $data);
                    });
                }
            },
            function ($error) use ($clientRequest)
            {
                $clientRequest->error(500, '', true);
            }
        );
    }

    /**
     * Helper method for adding a new request property and setup various elements
     *
     * @param Stream                 $stream
     * @param Vhost                  $vhost
     * @param ClientRequestInterface $clientRequest
     *
     * @return \Hathoora\Jaal\Daemons\Http\Upstream\Request
     */
    protected function upstreamRequestFactory(Stream $stream, $vhost, ClientRequestInterface $clientRequest)
    {
        if ($this->debug)
            $this->logger->log(-50, sprintf('%-25s' . $this->debugClientRequest($clientRequest) . "\n" .
                                            "\t" . $this->debugVhost($vhost), 'UPSTREAM-REQ-NEW'));

        $upstreamRequest = new UpstreamRequest($this, $vhost, $clientRequest);
        $upstreamRequest->setStartTime()
                        ->setStream($stream)
                        ->setState(UpstreamRequestInterface::STATE_CONNECTED)
                        ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING);
        //->send();

        $stream->hits++;
        $stream->resource = $upstreamRequest->getUrl();
        $this->outboundIOManager->add($stream)
                                ->setProp($stream, 'request', $upstreamRequest)
            ->stats['hits']++;

        $vhost->getQueueRequests()->enqueue($upstreamRequest);

        return $upstreamRequest;
    }

    /**
     * Similar to onClientRequestData, this method handle's response data from upstream and makes sense of it
     *
     * @param Stream $stream
     * @param        $data
     */
    protected function onUpstreamRequestData(Stream $stream, $data)
    {
        /** @var $request UpstreamRequestInterface */
        if ($request = $this->outboundIOManager->getProp($stream, 'request'))
        {
            /**
             * $status values:
             * NULL     being processed
             * TRUE     when reached EOM
             * INT      when error code
             */
            $status = $request->onInboundData($data);

            if (is_int($status) && $request->getState() == UpstreamRequestInterface::STATE_ERROR) {
                $this->onUpstreamRequestEOM($request);
            }
            // response is ready (has reached EOM)
            else
            {
                // send response to client
                $request->getClientRequest()->onOutboundData($request->getParsingAttr('buffer'));
                $request->setParsingAttr('buffer', '');

                ## we reached EOM, lets be prepared to parse a new request on the same channel
                if ($status === true && $request->getStateParsing() == UpstreamRequestInterface::STATE_PARSING_EOM)
                {
                    $request->getClientRequest()->setExecutionTime()
                            ->setState(ClientRequestInterface::STATE_EOM)
                            ->hasBeenReplied();

                    $this->onUpstreamRequestEOM($request);
                }
            }
        }
        // upstream should handle only one request at a time
        // @TODO: how to handle requests in queue (of $vhost->getQueueRequests())?
        else
            $stream->end();
    }

    /**
     * Actions to take when we got entire message (reply) from the upstream server, at this point we are ready
     * to handle next request in queue of vhost (for upstream server)
     *
     * @param UpstreamRequestInterface $request
     */
    protected function onUpstreamRequestEOM(UpstreamRequestInterface $request)
    {
        // remove old processed requests..
        $this->outboundIOManager->removeProp($request->getStream(), 'request');
        $request->getStream()->resource = '';

        if ($this->debug)
            $this->logger->log(-50, sprintf('%-25s' . $this->debugUpstreamRequest($request), 'UPSTREAM-REQ-EOM'));

        $this->onUpstreamRequestDone($request);
    }

    /**
     * Actions to take when we got the reply from upstream server
     *
     * @param UpstreamRequestInterface $request
     * @param bool                     $closeStream
     */
    public function onUpstreamRequestDone(UpstreamRequestInterface $request, $closeStream = false)
    {
        $queue             = $request->getVhost()->getQueueRequests();
        $numQueuedRequests = $queue->count();
        $keepAlive         = true;

        if (
            $closeStream ||
            $request->getClientRequest()->getProtocolVersion() == '1.0' ||
            !$numQueuedRequests ||
            (!$request->getVhost()->config->get('upstreams.keepalive.max') && !$request->getVhost()->config->get('upstreams.keepalive.max'))
        )
        {
            $keepAlive = false;
        }

        if ($this->debug)
            $this->logger->log(-50, sprintf('%-25s' . $this->debugUpstreamRequest($request), 'UPSTREAM-REQ-DONE[KA=' . ($keepAlive ? '1' : 0) . ']'));

        if (!$keepAlive)
            $request->getStream()->end();

        $this->upstreamRequestProcess($request->getVhost());
        $request->cleanup();
        unset($request);
    }

    /**
     * Starts sending upstream request to upstream server
     *
     * @param $vhost
     */
    protected function upstreamRequestProcess($vhost)
    {
        if ($vhost->getQueueRequests()->count() && ($nextRequest = $vhost->getQueueRequests()->dequeue()))
            $nextRequest->send();
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

























    ####################################################################################################################
    ##
    ##                      Debugging..
    ##
    /**
     * Returns a format string about this request for debugging
     *
     * @param \Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request
     * @param string                                              $multilineAppender
     *
     * @return string
     */
    public function debugClientRequest(ClientRequestInterface $request, $multilineAppender = '')
    {
        return sprintf($request->getMethod() . ' HTTP ' . $request->getProtocolVersion() . ' ' . $request->getUrl() . ($request->getState() == ClientRequestInterface::STATE_DONE ? ' Took: ' . $request->getExecutionTime() . 'ms' : '') . "\n" .
                       $multilineAppender . "\t" . '%-15s [Connection: ' . ($request->getHeader('Connection') ? $request->getHeader('Connection') : '-') . '] HTTP ' . $request->getProtocolVersion() . "\n" .
                       $multilineAppender . "\t" . '%-15s [eom-strategy: ' . $request->getParsingAttr('methodEOM') . ', packet: ' . $request->getParsingAttr('packets') . ', ' .
                       'size: ' . $request->getParsingAttr('contentLength') . ', consumed: ' . $request->getParsingAttr('consumed') . ']' . "\n" .
                       $multilineAppender . "\t" . $this->debugStream($request->getStream()), 'Headers:', 'Parsing:');
    }

    /**
     * Returns a format string about this request for debugging
     *
     * @param \Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface $request
     * @param string                                                $multilineAppender
     *
     * @return string
     */
    public function debugUpstreamRequest(UpstreamRequestInterface $request, $multilineAppender = '')
    {
        return sprintf($request->getMethod() . ' HTTP ' . $request->getProtocolVersion() . ' ' . $request->getUrl() . ($request->getState() == ClientRequestInterface::STATE_DONE ? ' Took: ' . $request->getExecutionTime() . 'ms' : '') . "\n" .
                       $multilineAppender . "\t" . 'Headers: [Connection: ' . ($request->getHeader('Connection') ? $request->getHeader('Connection') : '-') . '] HTTP ' . $request->getProtocolVersion() . "\n" .
                       $multilineAppender . "\t" . 'Parsing: [eom-strategy: ' . $request->getParsingAttr('methodEOM') . ', packet: ' . $request->getParsingAttr('packets') . ', ' .
                       'size: ' . $request->getParsingAttr('contentLength') . ', consumed: ' . $request->getParsingAttr('consumed') . ']' . "\n" .
                       $multilineAppender . "\t" . $this->debugStream($request->getStream()));
    }

    /**
     * Returns a format string about this stream
     *
     * @param $stream Stream|ConnectionInterface
     *
     * @return string
     */
    public function debugStream($stream)
    {
        return sprintf('%-15s ' . $stream->id . ', [local: ' . $stream->id . ', remote: ' . $stream->remoteId . ', hits: ' . $stream->hits . ', ' . 'connect-time: ' . Time::millitimeDiff($stream->millitime) . 'ms, ' .
                       'idle-time: ' . Time::millitimeDiff($stream->lastActivity) . 'ms, resource: ' . $stream->resource . ']', 'Stream:');
    }

    /**
     * @param \Hathoora\Jaal\Daemons\Http\Vhost\Vhost $vhost
     *
     * @return string
     */
    public function debugVhost(Vhost $vhost)
    {
        return sprintf('%-15s ' . $vhost->id, 'Vhost:');
    }
}