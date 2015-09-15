<?php

// $this->registerHook('grapher', '\\Icinga\\Module\\Pnp4nagios\\Grapher');
$this->registerHook('Reporting\\Report', 'Icinga\\Module\\Rrdstore\\Report\\PerfdataReport', 'perfdata');

