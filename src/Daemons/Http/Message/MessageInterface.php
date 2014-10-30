<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

interface MessageInterface
{
    const URL_ENCODED = 'application/x-www-form-urlencoded; charset=utf-8';
    const MULTIPART = 'multipart/form-data';

    /**
     * Set the HTTP protocol version of the request (e.g. 1.1 or 1.0)
     *
     * @param string $protocol HTTP protocol version to use with the request
     *
     * @return self
     */
    public function setProtocolVersion($protocol);

    /**
     * Get the HTTP protocol version of the request
     *
     * @return string
     */
    public function getProtocolVersion();

    /**
     * Add a new header by overwriting any existing header
     *
     * @param $header
     * @param $value
     * @return self
     */
    public function addHeader($header, $value);

    /**
     * Get single header value
     *
     * @param $header
     * @return string
     */
    public function getHeader($header);

    /**
     * Add headers by overwriting existing headers
     *
     * @param array $headers of key value
     * @return self
     */
    public function addHeaders(array $headers);

    /**
     * Returns array of headers
     *
     * @return array
     */
    public function getHeaders();

    /**
     * Checks to see if a header value exists
     *
     * @param $header
     * @return bool
     */
    public function hasHeader($header);

    /**
     * Removes a header
     *
     * @param $header
     * @return self
     */
    public function removeHeader($header);

    /**
     * Sets body
     *
     * @param $body
     * @return self
     */
    public function setBody($body);

    /**
     * Gets body
     *
     * @return mixed
     */
    public function getBody();

    /**
     * Returns end of message strategy based on headers
     *
     * For message with Content-Length it returns length
     * For message with Transfer-Encoding : chunked it returns chunked
     *
     * @return length|chunked|null
     */
    public function getEOMStrategy();

    /**
     * Returns message size (in bytes)
     *
     * @return mixed
     */
    public function getSize();
}