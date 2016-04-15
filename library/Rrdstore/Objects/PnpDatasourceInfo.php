<?php

namespace Icinga\Module\Rrdstore\Objects;

use Icinga\Module\Rrdstore\Data\Db\DbObject;

class PnpDatasourceInfo extends DbObject
{
    protected $table = 'pnp_datasource_info';

    protected $keyName = 'rrd_datasource_id';

    protected $defaultProperties = array(
        'rrd_datasource_id'    => null,
        'pnp_object_id'        => null,
        'datasource_name'      => null,
        'datasource_label'     => null,
        'datasource_unit'      => null,
        'last_update'          => null,
        'last_value'           => null,
        'min_value'            => null,
        'max_value'            => null,
        'threshold_warn'       => null,
        'threshold_warn_min'   => null,
        'threshold_warn_max'   => null,
        'warn_range_type'      => null,
        'threshold_crit'       => null,
        'threshold_crit_min'   => null,
        'threshold_crit_max'   => null,
        'crit_range_type'      => null,
        'pnp_template'         => null,
        'pnp_rrd_storage_type' => null,
        'pnp_rrd_heartbeat'    => null,
    );
}