<?php

namespace Attozk\Jaal\Upstream;

use Dflydev\DotAccessConfiguration\Configuration;

class Pool implements PoolInterface
{
    /**
     * @var \Dflydev\DotAccessConfiguration\Configuration
     */
    public $config;

    public function __construct($arrConfig)
    {
        $this->config = new Configuration($arrConfig);
    }
}