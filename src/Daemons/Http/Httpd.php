<?php

namespace Hathoora\Jaal\Daemons\Http;

use Hathoora\Jaal\Daemons\Http\Message\Parser;
use Hathoora\Jaal\Daemons\Http\Client\RequestInterface as ClientRequestInterface;
use Hathoora\Jaal\Daemons\Http\Message\RequestInterface;
use Hathoora\Jaal\Daemons\Http\Upstream\Request as UpstreamRequest;
use Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface as UpstreamRequestInterface;
use Hathoora\Jaal\Daemons\Http\Vhost\Factory as VhostFactory;
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
                        $this->handleClientRequestData($client, $data);
                    });
                }
            );
        });
    }

    /**
     * @param ConnectionInterface $client
     * @param $data
     */
    protected function handleClientRequestData(ConnectionInterface $client, $data)
    {
        $request = null;

        if (!$this->inboundIOManager->getProp($client, 'request')) {

            /** @var $request \Hathoora\Jaal\Daemons\Http\Client\RequestInterface */
            $request = Parser::getClientRequest($data);
            $request->setStartTime()
                ->setStream($client);

            Logger::getInstance()->log(-50,
                'REQUEST ' . $request->getMethod() . ' ' . Logger::getInstance()->color($request->getUrl(),
                    'red') . ' ' . $request->getHost() . ' using stream: ' . Logger::getInstance()->color($client->id,
                    'green') . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'yellow'));

            $client->resource = $request->getUrl();
            $client->hits++;

            if ($client->hits > 1) {
                Logger::getInstance()->log(-99,
                    Logger::getInstance()->color($client->id, 'green') . ' keep-alive requested '. $request->getUrl() .', hits: ' . $client->hits . ', idle: ' . Time::millitimeDiff($this->inboundIOManager->getProp($client,
                        'lastActivity')) . ' ms ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                        'lightCyan'));
            }

            $this->inboundIOManager->setProp($client, 'request', $request);

        } else {
            /** @var $request \Hathoora\Jaal\Daemons\Http\Client\RequestInterface */
            if ($request = $request = $this->inboundIOManager->getProp($client, 'request')) {
                $request->handleData($client, $data);
            }
        }


        if ($request && $request->isValid() === true) {

            $emitVhostKey = 'client.request.' . $request->getHost() . ':' . $request->getPort();

            if (count($this->listeners($emitVhostKey))) {
                $this->emit($emitVhostKey, [$request]);
            } else {
                $this->emit('client.request' . ':' . $request->getPort(), [$request]);
            }
        }

        if (!$request) {

            Logger::getInstance()->log('ERROR', 'Unable to handle client request.');
            $client->end();
        }
    }

    public function proxy($arrVhostConfig, ClientRequestInterface $clientRequest)
    {
        static $i = 0;
        $i++;
        Logger::getInstance()->log(-50,
            'PROXY ' . $clientRequest->getMethod() . ' ' . Logger::getInstance()->color($clientRequest->getUrl(),
                'red') . ' ' . $clientRequest->getHost() . ' using stream: ' . Logger::getInstance()->color($clientRequest->getStream()->id,
                'green') . ' ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                'yellow'));

        $vhost = VhostFactory::create($arrVhostConfig, $clientRequest->getScheme(), $clientRequest->getHost(),
            $clientRequest->getPort());
        $arrUpstreamConfig = $vhost->getUpstreamConnectorConfig();

        $ip = $arrUpstreamConfig['ip'];
        $port = $arrUpstreamConfig['port'];
        $keepalive = $arrUpstreamConfig['keepalive'];
        $timeout = $arrUpstreamConfig['timeout'];

        $upstreamRequest = new UpstreamRequest($vhost, $clientRequest);
        $upstreamRequest->setBody($clientRequest->getBody());

        $this->outboundIOManager->buildConnector($ip, $port, $keepalive, $timeout)->then(

            function (Stream $stream) use ($upstreamRequest, $i) {

                $this->outboundIOManager->setProp($stream, 'request', $upstreamRequest);
                $stream->hits++;
                $stream->resource = $upstreamRequest->getUrl();

                $upstreamRequest->setStartTime()
                    ->setStream($stream)
                    ->setState(RequestInterface::STATE_CONNECTING)
                    ->send();

                $stream->on('data', function ($data) use ($stream, $upstreamRequest) {

                    #if (!$this->outboundIOManager->getProp($stream, 'request'))
                    #{
                    #    $this->outboundIOManager->setProp($stream, 'request', $upstreamRequest);
                    #}

                    $this->handleUpstreamRequestData($stream, $data);
                });
            }, function($error) {

                die("ERROR buildConnector....");
            }
        );
    }

    protected function handleUpstreamRequestData(Stream $stream, $data)
    {
        /** @var $request \Hathoora\Jaal\Daemons\Http\Upstream\RequestInterface */
        if ($request = $this->outboundIOManager->getProp($stream, 'request')) {
            $request->handleData($stream, $data);
        } else {

            die('handleUpstreamRequestData error');
            //echo "---------------------------------------\n". $data . "---------------------------------------\n";

            Logger::getInstance()->log(-99,
                'ERROR  handleUpstreamRequestData stream: ' . Logger::getInstance()->color($stream->id,
                    'green') . ' has no request ' . Logger::getInstance()->color('[' . __METHOD__ . ']',
                    'yellow'));
            $stream->end();

        }
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