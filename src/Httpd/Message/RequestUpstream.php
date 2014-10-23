<?php

namespace Attozk\Jaal\Httpd\Message;

use Attozk\Jaal\Logger;
use Attozk\Jaal\Upstream\PoolHttpd;
use React\Promise\Deferred;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;

class RequestUpstream extends Request implements RequestUpstreamInterface
{
    private $jStartMTime;         // request start micro time
    private $jExecTime;           // request execution time im milliseconds

    /**
     * @var Request
     */
    private $clientRequest;

    /** @var \React\SocketClient\ConnectorInterface */
    protected $upstreamSocket;

    /** @var \React\Stream\Stream */
    protected $upstreamStream;

    /**
     * @param PoolHttpd $pool
     * @param RequestInterface $request
     * @param ConnectorInterface $connector
     */
    public function __construct(PoolHttpd $pool, RequestInterface $request, ConnectorInterface $connector)
    {
        $this->setStartTime();
        $this->pool = $pool;
        $this->setClientRequest($request);
        $this->upstreamSocket = $connector;

        parent::__construct($request->getMethod(), $request->getUrl());

        $this->pool->prepareUpstreamRequestHeaders($this);

        Logger::getInstance()->debug($this->getClientRequest()->getClientSocket()->getRemoteAddress() . ' ' .
            $this->getClientRequest()->getMethod() . ' ' . $this->getClientRequest()->getUrl() . ' >> UPSTREAM >> ' .
            $this->getUrl());
    }

    /**
     * @param Request $request
     */
    public function setClientRequest(Request $request)
    {
        $this->clientRequest = $request;
    }

    /**
     * @return Request
     */
    public function getClientRequest()
    {
        return $this->clientRequest;
    }

    public function setUpstreamSocket(ConnectorInterface $connector)
    {
        $this->upstreamSocket = $connector;

        return $this;
    }

    public function setUpstreamStream(Stream $stream)
    {
        $this->upstreamStream = $stream;
    }

    public function getUpstreamStream()
    {
        return $this->upstreamStream;
    }

    /**
     * Returns upstream socket
     *
     * @return ConnectorInterface $connector
     */
    public function getUpstreamSocket()
    {
        return $this->$upstreamSocket;
    }

    /**
     * Opens upstream server
     * @return \React\Promise\Promise
     */
    public function connectUpstreamSocket()
    {
        $deferred = new Deferred();
        $arrServer = $this->pool->getServer($this->clientRequest);
        $promise = $deferred->promise();

        $this->upstreamSocket->create($arrServer['ip'], $arrServer['port'])->then(
            function($stream) use($deferred, $arrServer) {

                Logger::getInstance()->debug('Upstream connected to ' . $arrServer['ip'] . ':' . $arrServer['port']);
                $this->setUpstreamStream($stream);
                $deferred->resolve($this);
            },
            // @TODO handle error
            function() use($deferred, $arrServer)
            {
                $deferred->reject();

                Logger::getInstance()->debug('Upstream connection error to ' . $arrServer['ip'] . ':' . $arrServer['port']);
            }
        );

        return $promise;
    }

    /**
     * Communicates with upstream server and return response to client
     *
     * @void
     */
    public function send()
    {
        $this->connectUpstreamSocket()->then(
            function(RequestUpstreamInterface $request)
            {
                Logger::getInstance()->debug('Writing to upstream...');

                $stream = $request->getUpstreamStream();
                $stream->write($this->getRawHeaders() . "\r\n\r\n");

                #echo "\n---------------------Header---------------------\n";
                #echo htmlspecialchars($this->getRawHeaders());
                #echo "\n---------------------/Header---------------------\n";


                $consumed = 0;
                $bodyLength = 0;
                $headers = $bodyBuffer = $compression = '';
                $hasError = false;
                $stream->on('data', function($data) use($request, $stream, &$bodyLength, &$consumed, &$compression, &$headers, &$bodyBuffer, &$hasError) {


                    /*echo "\n---------------------Before Data---------------------\n";
                    echo $data . "\n\n";
                    echo "\n---------------------/Data---------------------\n";
                    */

                    if (!$bodyLength) {
                        $response = \Attozk\Jaal\Httpd\Message\Response::fromMessage($data);
                        if ($response) {
                            $bodyLength = (int)(string)$response->getHeader('Content-Length');

                            if ($response->hasHeader('Content-Encoding')) {
                                $compression = $response->getHeader('Content-Encoding');

                                $data = $response->getBody();
                                $headers = $response->getRawHeaders();
                            }
                        }
                        else
                            $hasError = true;
                    }

                    $consumed += strlen($data);

                    /*
                    if ($compression) {
                        if ($compression == 'gzip') {
                            $data = gzdecode($data);
                        }
                    }*/

                    $bodyBuffer .= $data;

                    /*
                    echo "\n---------------------After Data---------------------\n";
                    //echo $this->hex_dump($data). "\n\n";
                    echo $data . "\n\n";
                    echo "\n---------------------/Data---------------------\n";

                    echo "---> $bodyLength <-> $consumed \n";
                    */

                    if ($consumed >= $bodyLength)
                    {
                        //$response = \Attozk\Jaal\Httpd\Message\Response::fromMessage($data);
                        $request->setExecutionTime();
                        $request->getClientRequest()->getClientSocket()
                                ->write($headers . $bodyBuffer);

                        $request->getClientRequest()->getClientSocket()->end();
                        $stream->end();
                        #echo "--------------------CLIENT----------------------\n";
                        #echo $headers . $bodyBuffer;
                        #echo "\n--------------------/CLIENT----------------------\n";
                    }

                    /*
                     *
                    // KEEP ALIVE
                    $request->setExecutionTime();

                    $response->addHeader('Jaal-Exectime', $request->getExecutionTime());
                    $request->setResponse($response);

                    $request->getClientRequest()->getClientSocket()
                            ->write($response->getRawHeaders() . $response->getBody());

                    */
                });
            },
            // @TODO handle error
            function($error) {
                echo "Unable to connec... \n";
            }
        );
    }

    function hex_dump($data, $newline="\n")
{
  static $from = '';
  static $to = '';

  static $width = 16; # number of bytes per line

  static $pad = '.'; # padding for non-visible characters

  if ($from==='')
  {
    for ($i=0; $i<=0xFF; $i++)
    {
      $from .= chr($i);
      $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
    }
  }

  $hex = str_split(bin2hex($data), $width*2);
  $chars = str_split(strtr($data, $from, $to), $width);

  $offset = 0;
  foreach ($hex as $i => $line)
  {
    echo sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2)) . ' [' . $chars[$i] . ']' . $newline;
    $offset += $width;
  }
}
}