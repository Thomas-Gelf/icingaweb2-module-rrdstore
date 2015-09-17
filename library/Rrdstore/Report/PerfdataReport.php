<?php

namespace Icinga\Module\Rrdstore\Report;

use Icinga\Application\Config;
use Icinga\Module\Reporting\Web\Form\QuickForm;
use Icinga\Module\Reporting\Report\IdoReport;
use Icinga\Module\Rrdstore\Db;

class PerfdataReport extends IdoReport
{
    protected $db;

    protected $sizes = array(
        'small'   => array(
            'title'  => 'Small',
            'width'  => '72',
            'height' => '31',
        ),
        'compact' => array(
            'title'  => 'Compact',
            'width'  => 96,
            'height' => 48,
        ),
        'compact-wide' => array(
            'title'  => 'Compact-Wide',
            'width'  => 480,
            'height' => 48,
        ),
        'normal' => array(
            'title'  => 'Normal',
            'width'  => '400',
            'height' => '100',
        ),
    );

    public function getName()
    {
        return mt('rrdstore', 'Performance data');
    }

    public function addFormElements(QuickForm $form)
    {
        $this->addTimeframeElement($form);
        $form->addElement('select', 'hostgroup', array(
            'label'        => $form->translate('Hostgroup'),
            'multiOptions' => $form->optionalEnum($this->ido()->enumHostgroups()),
            'class'        => 'autosubmit',
            'required'     => true
        ));

        $form->addElement('text', 'host', array(
            'label'       => $form->translate('Host search'),
            'description' => $form->translate('Use * for wildcard searches')
        ));

        $form->addElement('text', 'service', array(
            'label'       => $form->translate('Service search'),
            'description' => $form->translate('Use * for wildcard searches')
        ));

        $form->addElement('select', 'limit', array(
            'label'        => $form->translate('Limit'),
            'multiOptions' => $this->enumLimits($form->translate('%s images')),
            'class'        => 'autosubmit',
            'required'     => true
        ));

        $form->addElement('select', 'size', array(
            'label'        => $form->translate('Size'),
            'multiOptions' => $this->enumSizes(),
            'class'        => 'autosubmit',
            'required'     => true,
        ));

        if (! $form->hasBeenSent() || ! $form->isValidPartial($form->getRequest()->getPost())) {
            return;
        }

        if ($hostgroup = $form->getValue('hostgroup')) {
        }
    }

    protected function getSelectedTimeframe()
    {
        return $this->configuredTimeframes()->get($this->getValue('timeframe'));
    }

    public function getViewScript()
    {
        return 'reports/perfdata.phtml';
    }

    public function getViewData()
    {
        $db = $this->db()->getDbAdapter();
        $size = $this->getValue('size');
        $timeframe = $this->getSelectedTimeframe();
        return array(
            'graphs' => $db->fetchAll(
                $this->prepareGraphQuery()->limit(
                    $this->getValue('limit')
                )
            ),
            'width'  => $this->sizes[$size]['width'],
            'height' => $this->sizes[$size]['height'],
            'start'  => $timeframe->getStart(),
            'end'    => $timeframe->getEnd(),
        );
    }

    protected function enumSizes()
    {
        $enum = array();
        foreach ($this->sizes as $name => $size) {
            $enum[$name] = $size['title'];
        }
        return $enum;
    }

    protected function enumLimits($caption)
    {
        $enum = array();
        foreach (array(50, 100, 200, 500, 1000, 5000) as $limit) {
            $enum[$limit] = sprintf($caption, $limit);
        }
        return $enum;
    }

    protected function prepareGraphQuery()
    {
        $hostgroup = $this->getValue('hostgroup');
        $db = $this->db()->getDbAdapter();
        $columns = array(
            'object_id'   => 'o.id',
            'graph_id'    => 'g.id',
            'host'        => 'o.icinga_host',
            'search_service' => 'CASE WHEN o.icinga_sub_service IS NULL THEN o.icinga_service ELSE o.icinga_sub_service END',
            'service'     => 'o.icinga_service',
            'sub_service' => 'o.icinga_sub_service',
            'graph_name'  => 'g.graph_name'
        );

        $query = $db->select()->from(
            array('o' => 'pnp_object'),
            $columns
        )->join(
            array('g' => 'pnp_graph'),
            'g.pnp_object_id = o.id',
            array()
        );


        if ($hostgroup) {
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

            $query->where('hgo.name1 = ?', $hostgroup);
        }

        $filters = array(
            'host'           => $this->getValue('host'),
            'search_service' => $this->getValue('service'),
            // 'sub_service' => $this->getValue('sub_service'),
            'graph_name'     => $this->getValue('graph_name'),
        );

        foreach ($columns as $alias => $col) {
            if (! array_key_exists($alias, $filters)) continue;
            if ($value = $filters[$alias]) {
                if (strpos($value, '*') === false) {
                    $query->where($col . ' = ?', $value);
                } else {
                    $query->where($col . ' LIKE ?', str_replace('*', '%', $value));
                }
            }
        }

        return $query;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = Db::fromResourceName(Config::module('rrdstore')->get('db', 'resource'));
        }

        return $this->db;
    }

}
