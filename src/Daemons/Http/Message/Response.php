<?php

namespace Hathoora\Jaal\Daemons\Http\Message;


Class Response extends Request implements ResponseInterface
{
    protected $statusCode;

    protected $reasonPhrase;

    public function __construct($statusCode, array $headers = array())
    {
        parent::__construct('', '', $headers);
        $this->setStatusCode($statusCode);
    }

    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        $reason = isset(StatusCode::$arrCodes[$code]) ? StatusCode::$arrCodes[$code] : '';
        $this->setReasonPhrase($reason);

        return $this;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setReasonPhrase($phrase)
    {
        $this->reasonPhrase = $phrase;

        return $this;
    }

    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     * Get the the raw message headers as a string
     *
     * @return string
     */
    public function getRawHeaders()
    {
        $headers = 'HTTP/1.1 ' . $this->statusCode . ' ' . $this->reasonPhrase . "\r\n";
        $lines = $this->getHeaderLines();
        if (!empty($lines)) {
            $headers .= implode("\r\n", $lines) . "\r\n";
        }

        return $headers . "\r\n";
    }
}
