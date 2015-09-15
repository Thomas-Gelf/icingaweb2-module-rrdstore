<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Rrdstore\Rrdstore;

class Pnpstore
{
    protected $db;

    protected $basedir;

    protected $dbFiles;

    protected $rrdstore;

    protected $xmlFiles;

    protected $fileIdMap;

    public function __construct(DbConnection $db, Rrdstore $rrdstore)
    {
        $this->db = $db;
        $this->rrdstore = $rrdstore;
        $this->basedir = $rrdstore->getBasedir();
    }

    protected function optionalFloat($value)
    {
        return strlen((string) $value) ? (float) $value : null;
    }

    public function refreshDb()
    {
        // TODO: pnp_ds_info, pnp_file_info
        $datasources = array();
        $pnp_file = array();
        $baselen = strlen($this->basedir);

        foreach ($this->xmlFiles() as $file) {
            $filename = substr($file, $baselen + 1);
            $pnp_file[] = array('filename' => $filename);
        }

        $db = $this->db->getConnection();
/*
// TODO: sync
        printf("Creating %d files\n", count($pnp_file));
        $db->beginTransaction();
        foreach ($pnp_file as $row) {
            $db->insert('pnp_xmlfile', $row);
        }
        $db->commit();
*/
        $query = $db->select()->from('pnp_xmlfile', array('filename', 'id'));
        $pnp_file = $db->fetchPairs($query);
        $isMulti = array(
            0 => 'no',
            1 => 'parent',
            2 => 'child',
        );

        foreach ($this->xmlFiles() as $file) {
            $filename = substr($file, $baselen + 1);
            $xml = simplexml_load_file($file);
            $cnt_ds = count($xml->DATASOURCE);
            for ($i = 0; $i < $cnt_ds; $i++) {
                $ds = & $xml->DATASOURCE[$i];
                // echo $ds->RRDFILE . "\n";
                // echo $ds->TEMPLATE . "\n";
                $rrdfile = (string) $ds->RRDFILE;
                $rrdDsId = $this->rrdstore->getDatasourceId($rrdfile, (string) $ds->DS);
                if ($rrdDsId === null) {
                    printf("%s:%s: rrd DS not found\n", $filename, (string) $ds->DS);
                    continue;
                }
                $ds = array(
                    // TODO: icinga_object_id default null
                    'pnp_xmlfile_id'       => $pnp_file[$filename],
                    'rrd_datasource_id'    => $rrdDsId,
                    'icinga_host'          => (string) $xml->NAGIOS_DISP_HOSTNAME,
                    'icinga_service'       => (string) $xml->NAGIOS_DATATYPE === 'HOSTPERFDATA'
                                              ? null
                                              : (string) $xml->NAGIOS_AUTH_SERVICEDESC,
                    'icinga_multi_service' => (int) $ds->IS_MULTI === 2
                                              ? (string) $xml->NAGIOS_DISP_SERVICEDESC
                                              : null,
                    'datasource_name'      => (string) $ds->NAME,
                    'datasource_label'     => (string) $ds->LABEL,

                    'min_value'            => $this->optionalFloat($ds->MIN),
                    'max_value'            => $this->optionalFloat($ds->MAX),
                    'threshold_warn'       => $this->optionalFloat($ds->WARN),
                    'threshold_warn_min'   => $this->optionalFloat($ds->WARN_MIN),
                    'threshold_warn_max'   => $this->optionalFloat($ds->WARN_MAX),
                    'threshold_crit'       => $this->optionalFloat($ds->CRIT),
                    'threshold_crit_min'   => $this->optionalFloat($ds->CRIT_MIN),
                    'threshold_crit_max'   => $this->optionalFloat($ds->CRIT_MAX),

                    // RRD File properties
                    'pnp_is_multi'         => $isMulti[(int) $ds->IS_MULTI],
                    'pnp_template'         => (string) $ds->TEMPLATE,
                    'pnp_rrd_storage_type' => (string) $ds->RRD_STORAGE_TYPE,
                    'pnp_rrd_heartbeat'    => (string) $ds->RRD_HEARTBEAT,
                );

                $datasources[] = $ds;
            }
        }

        printf("Creating %d datasources\n", count($datasources));
        $db = $this->db->getConnection();
        $db->beginTransaction();
        foreach ($datasources as $row) {
            $db->insert('pnp_datasource_info', $row);
        }
        $db->commit();
    }


    protected function retrieveXmlfiles()
    {
        return glob($this->basedir . '/*/*.xml');
    }

    protected function xmlFiles()
    {
        if ($this->xmlFiles === null) {
            $this->xmlFiles = $this->retrieveXmlFiles();
        }

        return $this->xmlFiles;
    }
}
