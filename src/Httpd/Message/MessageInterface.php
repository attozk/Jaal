<?php

namespace Hathoora\Jaal\Httpd\Message;

Interface MessageInterface
{
    public function __construct($message = NULL, $greedy = true);

    public function addBody(\http\Message\Body $body);

    /**
     * Add an header, appending to already existing headers.
     *
     * @param $name
     * @param $value
     * @return mixed
     */
    public function addHeader($name, $value);

    /**
     * Add headers, optionally appending values, if header keys already exist.
     *
     * @param array $headers
     * @param bool $append
     * @return mixed
     */
    public function addHeaders(array $headers, $append = false);

    /**
     * Implements Countable
     *
     * @return int, the count of messages in the chain above the current message.
     */
    public function count();

    /**
     * Implements iterator.
     *
     * @return \http\Message, the current message in the iterated message chain
     */
    public function current();

    /**
     * Detach a clone of this message from any message chain.
     *
     * @return \http\Message, clone.
     */
    public function detach();

    /**
     * Retrieve the message’s body
     *
     * @return \http\Message\Body, the message body.
     */
    public function getBody();

    /**
     * Retrieve a single header, optionally hydrated into a \http\Header extending class.
     *
     * @param $header
     * @param null $into_class
     * @return mixed|\http\Header
     */
    public function getHeader($header, $into_class = NULL);

    /**
     * Retrieve all message headers
     *
     * @return array
     */
    public function getHeaders();

    /**
     * Retreive the HTTP protocol version of the message.
     *
     * @return string, the HTTP protocol version, e.g. “1.0”; defaults to “1.1”.
     */
    public function getHttpVersion();

    /**
     * Retrieve the first line of a request or response message
     *
     * @return mixed
     */
    public function getInfo();

    /**
     * Retrieve any parent message
     *
     * @exception \http\Exception\InvalidArgumentException
     * @exception \http\Exception\BadMethodCallException
     * @return \http\Message, the parent message.
     */
    public function getParentMessage();

    /**
     * Retrieve the request method of the message.
     *
     * @return string
     */
    public function getRequestMethod();

    /**
     * Retrieve the request URL of the message
     *
     * @return string
     */
    public function getRequestUrl();

    /**
     * Retrieve the response code of the message.
     *
     * @return int
     */
    public function getResponseCode();

    /**
     * Retrieve the response status of the message.
     *
     * @return string
     */
    public function getResponseStatus();

    /**
     * Retrieve the type of the message.
     *
     * @return int
     */
    public function getType();

    /**
     * Check whether this message is a multipart message based on it’s content type.
     *
     * @param null $boundary
     * @return bool
     */
    public function isMultipart(&$boundary = NULL);

    public function key();

    public function next();

    /**
     * Prepend message(s) $message to this message, or the top most message of this message chain.
     *
     * @param \http\Message $message
     * @param bool $top
     * @return mixed
     */
    public function prepend(\http\Message $message, $top = true);

    public function reverse();

    public function rewind();

    public function serialize();

    public function setBody(\http\Message\Body $body);

    public function setHeader($header, $value = NULL);

    public function setHeaders(array $headers = NULL);

    public function setHttpVersion($http_version);

    /**
     * Set the complete message info, i.e. type and response resp. request information, at once.
     */
    public function setInfo($http_info);

    public function setRequestMethod($method);

    public function setRequestUrl($url);

    public function setResponseCode($response_code, $strict = true);

    public function setResponseStatus($response_status);

    public function setType($type);

    public function splitMultipartBody();

    /**
     * Stream the message through a callback.
     *
     * @param callable $callback
     * @param int $offset
     * @param int $maxlen
     * @return mixed
     */
    public function toCallback(callable $callback, $offset = 0, $maxlen = 0);

    /**
     * Stream the message into stream $stream, starting from $offset, streaming $maxlen at most.
     *
     * @param resource $stream
     * @param int $offset
     * @param int $maxlen
     * @return mixed
     */
    public function toStream(resource $stream, $offset = 0, $maxlen = 0);

    public function toString($include_parent = false);

    public function unserialize($data);

    public function valid();
}