<?php

// $this->registerHook('grapher', '\\Icinga\\Module\\Rrdstore\\Grapher');
$this->provideHook('reporting/Report', 'Icinga\\Module\\Rrdstore\\Report\\PerfdataReport');
$this->provideHook('reporting/Report', 'Icinga\\Module\\Rrdstore\\Report\\AnomaliesReport');
