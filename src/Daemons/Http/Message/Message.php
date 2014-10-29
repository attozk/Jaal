<?php

namespace Hathoora\Jaal\Daemons\Http\Message;


abstract class Message implements MessageInterface
{
    /** @var string HTTP protocol version of the message */
    protected $protocolVersion = '1.1';
    protected $method;
    protected $headers = array();
    protected $body = '';

    private function normalizeHader($header)
    {
        return strtolower($header);
    }

    public function addHeader($header, $value)
    {
        $header = $this->normalizeHader($header);

        $this->headers[$header] = $value;

        return $this;
    }

    public function addHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->addHeader($key, $value);
        }

        return $this;
    }

    public function getHeader($header)
    {
        $header = $this->normalizeHader($header);

        return $this->headers[$header];
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeader($header, $value)
    {
        $this->addHeader($header, $value);

        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = array();

        foreach ($headers as $key => $value) {
            $this->addHeader($key, $value);
        }

        return $this;
    }

    public function hasHeader($header)
    {
        $header = $this->normalizeHader($header);

        return isset($this->headers[$header]);
    }

    public function removeHeader($header)
    {
        $header = $this->normalizeHader($header);
        unset($this->headers[$header]);

        return $this;
    }

    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getEOMType()
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