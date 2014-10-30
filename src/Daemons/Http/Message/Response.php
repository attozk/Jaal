<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

Class Response extends Request implements ResponseInterface
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Reason phrase for code
     *
     * @var string
     */
    protected $reasonPhrase;

    /**
     * @param $statusCode
     * @param array $headers
     */
    public function __construct($statusCode, array $headers = array())
    {
        parent::__construct('', '', $headers);
        $this->setStatusCode($statusCode);
    }

    /**
     * Set HTTP status for response
     *
     * @param $code int
     * @return self
     */
    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        $reason = isset(StatusCode::$arrCodes[$code]) ? StatusCode::$arrCodes[$code] : '';
        $this->setReasonPhrase($reason);

        return $this;
    }

    /**
     * Get HTTP status of response
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Set reason phrase for $code
     *
     * @param $phrase
     * @return self
     */
    public function setReasonPhrase($phrase)
    {
        $this->reasonPhrase = $phrase;

        return $this;
    }

    /**
     * Get reason phrase
     *
     * @return string
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     * Return Raw HTTP headers
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

        return $headers;
    }
}
