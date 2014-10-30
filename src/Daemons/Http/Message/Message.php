<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

abstract class Message implements MessageInterface
{
    /**
     * @var string HTTP protocol version
     */
    protected $protocolVersion = '1.1';

    /**
     * @var string HTTP method
     */
    protected $method;

    /**
     * @var array of HTTP headers
     */
    protected $headers = array();

    /**
     * @var string HTTP body
     */
    protected $body = '';

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
     * HTTP headers are case-insensitive, we store all headers in lower case to ensure that we can perform matching operations
     *
     * @param $header
     * @return string
     */
    private function normalizeHeader($header)
    {
        return strtolower($header);
    }

    /**
     * Add a new header by overwriting any existing header
     *
     * @param $header
     * @param $value
     * @return self
     */
    public function addHeader($header, $value)
    {
        $header = $this->normalizeHeader($header);

        $this->headers[$header] = $value;

        return $this;
    }

    /**
     * Get single header value
     *
     * @param $header
     * @return string
     */
    public function getHeader($header)
    {
        $header = $this->normalizeHeader($header);

        return isset($this->headers[$header]) ? $this->headers[$header] : null;
    }

    /**
     * Add headers by overwriting existing headers
     *
     * @param array $headers of key value
     * @return self
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->addHeader($key, $value);
        }

        return $this;
    }

    /**
     * Returns array of headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Checks to see if a header value exists
     *
     * @param $header
     * @return bool
     */
    public function hasHeader($header)
    {
        $header = $this->normalizeHeader($header);

        return isset($this->headers[$header]);
    }

    /**
     * Removes a header
     *
     * @param $header
     * @return self
     */
    public function removeHeader($header)
    {
        $header = $this->normalizeHeader($header);
        unset($this->headers[$header]);

        return $this;
    }

    /**
     * Sets body
     *
     * @param $body
     * @return self
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Gets body
     *
     * @return mixed
     */

    public function getBody()
    {
        return $this->body;
    }

    /**
     * Returns end of message strategy based on headers
     *
     * For message with Content-Length it returns length
     * For message with Transfer-Encoding : chunked it returns chunked
     *
     * @return length|chunked|null
     */
    public function getEOMStrategy()
    {
        $method = null;

        if ($this->hasHeader('Content-Length')) {
            $method = 'length';
        } else {
            if ($this->hasHeader('Transfer-Encoding') && preg_match('/chunked/', $this->getHeader('Transfer-Encoding'))) {
                $method = 'chunked';
            }
        }

        return $method;
    }

    /**
     * Returns message size (in bytes)
     *
     * @return mixed
     */
    public function getSize()
    {
        if ($this->hasHeader('Content-Length')) {
            $size = $this->getHeader('Content-Length');
        } else {
            $size = strlen($this->body);
        }

        return $size;
    }
}