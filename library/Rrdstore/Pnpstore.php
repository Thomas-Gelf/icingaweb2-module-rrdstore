<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Application\Benchmark;
use Icinga\Data\Db\DbConnection;
use Icinga\Module\Rrdstore\Rrdstore;
use Icinga\Module\Rrdstore\Objects\PnpObject;
use Icinga\Module\Rrdstore\Objects\PnpDatasourceInfo;
use Icinga\Module\Rrdstore\Objects\PnpXmlfile;

class Pnpstore
{
    protected $db;

    protected $basedir;

    protected $dbFiles;

    protected $rrdstore;

    protected $xmlFiles;

    protected $fileIdMap;

    protected $pnpObjects;

    public function __construct(DbConnection $db, Rrdstore $rrdstore)
    {
        $this->db = $db;
        $this->rrdstore = $rrdstore;
        $this->basedir = $rrdstore->getBasedir();

        libxml_disable_entity_loader(true);
    }

    protected function optionalFloat($value)
    {
        return strlen((string) $value) ? (float) $value : null;
    }

    public function refreshDb()
    {
        // TODO: pnp_ds_info, pnp_file_info
        $datasources = array();
        $pnp_file = array();

        $this->xmlFiles(true);

        $db = $this->db->getConnection();

        $isMulti = array(
            0 => 'no',
            1 => 'parent',
            2 => 'child',
        );
        echo "Checking and persisting PNP objects\n";
        foreach ($this->xmlFiles() as $file) {
            $pnpObjectId = $this->requirePnpObjectForFile($file);
        }

        $this->persistModifiedPnpObjects();

        echo "Going to DS\n";
        foreach ($this->xmlFiles() as $file) {
            $xml = $file->getXml();
            $cnt_ds = $file->countDatasources();

            for ($i = 0; $i < $cnt_ds; $i++) {
                $ds = $file->getDatasourceByIndex($i);
                // echo $ds->RRDFILE . "\n";
                // echo $ds->TEMPLATE . "\n";
                $rrdfile = (string) $ds->RRDFILE;
                $rrdDsId = $this->rrdstore->getDatasourceId($rrdfile, (string) $ds->DS);
                if ($rrdDsId === null) {
                    printf("%s:%s: rrd DS not found\n", $file->filename, (string) $ds->DS);
                    continue;
                }

                $ds = array(
                    'rrd_datasource_id'    => $rrdDsId,
                    'pnp_object_id'        => $this->getPnpObjectForFile($file)->id,

                    'datasource_name'      => (string) $ds->NAME,
                    'datasource_label'     => (string) $ds->LABEL,

                    'min_value'            => $this->optionalFloat($ds->MIN),
                    'max_value'            => $this->optionalFloat($ds->MAX),
                    'threshold_warn'       => $this->optionalFloat($ds->WARN),
                    'threshold_warn_min'   => $this->optionalFloat($ds->WARN_MIN),
                    'threshold_warn_max'   => $this->optionalFloat($ds->WARN_MAX),
                    'threshold_crit'       => $this->optionalFloat($ds->CRIT),
                    'threshold_crit_min'   => $this->optionalFloat($ds->CRIT_MIN),
                    'threshold_crit_max'   => $this->optionalFloat($ds->CRIT_MAX),

                    // RRD File properties
                    'pnp_template'         => (string) $ds->TEMPLATE,
                    'pnp_rrd_storage_type' => (string) $ds->RRD_STORAGE_TYPE,
                    'pnp_rrd_heartbeat'    => (string) $ds->RRD_HEARTBEAT,
                );

                $datasources[$rrdDsId] = $ds;
            }
        }

        printf("Creating %d datasources\n", count($datasources));

        $dslist = PnpDatasourceInfo::loadAll($this->db, null, 'rrd_datasource_id');
        foreach ($datasources as $dsId => $ds) {
            if (array_key_exists($dsId, $dslist)) {
                $dslist[$dsId]->setProperties($ds);
            } else {
                $dslist[$dsId] = PnpDatasourceInfo::create($ds, $this->db);
            }
        }

        foreach ($dslist as $id => $ds) {
            if (! array_key_exists($id, $datasources)) {
                $ds->markForRemoval();
            }
        }

        printf("Persisting Datasource infos\n", count($dslist));
        $this->storeAsBulk($dslist);
        /*
        $db = $this->db->getConnection();
        $db->beginTransaction();
        foreach ($datasources as $row) {
            $db->insert('pnp_datasource_info', $row);
        }
        $db->commit();
*/
    }

    protected function requirePnpObjectForFile(PnpXmlfile $file)
    {
        $this->loadPnpObjects();
        $id = $file->id;
        if (array_key_exists($id, $this->pnpObjects)) {
            $file->refreshPnpObject($this->pnpObjects[$id]);
        } else {
            $this->pnpObjects[$id] = $file->createNewPnpObject();
        }

        return $id;
    }

    protected function getPnpObjectForFile(PnpXmlfile $file)
    {
        return $this->pnpObjects[$file->id];
    }

    protected function persistModifiedPnpObjects()
    {
        $this->storeAsBulk($this->pnpObjects);
    }

    protected function loadPnpObjects($force = false)
    {
        if ($force || $this->pnpObjects === null) {
            $this->pnpObjects = PnpObject::loadAll(
                $this->db,
                null,
                'pnp_xmlfile_id'
            );
        }
    }

    protected function retrieveXmlfiles()
    {
        return glob($this->basedir . '/*/*.xml');
    }

    protected function refreshXmlfiles()
    {
        $onDisk = array();

        Benchmark::measure('Read XML files from disk');
        foreach ($this->retrieveXmlfiles() as $filename) {
            $onDisk[$filename] = true;
            if (! array_key_exists($filename, $this->xmlFiles)) {
                $this->xmlFiles[$filename] = PnpXmlfile::create(
                    array('filename' => $filename)
                );
            }
        }

        foreach ($this->xmlFiles as $filename => $file) {
            if (! array_key_exists($filename, $onDisk)) {
                $file->markForRemoval();
                echo "MARK\n";
            }
        }

        Benchmark::measure('Got files from disk, storing changes');
        $this->storeAsBulk($this->xmlFiles);
        Benchmark::measure('Successfully refreshed XML files');
    }

    protected function storeAsBulk($objects)
    {
        $cnt = 0;
        $mod = 0;
        $del = 0;

        $db = $this->db->getConnection();
        $db->beginTransaction();
        foreach ($objects as $obj) {
            if ($obj->shouldBeRemoved()) {
                $cnt++;
                $del++;
                $obj->delete();
                echo "DEL\n";
            } elseif ($obj->hasBeenModified()) {
                $cnt++;
                $mod++;
                $obj->store($this->db);
            }

            if ($cnt >= 1500) {
                Benchmark::measure('Committing ' . $cnt . ' queries');
                $db->commit();
                echo "COMMIT\n";
                $cnt = 0;
                $db->beginTransaction();
                Benchmark::measure('New transaction started');
            }
        }
        $db->commit();
        Benchmark::measure(sprintf(
            '%d out of %d DB objects modified, %d deleted',
            $mod,
            count($objects),
            $del
        ));

        return $this;
    }

    protected function xmlFiles($refresh = false)
    {
        if ($this->xmlFiles === null) {
            Benchmark::measure('Loading XML files from DB');
            $this->xmlFiles = PnpXmlfile::loadAll($this->db, null, 'filename');
            Benchmark::measure('Got XML files from DB');
            if ($refresh) {
                $this->refreshXmlFiles();
            }
        }

        return $this->xmlFiles;
    }
}
