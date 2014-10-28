<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

interface RequestInterface extends MessageInterface
{
    /**
     * Sets a unique request id
     *
     * @return self
     */
    public function setRequestId();

    /**
     * Gets the unique request id
     *
     * @return mixed
     */
    public function getRequestId();

    public function setState($state);
    public function getState();
    public function getHeaderLines();
    public function getRawHeaders();

    /**
     * Sets start time of request in miliseconds
     *
     * @param null|int $miliseconds
     * @return self
     */
    public function setStartTime($miliseconds = null);

    /**
     * Sets execution time of request in miliseconds
     *
     * @return self
     */
    public function setExecutionTime();

    /**
     * Gets execution time of request in miliseconds
     */
    public function getExecutionTime();

    /**
     * @return string
     */
    public function __toString();

    /**
     * Set the URL of the request
     *
     * @param string $url Full URL to set including query string
     *
     * @return self
     */
    public function setUrl($url);

    /**
     * Get the full URL of the request (e.g. 'http://www.guzzle-project.com/')
     *
     * @return string
     */
    public function getUrl();

    public function getResource();

    public function setQuery($str);

    public function getQuery();


    public function setMethod($method);

    /**
     * Get the HTTP method of the request
     *
     * @return string
     */
    public function getMethod();

    /**
     * Get the URI scheme of the request (http, https, ftp, etc)
     *
     * @return string
     */
    public function getScheme();

    /**
     * Set the URI scheme of the request (http, https, ftp, etc)
     *
     * @param string $scheme Scheme to set
     *
     * @return self
     */
    public function setScheme($scheme);

    /**
     * Get the host of the request
     *
     * @return string
     */
    public function getHost();

    /**
     * Set the host of the request. Including a port in the host will modify the port of the request.
     *
     * @param string $host Host to set (e.g. www.yahoo.com, www.yahoo.com:80)
     *
     * @return self
     */
    public function setHost($host);

    /**
     * Get the path of the request (e.g. '/', '/index.html')
     *
     * @return string
     */
    public function getPath();

    /**
     * Set the path of the request (e.g. '/', '/index.html')
     *
     * @param string|array $path Path to set or array of segments to implode
     *
     * @return self
     */
    public function setPath($path);

    /**
     * Get the port that the request will be sent on if it has been set
     *
     * @return int|null
     */
    public function getPort();

    /**
     * Set the port that the request will be sent on
     *
     * @param int $port Port number to set
     *
     * @return self
     */
    public function setPort($port);

    /**
     * Get the HTTP protocol version of the request
     *
     * @return string
     */
    public function getProtocolVersion();

    /**
     * Set the HTTP protocol version of the request (e.g. 1.1 or 1.0)
     *
     * @param string $protocol HTTP protocol version to use with the request
     *
     * @return self
     */
    public function setProtocolVersion($protocol);
}