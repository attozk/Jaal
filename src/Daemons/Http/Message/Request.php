<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

use Hathoora\Jaal\Util\Time;

class Request extends Message implements RequestInterface
{
    /**
     * @var string unique id for request
     */
    public $id;

    /**
     * @var int millitime at start of request
     */
    protected $millitime;

    /**
     * @var int millitime it took to process this request
     */
    protected $took;

    /**
     * Stores URL parts to construct URL
     *
     * @var array
     */
    protected $urlParts = [];

    /**
     * Cache busting for $url so it can be regenerated
     *
     * @internal use getUrl() to get url
     * @var bool
     */
    protected $urlPartsModified = TRUE;

    /**
     * Internal pseudo cache for storing getUrl()
     *
     * @internal use getUrl() to get url
     * @var string
     */
    protected $url;

    /**
     * Stores internal state of request
     * This is not to be mixed with HTTP Request/Response status code
     *
     * @var int
     */
    protected $state;

    /**
     * Similar to $state, but it used only for parsing of message (from/to stream)
     *
     * @var int
     */
    protected $stateParsing;

    /**
     * Messaging parsing attributes
     * @var array
     */
    protected $parsingAttrs = [
        'state' => '',
        'consumed'  => 0,
        'methodEOM' => '',
        'contentLength'    => 0,
        'packets'  => 0,        // how many packets it took for entire message
        'buffer'    => '',
        'errorCode'  => 0       // stores error code e.g. 400
    ];

    /**
     * This is true when headers has been sent to client/upstream
     *
     * @var bool
     */
    protected $headersSent = FALSE;

    /**
     * @param       $method
     * @param       $url
     * @param array $headers
     */
    public function __construct($method, $url, $headers = [])
    {
        $this->setMethod($method);
        $this->setUrl($url);
        $this->setRequestId();
        $this->addHeaders($headers);
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

    /**
     * Sets the parsing state, which is different from $this->stats
     * @param $state
     * @return $this
     */
    public function setStateParsing($state)
    {
        $this->stateParsing = $state;

        return $this;
    }

    /**
     * Sets the parsing state, which is different from $this->stats
     */
    public function getStateParsing()
    {
        return $this->stateParsing;
    }

    /**
     * Resets parsing attributes, this is usually done at the end of STATE_PARSING_EOM
     *
     * @return celd
     */
    protected function resetParsingAttrs()
    {
        $this->parsingAttrs = [
            'state' => '',
            'consumed'  => 0,
            'methodEOM' => '',
            'contentLength'    => 0,
            'packets'  => 0,
            'buffer'    => '',
            'errorCode'  => 0
        ];

        return $this;
    }


    /**
     * Gets the parsing attribute from $$parsingAttrs
     *
     * @param $key
     * @return null
     */
    public function getParsingAttr($key)
    {
        return isset($this->parsingAttrs[$key]) ? $this->parsingAttrs[$key] : null;
    }

    /**
     * Sets the parsing attrs
     *
     * @param $key
     * @param $value
     * @return self
     */
    public function setParsingAttr($key, $value)
    {
        $this->parsingAttrs[$key] = $value;

        return $this;
    }

    /**
     * Set's internal state of request as it goes through various stages
     * When passed NULL it would automatically try to set appropriate state
     * This is not to be mixed with HTTP Request/Response status code
     *
     * @param null $state
     * @return self
     */
    public function setState($state = NULL)
    {
        if (!$state) {

            $state = self::STATE_PENDING;
            $EOM   = $this->getEOMStrategy();

            if (!$EOM && $this->method == 'GET' && count($this->headers)) {
                $state = self::STATE_DONE;
            } // content length
            else if ($EOM == 'length' && $this->method != 'GET' && strlen($this->body) == $this->getSize()) {
                $state = self::STATE_DONE;
            } // @TODO handle chunked
            else if ($EOM == 'chunked') {
            }
        }

        $this->state = $state;

        return $this;
    }

    /**
     * Returns internal state
     * This is not to be mixed with HTTP Request/Response status code
     *
     * @return mixed
     */
    public function getState()
    {

        return $this->state;
    }

    /**
     * Returns headers as an array of HEADER: VALUE
     *
     * @return array
     */
    public function getHeaderLines()
    {
        $headers = [];
        foreach ($this->headers as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $headers[] = $key . ': ' . $v;
                }
            } else {
                $headers[] = $key . ': ' . $value;
            }
        }

