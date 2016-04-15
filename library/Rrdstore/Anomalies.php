<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Application\Config;
use Icinga\Module\Rrdstore\Db;
use Icinga\Module\Rrdstore\Rrdstore;

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

    public function get($key)
    {
        return $this->config->getSection($key);
    }

    public function runChecks(Rrdstore $rrdstore, Db $db)
    {
        $dbc = $db->getConnection();

        $existing = $dbc->fetchCol(
            $dbc->select()->from(
                array('ac' => 'anomaly_checks'),
                array('check_name' => 'ac.check_name')
            )
        );

        foreach ($this->config as $name => $section) {
            $result = $rrdstore->checkData(
                $section->host,
                $section->service,
                $section->filter,
                $section->start,
                $section->end
            );

            if (in_array($name, $existing)) {
                $dbc->update('anomaly_checks', array(
                    'last_run' => date('Y-m-d H:i:s'),
                    'matches'  => json_encode($result),
                ), $dbc->quoteInto('check_name = ?', $name));
            } else {
                $dbc->insert('anomaly_checks', array(
                    'check_name' => $name,
                    'last_run'   => date('Y-m-d H:i:s'),
                    'matches'    => json_encode($result),
                ));
            }
        }
    }
}
