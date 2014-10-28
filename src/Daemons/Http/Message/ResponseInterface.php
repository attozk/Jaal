<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

Interface ResponseInterface extends MessageInterface
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

    public function setStatusCode($code);

    public function getStatusCode();

    public function setReasonPhrase($phrase);

    public function getReasonPhrase();

    public function getHeaderLines();
    public function getRawHeaders();
}