<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Rrdstore\Db;
use Icinga\Module\Rrdstore\Rrdstore;
use Icinga\Web\Hook\GrapherHook;
use Icinga\Web\Url;

class Grapher extends GrapherHook
{
    protected $hasPreviews = true;

    protected $db;

    protected $rrdstore;

    public function has(MonitoredObject $object)
    {
        // TODO
        return false;
    }

    public function getPreviewHtml(MonitoredObject $object)
    {
        $db = $this->db()->getDbAdapter();
        $filters = array('host' => $object->host_name);

        if ($object instanceof Service) {
            $filters['service'] = $object->service_description;
            $query = $this->db()->prepareGraphQuery($filters);
        } else {
            $query = $this->db()->prepareGraphQuery($filters);
            $query->where('o.icinga_service IS NULL');
        }

        $graphs = $db->fetchAll($query->limit(100));
        $width = 50;
        $height = 24;
        $end = floor(time() / 60) * 60;
        $start = $end - 4 * 3600;

        $html = '';
        foreach ($graphs as $graph) {
            $params = array('start' => $start, 'end' => $end);
            $params = $this->graphParams($graph);
            $params['start'] = $start;
            $params['end'] = $end;

            $html .= '<a href="' . Url::fromPath(
                'rrdstore/render/large',
                $params
            ) . '">';
            $html .= '<img src="' . Url::fromPath('rrdstore/render/graph', array(
                'id'     => $graph->graph_id,
                'width'  => $width,
                'height' => $height,
                'start'  => $start,
                'end'    => $end
            )) . '" />';
            $html .= "</a>\n";
        }

        return $html;
    }

    protected function graphParams($row)
    {
        $keep = array('host', 'service', 'sub_service', 'graph_name');
        $params = array();
        foreach ($keep as $key) {
            if ($row->$key) {
                $params[$key] = $row->$key;
            }
        }

        return $params;
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

    protected function Config()
    {
        return Config::module('rrdstore');
    }
}
