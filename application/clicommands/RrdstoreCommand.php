<?php

namespace Icinga\Module\Rrdstore\Clicommands;

use Icinga\Application\Icinga;
use Icinga\Cli\Command;
use Icinga\Module\Rrdstore\Db;
use Icinga\Module\Rrdstore\Rrdstore;
use Icinga\Module\Rrdstore\Pnpstore;
use Icinga\Module\Rrdstore\PnpTemplateWrapper;

class RrdstoreCommand extends Command
{
    protected $db;

    protected $rrdstore;

    public function graphtestAction()
    {
        PnpTemplateWrapper::fromDb($this->db(), $this->params->shift(), $this->params->shift());
        // print_r(PnpTemplateWrapper::fromDb($this->db(), $this->params->shift(), $this->params->shift())->getGraphs());
    }

    public function refreshAction()
    {
        $this->rrdstore()->refreshDb();
    }

    public function pnprefreshAction()
    {
        $pnpstore = new Pnpstore($this->db(), $this->rrdstore());
        $pnpstore->refreshDb();
    }

    public function datatestAction()
    {
        $this->rrdstore()->testData();
    }

    public function checkAction()
    {
        $host = $this->params->shift('host');
        $service = $this->params->shift('service');
        $filter = $this->params->shift('filter');
        $start = $this->params->shift('start');
        $end = $this->params->shift('end');
        $this->rrdstore()->checkData($host, $service, $filter, $start, $end);
    }

    protected function db()
    {
        if ($this->db === null) {
            Icinga::app()->setupZendAutoloader();
            $this->db = Db::fromResourceName($this->Config()->get('db', 'resource'));
        }

        return $this->db;
    }

    protected function rrdstore()
    {
        if ($this->rrdstore === null) {
            $this->rrdstore = new Rrdstore($this->db(), $this->Config()->get('store', 'basedir'));
        }

        return $this->rrdstore;
    }
}
