<?php

namespace Hathoora\Jaal\IO\Elastica;

use React\Promise\Deferred;

class Type extends \Elastica\Type
{
    /**
     * Returns current mapping for the given type
     *
     * @return \React\Promise\Promise
     */
    public function getMapping()
    {
        $path = '_mapping';
        $deferred = new Deferred();

        $this->request($path, Request::GET)
            ->then(function ($response) use ($deferred) {
                $mapping = array();

                $data = $response->getData();
                $arr = array_shift($data);

                if (isset($arr['mappings'])) {
                    $mapping = $arr['mappings'];
                }

                $deferred->resolve($mapping);

            }, function () use ($deferred) {
                $deferred->reject();
            });

        return $deferred->promise();
    }


    /**
     * Makes calls to the elasticsearch server based on this type
     *
     * @param  string $path Path to call
     * @param  string $method Rest method to use (GET, POST, DELETE, PUT)
     * @param  array $data OPTIONAL Arguments as array
     * @param  array $query OPTIONAL Query params
     */
    public function request($path, $method, $data = array(), array $query = array())
    {
        $path = $this->getName() . '/' . $path;

        return $this->getIndex()->request($path, $method, $data, $query);
    }
}