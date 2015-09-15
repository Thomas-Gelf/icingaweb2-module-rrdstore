<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Rrdstore\Db;

class PnpTemplateWrapper
{
    protected $graphs;

    protected function __construct()
    {
        require_once __DIR__ . '/wrapper.php';

        $this->templatePaths = array(
            '/etc/pnp4nagios/templates',
            SYSPATH . '/html/templates.dist'
        );
    }

    public static function fromDb(Db $db, $host, $service = null)
    {
        $object = new self();
        return $object->loadFromDb($db, $host, $service);
    }

    protected function loadFromDb(Db $db, $host, $service = null)
    {
        $db = $db->getDbAdapter();
        $query = $db->select()->from(
            array('pdi' => 'pnp_datasource_info'),
            array(
                'rrd_datasource_id'      => 'ds.id',
                'rrd_file_filename'      => 'f.filename',
                'pnp_object_id'          => 'po.id',
                'icinga_object_id'       => 'po.icinga_object_id',
                'icinga_host'            => 'po.icinga_host',
                'icinga_service'         => 'po.icinga_service',
                'icinga_sub_service'     => 'po.icinga_sub_service',
                'rrd_ds_datasource_name' => 'ds.datasource_name',
                'pnp_template'           => 'pdi.pnp_template',
                'pnp_rrd_storage_type'   => 'pdi.pnp_rrd_storage_type',
                'pnp_rrd_heartbeat'      => 'pdi.pnp_rrd_heartbeat', // ds.minimal_heartbeat
                'pnp_is_multi'           => 'po.pnp_is_multi',
                'datasource_name'        => 'pdi.datasource_name',
                'datasource_label'       => 'pdi.datasource_label',
                'datasource_unit'        => 'pdi.datasource_unit',
                'last_value'             => 'pdi.last_value',
                'threshold_warn'         => 'pdi.threshold_warn',
                'threshold_warn_min'     => 'pdi.threshold_warn_min',
                'threshold_warn_max'     => 'pdi.threshold_warn_max',
                'warn_range_type'        => 'pdi.warn_range_type',
                'threshold_crit'         => 'pdi.threshold_crit',
                'threshold_crit_min'     => 'pdi.threshold_crit_min',
                'threshold_crit_max'     => 'pdi.threshold_crit_max',
                'crit_range_type'        => 'pdi.crit_range_type',
                'min_value'              => 'pdi.min_value',
                'max_value'              => 'pdi.max_value',
            )
        )->join(
            array('ds' => 'rrd_datasource'),
            'pdi.rrd_datasource_id = ds.id',
            array()
        )->join(
            array('po' => 'pnp_object'),
            'pdi.pnp_object_id = po.id',
            array()
        )->join(
            array('f' => 'rrd_file'),
            'ds.rrd_file_id = f.id',
            array()
        );

        if ($host !== null) {
            $query->where('po.icinga_host = ?', $host);
        }

        if ($host !== null && $service === null) {
            $query->where('po.icinga_service IS NULL');
        } else {
            if ($service !== null && $service !== '*') {
                $query->where('po.icinga_service = ?', $service);
            }
        }

        $query->order('po.id')->order('ds.datasource_name');
        $this->graphs = array();
        $ds = array();

        $lastObjectId = null;

        foreach ($db->fetchAll($query) as $row) {
            if ($row->pnp_object_id !== $lastObjectId) {
                if (! empty($ds)) {
                    $this->extractGraphsForSources($ds, $macro, $lastObjectId);
                }
                $ds    = array();
                $macro = array(
                    'DISP_HOSTNAME'    => $row->icinga_host,
                    'DISP_SERVICEDESC' => $row->icinga_sub_service ?: $row->icinga_service,
                    'MULTI_PARENT'     => $row->icinga_sub_service ? $row->icinga_service : ''
                    // TODO: MULTI dingens
                );
            }
            $ds[] = $this->dbRowToDs($row);
            $lastObjectId = $row->pnp_object_id;
        }

        if (! empty($ds)) {
            $this->extractGraphsForSources($ds, $macro, $lastObjectId);
        }
        $db->beginTransaction();
        $db->delete('pnp_graph');
        foreach ($this->graphs as $graph) {
            $db->insert('pnp_graph', $graph->getProperties());
        }
        $db->commit();

        return $this;
    }

    protected function extractGraphsForSources($ds, $macro, $objectId)
    {
        $template = PnpTemplate::run($ds, $macro);
        foreach ($template->getGraphs() as $name => $graph) {
            $graph->pnp_object_id = $objectId;
            $this->graphs[] = $graph;
        }
    }

    public function getGraphs()
    {
        return $this->graphs;
    }

    protected function dbRowToDs($row)
    {
        $isMulti = array(
            'no'     => 0,
            'parent' => 1,
            'child'  => 2
        );

        return array(
            'RRDFILE'          => $row->rrd_file_filename,
            'RRD_STORAGE_TYPE' => $row->pnp_rrd_storage_type,
            'RRD_HEARTBEAT'    => $row->pnp_rrd_heartbeat,  // ds.minimal_heartbeat
            'IS_MULTI'         => $isMulti[$row->pnp_is_multi],
            'DS'               => $row->rrd_ds_datasource_name,
            'TEMPLATE'         => $row->pnp_template,
            'NAME'             => $row->datasource_name,
            'LABEL'            => $row->datasource_label,
            'UNIT'             => $row->datasource_unit,
            'ACT'              => $row->last_value,
            'WARN'             => $row->threshold_warn,
            'WARN_MIN'         => $row->threshold_warn_min,
            'WARN_MAX'         => $row->threshold_warn_max,
            'WARN_RANGE_TYPE'  => $row->warn_range_type,
            'CRIT'             => $row->threshold_crit,
            'CRIT_MIN'         => $row->threshold_crit_min,
            'CRIT_MAX'         => $row->threshold_crit_max,
            'CRIT_RANGE_TYPE'  => $row->crit_range_type,
            'MIN'              => $row->min_value, // often != ds.min_value
            'MAX'              => $row->max_value, // often != ds.max_value
        );
    }
}
