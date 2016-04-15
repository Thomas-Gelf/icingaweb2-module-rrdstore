<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Application\Config;

class Anomalies
{
    protected $config;

    public function __construct()
    {
        $this->config = Config::module('rrdstore', 'anomalies');
    }

    public function listConfiguredChecks()
    {
        return $this->config->keys();
    }
}
