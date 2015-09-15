<?php

namespace Icinga\Module\Rrdstore;

class Rrdinfo
{
    protected $rrdstore;

    protected function __construct(Rrdstore $rrdstore)
    {
        $this->rrdstore = $rrdstore;
    }

    public static function fromFile(Rrdstore $rrdstore, $file)
    {
        $info = new static($rrdstore);
        return $info->rrdfileInfo($file);
    }

    protected function rrdfileInfo($file)
    {
        $store = $this->rrdstore;

        // TODO: create command helpers allowing us to ignore basedir
        $cmd = sprintf("info '%s/%s'", $store->getBasedir(), $file);
        $rrd = $store->rrdtool();
        if ($rrd->run($cmd)) {
            $lines = $rrd->getStdout();
        } else {
            die($rrd->getError() . ' ' . $rrd->getStderr());
        }

        return self::parseRrdinfo($lines);
    }

    protected static function parseValue($value)
    {
        if ($value === 'NaN') {
            return null;
        }

        if (strlen($value) && $value[0] === '"') {
            return trim($value, '"');
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        die($value . ' is not a known data type');
    }

    public static function parseOutput($info)
    {
        $lines = preg_split('/\n/', $info, -1, PREG_SPLIT_NO_EMPTY);
        $res = array();
        foreach ($lines as $line) {
            if (false === strpos($line, ' = ')) continue;
            list($key, $val) = explode(' = ', $line);

            if (false === ($bracket = strpos($key, '['))) {
                $res[$key] = self::parseValue($val);
            } else {
                $type = substr($key, 0, $bracket);
                $key = substr($key, $bracket + 1);
                $bracket = strpos($key, ']');
                if ($bracket === false) continue; // WTF? TODO: Log.
                $idx = substr($key, 0, $bracket);
                $key = substr($key, $bracket + 2);

                // No nesting support, e.g. ignore rra[0].cdp_prep[0].value
                // We also need inf/-inf support before allowing them
                if (false !== strpos($key, '[')) continue;

                $res[$type][$idx][$key] = self::parseValue($val);
            }
        }

        return $res;
    }
}
