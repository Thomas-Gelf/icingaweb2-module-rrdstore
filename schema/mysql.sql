
-- CREATE TABLE rrdhost (
--   id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
--   hostname VARCHAR(64) NOT NULL,
--   PRIMARY KEY (id),
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- CREATE TABLE rrdstore (
--   id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
--   host_id INT(10) UNSIGNED NOT NULL,
--   rrdhost_id INT(10) UNSIGNED NOT NULL,
--   rrdcached_socket VARCHAR(64) DEFAULT NULL,
--   basedir VARCHAR(64) NOT NULL,
--   rrdtool VARCHAR(64) NOT NULL, -- /usr/bin/rrdtool
-- ) ENGINE=InnoDb DEFAULT CHARSET=utf8;

-- CREATE TABLE pnp4nagios_config (
--   id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
--   host_id INT(10) UNSIGNED NOT NULL,
--   rrdstore_id INT(10) UNSIGNED DEFAULT NULL,
--   configdir VARCHAR(255) NOT NULL,
-- ) ENGINE=InnoDB;

CREATE TABLE rrd_archive_set (
  checksum VARBINARY(20) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (checksum),
  INDEX search_idx (description)
) ENGINE=InnoDb DEFAULT CHARSET=utf8;

CREATE TABLE rrd_archive (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  rrd_archive_set_checksum VARBINARY(20) NOT NULL,
  consolidation_function ENUM(
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
  ),
  xfiles_factor FLOAT NOT NULL DEFAULT 0.5,
  row_count INT(10) UNSIGNED NOT NULL,
  step_size INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT rrd_archive_set_checksum
    FOREIGN KEY rrd_archive_set (rrd_archive_set_checksum)
    REFERENCES rrd_archive_set (checksum)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDb DEFAULT CHARSET=utf8;

CREATE TABLE rrd_file (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  -- rrdstore_id INT(10) UNSIGNED DEFAULT NULL,
  filename VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  rrd_step INT(10) UNSIGNED NOT NULL COMMENT 'Step size, e.g. 60, 300',
  rrd_version CHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  rrd_header_size INT(10) UNSIGNED NOT NULL,
  rrd_last_update BIGINT(10) UNSIGNED DEFAULT NULL COMMENT 'Last SEEN update',
  rrd_archive_set_checksum VARBINARY(20) NOT NULL,
  deleted ENUM('n', 'y') NOT NULL DEFAULT 'n',
  -- UNIQUE INDEX file_idx (rrdstore_id, filename)
  PRIMARY KEY (id),
  UNIQUE INDEX file_idx (filename),
  CONSTRAINT rrd_file_rrd_archive_set
    FOREIGN KEY rrd_archive_set (rrd_archive_set_checksum)
    REFERENCES rrd_archive_set (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDb DEFAULT CHARSET=utf8;

CREATE TABLE rrd_datasource (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  rrd_file_id BIGINT(20) UNSIGNED NOT NULL,
  datasource_index INT(10) UNSIGNED NOT NULL,
  datasource_name VARCHAR(19) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
    COMMENT '1 to 19 characters long in the characters [a-zA-Z0-9_]',
  datasource_type ENUM('GAUGE', 'COUNTER', 'DERIVE', 'DCOUNTER', 'DDERIVE', 'ABSOLUTE') NOT NULL,
  minimal_heartbeat INT(10) UNSIGNED NOT NULL COMMENT 'Max seconds before "unknown"',
  min_value DOUBLE DEFAULT NULL,
  max_value DOUBLE DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX datasource_idx (rrd_file_id, datasource_index),
  UNIQUE INDEX datasource_name_idx (rrd_file_id, datasource_name),
  CONSTRAINT rrd_datasource_rrd_file
    FOREIGN KEY rrd_file (rrd_file_id)
    REFERENCES rrd_file (id)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDb DEFAULT CHARSET=utf8;

CREATE TABLE pnp_xmlfile (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  filename VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX filename (filename)
) ENGINE=InnoDb DEFAULT CHARSET=utf8;

CREATE TABLE pnp_datasource_info (
  pnp_xmlfile_id BIGINT(20) UNSIGNED NOT NULL,
  rrd_datasource_id BIGINT(20) UNSIGNED NOT NULL,
  icinga_host varchar(255) NOT NULL,
  icinga_service varchar(255) DEFAULT NULL,
  icinga_multi_service varchar(255) DEFAULT NULL,
  datasource_name VARCHAR(255) NOT NULL,
  datasource_label VARCHAR(255) NOT NULL,
  datasource_unit ENUM('s', 'us', 'ms', '%', 'B', 'KB', 'MB', 'TB', 'c') DEFAULT NULL
    COMMENT 'NULL is number - int or float',
  min_value double DEFAULT NULL,
  max_value double DEFAULT NULL,
  threshold_warn DOUBLE DEFAULT NULL,
  threshold_warn_min DOUBLE DEFAULT NULL,
  threshold_warn_max DOUBLE DEFAULT NULL,
  warn_range_type ENUM('inside', 'outside') NULL DEFAULT NULL,
  threshold_crit DOUBLE DEFAULT NULL,
  threshold_crit_min DOUBLE DEFAULT NULL,
  threshold_crit_max DOUBLE DEFAULT NULL,
  crit_range_type ENUM('inside', 'outside') NULL DEFAULT NULL,
  pnp_template varchar(64) NOT NULL,
  pnp_rrd_storage_type enum('SINGLE','MULTIPLE') NOT NULL,
  pnp_rrd_heartbeat int(10) unsigned NOT NULL,
  PRIMARY KEY (pnp_xmlfile_id, rrd_datasource_id),
  UNIQUE INDEX pnp_datasource_idx (pnp_xmlfile_id, datasource_name),
  UNIQUE INDEX pnp_rrd_datasource_idx (rrd_datasource_id),
  INDEX search_idx (icinga_host(64), icinga_service(64), icinga_multi_service(64)),
  CONSTRAINT pnp_datasource_info_rrd_datasource
    FOREIGN KEY rrd_datasource (rrd_datasource_id)
    REFERENCES rrd_datasource (id)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDb DEFAULT CHARSET=utf8;

CREATE TABLE aggregated_daily (
  rrd_datasource_id BIGINT(20) UNSIGNED NOT NULL,
  aggregation_day DATE NOT NULL,
  min_value DOUBLE DEFAULT NULL,
  max_value DOUBLE DEFAULT NULL,
  avg_value DOUBLE DEFAULT NULL,
  stdev_value DOUBLE DEFAULT NULL,
  PRIMARY KEY (rrd_datasource_id, aggregation_day),
  INDEX sort_avg_idx (avg_value),
  INDEX sort_max_idx (max_value),
  INDEX sort_stdev_idx (stdev_value),
  CONSTRAINT aggregated_monthly_rrd_datasource
    FOREIGN KEY rrd_datasource (rrd_datasource_id)
    REFERENCES rrd_datasource (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDb;

CREATE TABLE aggregated_monthly (
  rrd_datasource_id BIGINT(20) UNSIGNED NOT NULL,
  aggregation_month DATE NOT NULL COMMENT 'Please store month as YYYY-MM-01',
  min_value DOUBLE DEFAULT NULL,
  max_value DOUBLE DEFAULT NULL,
  avg_value DOUBLE DEFAULT NULL,
  stdev_value DOUBLE DEFAULT NULL,
  PRIMARY KEY (rrd_datasource_id, aggregation_month),
  INDEX sort_avg_idx (avg_value),
  INDEX sort_max_idx (max_value),
  INDEX sort_stdev_idx (stdev_value),
  CONSTRAINT aggregated_dayly_rrd_datasource
    FOREIGN KEY rrd_datasource (rrd_datasource_id)
    REFERENCES rrd_datasource (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDb;

CREATE TABLE anomaly_checks (
  id INT UNSIGNED NOT NULL,
  check_name VARCHAR(255) NOT NULL,
  last_run DATETIME DEFAULT NULL,
  matches MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX check_name (check_name)
) ENGINE=InnoDb;
