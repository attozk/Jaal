<?php

namespace Hathoora\Jaal\Daemons;

interface DaemonInterface
{
    /**
     */
    public function config();

    /**
     * Reload config
     */
    public function reload();
}
