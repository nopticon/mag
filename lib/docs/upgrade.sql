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