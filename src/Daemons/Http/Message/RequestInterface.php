<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

interface RequestInterface extends MessageInterface
{
    /**
     * Message is corrupt
     */
    const STATE_ERROR      = -10;

    /**
     * Error in parsing message
     */
    const STATE_PARSING_ERROR = -1;

    /**
     * Request has not been yet been parsed
     * i.e. we are waiting on from stream to send data so we can start parsing
     */
    const STATE_PARSING_PENDING    = 0;

    /**
     * We have started parsing data and trying to figure out EOM (end of message)
     */
    const STATE_PARSING_PROCESSING    = 1;

    /**
     * After parsing we have reached EOM
     */
    const STATE_PARSING_EOM    = 2;

    /**
     * Message is ready to be processed i.e. either to be sent to upstream server/local docroot
     */
    const STATE_PENDING    = 10;

    /**
     * We connected to upstream server/read from local
     */
    const STATE_CONNECTED = 12;

    /**
     * We are sending data to client/upstream server
     */
    const STATE_SENDING = 13;

    /**
     * After getting connected, we have started reading data from stream/local docroot
     */
    const STATE_RETRIEVING = 14;

    /**
     * We got all the data from client/upstream/local docroot
     */
    const STATE_EOM = 18;

    /**
     * Everything is done with this message client got the reply, we should close it
     */
    const STATE_DONE       = 20;

    /**
     * Sets the parsing state, which is different from $this->state
     * @param $state
     * @return $this
     */
    public function setStateParsing($state);

    /**
     * Gets the parsing state, which is different from $this->state
     */
    public function getStateParsing();

    /**
     * Gets the parsing attribute from $$parsingAttrs
     *
     * @param $key
     * @return null
     */
    public function getParsingAttr($key);

    /**
     * Sets the parsing attrs
     *
     * @param $key
     * @param $value
     * @return self
     */
    public function setParsingAttr($key, $value);

    /**
     * Set's internal state of request as it goes through various stages
     * When passed NULL it would automatically try to set appropriate state
     * This is not to be mixed with HTTP Request/Response status code
     *
     * @param null $state
     * @return self
     */
    public function setState($state = NULL);

    /**
     * Returns internal state
     * This is not to be mixed with HTTP Request/Response status code
     *
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
     * @return string
     */
    public function getRawHeaders();

    /**
     * Sets start time of request in milliseconds
     *
     * @param null|int $millitime when NULL uses current millitime
     * @return self
     */
    public function setStartTime($millitime = NULL);

    /**
     * Sets execution time of request in milliseconds
     *
     * @param int|null $millitime when passed, it would use passed millitime to overwrite execution time
     * @return self
     */
    public function setExecutionTime($millitime = NULL);

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
    public function getQuery($toArray = FALSE);

    /**
     * Checks to see if we have a proper message
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