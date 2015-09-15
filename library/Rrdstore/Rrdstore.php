<?php

namespace Icinga\Module\Rrdstore;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Itenossla\Timeframe;

class Rrdstore
{
    protected $db;

    protected $basedir;

    protected $dbFiles;

    protected $dbArchiveSets;

    protected $diskFiles;

    protected $fileIdMap;

    protected $dsIdMap;

    protected $rrdtool;

    protected $dsMap = array(
        'index'             => 'datasource_index',
        'type'              => 'datasource_type',
        'minimal_heartbeat' => 'minimal_heartbeat',
        'min'               => 'min_value',
        'max'               => 'max_value',
        // Ignoring last_ds, value, unknown_sec
    );

    protected $rraMap = array(
        'cf'          => 'consolidation_function',
        'xff'         => 'xfiles_factor',
        'rows'        => 'row_count',
        'pdp_per_row' => 'step_size',
    );

    protected $rrdMap = array(
        // $filename => 'filename', (without basedir)
        'step'        => 'rrd_step',
        'rrd_version' => 'rrd_version',
        'header_size' => 'rrd_header_size',
        'last_update' => 'rrd_last_update',
    );

    public function __construct(DbConnection $db, $basedir)
    {
        $this->db = $db;
        $this->basedir = rtrim($basedir, '/');
    }

    public function getBasedir()
    {
        return $this->basedir;
    }

    public function dbHasFile($filename)
    {
        if ($this->fileIdMap === null) {
            $this->dbFiles();
        }

        return array_key_exists($filename, $this->fileIdMap);
    }

    public function getFileId($filename, $optional = false)
    {
        if (! $this->dbHasFile($filename)) {
            if ($optional) {
                return null;
            }
            die('No such file ' . $filename);
        }

        return $this->fileIdMap[$filename];
    }

    protected function dbHasArchiveSet($checksum)
    {
        if ($this->dbArchiveSets === null) {
            $this->dbArchiveSets = $this->retrieveDbArchiveSets();
        }

        return array_key_exists($checksum, $this->dbArchiveSets);
    }

    public function refreshDb()
    {
        $create = array();
        $baselen = strlen($this->basedir);
        $cnt = 0;
        foreach ($this->diskFiles() as $filename) {
            $filename = substr($filename, $baselen + 1);
            if (! $this->dbHasFile($filename)) {
                $cnt++;
                $data = array('filename' => $filename);
                $create[] = $data;
                if ($cnt === 1000) {
                    $this->reallyRefreshDb($create);
                    $create = array();
                    $cnt = 0;
                }
            }
        }
        if (! empty($create)) {
            $this->reallyRefreshDb($create);
        }
    }

    public function testData()
    {
        $db = $this->db->getConnection();

        $query = $db->select()->from(
            array('pds' => 'pnp_datasource_info'),
            array(
                'datasource_id' => 'ds.id',
                'filename'      => 'f.filename',
                'datasource'    => 'ds.datasource_name'
            )
        )->join(
            array('ds' => 'rrd_datasource'),
            'ds.id = pds.rrd_datasource_id',
            array()
        )->join(
            array('f' => 'rrd_file'),
            'f.id = ds.rrd_file_id',
            array()
        );
/*->where('pds.icinga_host LIKE ?', 'ORLN%')
         ->where('pds.icinga_service = ?', 'mobileStatTable_CSQ')
         ->where('pds.datasource_label = ?', 'UMTS Empfangspegel#');
*/
        $datasources = $db->fetchAll($query);
        $months = array(
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        );
        $years = array(
            //'2014',
            '2015'
        );

        $days = 14;
        $this->collectMonthlySummaries($datasources, $months, $years);
        $this->collectDailySummaries($datasources, $days);

    }

