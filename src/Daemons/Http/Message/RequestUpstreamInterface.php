<?php

namespace Hathoora\Jaal\Daemons\Http\Message;

Use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;

interface RequestUpstreamInterface extends GuzzleRequestInterface
{
    public function setRequestId();

    public function getRequestId();

    public function setClientRequest(Request $request);

    public function getClientRequest();

    public function setUpstreamResponse(ResponseUpstream $response);

    public function getUpstreamResponse();
}