<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;

class PnpTemplate
{
    protected $DS;

    protected $MACRO;

    protected $RRD;

    protected $templatePaths;

    protected $chosenTemplate;

    protected $chosenTemplateFile;

    protected $graphs;

    protected function __construct()
    {
        require_once __DIR__ . '/wrapper.php';

        $this->templatePaths = array(
            '/etc/pnp4nagios/templates',
            SYSPATH . '/html/templates.dist'
        );
    }

    public static function run($ds, $macro)
    {
        if (empty($ds)) {
            throw new NotFoundError('No related data sources found');
        }

        $object = new self();
        $template = $ds[0]['TEMPLATE'];
        $object->includeTemplate($template, $ds, $macro);
        return $object;
    }

    protected function findTemplateFile($template, $original = null)
    {
        foreach ($this->templatePaths as $path) {
            $filename = sprintf('%s/%s.php', $path, $template);
            if (file_exists($filename)) {
                $this->chosenTemplate     = $template;
                $this->chosenTemplateFile = $filename;
                return $filename;
            }
        }

        if ($filename === 'default') {
            throw new NotFoundError('Template %s not found', $original ?: $template);
        }

        return $this->findTemplateFile('default', $template);
    }

    public function getGraphs()
    {
        return $this->graphs;
    }

    protected function includeTemplate($template, $ds, $macro)
    {
        $filename = $this->findTemplateFile($template);

        $this->DS    = $ds;
        $this->MACRO = $macro;
        $this->RRD   = array();
        // TIMERANGE = $this->TIMERANGE;

        $opt = $def = $ds_name = array();

        // for 0.4.x templates
        foreach ($this->DS as $key => $val) {
            $key++;

            foreach(array_keys($val) as $tag) {
                ${$tag}[$key] = $val[$tag];
            }
        }

        foreach($this->MACRO as $key => $val ) {
            ${'NAGIOS_' . $key } = $val;
        }

        $hostname    = $this->MACRO['DISP_HOSTNAME'];
        $servicedesc = $this->MACRO['DISP_SERVICEDESC'];

        if (isset($RRDFILE[1])) {
            $rrdfile = $RRDFILE[1];
        }

        ob_start();
        $oldLevel = error_reporting();
        error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));
        include $filename;
        error_reporting($oldLevel);
        ob_end_clean();

        $defs = array_values($def);
        $opts = array_values($opt);
        $ds_names = array_values($ds_name);
        $this->graphs = array();
        foreach ($ds_names as $key => $name) {
            $this->graphs[] = PnpTemplateGraph::create(array(
                'graph_name'   => $name,
                'last_update'  => date('Y-m-d H:i:s'),
                'last_rrd_opt' => $opts[$key],
                'last_rrd_def' => $defs[$key],
            ));
        }
    }
}
