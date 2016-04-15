<?php

namespace Icinga\Module\Rrdstore\Report;

use Icinga\Application\Config;
use Icinga\Module\Reporting\Web\Form\QuickForm;
use Icinga\Module\Reporting\Report\IdoReport;
use Icinga\Module\Rrdstore\Db;

abstract class RrdstoreReport extends IdoReport
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
