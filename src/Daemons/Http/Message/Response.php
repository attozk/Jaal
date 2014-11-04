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
     * @param       $statusCode
     * @param array $headers
     */
    public function __construct($statusCode, array $headers = [])
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
    public function setReasonPhrase($phrase = '')
    {
        if ($phrase == '') {

            $this->reasonPhrase =
                isset(StatusCode::$arrCodes[$this->statusCode]) ? StatusCode::$arrCodes[$this->statusCode] : '';
        }

        if ($this->reasonPhrase) {
            $this->body = '<h1>' . $this->statusCode . ' - ' . $this->reasonPhrase . '</h1>';
        }

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
        $lines   = $this->getHeaderLines();

        if (!empty($lines)) {
            $headers .= implode("\r\n", $lines) . "\r\n";
        }

        return $headers;
    }
}
