<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Data\Db\DbConnection;
use Icinga\Web\UrlParams;

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

    public function prepareGraphQuery($filters)
    {
        $db = $this->getDbAdapter();

        $columns = array(
            'object_id'   => 'o.id',
            'graph_id'    => 'g.id',
            'host'        => 'o.icinga_host',
            'search_service' => 'CASE WHEN o.icinga_sub_service IS NULL THEN o.icinga_service ELSE o.icinga_sub_service END',
            'service'     => 'o.icinga_service',
            'sub_service' => 'o.icinga_sub_service',
            'graph_name'  => 'g.graph_name'
        );

        if ($filters instanceof UrlParams) {
            $params = $filters;
            $filters = array();
            foreach (array_merge(array('hostgroup'), array_keys($columns)) as $param) {
                $filters[$param] = $params->get($param);
            }
        }

        $query = $db->select()->from(
            array('o' => 'pnp_object'),
            $columns
        )->join(
            array('g' => 'pnp_graph'),
            'g.pnp_object_id = o.id',
            array()
        );

        if (array_key_exists('hostgroup', $filters)) {
            $hostgroup = $filters['hostgroup'];
            if ($hostgroup) {
                $this->joinIcingaHostgroups($query);
                $this->addFilter($query, 'hgo.name1', $hostgroup);
            }
        }

        foreach ($columns as $alias => $col) {
            if (! array_key_exists($alias, $filters)) continue;
            if (array_key_exists($alias, $filters)) {
                if ($filters[$alias] !== null && $filters[$alias] !== '') {
                    $value = $filters[$alias];
                    $this->addFilter($query, $col, $value);
                }
            }
        }

        return $query;
    }

    protected function addFilter($query, $col, $value)
    {
        if (strpos($value, '*') === false) {
            $query->where($col . ' = ?', $value);
        } else {
            $query->where($col . ' LIKE ?', str_replace('*', '%', $value));
        }

        return $query;
    }

    protected function joinIcingaHostgroups($query)
    {
        $query->join(
            array('hgm' => 'icinga.icinga_hostgroup_members'),
            'hgm.host_object_id = o.icinga_host_id',
            array()
        )->join(
            array('hg' => 'icinga.icinga_hostgroups'),
            'hgm.hostgroup_id = hg.hostgroup_id',
            array()
        )->join(
            array('hgo' => 'icinga.icinga_objects'),
            'hgo.object_id = hg.hostgroup_object_id',
            array()
        );

        return $query;
    }

    public function isPgsql()
    {
        return $this->getDbType() === 'pgsql';
    }
}
