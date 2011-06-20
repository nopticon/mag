CREATE TABLE IF NOT EXISTS _alarms (
	alarm_id INT(11) NOT NULL AUTO_INCREMENT,
	alarm_bio INT(11) NOT NULL,
	alarm_start INT(11) NOT NULL,
	alarm_end INT(11) NOT NULL,
	alarm_bubble INT(11) NOT NULL,
	alarm_email TINYINT(1) NOT NULL,
	PRIMARY KEY (`alarm_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_newsletter (
	newsletter_id INT(11) NOT NULL AUTO_INCREMENT,
	newsletter_bio INT(11) NOT NULL,
	newsletter_receive INT(11) NOT NULL,
	PRIMARY KEY (`newsletter_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_friends (
	friend_id INT(11) NOT NULL AUTO_INCREMENT,
	friend_assoc INT(11) NOT NULL,
	friend_bio INT(11) NOT NULL,
	friend_active INT(11) NOT NULL,
	friend_time INT(11) NOT NULL,
	friend_message VARCHAR(255) NOT NULL,
	PRIMARY KEY (`friend_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_group (
	group_id INT(11) NOT NULL AUTO_INCREMENT,
	group_assoc INT(11) NOT NULL,
	group_bio INT(11) NOT NULL,
	group_time INT(11) NOT NULL,
	PRIMARY KEY (`group_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_rate (
	rate_id INT(11) NOT NULL AUTO_INCREMENT,
	rate_assoc INT(11) NOT NULL,
	rate_bio INT(11) NOT NULL,
	rate_value TINYINT(2) NOT NULL,
	rate_time INT(11) NOT NULL,
	PRIMARY KEY (`rate_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_store (
	store_id INT(11) NOT NULL AUTO_INCREMENT,
	store_bio INT(11) NOT NULL,
	store_field INT(11) NOT NULL,
	store_value TEXT NOT NULL,
	PRIMARY KEY (`store_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_fields (
	field_id INT(11) NOT NULL AUTO_INCREMENT,
	field_alias VARCHAR(25) NOT NULL,
	field_name VARCHAR(50) NOT NULL,
	field_required TINYINT(1) NOT NULL,
	field_show TINYINT(1) NOT NULL,
	field_type VARCHAR(25) NOT NULL,
	field_relation VARCHAR(50) NOT NULL,
	PRIMARY KEY (`field_id`)
) ENGINE=InnoDB;

INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('email_0', 'Email', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('icq', 'ICQ', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('blog', 'Blog', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('website', 'Sitio web', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('location', 'Ubicaci&oacute;n', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('wlive', 'Windows Live', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('lastfm', 'Last FM', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('occ', 'Ocupaci&oacute;n', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('interests', 'Intereses', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('os', 'Sistema operativo', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('fgenres', 'G&eacute;neros musicales', 0, 1, 'text', '');
INSERT INTO _bio_fields (field_alias, field_name, field_required, field_show, field_type, field_relation) VALUES ('fartists', 'Artistas favoritos', 0, 1, 'text', '');

CREATE TABLE _bio (
	bio_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	bio_type MEDIUMINT(5) NOT NULL DEFAULT '0',
	bio_level TINYINT(2) NOT NULL DEFAULT '0',
	bio_active TINYINT(1) NOT NULL DEFAULT '0',
	bio_alias VARCHAR(25) NOT NULL DEFAULT '',
	bio_name VARCHAR(25) NOT NULL DEFAULT '',
	bio_first VARCHAR(50) NOT NULL DEFAULT '',
	bio_last VARCHAR(50) NOT NULL DEFAULT '',
	bio_key VARCHAR(100) NOT NULL DEFAULT '',
	bio_address INT(11) NOT NULL DEFAULT '0',
	bio_gender TINYINT(1) NOT NULL DEFAULT '0',
	bio_birth VARCHAR(8) NOT NULL DEFAULT '',
	bio_birthlast TINYINT(4) NOT NULL DEFAULT '0',
	bio_regip VARCHAR(25) NOT NULL DEFAULT '0',
	bio_regdate INT(11) NOT NULL DEFAULT '0',
	bio_session_time INT(11) NOT NULL DEFAULT '0',
	bio_lastpage VARCHAR(255) NOT NULL DEFAULT '0',
	bio_timezone DECIMAL(5,2) NOT NULL DEFAULT '0.00',
	bio_dst TINYINT(1) NOT NULL DEFAULT '0',
	bio_dateformat VARCHAR(14) NOT NULL DEFAULT '',
	bio_lang VARCHAR(2) NOT NULL DEFAULT '',
	bio_country INT(11) NOT NULL DEFAULT '0',
	bio_avatar VARCHAR(100) NOT NULL DEFAULT '0',
	
	bio_actkey VARCHAR(25) NOT NULL DEFAULT '0',
	bio_recovery INT(11) NOT NULL DEFAULT '0',
	bio_fails MEDIUMINT(5) NOT NULL DEFAULT '0'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_auth (
	auth_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	auth_bio INT(11) NOT NULL DEFAULT '0',
	auth_field INT(11) NOT NULL DEFAULT '0',
	auth_value MEDIUMINT(5) NOT NULL DEFAULT '0'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS _bio_auth_fields (
  field_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  field_alias VARCHAR(50) NOT NULL,
  field_name VARCHAR(50) NOT NULL,
  field_global TINYINT(1) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE _reference (
	ref_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	ref_type TINYINT(3) NOT NULL DEFAULT '0',
	ref_bio INT(11) NOT NULL DEFAULT '0',
	ref_important TINYINT(1) NOT NULL DEFAULT '0',
	ref_approved TINYINT(1) NOT NULL DEFAULT '0',
	ref_subject VARCHAR(255) NOT NULL DEFAULT '',
	ref_content VARCHAR(255) NOT NULL DEFAULT '',
	ref_link VARCHAR(255) NOT NULL DEFAULT '',
	ref_time INT(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB;

CREATE TABLE _reference_likes (
	like_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	like_ref INT(11) NOT NULL,
	like_bio INT(11) NOT NULL,
	like_time INT(11) NOT NULL
) ENGINE = InnoDB;

ALTER TABLE _sessions
	CHANGE session_user_id session_bio_id MEDIUMINT(8) NOT NULL DEFAULT '0';

UPDATE _config SET config_value = '15' WHERE config_name = 'topics_per_page';

DROP TABLE _members_auth;

CREATE TABLE IF NOT EXISTS _bio_auth_fields (
  field_id int(11) NOT NULL auto_increment,
  field_alias varchar(50) NOT NULL,
  field_name varchar(50) NOT NULL,
  field_global tinyint(1) NOT NULL,
  PRIMARY KEY (field_id)
) ENGINE=InnoDB;

CREATE TABLE _reference_likes (
	like_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	like_ref INT(11) NOT NULL,
	like_uid INT(11) NOT NULL,
	like_time INT(11) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE _pages (
	page_id INT( 11 ) NOT NULL ,
	page_alias VARCHAR( 100 ) NOT NULL ,
	page_subject VARCHAR( 255 ) NOT NULL ,
	page_content TEXT NOT NULL ,
	page_tags VARCHAR( 255 ) NOT NULL ,
	page_author INT( 11 ) NOT NULL ,
	page_time INT( 11 ) NOT NULL
) ENGINE = InnoDB;

UPDATE _config SET config_value = '15' WHERE config_name = 'topics_per_page';
UPDATE _config SET config_name = 'site_timezone' WHERE config_name = 'board_timezone';
UPDATE _config SET config_name = 'site_startdate' WHERE config_name = 'board_startdate';
UPDATE _config SET config_name = 'site_dateformat' WHERE config_name = 'default_dateformat';
UPDATE _config SET config_name = 'site_address' WHERE config_name = 'address';
UPDATE _config SET config_name = 'site_lang' WHERE config_name = 'default_lang';
UPDATE _config SET config_name = 'site_dst' WHERE config_name = 'board_dst';
UPDATE _config SET config_name = 'template_time' WHERE config_name = 'xs_template_time';
UPDATE _config SET config_name = 'auto_compile' WHERE config_name = 'xs_auto_compile';
UPDATE _config SET config_name = 'auto_recompile' WHERE config_name = 'xs_auto_recompile';
UPDATE _config SET config_name = 'site_template' WHERE config_name = 'xs_def_template';
UPDATE _config SET config_name = 'check_switches' WHERE config_name = 'xs_check_switches';

DELETE FROM _config
	WHERE config_name IN ('config_id', 'board_email_sig', 'board_email', 'avatar_path', 'avatar_gallery_path', 'smilies_path',
	's_version', 'default_avatar_set', 'default_avatar_users_url', 'default_avatar_guests_url', 'xs_downloads_0', 'xs_downloads_title_0', 
	'default_a_rank', 'num_topics', 'num_posts', 'max_users', 'script_path', 'xs_version', 'max_topics', 'max_posts', 'max_artists',
	'ub_fans_f', 'check_www', 'dl_rate', 'main_ub', 'xs_downloads_count', 'xs_downloads_default', 'main_topics', 'main_poll_f',
	'main_dl', 's_posts', 'max_sig_chars', 'site_desc', 'smtp_delivery', 'smtp_host', 'smtp_username', 'smtp_password',
	'board_email_form', 'gzip_compress', 'enable_confirm', 'sendmail_fix', 'server_name', 'server_port', ', 'xs_ftp_host'',
	'xs_ftp_login', 'xs_ftp_path', 'xs_shownav', 'mailserver_url', 'mailserver_port_url', 'mailserver_news_login', 'mailserver_news_pass',
	'shoutcast_host', 'shoutcast_port', 'shoutcast_code', 'sc_stats_host', 'sc_stats_port', 'sc_stats_ip', 'sc_stats_ipport', 
	'sc_stats_down', 'sc_kick', 'default_pagetitle', 'default_email', 'board_disable', 'prune_enable', 'forum_for_mod', 'forum_for_radio',
	'forum_for_colab', 'forum_for_all', 'cookie_secure', 'flood_interval', 'xs_warn_includes', 'max_login_attempts', 'login_reset_time',
	'xs_use_cache', 'aws', 'a_picnik_key');