        return $headers;
    }

    /**
     * Returns raw HTTP Headers that usually initiate HTTP request
     * @url https://github.com/guzzle/guzzle
     *
     * @return string
     */
    public function getRawHeaders()
    {
        $protocolVersion = $this->protocolVersion ? : '1.1';

        return trim($this->method . ' ' . $this->getResource()) . ' ' .
               strtoupper(str_replace('https', 'http', $this->getScheme())) . '/' .
               $protocolVersion . "\r\n" . implode("\r\n", $this->getHeaderLines());
    }

    /**
     * Sets start time of request in milliseconds
     *
     * @param null|int $millitime when NULL uses current millitime
     * @return self
     */
    public function setStartTime($millitime = NULL)
    {
        if (!$millitime) {
            $millitime = Time::millitime();
        }

        $this->millitime = $millitime;

        return $this;
    }

    /**
     * Sets execution time of request in milliseconds
     *
     * @param int|null $millitime when passed, it would use passed millitime to overwrite execution time
     * @return self
     */
    public function setExecutionTime($millitime = NULL)
    {
        if ($millitime) {
            $this->took = Time::millitimeDiff($this->millitime, $millitime);
        } else if (!$this->took) {
            $this->took = Time::millitimeDiff($this->millitime);
        }

        return $this;
    }

    /**
     * Gets execution time of request in milliseconds
     *
     * @return int milliseconds
     */
    public function getExecutionTime()
    {
        return $this->took;
    }

    /**
     * Set the URI scheme of the request (http, https, ftp, etc)
     *
     * @param $method
     * @return self
     */
    public function setMethod($method)
    {
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
     * Set the URL of the request
     *
     * @param string $url Full URL to set including query string
     * @return self
     */
    public function setUrl($url)
    {
        $this->urlPartsModified = FALSE;
        $this->urlParts         = parse_url($url);
        $this->url              = $url;

        return $this;
    }

    /**
     * Get the full URL of the request (e.g. 'http://www.github.com/path?query=1')
     *
     * @return string
     */
    public function getUrl()
    {
        if ($this->urlPartsModified) {
            $scheme = $this->getScheme();
            $host   = $this->getHost();
            $port   = $this->getPort();
            #$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
            #$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
            #$pass     = ($user || $pass) ? "$pass@" : '';
            $path  = $this->getPath();
            $query = !empty($this->urlParts['query']) ? '?' . $this->urlParts['query'] : '';

            if ($scheme == 'http' && $port != 80) {
                $port = ':' . $port;
            } else if ($scheme == 'https' && $port != 443) {
                $port = ':' . $port;
            }

            $this->url = "$scheme://$host$port$path$query";
        }

        return $this->url;
    }

    /**
     * Get resource of URL, which is /PATH + QUERY
     *
     * @return string
     */
    public function getResource()
    {
        $path  = $this->getPath();
        $query = isset($this->urlParts['query']) ? '?' . $this->urlParts['query'] : '';

        return $path . $query;
    }

    /**
     * Set the URI scheme of the request (http, https, ftp, etc)
     *
     * @param string $scheme Scheme to set
     * @return self
     */
    public function setScheme($scheme)
    {
        $this->urlParts['scheme'] = $scheme;
        $this->urlPartsModified   = TRUE;

        return $this;
    }

    /**
     * Get the URI scheme of the request (http, https, ftp, etc)
     *
     * @return string
     */
    public function getScheme()
    {
        return isset($this->urlParts['scheme']) ? $this->urlParts['scheme'] : NULL;
    }

    /**
     * Set the port that the request will be sent on
     *
     * @param int $port Port number to set
     * @return self
     */
    public function setPort($port)
    {
        $this->urlParts['port'] = $port;
        $this->urlPartsModified = TRUE;

        return $this;
    }

    /**
     * Get the port that the request will be sent on if it has been set
     *
     * @return int|null
     */
    public function getPort()
    {
        return isset($this->urlParts['port']) ? $this->urlParts['port'] : NULL;
    }

    /**
     * Set the host of the request. Including a port in the host will modify the port of the request.
     *
     * @param string $host Host to set (e.g. www.yahoo.com, www.yahoo.com:80)
     * @return self
     */
    public function setHost($host)
    {
        $this->urlParts['host'] = $host;
        $this->urlPartsModified = TRUE;

        return $this;
    }

    /**
     * Get the host of the request
     *
     * @return string
     */
    public function getHost()
    {
        return isset($this->urlParts['host']) ? $this->urlParts['host'] : NULL;
    }

    /**
     * Set the path of the request (e.g. '/', '/index.html')
     *
     * @param string $path Path to set or array of segments to implode
     * @return self
     */
    public function setPath($path)
    {
        $this->urlParts['path'] = $path;
        $this->urlPartsModified = TRUE;

        return $this;
    }

    /**
     * Get the path of the request (e.g. '/', '/index.html')
     *
     * @return string
     */
    public function getPath()
    {
        return isset($this->urlParts['path']) ? $this->urlParts['path'] : NULL;
    }

    /**
     * Set the query of the request (e.g. id=1&sort=asc)
     *
     * @param string $query
     * @return self
     */
    public function setQuery($query)
    {
        $this->urlParts['query'] = $query;
        $this->urlPartsModified  = TRUE;

        return $this;
    }

    /**
     * Get the query of the request (e.g. id=1&sort=asc)
     *
     * @param bool $toArray when true, return as array
     * @return null|string|array
     */
    public function getQuery($toArray = FALSE)
    {
        $query = NULL;
        if (isset($this->urlParts['query'])) {
            $query = $this->urlParts['query'];

            if ($toArray) {
                $arr = [];
                parse_str($query, $arr);
                $query = $arr;
            }
        }

        return $query;
    }

    /**
     * Checks to see if we have a proper message
     * This returns TRUE when message is valid
     * This returns CODE when message is invalid
     *
     * @return true|int
     */
    public function isValid()
    {
        $valid = TRUE;

        if ($this->body && !$this->hasHeader('Content-Length')) {
            $valid = 400;
        }

        return $valid;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        // TODO: Implement __toString() method.
    }
}