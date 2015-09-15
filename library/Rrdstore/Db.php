<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Data\Db\DbConnection;

class Db extends DbConnection
{
    public function enumDistinctIcingaHost($filters = null)
    {
        return $this->enumDistinctPnpColumn('icinga_host', $filters);
    }

    public function enumDistinctIcingaServices($filters = null)
    {
        return $this->enumDistinctPnpColumn('icinga_service', $filters);
    }

    public function enumDistinctIcingaMultiServices($filters = null)
    {
        return $this->enumDistinctPnpColumn('icinga_multi_service', $filters);
    }

    public function enumDistinctDatasourceLabel($filters = null)
    {
        return $this->enumDistinctPnpColumn('datasource_label', $filters);
    }

    public function enumDistinctPnpColumn($column, $filters = null)
    {
        $db = $this->getConnection();
        $query = $db
            ->select()
            ->distinct()
            ->from(
                // array('pds' => 'pnp_datasource_info'),
                array('po' => 'pnp_object'),
                array(
                    'k' => 'po.' . $column,
                    'v' => 'po.' . $column
                )
            )/*->join(
                array('o' => 'icinga.icinga_objects'),
                'o.is_active = 1 AND ('
//              . ' (o.objecttype_id = 1 AND o.name1 = pds.icinga_host AND pds.icinga_service IS NULL) OR'
              . ' (o.objecttype_id = 2 AND o.name1 = pds.icinga_host AND o.name2 = pds.icinga_service)'
              . ')',
                array()
            )->join(
                array('s' => 'icinga.icinga_services'),
                'o.object_id = s.service_object_id',
                array()
            )->join(
                array('hgm' => 'icinga.icinga_hostgroup_members'),
                'hgm.host_object_id = s.host_object_id',
                array()
            )->join(
                array('hg' => 'icinga.icinga_hostgroups'),
                'hg.hostgroup_id = hgm.hostgroup_id',
                array()
            )->join(
                array('hgo' => 'icinga.icinga_objects'),
                'hg.hostgroup_object_id = hgo.object_id',
                array()
            )*/
            ->where($column . ' IS NOT NULL')
            // ->where("icinga_host LIKE 'ORL%'")
            //->where('hgo.name1 = ?', 'orlen')
            // TODO: order by CI
            ->order($column);

        if ($filters !== null) {
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $query->where($key . ' IN (?)', $value);
                } else {
                    $query->where($key . ' = ?', (string) $value);
                }
            }
        }

        return $db->fetchPairs($query);
    }

    public function fetchFilteredServiceDings($filters)
    {
        $db = $this->getConnection();
        $query = $db
            ->select()
            ->distinct()
            ->from('pnp_datasource_info', array('icinga_host', 'icinga_service', 'icinga_multi_service'))
            // ->where("icinga_host LIKE 'ORL%'")
            ->order('icinga_host')
            ->order('icinga_service')
            ->order('icinga_multi_service')
            ->limit(60);

        if ($filters !== null) {
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $query->where($key . ' IN (?)', $value);
                } else {
                    $query->where($key . ' = ?', (string) $value);
                }
            }
        }

        return $db->fetchAll($query);
    }
}
