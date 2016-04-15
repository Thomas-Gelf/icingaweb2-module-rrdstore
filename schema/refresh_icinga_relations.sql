
UPDATE pnp_object po
  JOIN icinga.icinga_objects o
    ON o.name1 = po.icinga_host
   AND o.is_active = 1
   AND o.objecttype_id = 1
   AND po.object_type = 'host'
   SET po.icinga_object_id = o.object_id,
       po.icinga_host_id = o.object_id;

UPDATE pnp_object po
  JOIN icinga.icinga_objects o
    ON o.name1 = po.icinga_host
   AND o.name2 = po.icinga_service
   AND o.is_active = 1
   AND o.objecttype_id = 2
   AND po.object_type = 'service'
   SET po.icinga_object_id = o.object_id,
       po.icinga_service_id = o.object_id;


UPDATE pnp_object po
  JOIN icinga.icinga_objects o
    ON o.name1 = po.icinga_host
   AND o.is_active = 1
   AND o.objecttype_id = 1
   AND po.object_type = 'service'
   SET po.icinga_host_id = o.object_id;

