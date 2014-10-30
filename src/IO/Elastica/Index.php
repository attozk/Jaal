<?php

namespace Hathoora\Jaal\IO\Elastica;

class Index extends \Elastica\Index
{
    /**
     * Returns a type object for the current index with the given name
     *
     * @param  string $type Type name
     * @return Type Type object
     */
    public function getType($type)
    {
        return new Type($this, $type);
    }
}