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

        $this->addSizesElement($form);
    }

    public function getViewScript()
    {
        return 'reports/anomalies.phtml';
    }

    public function getViewData()
    {
        $db = $this->db()->getDbAdapter();
        $results = $db->fetchOne(
            $db->select()->from('anomaly_checks', 'matches')
               ->where('check_name = ?', $this->getValue('anomaly_check'))
        );

        $anomaly = $this->anomalies->get($this->getValue('anomaly_check'));

        $size = $this->getValue('size');

        return array(
            'graphs'  => array(),
            'results' => json_decode($results),
            'width'  => $this->sizes[$size]['width'],
            'height' => $this->sizes[$size]['height'],
        );
    }

    protected function enumAnomalies()
    {
        $checks = $this->anomalies->listConfiguredChecks();

        return array_combine($checks, $checks);
    }
}
