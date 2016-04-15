<?php

namespace Icinga\Module\Rrdstore\Objects;

use Icinga\Module\Rrdstore\Data\Db\DbObject;

class PnpObject extends DbObject
{
    protected $table = 'pnp_object';

    protected $keyName = 'pnp_xmlfile_id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                 => null,
        'pnp_xmlfile_id'     => null,
        'icinga_object_id'   => null,
        'icinga_host_id'     => null,
        'icinga_service_id'  => null,
        'object_type'        => null,
        'icinga_host'        => null,
        'icinga_service'     => null,
        'icinga_sub_service' => null,
        'pnp_is_multi'       => null,
    );

    public function getCombinedKey()
    {
        return self::createCombinedKey(
            $this->icinga_host,
            $this->icinga_serivce,
            $this->icinga_sub_service
        );
    }

    public static function createCombinedKey($host, $service = null, $subService = null)
    {
        return implode(
            '!',
            array_filter(
                array($host, $service, $subService),
                'strlen'
            )
        );
    }
}