    protected function collectDailySummaries($datasources, $days)
    {
        for ($i = 0; $i < $days; $i++) {
            $start = strtotime(sprintf('now -%dday 00:00:00', $i));
            $end = strtotime(sprintf('now -%dday 23:59:59', $i));
            if ($start > time()) {
                continue;
            }
            printf("%s - %s\n", date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end));
            $res = $this->summariesForDatasources($datasources, $start, $end, 'aggregation_day');
            $db->beginTransaction();
            foreach ($res as $r) {
                $db->insert('aggregated_daily', $r);
            }
            $db->commit();
        }
    }

    protected function collectMonthlySummaries($datasources, $years, $months)
    {
        foreach ($years as $year) {
            foreach ($months as $month) {
                $start = strtotime(sprintf('first day of %s %s 00:00:00', $month, $year));
                $end = strtotime(sprintf('last day of %s %s 23:59:59', $month, $year));
                if ($start > time()) {
                    continue;
                }
                printf("%s - %s\n", date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end));
                $res = $this->summariesForDatasources($datasources, $start, $end, 'aggregation_month');
                $db->beginTransaction();
                foreach ($res as $r) {
                    $db->insert('aggregated_monthly', $r);
                }
                $db->commit();
            }
        }
    }

    public function graphCommand($id, $width = 400, $height = 100, $start = null, $end = null)
    {
        // From PNP?
        $graphOpt = ' --color BACK#FFF --color SHADEA#FFF --color SHADEB#FFF --disable-rrdtool-tag';

        $db = $this->db->getDbAdapter();
        $props = $db->fetchRow($db->select()->from('pnp_graph')->where('id = ?', $id));

        $defs = $props->last_rrd_def;

        $niceDefs = preg_replace('/(HRULE|VDEF|DEF|CDEF|GPRINT|LINE|AREA|COMMENT)/', "\n\${1}", $defs);

        $defs = '';
        foreach (preg_split('/\n/', $niceDefs, -1, PREG_SPLIT_NO_EMPTY) as $def) {
            $defs .= $def;
        }

        if ($end === null) {
            $end = time();
        }
        if ($start === null) {
            $start = $end - 3600 * 24 * 7;
        }

        $cmd = 'graph - --start ' . $start . ' --end ' . $end . $graphOpt;
        $cmd .= ' --width ' . $width . ' --height ' . $height;
        if ($height < 32) {
            $cmd .= ' -j'; // -j|--only-graph
        } elseif ($height < 64) {
            $cmd .= ' -g'; // -g|--no-legend
            $cmd .= ' -n AXIS:5 --units-length 4';
            $cmd .= ' ' . $this->removeParams(
                $props->last_rrd_opt,
                array('title', 'right-axis', 'right-format', 'right-axis-label', 'vertical-label')
            );
            // $cmd .= ' ' . $props->last_rrd_opt;
        } else {
            $cmd .= ' ' . $props->last_rrd_opt;
        }
        $cmd .= ' -a PNG';

// $cmd .= ' -c BACK#FFFFFF00 -c CANVAS#FFFFFF00 -c AXIS#DDDDDD -c ARROW#DDDDDD -c MGRID#DDDDDD --border 0'; // -n|--font
        $cmd .= ' ' . $defs;

        return $cmd;
    }

    protected function removeParams($str, $params)
    {
        $str = preg_replace('/ --/', "\n--", $str);
        $res = array();
        $parts = array();
        foreach ($params as $p) {
            $parts[] = preg_quote($p, '/');
        }

        foreach (preg_split('/\n/', $str) AS $row) {
            if (preg_match('/^\-\-(?:' . implode($parts, '|') . ')[= ]/', $row)) {
                continue;
            }

            $res[] = $row;
        }

        return implode($res, ' ');
    }

    public function graph($id, $width = 400, $height = 100, $start = null, $end = null)
    {
        $rrd = $this->rrdtool();
        if ($rrd->run($this->graphCommand($id, $width, $height, $start, $end))) {
            header("Content-type: image/png");
            echo $rrd->getStdOut();
        } else {
            printf('Graph generation failed: %s', $rrd->getStdError());
        }
        exit;
    }

    protected function summariesForDatasources($datasources, $start, $end, $dayColumn)
    {
        $pattern = array(
            'max'   => ' DEF:%3$sa=%1$s:%2$s:MAX VDEF:%3$saa=%3$sa,MAXIMUM PRINT:%3$saa:"%4$d %5$s %%.2lf"',
            'min'   => ' DEF:%3$sa=%1$s:%2$s:MIN VDEF:%3$saa=%3$sa,MINIMUM PRINT:%3$saa:"%4$d %5$s %%.2lf"',
            'avg'   => ' DEF:%3$sa=%1$s:%2$s:AVERAGE VDEF:%3$saa=%3$sa,AVERAGE PRINT:%3$saa:"%4$d %5$s %%.2lf"',
            'stdev' => ' DEF:%3$sa=%1$s:%2$s:AVERAGE VDEF:%3$saa=%3$sa,STDEV PRINT:%3$saa:"%4$d %5$s %%.2lf"',
        );
        $cmds = array();

        $basecmd = 'graph /dev/null -f "" --start ' . $start . ' --end ' . $end;
        $cmd = $basecmd;
        $cnt = 0;

        foreach ($datasources as $idx => $ds) {
            foreach ($pattern as $name => $rpn) {
                $prefix = $name . $idx;
                $cmd .= sprintf(
                    $rpn,
                    $ds->filename,
                    $ds->datasource,
                    $prefix,
                    $idx,
                    $name
                );
            }

            $cnt++;
            if ($cnt > 100) {
                $cmds[] = $cmd;
                $cmd = $basecmd;
                $cnt = 0;
            }
        }
        if ($cnt !== 0) {
            $cmds[] = $cmd;
        }

        $rrd = $this->rrdtool();
        $res = array();

        foreach ($rrd->runBulk($cmds) as $key => $stdout) {
            if ($stdout === false) {
                // printf("%s failed\n", $cmds[$key]);
            } else {
                // printf("%s SUCCEEDED\n", $cmds[$key]);
                // echo $stdout;

                foreach (preg_split('/\n/', $stdout, -1, PREG_SPLIT_NO_EMPTY) as $line) {
                    list($dsid, $what, $value) = preg_split('/ /', $line, 3);
                    $dskey = $datasources[$dsid]->filename . ': ' . $datasources[$dsid]->datasource;
                    if (! array_key_exists($dskey, $res)) {
                        $res[$dskey] = array(
                            'rrd_datasource_id' => $datasources[$dsid]->datasource_id,
                            $dayColumn => date('Y-m-d', $start)
                        );
                    }

                    // TODO: What about inf/-inf?
                    if (strtolower($value) === 'nan' || strtolower($value) === '-nan') {
                        $value = null;
                    }

                    $res[$dskey][$what . '_value'] = $value;
                }
            }
        }

        return $res;
    }

    protected function reallyRefreshDb($create)
    {
        $newinfo = array();
        $skipFiles = array();

        foreach ($create as $key => & $file) {
            $cmds[$key] = sprintf("info '%s/%s'", $this->getBasedir(), $file['filename']);
        }

        foreach ($this->rrdtool()->runBulk($cmds) as $key => $stdout) {
            $filename = $create[$key]['filename'];
            if ($stdout === false) {
                printf('%s failed', $filename);
            } else {
                $newinfo[$filename] = Rrdinfo::parseOutput($stdout);
                if (! $this->mapData($newinfo[$filename], $this->rrdMap, $create[$key])) {
                    printf("SKIPPING file %s\n", $filename);
                    print_r($newinfo[$filename]);
                    $skipFiles[$key] = $filename;
                }
            }
        }

        foreach ($skipFiles as $key => $filename) {
            unset($create[$key]);
            unset($newinfo[$filename]);
        }

        if (! empty($create)) {
            // Start creating RRA sets
            $createRra = array();
            $fileRraSets = array();
            foreach ($newinfo as $filename => & $info) {
                $checksums = array();
                $currentRras = array();
                foreach ($info['rra'] as & $rra) {
                    $data = array();
                    $this->mapData($rra, $this->rraMap, $data);
                    ksort($data);
                    $checksums[] = sha1(implode(';', array_values($data)));
                    $currentRras[] = $data;
                }

                $checksum = sha1(implode(';', $checksums));
                if (! array_key_exists($checksum, $createRra)
                    && ! $this->dbHasArchiveSet($checksum)
                ) {
                    $createRra[$checksum] = $currentRras;
                }

                $fileRraSets[$filename] = $checksum;
            }

            printf("Creating %d missing archives\n", count($createRra));

            $db = $this->db->getConnection();
            $db->beginTransaction();
            foreach ($createRra as $checksum => $rra) {
                $binaryChecksum = pack('H*', $checksum);
                $db->insert('rrd_archive_set', array('checksum' => $binaryChecksum));
                foreach ($rra as $row) {
                    $row['rrd_archive_set_checksum'] = $binaryChecksum;
                    $db->insert('rrd_archive', $row);
                }
                $this->dbArchiveSets[$checksum] = $checksum;
            }
            unset($createRra);

            printf("Creating %d missing RRD files\n", count($create));
            foreach ($create as $row) {
                // No multiple insert support in ZF?
                $row['rrd_archive_set_checksum'] = pack('H*', $checksum);
                $db->insert('rrd_file', $row);
            }
            unset($create);

            $this->dbFiles(true);
            $createDs  = array();
            foreach ($newinfo as $filename => & $info) {
                $file_id = $this->getFileId($filename);
                
                foreach ($info['ds'] as $dsName => & $ds) {
                    $data = array(
                        'rrd_file_id'     => $file_id,
                        'datasource_name' => $dsName,
                    );
                    $this->mapData($ds, $this->dsMap, $data);
                    $createDs[] = $data;
                }
            }

            printf("Creating %d missing datasources\n", count($createDs));
            foreach ($createDs as $row) {
                $db->insert('rrd_datasource', $row);
            }
            unset($createDs);

            $db->commit();
        }
    }

    public function dbFiles($refresh = false)
    {
        if ($refresh || ($this->dbFiles === null)) {
            $this->dbFiles = $this->retrieveDbFiles();
            $this->fileIdMap = array();
            foreach ($this->dbFiles as $file) {
                $this->fileIdMap[$file->filename] = $file->id;
            }
        }

        return $this->dbFiles;
    }

    public function getDatasourceId($filename, $dsname)
    {
        if ($this->dsIdMap === null) {
            $this->dsIdMap = $this->retrieveDatasourceMap();
        }

        $key = $filename . '|' . $dsname;
        if (array_key_exists($key, $this->dsIdMap)) {
            return $this->dsIdMap[$key];
        }

        return null;
    }

    protected function retrieveDiskfiles()
    {
        return glob($this->basedir . '/*/*.rrd');
    }

    protected function retrieveDbArchiveSets()
    {
        $db = $this->db->getConnection();
        $query = $db->select()->from(
            array('ras' => 'rrd_archive_set'),
            array(
                'idx'      => 'LOWER(HEX(ras.checksum))',
                'checksum' => 'LOWER(HEX(ras.checksum))'
            )
        )->order('checksum');

        return $db->fetchPairs($query);
    }

    protected function retrieveDatasourceMap()
    {
        $db = $this->db->getConnection();
        $query = $db->select()->from(
            array('f' => 'rrd_file'),
            array(
                'refkey' => "f.filename || '|' || ds.datasource_name",
                'id'     => 'ds.id'
            )
        )->join(
            array('ds' => 'rrd_datasource'),
            'ds.rrd_file_id = f.id',
            array()
        );

        return $db->fetchPairs($query);
    }

    protected function retrieveDbFiles()
    {
        $db = $this->db->getConnection();
        $query = $db->select()->from('rrd_file', '*')->order('filename');
        return $db->fetchAll($query);
    }

    protected function diskFiles()
    {
        if ($this->diskFiles === null) {
            $this->diskFiles = $this->retrieveDiskFiles();
        }

        return $this->diskFiles;
    }

    protected function mapData(& $values, & $map, & $data)
    {
        $success = true;

        foreach ($map as $k => $v) {
            if (array_key_exists($k, $values)) {
                $data[$v] = $values[$k];
            } else {
                $success = false;
            }
        }

        return $success;
    }

    public function rrdtool()
    {
        if ($this->rrdtool === null) {
            $this->rrdtool = new Rrdtool($this->basedir);
        }

        return $this->rrdtool;
    }
}
