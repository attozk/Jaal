<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

Use Guzzle\Http\Message\Response as GuzzleResponse;
use Guzzle\Parser\ParserRegistry;

class ResponseUpstream extends GuzzleResponse implements ResponseUpstreamInterface
{
    /**
     * Create a new Response based on a raw response message
     *
     * @param string $message Response message
     *
     * @return self|bool Returns false on error
     */
    public static function fromMessage($message)
    {
        $data = ParserRegistry::getInstance()->getParser('message')->parseResponse($message);
        if (!$data) {
            return false;
        }

        $response = new static($data['code'], $data['headers'], $data['body']);
        $response->setProtocol($data['protocol'], $data['version'])
                 ->setStatus($data['code'], $data['reason_phrase']);

        return $response;
    }
}