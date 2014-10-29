<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

Interface ResponseInterface extends MessageInterface
{
    public function setStatusCode($code);

    public function getStatusCode();

    public function setReasonPhrase($phrase);

    public function getReasonPhrase();

    public function getHeaderLines();
    public function getRawHeaders();
}