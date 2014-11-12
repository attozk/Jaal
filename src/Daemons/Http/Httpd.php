<?php

namespace Hathoora\Jaal\Daemons\Http;

use Evenement\EventEmitter;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Client\Request as ClientRequest;
use Hathoora\Jaal\Daemons\Http\Upstream\Request as UpstreamRequest;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Factory as VhostFactory;
use Hathoora\Jaal\Daemons\Http\Vhost\Vhost;
Use Hathoora\Jaal\IO\Manager\InboundManager;
use Hathoora\Jaal\IO\Manager\OutboundManager;
use Hathoora\Jaal\IO\React\Socket\Connection;
use Hathoora\Jaal\IO\React\Socket\ConnectionInterface;
use Hathoora\Jaal\IO\React\Socket\Server as SocketServer;
use Hathoora\Jaal\IO\React\SocketClient\Stream;
use Hathoora\Jaal\Jaal;
use Hathoora\Jaal\Util\Time;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

/**
 * Class Httpd
 *
 * @emit    request.HOST:PORT [$request] for request to be handles by vhost
 *
 * @package Hathoora\Jaal\Daemons\Http
 */
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
     * This method create a new client request and updates $inboundIOManager registry and attaches listeners
     *
     * @param ConnectionInterface|Connection $client
     *
     * @return \Hathoora\Jaal\Daemons\Http\Client\Request
     */
    protected function clientRequestFactory(ConnectionInterface $client)
    {
        /**
         * This is a empty request and has not yet been been parsed/filled with the actual client's request
         */
        $request = new ClientRequest();
        $request->setStartTime()
                ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING)
                ->setStream($client);

        /**
         * We add this client to Inbound registry and associate a new request to client's connection.
         * The purpose of this association is to ensure that a client finishes sending a complete request, before
         * sending another one. If a client does a such thing, we terminate their connection.
         *
         * Af the end of a request's EOM, we remove this association (of connection-to-request)
         */
        $this->inboundIOManager->add($client)
            ->setProp($client, 'request', $request);

        /**
         * In case of error's during parsing and/or other things, we need to close connection even if it is a
         * keep-alive session.
         *
         * We also need to make sure that we perform EOM operations as an error can occur without reaching EOM state
         * and that corrupt connection-to-request association
         */
        $request->once('inbound.error', function ($request, $code)
        {
            $this->onClientRequestEOM($request);
            $request->error($code, '', true);
        });

        /**
         * While the request is buffering, we want to update some stats used in admin, such as: connection hits,
         * resource etc.
         *
         * After the first packet has been received we are finally able to parse the message: HEADERS, PROTOCOL,
         * METHOD, URL and BODY. However if BODY > buffer limit (of \Hathoora\Jaal\IO\React\Socket\Connection) then
         * we need to keep on reading until we have reached EOM (at which point it emits "eom").
         *
         * At this point we also emit request's readiness to be handled by proxy server. This behavior may needs to
         * be revisited because as a comparison Nginx would keep client_body_buffer_size in memory if the size is
         * greater than that it would write the request's data to local disk.
         *
         * @see http://nginx.org/en/docs/http/ngx_http_core_module.html#client_body_buffer_size
         * @see http://forum.nginx.org/read.php?15,254617
         */
        $request->on('inbound.buffering', function ($request, $buffer) use ($client)
        {
            $readyForVhost = false;

            /** @var $request ClientRequest */
            if ($request->getParsingAttr('packets') == 1)
            {
                $client->hits++;
                $client->resource = $request->getUrl();
                $this->inboundIOManager->stats['hits']++;
                $readyForVhost = true;
            }

            if ($this->debug)
                $this->logger->log(-50, sprintf('%-25s' . $this->debugClientRequest($request), 'REQUEST-NEW'));

            // do this after debugging log
            if ($readyForVhost)
                $this->onClientRequestReadyForVhost($request);
        });

        /**
         * This is where client has finishing sending the request and it has been parsed
         */
        $request->once('inbound.eom', function ($request)
        {
            $this->onClientRequestEOM($request);
        });

        /**
         * A response has been sent to the client, we need to either close the connection or keep it alive
         */
        $request->once('done', function ($request, $closeStream)
        {
            $this->onClientRequestDone($request, $closeStream);
        });

        return $request;
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
        if ($request = $this->inboundIOManager->getProp($client, 'request'))
            $request->onInboundData($data);
        // a client cannot send another request until it has finished sending the first one
        // @TODO: throw friendly (4XX) error?
        else
            $client->end();
    }

    /**
     * Actions to take when we client's request has been parsed and reached EOM
     *
     * @param ClientRequestInterface $request
     */
    protected function onClientRequestEOM(ClientRequestInterface $request)
    {
        $this->inboundIOManager->removeProp($request->getStream(), 'request');
        $this->clientRequestFactory($request->getStream());

        if ($this->debug)
            $this->logger->log(-50, sprintf('%-25s' . $this->debugClientRequest($request), 'REQUEST-EOM'));
    }

    /**
     * Actions to take when we client has received all the data (from upstream/docroot)
     * We need to close the connection or keep it open depending upon the following:
     *
     * Close when:
     *      $closeStream == true
     *      httpd.keepalive.timeout && httpd.keepalive.max not defined
     *      $request is 1.0 which does not support http keep alive
     *      $request's Connection header != keep-alive
     *
     * @param ClientRequestInterface $request
     * @param bool                   $closeStream
     */
    protected function onClientRequestDone(ClientRequestInterface $request, $closeStream = false)
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
     * Request is ready to be handled by vhost configuration, it does not mean it can be send to upstream server
     * for that upstream.ready should be using (from $upstreamRequest0
     *
     * @param ClientRequestInterface $request
     *
     * @emit request.HOST:PORT [$request] for request to be handles by vhost
     */
    protected function onClientRequestReadyForVhost(ClientRequestInterface $request)
    {
        $emitEvent = $this->getVhostEmitName($request);
        $this->emit($emitEvent, [$request]);
    }

    /**
     * @param \Hathoora\Jaal\Daemons\Http\Client\RequestInterface $request
     *
     * @return string
     */
    protected function getVhostEmitName(ClientRequestInterface $request)
    {
        return 'request.' . $request->getHost() . ':' . $request->getPort();
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
        $vhost = null;

        if (is_array($vhostConfig))
            $vhost = VhostFactory::create($this, $vhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(), $clientRequest->getPort());
        else if ($vhostConfig instanceof Vhost)
            $vhost = $vhostConfig;

        // vhost has established a new connection for $stream
        $vhost->on('connection', function ($stream, $poolKey) use ($vhost, $clientRequest)
        {
            $stream->on('data', function ($data) use ($stream)
            {
                $this->onUpstreamRequestData($stream, $data);
            });
        });

        $vhost->connectToUpstreamServer($clientRequest)->then(
            function ($stream) use ($vhost, $clientRequest)
            {
                $this->upstreamRequestFactory($stream, $vhost, $clientRequest);
            },
            function ($error) use ($clientRequest)
            {
                $clientRequest->error(500, '', true);
            }
        );
   }

    /**
     * This method create a new upstream request and updates $outboundIOManager registry and attaches listeners
     *
     * @param \Hathoora\Jaal\IO\React\SocketClient\Stream         $stream
     * @param                                                     $vhost
     * @param \Hathoora\Jaal\Daemons\Http\Client\RequestInterface $clientRequest
     *
     * @return \Hathoora\Jaal\Daemons\Http\Upstream\Request
     */
    protected function upstreamRequestFactory(Stream $stream, $vhost, ClientRequestInterface $clientRequest)
    {
        if ($this->debug)
            $this->logger->log(-50, sprintf('%-25s' . $this->debugClientRequest($clientRequest) . "\n" .
                                            "\t" . $this->debugVhost($vhost), 'UPSTREAM-REQ-NEW'));

        $request = new UpstreamRequest($vhost, $clientRequest);
        $request->setStartTime()
                ->setStream($stream)
                ->setState(UpstreamRequestInterface::STATE_CONNECTED)
                ->setStateParsing(ClientRequestInterface::STATE_PARSING_PENDING);

        /**
         * We add this stream to Outbound registry and associate this upstream-request to upstreams's connection.
         */
        $this->outboundIOManager->add($stream)
            ->setProp($stream, 'request', $request);
        $vhost->getQueueRequests()->enqueue($request);

        // ready to process upstream requests
        $request->once('upstream.ready', function ($request)
        {
            $request->send();
        });

        $request->once('inbound.error', function ($request, $code)
        {
            $this->onUpstreamRequestEOM($request);
        });

        $request->on('inbound.buffering', function ($request, $data)
        {
            /** @var $request ClientRequest */
            if ($request->getParsingAttr('packets') == 1)
            {
                $request->getStream()->hits++;
                $request->getStream()->resource = $request->getUrl();
                $this->outboundIOManager->stats['hits']++;
            }

            if ($this->debug)
            {
                $this->logger->log(-50, sprintf('%-25s' . $request->getMethod() . ' ' . $request->getUrl() . ' ' . "\n" .
                                                "\t" . 'Client-Request: ' . $this->debugClientRequest($request->getClientRequest(), "\t") . "\n" .
                                                "\t" . 'Upstream-Request: ' . $this->debugUpstreamRequest($request, "\t"), 'UPSTREAM-REQ-BUFFERING'));
            }

            $request->getClientRequest()->onOutboundData($data);
        });

        $request->once('inbound.eom', function ($request)
        {
            $this->onUpstreamRequestEOM($request);
        });

        return $request;
    }

    /**
     * Similar to onClientRequestData, this method handle's response data from upstream and makes sense
     * of it
     *
     * @param Stream $stream
     * @param        $data
     */
    protected function onUpstreamRequestData(Stream $stream, $data)
    {
        /** @var $request UpstreamRequest */
        if ($request = $this->outboundIOManager->getProp($stream, 'request'))
            $request->onInboundData($data);
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
     * Upstream server is ready to handle next request in queue or close upstream connection
     *
     * Close when:
     *      $closeStream == true
     *      Upstream request was made using HTTP 1.0 protocol
     *      No more requests in queue ($request->getVhost()->getQueueRequests()->count == 0)
     *      Vhost's configs doest not have upstreams.keepalive.timeout & upstreams.keepalive.max
     *
     * @param UpstreamRequestInterface $request
     * @param bool                     $closeStream
     */
    protected function onUpstreamRequestDone(UpstreamRequestInterface $request, $closeStream = false)
    {
        $queue     = $request->getVhost()->getQueueRequests();
        $numQueuedRequests = $queue->count();
        $keepAlive = true;

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

        $request->getVhost()->readyForNextQueuedRequest();



        #if ($upstreamRequest = $request->getVhost()->readyForNextQueuedRequest())
        #    $upstreamRequest->send();

        echo "------------->VHOST " . count($request->getVhost()->listeners) . " LISTENERS \n";

        $request->cleanup();

        echo "------------->UPSTREAM " . count($request->listeners) . " LISTENERS \n";
        unset($request);

        memprof_dump_callgrind(fopen("//media/sf_www/hathoora/Jaal/callgrind.out", "w"));
        die;
    }

    /**
     * get pretty stats
     */
    public function stats()
    {
        return ['inbound' => $this->inboundIOManager->stats(), 'outbound' => $this->outboundIOManager->stats()];
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

/* Keep ALIVE
if (!$this->inboundIOManager->getProp($client, 'timerTimeout'))
{
    $timeout = Jaal::getInstance()->config->get('httpd.timeout');

    $timerTimeout = $this->loop->addPeriodicTimer($timeout, function () use ($client) {

        Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id, 'green') .
            ' connection timeout from Inbound Manager, hits: ' . $stream->hits .
            ', connection time: ' . Time::millitimeDiff($stream->millitime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));
    }
});
$this->setProp($stream, 'timerTimeout', $timerTimeout);

}

$keepaliveTimeout = Jaal::getInstance()->config->get('httpd.keepalive.timeout');



if ($timeout && $keepaliveTimeout && ($keepaliveTimeout * 1.5) < $timeout) {
    $timerKeepaliveTimeout = $this->loop->addPeriodicTimer($keepaliveTimeout, function () use ($stream) {
        if ($request = $this->getProp($stream, 'request')) {
            if (($timerKeepaliveTimeout = $this->getProp($stream, 'timerKeepaliveTimeout')) &&
                $timerKeepaliveTimeout instanceof TimerInterface)
            {
                $this->loop->cancelTimer($timerKeepaliveTimeout);
                $this->removeProp($stream, 'timerKeepaliveTimeout');
            }

            Logger::getInstance()->log(-99, Logger::getInstance()->color($stream->id, 'green') . ' keep-alive timeout from Inbound Manager, hits: ' .
                $stream->hits . ', connection time: ' . Time::millitimeDiff($stream->millitime) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']', 'lightCyan'));


            $stream->end();
        }
    });
    $this->setProp($stream, 'timerKeepaliveTimeout', $timerKeepaliveTimeout);
}
*/
