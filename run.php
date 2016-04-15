<?php

// $this->registerHook('grapher', '\\Icinga\\Module\\Rrdstore\\Grapher');
$this->registerHook('Reporting\\Report', 'Icinga\\Module\\Rrdstore\\Report\\PerfdataReport', 'perfdata');
$this->registerHook('Reporting\\Report', 'Icinga\\Module\\Rrdstore\\Report\\AnomaliesReport', 'anomalies');
