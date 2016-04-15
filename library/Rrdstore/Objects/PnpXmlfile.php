<?php

namespace Icinga\Module\Rrdstore\Objects;

use Icinga\Module\Rrdstore\Data\Db\DbObject;

/**
 * @TODO:
 * - set rrdstore id?
 * - get path from there?
 */
class PnpXmlfile extends DbObject
{
    protected $table = 'pnp_xmlfile';

    protected $keyName = 'filename';

    protected $autoincKeyName = 'id';

    protected $xml;

    protected $defaultProperties = array(
        'id'       => null,
        'filename' => null,
    );

    private $isMultiMap = array(
        0 => 'no',
        1 => 'parent',
        2 => 'child',
    );

    public function getFilenameRelativeTo($path)
    {
        // TODO: Check path?
        $baselen = strlen($path);
        $filename = substr($file, $baselen + 1);
    }

    public function getDatasourceByIndex($index)
    {
        $xml = $this->getXml();
        return $xml->DATASOURCE[$index];
    }

    public function getFirstDatasource()
    {
        return $this->getDatasourceByIndex(0);
    }
    
    public function createNewPnpObject()
    {
        $xml = $this->getXml();
        $isMulti = $this->isMultiMap[
            (int) $this->getFirstDatasource()->IS_MULTI
        ];

        $objectType = (string) $xml->NAGIOS_DATATYPE === 'HOSTPERFDATA'
                    ? 'host'
                    : 'service';

        return PnpObject::create(array(
            'id'                 => null,
            'pnp_xmlfile_id'     => $this->id,
            'object_type'        => $objectType,
            'icinga_host'        => (string) $xml->NAGIOS_DISP_HOSTNAME,
            'icinga_service'     => $objectType === 'host'
                                    ? null
                                    : (string) $xml->NAGIOS_AUTH_SERVICEDESC,
            'icinga_sub_service' => $isMulti === 'child'
                                    ? (string) $xml->NAGIOS_DISP_SERVICEDESC
                                    : null,
            'pnp_is_multi'       => $isMulti,
        ));
    }

    public function refreshPnpObject(PnpObject $pnpObject)
    {
        $xml = $this->getXml();
        $ds = $xml->DATASOURCE[0];
        $isMulti = $this->isMultiMap[(int) $ds->IS_MULTI];
        $objectType = (string) $xml->NAGIOS_DATATYPE === 'HOSTPERFDATA'
                    ? 'host'
                    : 'service';

        $pnpObject->object_type    = $objectType;
        $pnpObject->icinga_host    = (string) $xml->NAGIOS_DISP_HOSTNAME;
        $pnpObject->icinga_service = $objectType === 'host'
                                    ? null
                                    : (string) $xml->NAGIOS_AUTH_SERVICEDESC;
        $pnpObject->icinga_sub_service = $isMulti === 'child'
                                    ? (string) $xml->NAGIOS_DISP_SERVICEDESC
                                    : null;
        $pnpObject->pnp_is_multi = $isMulti;

        return $pnpObject;
    }

    public function getXml()
    {
        if ($this->xml === null) {
            $this->loadXml();
        }

        return $this->xml;
    }

    public function countDatasources()
    {
        return count($this->getXml()->DATASOURCE);
    }

    protected function loadXml()
    {
        $this->xml = simplexml_load_string(file_get_contents($this->filename));
    }
}