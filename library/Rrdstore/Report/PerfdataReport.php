<?php

namespace Icinga\Module\Rrdstore\Report;

use Icinga\Module\Reporting\Web\Form\QuickForm;

class PerfdataReport extends RrdstoreReport
{
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
}
