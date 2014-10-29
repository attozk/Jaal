<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

interface MessageInterface
{
    const URL_ENCODED = 'application/x-www-form-urlencoded; charset=utf-8';
    const MULTIPART = 'multipart/form-data';

    public function addHeader($header, $value);
    public function addHeaders(array $headers);
    public function getHeader($header);
    public function getHeaders();
    public function setHeader($header, $value);
    public function setHeaders(array $headers);
    public function hasHeader($header);
    public function removeHeader($header);
    public function setBody($body);
    public function getBody();
    public function getSize();
    public function getEOMType();
}