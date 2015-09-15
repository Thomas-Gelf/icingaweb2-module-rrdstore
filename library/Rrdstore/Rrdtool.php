<?php

namespace Icinga\Module\Rrdstore;

class Rrdtool
{
    protected $basedir;

    protected $stderr = '';

    protected $stdout = '';

    protected $errorMsg;

    protected $rrdtool;

    protected $process;

    protected $pipes;

    protected $cmdCount;

    protected $maxCmds = 1000;

    public function __construct($basedir, $rrdtool = '/usr/bin/rrdtool')
    {
        $this->basedir = $basedir;
        $this->rrdtool = $rrdtool;
    }

    protected function rrdProcess()
    {
        if ($this->process === null || ! is_resource($this->process)) {
            $process = $this->forkRrdProcess();

            if (! is_resource($process)) {
                $this->process = null;
                return $this->setError('ERROR: unable to fork ' . $rrdtool);
            }

            $this->process = $process;
            $this->cmdCount = 0;
        }

        return $this->process;
    }

    protected function forkRrdProcess()
    {
        $fds = array (
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $this->pipes = array();
        $this->stderr = '';
        $this->stdout = '';
        $this->errorMsg = null;

        $rrdtool = sprintf('%s - ', $this->rrdtool);

        // Not sure about this one... required for rrdcached
        // $command = preg_replace(sprintf('/%s/', preg_quote($this->basedir, '/')), '', $command);

        $process = proc_open($rrdtool, $fds, $this->pipes, $this->basedir);

        $status = proc_get_status($process);
        if (! $status['running']) {
            return $this->setError('rrdtool is not running');
        }

        // printf("rrdtool is running with PID %s\n", $status['pid']);

        
        // TODO: establish a strategy to handle all kind of blocking issues
        stream_set_blocking($this->pipes[1], 0);
        stream_set_blocking($this->pipes[2], 0);
        stream_set_read_buffer($this->pipes[1], 0);
        stream_set_write_buffer($this->pipes[1], 0);
        stream_set_read_buffer($this->pipes[2], 0);
        stream_set_write_buffer($this->pipes[2], 0);
        
        return $process;
    }

    public function runBulk(array $commands)
    {
        $results = array();

        foreach ($commands as $key => $command) {
            // printf("Running %s: ", $command);
            $start = microtime(true);
            $success = $this->run($command, false);
            $duration = microtime(true) - $start;

            if ($success) {
                // printf("Success in %.f\n", $duration);
                $results[$key] = $this->getStdout();
            } else {
                // printf("Running %s: ", $command);
                // printf("Failure in %.f\n", $duration);
                $results[$key] = false;
            }

            $this->discardOutput();
        }

        return $results;
    }

    public function run($command, $disconnect = true)
    {
        $debug = array();
        $process = $this->rrdProcess();
        $pipes = & $this->pipes;
        $data = '';

        fwrite($pipes[0], $command . "\n");
        $this->cmdCount++;
        if ($this->cmdCount >= $this->maxCmds) {
            $disconnect = true;
        }

        if ($disconnect) {
            fwrite($pipes[0], "quit\n");
            fclose($pipes[0]);
        }

        $toRead = array((string) $pipes[1] => $pipes[1], (string) $pipes[2] => $pipes[2]);
        $r = array_values($toRead);
        $w = $e = array();

        // -> false = error, 0 = timeout
        while ($changedCount = stream_select($r, $w, $e, 2)) {
            foreach ($r as $read) {
                $ret = fread($read, 4096);
                if (empty($ret)) {
                    $meta = stream_get_meta_data($pipes[1]);
                    if ($meta['eof']) {
                        unset($toRead[(string) $read]);
                        if (empty($toRead)) {
                            $changedCount = 0;
                            break 2;
                        }
                    }

                    break 1;
                }

                if ($read === $pipes[1]) {
                    $this->stdout .= $ret;
                    if (strpos($ret, "\n") !== false) {
                    }
                } elseif ($read === $pipes[2]) {
                    $this->stderr .= $ret;
                } else {
                    var_dump($read);
                    var_dump($pipes);
                    die('WTF?');
                }

                if ($this->stdOutIsComleted()) {
                    break 2;
                }
            }
            $r = array_values($toRead);
            $w = $e = array();
        }

        if ($changedCount === false) {
            if ($disconnect) $this->disconnect();
            return $this->setError('select gave false');
        }

        $meta = stream_get_meta_data($pipes[1]);
        if ($meta['timed_out'] == 1){
            if ($disconnect) $this->disconnect();
            return $this->setError('ERROR: Timeout while reading rrdtool data.');
        }

        if ($disconnect) {
            if ($this->disconnect() !== 0) {
                return $this->setError('rrdtool gave us exit code ' . $retcode);
            }
        }

        return true;
    }

    protected function disconnect()
    {
        if ($this->process === null || ! is_resource($this->process)) {
            $this->process = null;
            $this->closePipes();
            return 0;
        }

        $this->closePipes();
        $retcode = proc_close($this->process);
        $this->process = null;
        return $retcode;
    }

    protected function closePipes()
    {
        if ($this->pipes === null) {
            return;
        }

        foreach ($this->pipes as $num => $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            } else {
                // printf("Not closing pipe %d: %s\n", $num, var_export($pipe, 1));
            }
        }

        $this->pipes = null;

        return $this;
    }

    protected function stdOutIsComleted()
    {
        // Strip line saying OK u:1.14 s:0.07 r:1.21
        if (empty($this->stdout) || substr($this->stdout, -1) !== "\n") {
            return false;
        }

        $lastline = substr($this->stdout, strrpos($this->stdout, "\n", -2) + 1);
        if (substr($lastline, 0, 3) === 'OK ') {
            return true;
        } elseif (substr($lastline, 0, 3) === 'ERROR: ') {
            $this->setError($lastline);
            return true;
        }

        return false;
    }

    public function getStdout()
    {
        // Strip line saying OK u:1.14 s:0.07 r:1.21
        $lastline = substr($this->stdout, strrpos($this->stdout, "\n", -2) + 1);
        if (substr($lastline, 0, 3) === 'OK ') {
            return substr($this->stdout, 0, strrpos($this->stdout, "\n", -2));
        } else {
            return $this->stdout;
        }
    }

    public function getStderr()
    {
        return $this->stderr;
    }

    public function hasStdout()
    {
        return $this->stdout !== '';
    }

    public function hasStderr()
    {
        return $this->stderr !== '';
    }

    public function hasError()
    {
        return $this->errorMsg !== null;
    }

    public function getError()
    {
        return $this->errorMsg;
    }

    public function discardOutput()
    {
        $this->errorMsg = $this->stdout = $this->stderr = null;
    }

    protected function setError($msg)
    {
        $this->errorMsg = $msg;
        return false;
    }
}
