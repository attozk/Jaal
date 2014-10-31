<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

interface RequestInterface extends MessageInterface
{
    const STATE_PENDING = 0;
    const STATE_CONNECTING = 1;
    const STATE_RETRIEVING = 2;
    const STATE_FINALIZING = 5;
    const STATE_ERROR = 7;
    const STATE_DONE = 9;

    /**
     * Set's interal state of request as it goes through various stages
     * When passed NULL it would automatically try to set appropriate state
     *
     * This is not to be mixed with HTTP Request/Response status code
     *
     * @param null $state
     * @return self
     */
    public function setState($state = null);

    /**
     * Returns internal state
     *
     * This is not to be mixed with HTTP Request/Response status code
     * @return mixed
     */
    public function getState();

    /**
     * Returns headers as an array of HEADER: VALUE
     *
     * @return array
     */
    public function getHeaderLines();

    /**
     * Returns raw HTTP Headers that usually initiate HTTP request
     *
     * @return sring
     */
    public function getRawHeaders();

    /**
     * Sets start time of request in milliseconds
     *
     * @param null|int $millitime when NULL uses current millitime
     * @return self
     */
    public function setStartTime($millitime = null);

    /**
     * Sets execution time of request in milliseconds
     *
     * @param int|null $millitime when passed, it would use passed millitime to overwrite execution time
     * @return self
     */
    public function setExecutionTime($millitime = null);

    /**
     * Gets execution time of request in milliseconds
     *
     * @return int milliseconds
     */
    public function getExecutionTime();

    /**
     * Set the URI scheme of the request (http, https, ftp, etc)
     *
     * @param $method
     * @return self
     */
    public function setMethod($method);

    /**
     * Get the HTTP method of the request
     *
     * @return string
     */
    public function getMethod();

    /**
     * Set the URL of the request
     *
     * @param string $url Full URL to set including query string
     *
     * @return self
     */
    public function setUrl($url);

    /**
     * Get the full URL of the request (e.g. 'http://www.github.com/path?query=1')
     *
     * @return string
     */
    public function getUrl();

    /**
     * Get resource of URL, which is /PATH + QUERY
     *
     * @return string
     */
    public function getResource();

    /**
     * Set the URI scheme of the request (http, https, ftp, etc)
     *
     * @param string $scheme Scheme to set
     * @return self
     */
    public function setScheme($scheme);

    /**
     * Get the URI scheme of the request (http, https, ftp, etc)
     *
     * @return string
     */
    public function getScheme();

    /**
     * Set the port that the request will be sent on
     *
     * @param int $port Port number to set
     *
     * @return self
     */
    public function setPort($port);

    /**
     * Get the port that the request will be sent on if it has been set
     *
     * @return int|null
     */
    public function getPort();

    /**
     * Set the host of the request. Including a port in the host will modify the port of the request.
     *
     * @param string $host Host to set (e.g. www.yahoo.com, www.yahoo.com:80)
     * @return self
     */
    public function setHost($host);

    /**
     * Get the host of the request
     *
     * @return string
     */
    public function getHost();

    /**
     * Set the path of the request (e.g. '/', '/index.html')
     *
     * @param string $path Path to set or array of segments to implode
     * @return self
     */
    public function setPath($path);

    /**
     * Get the path of the request (e.g. '/', '/index.html')
     *
     * @return string
     */
    public function getPath();

    /**
     * Set the query of the request (e.g. id=1&sort=asc)
     *
     * @param string $query
     * @return self
     */
    public function setQuery($query);

    /**
     * Get the query of the request (e.g. id=1&sort=asc)
     *
     * @param bool $toArray when true, return as array
     * @return null|string|array
     */
    public function getQuery($toArray = false);

    /**
     * Checks to see if we have a proper message
     *
     * This returns TRUE when message is valid
     * This returns CODE when message is invalid
     *
     * @return true|int
     */
    public function isValid();

    /**
     * @return string
     */
    public function __toString();
}