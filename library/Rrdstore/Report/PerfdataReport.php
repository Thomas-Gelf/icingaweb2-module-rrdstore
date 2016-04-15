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
            'value'        => 'compact-wide',
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

        $filters = array(
            'host'           => $this->getValue('host'),
            'hostgroup'      => $this->getValue('hostgroup'),
            'search_service' => $this->getValue('service'),
            // 'sub_service' => $this->getValue('sub_service'),
            'graph_name'     => $this->getValue('graph_name'),
        );

        $timeframe = $this->getSelectedTimeframe();
        return array(
            'graphs' => $db->fetchAll(
                $this->db()->prepareGraphQuery($filters)->limit(
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

    protected function db()
    {
        if ($this->db === null) {
            $this->db = Db::fromResourceName(Config::module('rrdstore')->get('db', 'resource'));
        }

        return $this->db;
    }

}
