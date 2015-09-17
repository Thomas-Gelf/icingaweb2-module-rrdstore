<?php

use Icinga\Web\Controller;
use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Rrdstore\Db;
use Icinga\Module\Rrdstore\Rrdstore;
use Icinga\Module\Itenossla\Timeframes;
use Icinga\Application\Config;

class Rrdstore_RenderController extends Controller
{
    protected $db;

    protected $rrdstore;

    public function graphAction()
    {
        $this->rrdstore()->graph(
            $this->params->get('id'),
            $this->params->get('width', 400),
            $this->params->get('height', 100),
            $this->params->get('start'),
            $this->params->get('end')
        );
    }

    public function hostAction()
    {
        $this->getTabs()->add('filtered', array(
            'url'   => $this->getRequest()->getUrl(),
            'label' => 'Filtered graphs'
        ))->activate('filtered');
        $db = $this->db()->getDbAdapter();
        $filters = array('host' => $this->params->get('host'));
        if ($service = $this->params->get('service')) {
            $filters['service'] = $service;
            $query = $this->prepareGraphQuery($filters);
        } else {
            $query = $this->prepareGraphQuery($filters);
            $query->where('o.icinga_service IS NULL');
        }

        $query->limit($this->params->get('limit', 200));
        $this->view->graphs = $db->fetchAll($query);
        $this->view->width = $this->params->get('width', 96);
        $this->view->height = $this->params->get('height', 48);
        $this->view->width = $this->params->get('width', 60);
        $this->view->height = $this->params->get('height', 30);
        $this->view->start = time() - 7200;
        $this->view->end = time();
    }

    public function largeAction()
    {
        $this->getTabs()->add('large', array(
            'url'   => $this->getRequest()->getUrl(),
            'label' => 'Single graph'
        ))->activate('large');
        $db = $this->db()->getDbAdapter();
        $this->view->graph  = $db->fetchRow($this->prepareGraphQuery());
        $this->view->width  = $this->params->get('width', 640);
        $this->view->height = $this->params->get('height', 480);
        $this->view->start  = $this->params->get('start');
        $this->view->end    = $this->params->get('end');
    }

    protected function prepareGraphQuery()
    {
        $db = $this->db()->getDbAdapter();
        $columns = array(
            'object_id'   => 'o.id',
            'graph_id'    => 'g.id',
            'host'        => 'o.icinga_host',
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

        foreach ($columns as $alias => $col) {
            if ($value = $this->params->get($alias)) {
                if (strpos($value, '*') === false) {
                    $query->where($col . ' = ?', $value);
                } else {
                    $query->where($col . ' LIKE ?', str_replace('*', '%', $value));
                }
            }
        }

        return $query;
    }

    protected function rrdstore()
    {
        if ($this->rrdstore === null) {
            $this->rrdstore = new Rrdstore($this->db(), $this->Config()->get('store', 'basedir'));
        }

        return $this->rrdstore;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = Db::fromResourceName($this->Config()->get('db', 'resource'));
        }

        return $this->db;
    }
}
