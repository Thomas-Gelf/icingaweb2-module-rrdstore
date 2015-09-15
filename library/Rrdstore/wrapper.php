<?php

define(
    'SYSPATH',
    Icinga\Application\Config::module('rrdstore')
        ->get('pnp4nagios', 'syspath', '/usr/share/pnp4nagios')
);

require_once SYSPATH . '/html/application/helpers/rrd.php';
class rrd extends rrd_Core {}

