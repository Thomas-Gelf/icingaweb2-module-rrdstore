<?php

namespace Icinga\Module\Rrdstore\Report;

use Icinga\Module\Reporting\Web\Form\QuickForm;
use Icinga\Module\Rrdstore\Anomalies;

class AnomaliesReport extends RrdstoreReport
{
    protected $anomalies;

    public function __construct()
    {
        $this->anomalies = new Anomalies();
    }

    public function getName()
    {
        return mt('rrdstore', 'Anomalies');
    }

    public function addFormElements(QuickForm $form)
    {
        $form->addElement('select', 'anomaly_check', array(
            'label'        => $form->translate('Anomaly check'),
            'multiOptions' => $form->optionalEnum($this->enumAnomalies()),
            'class'        => 'autosubmit',
            'required'     => true
        ));
    }

    public function getViewScript()
    {
        return 'reports/anomalies.phtml';
    }

    public function getViewData()
    {
        return array(
            'graphs' => array(),
            'results
        );
        $db = $this->db()->getDbAdapter();
        $size = $this->getValue('size');

        $filters = array(
            'host'           => $this->getValue('host'),
            'hostgroup'      => $this->getValue('hostgroup'),
            'search_service' => $this->getValue('service'),
            // 'sub_service' => $this->getValue('sub_service'),
            'graph_name'     => $this->getValue('graph_name'),
        );

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

    protected function enumAnomalies()
    {
        $checks = $this->anomalies->listConfiguredChecks();

        return array_combine($checks, $checks);
    }
}
