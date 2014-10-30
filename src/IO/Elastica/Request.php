<?php

namespace Hathoora\Jaal\IO\Elastica;

class Request extends \Elastica\Request
{
    /**
     * @return \React\Promise\Promise
     */
    public function send()
    {
        $transport = $this->getConnection()->getTransportObject();

        return $transport->execute($this, $this->getConnection()->toArray());
    }
}