<?php

namespace Icinga\Module\Rrdstore;

class PnpTemplateGraph
{
    protected $properties;

    protected function __construct()
    {
    }

    public static function create($properties = array())
    {
        $object = new self();
        $object->properties = $properties;
        return $object;
    }

    public function __get($key)
    {
        return $this->properties[$key];
    }

    public function __set($key, $value)
    {
        $this->properties[$key] = $value;
    }

    public function getProperties()
    {
        return $this->properties;
    }
}
