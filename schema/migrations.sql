
ALTER TABLE rrd_archive MODIFY consolidation_function ENUM(
  'AVERAGE',
  'MIN',
  'MAX',
  'LAST',
  'HWPREDICT',
  'MHWPREDICT',
  'SEASONAL',
  'DEVSEASONAL',
  'DEVPREDICT',
  'FAILURES'
);

ALTER TABLE rrd_file MODIFY filename VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE pnp_xmlfile MODIFY filename VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;

ALTER TABLE pnp_xmlfile ADD UNIQUE INDEX filename (filename);
ALTER TABLE pnp_object DROP KEY pnp_object_xmlfile, ADD UNIQUE INDEX pnp_xmlfile_id (pnp_xmlfile_id);

