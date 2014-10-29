<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

use Hathoora\Jaal\Util\Time;

class Request extends Message implements RequestInterface
{
    public $id;
    protected $militime;
    protected $took;        // took milli seconds to execute
    protected $urlParts = array();
    protected $state;  // 'Ready', 'Reading'

    public function __construct($method, $url, $headers = array())
    {
        $this->setMethod($method);
        $this->setUrl($url);
        $this->setRequestId();
        $this->setHeaders($headers);
    }

    /**
     * Sets a unique request id
     *
     * @return self
     */
    protected function setRequestId()
    {
        $this->id = uniqid('Request_');

        return $this;
    }

    public function setState($state = null) {

        if (!$state) {

            $state = self::STATE_PENDING;
            $EOM = $this->getEOMType();

            if (!$EOM && $this->method == 'GET' && count($this->headers))
                $state = self::STATE_DONE;

            // content length
            else if ($EOM == 'length' && $this->method != 'GET' && strlen($this->body) == $this->getSize()) {
                $state = self::STATE_DONE;
            }

            // @TODO handle chunked
            else if ($EOM == 'chunked') {

            }
        }

        $this->state = $state;

        return $this;
    }

    public function getState() {
        return $this->state;
    }

    public function getHeaderLines()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            if (is_array($value))
            {
                foreach($value as $v) {
                    $headers[] = $key . ': ' . $v;
                }
            }
            else {
                $headers[] = $key . ': ' . $value;
            }
        }

        return $headers;
    }

    public function getRawHeaders()
    {
        $protocolVersion = $this->protocolVersion ?: '1.1';

        return trim($this->method . ' ' . $this->getResource()) . ' '
        . strtoupper(str_replace('https', 'http', $this->getScheme()))
        . '/' . $protocolVersion . "\r\n" . implode("\r\n", $this->getHeaderLines());
    }

    /**
     * Sets start time of request in miliseconds
     *
     * @param null|int $miliseconds
     * @return self
     */
    public function setStartTime($miliseconds = null)
    {
        if (!$miliseconds) {
            $miliseconds = Time::millitime();
        }

        $this->militime = $miliseconds;

        return $this;
    }

    /**
     * Sets execution time of request in miliseconds
     *
     * @return self
     */
    public function setExecutionTime()
    {
        if (!$this->took) {
            //$this->took = Time::
        }

        return $this;
    }

    /**
     * Gets execution time of request in miliseconds
     */
    public function getExecutionTime()
    {
        return $this->took;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        // TODO: Implement __toString() method.
    }

    /**
     * Set the URL of the request
     *
     * @param string $url Full URL to set including query string
     *
     * @return self
     */
    public function setUrl($url)
    {
        $this->urlParts = parse_url($url);

        return $this;
    }

    /**
     * Get the full URL of the request (e.g. 'http://www.guzzle-project.com/')
     *
     * @return string
     */
    public function getUrl()
    {
        $scheme   = $this->getScheme();
        $host     = $this->getHost();
        $port     = $this->getPort();
        #$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        #$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        #$pass     = ($user || $pass) ? "$pass@" : '';
        $path     = $this->getPath();
        $query    = !empty($this->urlParts['query']) ? '?' . $this->urlParts['query'] : '';
        $fragment = !empty($this->urlParts['fragment']) ? '#' . $this->urlParts['fragment'] : '';

        if ($scheme == 'http' && $port != 80)
            $port = ':' . $port;
        else if ($scheme == 'https' && $port != 443)
            $port = ':' . $port;

        return "$scheme://$host$port$path$query$fragment";
    }

    public function getResource()
    {
        $path     = $this->getPath();
        $query    = isset($this->urlParts['query']) ? '?' . $this->urlParts['query'] : '';

        return $path . $query;
    }

    public function setQuery($str)
    {
        $this->urlParts['query'] = $str;

        return $this;
    }

    public function getQuery()
    {
        return isset($this->urlParts['query']) ? $this->urlParts['query'] : null;
    }

    public function setMethod($method) {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Get the HTTP method of the request
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get the URI scheme of the request (http, https, ftp, etc)
     *
     * @return string
     */
    public function getScheme()
    {
        return isset($this->urlParts['scheme']) ? $this->urlParts['scheme'] : null;
    }

    /**
     * Set the URI scheme of the request (http, https, ftp, etc)
     *
     * @param string $scheme Scheme to set
     *
     * @return self
     */
    public function setScheme($scheme)
    {
        $this->urlParts['scheme'] = $scheme;

        return $this;
    }

    /**
     * Get the host of the request
     *
     * @return string
     */
    public function getHost()
    {
        return isset($this->urlParts['host']) ? $this->urlParts['host'] : null;
    }

    /**
     * Set the host of the request. Including a port in the host will modify the port of the request.
     *
     * @param string $host Host to set (e.g. www.yahoo.com, www.yahoo.com:80)
     *
     * @return self
     */
    public function setHost($host)
    {
        $this->urlParts['host'] = $host;

        return $this;
    }

    /**
     * Get the path of the request (e.g. '/', '/index.html')
     *
     * @return string
     */
    public function getPath()
    {
        return isset($this->urlParts['path']) ? $this->urlParts['path'] : null;
    }

    /**
     * Set the path of the request (e.g. '/', '/index.html')
     *
     * @param string|array $path Path to set or array of segments to implode
     *
     * @return self
     */
    public function setPath($path)
    {
        $this->urlParts['path'] = $path;

        return $this;
    }

    /**
     * Get the port that the request will be sent on if it has been set
     *
     * @return int|null
     */
    public function getPort()
    {
        return isset($this->urlParts['port']) ? $this->urlParts['port'] : null;
    }

    /**
     * Set the port that the request will be sent on
     *
     * @param int $port Port number to set
     *
     * @return self
     */
    public function setPort($port)
    {
        $this->urlParts['port'] = $port;

        return $this;
    }

    /**
     * Get the HTTP protocol version of the request
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * Set the HTTP protocol version of the request (e.g. 1.1 or 1.0)
     *
     * @param string $protocol HTTP protocol version to use with the request
     *
     * @return self
     */
    public function setProtocolVersion($protocol)
    {
        $this->protocolVersion = $protocol;

        return $this;
    }

    public function isValid()
    {
        $valid = true;

        if ($this->body && !$this->hasHeader('Content-Length'))
            $valid = 400;

        return $valid;
    }
}