<?php

namespace Attozk\Jaal\Httpd\Message;

use Attozk\Jaal\Upstream\PoolInterface;
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
     * @param PoolInterface $pool
     * @param RequestInterface $request
     * @param ConnectorInterface $connector
     * @param array $arrOptions
     */
    public function __construct(PoolInterface $pool, RequestInterface $request, ConnectorInterface $connector, $arrOptions = array())
    {
        $this->setStartTime();
        $this->pool = $pool;
        $this->setClientRequest($request);
        $this->upstreamSocket = $connector;

        $arrRequestHeaders = $request->getHeaders();

        // headers passed to proxy
        $arrProxySetHeaders = isset($arrOptions['proxy_set_header']) && is_array($arrOptions['proxy_set_header']) ? $arrOptions['proxy_set_header'] : array();

        // add headers to response (i..e sent to the client)
        $arrAddHeaders = isset($arrOptions['add_header']) && is_array($arrOptions['add_header']) ? $arrOptions['add_header'] : array();

        // headers not passed from proxy server to client
        $arrProxyHideHeaders = isset($arrOptions['proxy_hide_header']) && is_array($arrOptions['proxy_hide_header']) ? $arrOptions['proxy_hide_header'] : array();


        $arrInHeaders = array(
            'Accept' => 1,
            'Accept-Language' => 1,
            'Accept-Charset' => 1,
            'Accept-Encoding' => 1,
            'Cache-Control' => 1,
            'Cookie' => 1,
            'Content-Length' => 1,
            'Content-Type' => 1,
            'Host' => 1,
            'If-Match' => 1,
            'If-Modified-Since' => 1,
            'User-Agent' => 1
        );

        parent::__construct($request->getMethod(), $request->getUrl());

        if (isset($arrOptions['http_version'])) {
            $this->setProtocolVersion($arrOptions['http_version']);
        }

        // proxy_set_header
        foreach($arrProxySetHeaders as $header => $value)
        {
            $this->setHeader($header, $value);
        }

        // copy headers from original request to upstream request
        foreach($arrRequestHeaders as $header => $value)
        {
            $header = strtoupper($header);
            if (isset($arrInHeaders[$header]) && !$this->hasHeader($header))
                    $this->setHeader($header, $this->getHeader($header));
        }
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
            function($stream) use($deferred) {

                $this->setUpstreamStream($stream);
                $deferred->resolve($this);
            },
            // @TODO handle error
            function() use($deferred)
            {
                $deferred->reject();
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
        $this->getClientRequest()->getClientSocket()->write('HTTP/1.1 200 OK
Date: Thu, 23 Oct 2014 05:31:28 GMT
Server: Apache/2.2.15 (CentOS)
Last-Modified: Tue, 08 Jul 2014 02:21:52 GMT
ETag: "202e6-7b-4fda54130f000"
Accept-Ranges: bytes
Content-Length: 0
Vary: Accept-Encoding,User-Agent
Content-Type: text/plain; charset=utf-8');

        $this->getClientRequest()->getClientSocket()->end();
        return;
        $this->connectUpstreamSocket()->then(
            function(RequestUpstreamInterface $request)
            {
                $stream = $request->getUpstreamStream();
                $stream->write($this->getRawHeaders() . "\r\n\r\n");

                /*echo "\n---------------------Header---------------------\n";
                echo htmlspecialchars($this->getRawHeaders());
                echo "\n---------------------/Header---------------------\n";
                */

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

                    if ($compression) {
                        if ($compression == 'gzip') {
                            $data = gzdecode($data);
                        }
                    }

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
                        /*echo "--------------------CLIENT----------------------\n";
                        echo $headers . $bodyBuffer;
                        echo "\n--------------------/CLIENT----------------------\n";*/
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