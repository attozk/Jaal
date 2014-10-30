<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

Interface ResponseInterface extends MessageInterface
{
    /**
     * @param $statusCode
     * @param array $headers
     */
    public function __construct($statusCode, array $headers = array());

    /**
     * Set HTTP status for response
     *
     * @param $code int
     * @return self
     */
    public function setStatusCode($code);

    /**
     * Get HTTP status of response
     *
     * @return int
     */
    public function getStatusCode();

    /**
     * Set reason phrase for $code
     *
     * @param $phrase
     * @return self
     */
    public function setReasonPhrase($phrase);

    /**
     * Get reason phrase
     *
     * @return string
     */
    public function getReasonPhrase();

    /**
     * Return Raw HTTP headers
     *
     * @return string
     */
    public function getRawHeaders();
}