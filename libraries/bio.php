<?php
/*
<NPT, a web development framework.>
Copyright (C) <2009>  <NPT>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
if (!defined('XFS')) exit;

class bio {
	protected $base;
	protected $queue;
	protected $auth;
	protected $lang;
	
	protected $page;
	protected $browser;
	protected $ip;
	protected $bio;
	protected $session;
	protected $date_format;
	protected $timezone;
	protected $dst;
	
	public function __construct() {
		$this->queue = new stdClass;
		$this->base = new stdClass;
		
		$this->uid = '';
		$this->session = '';
		
		$this->page = _page();
		$this->browser = v_server('HTTP_USER_AGENT');
		$this->ip = htmlspecialchars(v_server('REMOTE_ADDR'));
		
		foreach (w('write replace remove') as $row) {
			$this->queue->$row = w();
		}
		
		return;
	}
	
	public function select($value, $session = false) {
		if ($session) {
			$sql = 'SELECT *
				FROM _sessions s
				INNER JOIN _bio b ON b.bio_id = s.session_bio_id
				WHERE s.session_id = ?';
		} else {
			$sql = 'SELECT *
				FROM _bio
				WHERE bio_id = ?';
		}
		
		$result = sql_fieldrow(sql_filter($sql, $value));
		
		if (isset($result->bio_key)) unset($result->bio_key);
		
		$sql = 'SELECT *
			FROM _bio_auth_property
			LEFT JOIN _bio_auth ON property_profile = auth_profile
			INNER JOIN _bio_auth_profile ON property_profile = profile_id
			INNER JOIN _bio_auth_field ON property_field = field_id
			WHERE auth_bio = ?
			ORDER BY field_alias';
		$result->bio_auth = sql_rowset(sql_filter($sql, $result->bio_id));
		
		return $result;
	}
	
	public function start($_update = true) {
		global $core;
		
		if (array_strpos($this->page, w('ext')) !== false) {
			$_update = false;
		}
		
		$this->uid = request_var($core->v('cookie_name') . '_u', 0);
		$this->session = request_var($core->v('cookie_name') . '_sid', '');
		
		if (!empty($this->session) && ($this->base = $this->select($this->session, true))) {
			$s_ip = implode('.', array_slice(explode('.', $this->base->session_ip), 0, 4));
			$b_ip = implode('.', array_slice(explode('.', $this->ip), 0, 4));
			
			if ($b_ip == $s_ip && $this->base->session_browser == $this->browser) {
				// Only update session a minute or so after last update or if page changes
				if (time() - $this->base->session_time > 60 || $this->base->session_page != $this->page) {
					$sql_update = array('session_time' => time());
					if ($_update) {
						$sql_update['session_page'] = $this->page;
					}
					
					$sql = 'UPDATE _sessions SET ' . sql_build('UPDATE', $sql_update) . sql_filter('
						WHERE session_id = ?', $this->session);
					sql_query($sql);
				}
				
				if ($_update) {
					$this->base->session_page = $this->page;
				}
				
				if ($this->v('is_bio')) {
					return true;
				}
			}
		}
		
		// Create new session if no valid exists.
		return $this->session_create(false, $_update);
	}

	/**
	* Create a new session
	*
	* If upon trying to start a session we discover there is nothing existing we
	* jump here. Additionally this method is called directly during login to regenerate
	* the session for the specific user. In this method we carry out a number of tasks;
	* garbage collection, (search)bot checking, banned user comparison. Basically
	* though this method will result in a new session for a specific user.
	*/
	public function session_create($bio_id = false, $_update = true) {
		global $core;
		
		$this->base = w();
		
		// Garbage collection. Remove old sessions updating user information
		// if necessary. It means (potentially) 11 queries but only infrequently
		if (time() > $core->v('session_last_gc') + $core->v('session_gc')) {
			$this->session_gc();
		}
		
		// If we've been passed a bio_id we'll grab data based on that
		if ($bio_id !== false) {
			$this->cookie['u'] = $bio_id;
			$this->base = $this->select($this->cookie['u']);
		}
		
		// If no data was returned one or more of the following occured:
		// User does not exist
		// User is inactive
		// User is bot
		if (!count($this->base) || !is_array($this->base)) {
			$this->cookie['u'] = 1;
			$this->base = $this->select($this->cookie['u']);
		}
		
		$this->base->session_last_visit = time();
		
		if ($this->base->bio_id != 1) {
			$sql = 'SELECT session_time, session_id
				FROM _sessions
				WHERE session_bio_id = ?
				ORDER BY session_time DESC
				LIMIT 1';
			if ($result = sql_fieldrow(sql_filter($sql, $this->base->bio_id))) {
				$this->base = array_merge($this->base, $result);
				$this->session = $this->base->session_id;
				unset($result);
  		}
			
			$this->base->session_last_visit = (isset($this->base->session_time) && $this->base->session_time) ? $this->base->session_time : (($this->base->bio_lastvisit) ? $this->base->bio_lastvisit : time());
		}
		
		// Create or update the session
		$sql_ary = array(
			'session_bio_id' => $this->base->bio_id,
			'session_start' => time(),
			'session_last_visit' => $this->base->session_last_visit,
			'session_time' => time(),
			'session_browser' => (string) $this->browser,
			'session_ip' => (string) $this->ip
		);
		
		if ($_update) {
			$sql_ary['session_page'] = (string) $this->page;
			$this->base->session_page = $sql_ary['session_page'];
		}
		
		$run_update = false;
		if ($this->session) {
			$run_update = true;
			
			$sql = 'UPDATE _sessions SET ' . sql_build('UPDATE', $sql_ary) . sql_filter('
				WHERE session_id = ?', $this->session);
			sql_query($sql);
		}
		
		if (!$this->session || ($run_update && !sql_affectedrows())) {
			$this->session = $this->base->session_id = $sql_ary['session_id'] = (string) md5(unique_id());
			
			$sql = 'INSERT INTO _sessions' . sql_build('INSERT', $sql_ary);
			sql_query($sql);
		}
		
		$this->set_cookie('u', $this->cookie['u'], (time() + 31536000));
		$this->set_cookie('sid', $this->session, 0);
		
		return true;
	}
	
	/* 
	 * session_kill
	 * 
	 * Delete existing session, update last visit info first!
	 */
	public function session_kill() {
		$sql = 'DELETE FROM _sessions
			WHERE session_id = ?
				AND session_bio_id = ?';
		_sql(sql_filter($sql, $this->session, $this->base['bio_id']));
		
		if ($this->base['bio_id'] != 1) {
			$sql = 'UPDATE _bio
				SET bio_lastvisit = ?
				WHERE bio_id = ?';
			_sql(sql_filter($sql, $this->base['session_time'], $this->base['bio_id']));
			
			$this->base = $this->select(1);
		}
		
		$cookie_expire = time() - 31536000;
		$this->set_cookie('u', '', $cookie_expire);
		$this->set_cookie('sid', '', $cookie_expire);
		unset($cookie_expire);
		
		$this->session = '';
		
		return true;
	}
	
	/**
	* Session garbage collection
	*
	* Effectively we are deleting any sessions older than an admin definable 
	* limit. Due to the way in which we maintain session data we have to 
	* ensure we update user data before those sessions are destroyed.
	*/
	public function session_gc() {
		global $core;
		
		// Get expired sessions, only most recent for each user
		$sql = 'SELECT session_bio_id, session_page, MAX(session_time) AS recent_time
			FROM _sessions
			WHERE session_time < ' . (time() - $core->v('session_length')) . '
			GROUP BY session_bio_id, session_page
			LIMIT 5';
		$sessions = sql_rowset($sql);
		
		$del_bio_id = array();
		$del_sessions = 0;
		foreach ($sessions as $row) {
			if ($row->session_bio_id != 1) {
				$sql = 'UPDATE _bio
					SET bio_lastvisit = ?, bio_lastpage = ?
					WHERE bio_id = ?';
				sql_query(sql_filter($sql, $row->recent_time, $row->session_page, $row->session_bio_id));
			}
			
			$del_bio_id[] = $row->session_bio_id;
			$del_sessions++;
		}
		
		if ($del_bio_id) {
			// Delete expired sessions
			$sql = 'DELETE FROM _sessions
				WHERE session_bio_id IN (??)
					AND session_time < ?';
			sql_query(sql_filter($sql, implode(',', $del_bio_id), (time() - $core->v('session_length'))));
		}
		
		// Less than 5 sessions, update gc timer ... else we want gc
		// called again to delete other sessions
		if ($del_sessions < 5) {
			$core->v('session_last_gc', time());
		}

		return;
	}
	
	/**
	* Sets a cookie
	*
	* Sets a cookie of the given name with the specified data for the given length of time.
	*/
	public function set_cookie($name, $cookiedata, $cookietime, $onlyhttp = false) {
		global $core;
		
		$name = $core->v('cookie_name') . '_' . $name;
		$domain = ($core->v('cookie_domain') != 'localhost') ? $core->v('cookie_domain') : '';
		$secure = (v_server('SERVER_PORT') === 443) ? true : false;
		
		setcookie($name, $cookiedata, $cookietime, $core->v('cookie_path'), $domain, $secure, $onlyhttp);
		return true;
	}
	
	public function browser() {
		return $this->browser;
	}
	
	public function auth_bio($bio) {
		return ($bio !== false) ? $bio : $this->v('bio_id');
	}
	
	public function auth_all($bio = false) {
		$sql = 'SELECT *
			FROM _bio_auth a, _bio_auth_fields f
			WGERE a.auth_bio = ?
				AND a.auth_field = f.field_id
			ORDER BY f.field_name';
		$this->auth[$bio] = _rowset(sql_filter($sql, $bio_id), 'field_alias', 'auth_value');
	}
	
	public function auth_verify($key = false, $bio = false) {
		$bio = $this->auth_bio($bio);
		
		if ($key !== false) {
			return (isset($this->auth[$bio][$key]));
		}
		
		return (isset($this->auth[$bio]));
	}
	
	public function auth_read($key, $bio = false) {
		$bio = $this->auth_bio($bio);
		
		if (!$this->auth_verify($key, $bio)) {
			return false;
		}
		
		return true;
	}
	
	public function auth_write($key, $value, $bio = false) {
		$bio = $this->auth_bio($bio);
		
		if ($this->auth_verify($key, $bio)) {
			return false;
		}
		
		$this->auth_queue('write', $key, $bio);
		$this->auth[$bio][$key] = $value;
		
		return true;
	}
	
	public function auth_replace2($key, $value, $bio = false) {
		$bio = $this->auth_bio($bio);
		
		$this->auth_queue('replace', $key, $bio);
		$this->auth[$key][$bio] = $value;
		
		return true;
	}
	
	public function auth_remove2($key, $bio = false) {
		$bio = $this->auth_bio($bio);
		
		if (!$this->auth_verify($key, $bio)) {
			return false;
		}
		
		$this->auth_queue('remove', $key, $bio);
		unset($this->auth[$bio][$key]);
		
		return true;
	}
	
	public function auth_queue() {
		
	}
	
	public function auth_queue_process() {
		foreach ($this->queue as $i => $row) {
			switch ($i) {
				case 'create':
					break;
				case 'update':
					break;
				case 'remove':
					break;
			}
		}
	}
	
	public function v($d = false, $v = false) {
		if ($d === false) {
			if (!$this->base) {
				return false;
			}
			
			return $this->base;
		}
		
		$mode = array_key(explode('_', $d), 0);
		$key = str_replace($mode . '_', '', $d);
		$response = false;
		
		switch ($mode) {
			case 'is':
				$bio_id = $this->v('bio_id');
				
				switch ($key) {
					case 'bio':
						if (!isset($this->auth_bio[$bio_id][$d])) {
							$this->auth_bio[$bio_id][$d] = ($this->base->bio_id > 1 && $this->base->bio_active);
						}
						
						return $this->auth_bio[$bio_id][$d];
						break;
					default:
						break;
				}
				break;
			case 'auth':
				$bio_id = $this->v('bio_id');
				
				if (!isset($this->auth_bio[$bio_id])) {
					$sql = 'SELECT *
						FROM _bio_auth a, _bio_auth_fields f
						WHERE a.auth_bio = ?
							AND a.auth_field = f.field_id
						ORDER BY f.field_name';
					$this->auth_bio[$bio_id] = _rowset(sql_filter($sql, $bio_id), 'field_alias', 'auth_value');
				}
				
				/*
					// Ultimately to be removed
					$this->base['is_member'] = ($this->base['bio_id'] != 1) ? true : false;
					$this->base['is_founder'] = ($this->base['bio_id'] != 1 && $this->base['bio_level'] == 4) ? true : false;
					$this->base['is_bot'] = false;
					
					if ($this->base['is_member'])
					{
						return true;
					}
				 */
				
				/*
				case 'founder':
					$response = ($this->base['bio_id'] != 1 && $this->base['bio_level'] == 4);
					break;
				case 'identity':
					$response = ($this->base['bio_id'] != 1);
					break;
				case 'nameless':
					$response = ($this->base['bio_id'] == 1);
					break;
				case 'robot':
					
					break;
				*/
				
				$response = (isset($this->auth_bio[$bio_id][$key])) ? true : false;
				break;
			case 'bio';
				switch ($key) {
					case 'age':
						if (!isset($this->base[$d])) {
							// TODO: Calculate age based on birthday
						}
						break;
				}
				
				if ($v !== false) {
					$this->base[$d] = $v;
				}
				
				$response = (isset($this->base->$d)) ? $this->base->$d : false;
				break;
			case 'session':
				if ($v !== false) {
					$this->{$key} = $v;
				}
				
				$response = (isset($this->$key)) ? $this->{$key} : false;
				break;
			default:
				break;
		}
		
		return $response;
	}
	
	public function replace($a) {
		$this->base = $a;
		return true;
	}
	
	public function setup($tpl = '') {
		global $style, $core;
		
		$this->base->bio_lang = $core->v('site_lang');
		$this->date_format = $this->v('bio_dateformat');
		$this->timezone = $this->v('bio_timezone') * 3600;
		$this->dst = $this->v('bio_dst') * 3600;
		
		// Load global language
		$this->load_lang('main');
		$tpl = _tbrowser($tpl);
		
		// Load UI template
		$style->set_template(XFS.XHTM . ((!empty($tpl)) ? $tpl : ''));
		return;
	}
	
	public function load_lang($f, $d = false) {
		$lang = w();
		
		if ($d === false) {
			global $core;
			
			$d = $core->v('site_lang');
		}
		
		$filepath = XFS.XCOR . 'lang/' . $d . '/' . $f . '.php';
		if (@file_exists($filepath)) {
			require_once($filepath);
		}
		
		$this->lang += $lang;
		return true;
	}
	
	public function _lang_check() {
		return (sizeof($this->lang));
	}
	
	public function _lang() {
		$f = func_get_args();
		if ($this->is_lang($f[0])) {
			return array_construct($this->lang, array_map('strtoupper', $f));
		}
		
		return $f[0];
	}
	
	public function _lang_set($k, $v) {
		$this->lang[strtoupper($k)] = $v;
		return true;
	}
	
	public function is_lang($k) {
		if (is_array($k)) {
			return false;
		}
		
		return isset($this->lang[strtoupper($k)]);
	}
	
	public function time_diff($timestamp, $detailed = false, $n = 0) {
		// If the difference is positive "ago" - negative "away"
		$now = time();
		$action = ($timestamp >= $now) ? 'away' : 'ago';
		$diff = ($action == 'away' ? $timestamp - $now : $now - $timestamp);
		
		// Set the periods of time
		$periods = w('s m h d s m a');
		$lengths = array(1, 60, 3600, 86400, 604800, 2630880, 31570560);
		
		// Go from decades backwards to seconds
		$result = w();
		
		$i = count($lengths);
		$time = '';
		while ($i >= $n) {
			$item = $lengths[$i - 1];
			if ($diff < $item) {
				$i--;
				continue;
			}
			
			$val = floor($diff / $item);
			$diff -= ($val * $item);
			$result[] = $val . $periods[($i - 1)];
			
			if (!$detailed) {
				$i = 0;
			}
			$i--;
		}
		
		return (count($result)) ? $result : false;
	}
	
	public function format_date($gmepoch, $format = false, $forcedate = false) {
		static $lang_dates, $midnight;
		
		if (empty($lang_dates)) {
			foreach ($this->lang['datetime'] as $match => $replace) {
				$lang_dates[$match] = $replace;
			}
		}
		
		$format = (!$format) ? $this->date_format : $format;
		
		if (!$midnight) {
			list($d, $m, $y) = explode(' ', gmdate('j n Y', time() + $this->timezone + $this->dst));
			$midnight = gmmktime(0, 0, 0, $m, $d, $y) - $this->timezone - $this->dst;
		}
		
		if ((strpos($format, '\M') === false && strpos($format, 'M') !== false) || (strpos($format, '\r') === false && strpos($format, 'r') !== false)) {
			$lang_dates['May'] = $this->lang['datetime']['May_short'];
		}
		
		if ($forcedate != false) {
			$a = $this->time_diff($gmepoch, 1, 2);
			if ($a !== false) {
				if (count($a) < 4) {
					return implode(' ', $a);
				}
			} else {
				return $this->_lang('AGO_LESS_MIN');
			}
		}
		
		return strtr(@gmdate(str_replace('|', '', $format), $gmepoch + $this->timezone + $this->dst), $lang_dates);
	}
	
	public function _groups() {
		global $core;
		
		if (!$groups = $core->cache_load('groups')) {
			$sql = 'SELECT *
				FROM _groups
				ORDER BY group_name';
			$groups = $core->cache_store(_rowset($sql, 'group_id'));
		}
		return $groups;
	}
	
	public function auth_founder($bio) {
		global $core;
		
		if (!$founders = $core->cache_load('founders')) {
			// TODO: Make SQL to get founders profiles
			
			$sql = 'SELECT b.bio_id
				FROM _bio b
				INNER JOIN _bio_profile p ON b.bio_id = p.profile_bio 
				WHERE bio_active = ?';
			$founders = $core->cache_store(_rowset(sql_filter($sql, 1), 'bio_id'));
		}
		
		return (is_array($founders) && in_array($uid, array_keys($founders)));
	}
	
	public function auth_groups($uid = false) {
		if ($uid === false) {
			$uid = $this->v('bio_id');
		}
		
		$groups = w();
		if ($this->auth_founder($uid)) {
			$groups = array_keys($this->_groups());
		}
		
		if (!count($groups)) {
			$sql = 'SELECT g.group_id
				FROM _groups g, _groups_members gm
				WHERE g.group_id = gm.member_group
					AND gm.member_uid = ?';
			$groups = _rowset(sql_filter($sql, $uid), false, 'group_id');
		}
		
		return _implode(',', $groups);
	}
	
	public function auth_list() {
		global $core;
		
		if (!$fields = $core->cache_load('auth_fields')) {
			$sql = 'SELECT *
				FROM _bio_auth_field
				ORDER BY field_alias';
			$fields = $core->cache_store(_rowset($sql, 'field_id'));
		}
		
		return $fields;
	}
	
	public function auth($k, $v = -1, $uid = false) {
		global $bio;
		
		if ($uid === false) {
			$uid = $bio->v('bio_id');
		}
		
		if ($v !== -1) {
			$this->auth[$uid][$k] = $v;
		}
		
		return (isset($this->auth[$uid][$k])) ? $this->auth[$uid][$k] : false;
	}
	
	public function auth_replace($orig, $repl, $uid = false) {
		if (!$this->auth_get($repl, $uid)) {
			return false;
		}
		
		if ($uid === false) {
			$uid = $this->v('bio_id');
		}
		
		$auth_fields = $this->auth_list();
		
		$this->auth[$uid][$orig] = true;
		
		return $this->auth_get($orig, $uid);
	}
	
	public function auth_get($name, $uid = false, $global = false) {
		if ($uid === false) {
			$uid = $this->v('bio_id');
		}
		
		if ($this->auth_founder($uid)) {
			return true;
		}
		
		// Get auth fields
		$auth_fields = $this->auth_list();
		
		// Get all auth for uid
		if (!isset($this->auth[$uid])) {
			$this->auth[$uid] = w();
			
			$sql = 'SELECT *
				FROM _bio_auth
				WHERE auth_bio = ?';
			$auth = _rowset(sql_filter($sql, $uid));
			
			foreach ($auth as $row) {
				if (!isset($row['auth_field'])) {
					continue;
				}
				$this->auth[$uid][$auth_fields[$row['auth_field']]['field_alias']] = true;
			}
		}
		
		$name = _alias($name, w('-'));
		
		$response = false;
		if (isset($this->auth[$uid][$name])) {
			$response = true;
		}
		
		if ($response === false) {
			$field_found = false;
			foreach ($auth_fields as $row) {
				if ($name === $row['field_alias']) {
					$field_found = true;
					break;
				}
			}
			
			if (!$field_found) {
				global $core;
				
				$sql_insert = array(
					'alias' => $name,
					'name' => $name
				);
				$sql = 'INSERT INTO _bio_auth_field' . _build_array('INSERT', prefix('field', $sql_insert));
				_sql($sql);
				
				$core->cache_unload();
				
				if ($global) {
					$response = true;
				}
			}
		}
		
		return $response;
	}
	
	public function auth_update($f, $v = false, $uid = false) {
		global $core;
		
		if ($uid === false) {
			$uid = $this->v('bio_id');
		}
		
		$field = $this->auth_field($f);
		if ($field !== false) {
			$cv = isset($this->auth[$uid][$field['field_alias']]);
			
			switch ($v) {
				case true:
					if ($cv) {
						return;
					}
					
					$sql_insert = array(
						'bio' => $uid,
						'field' => $field['field_id']
					);
					$sql = 'INSERT INTO _bio_auth' . _build_array('INSERT', prefix('auth', $sql_insert));
					_sql($sql);
					
					$this->auth[$uid][$field['field_alias']] = true;
					break;
				case false:
					if (!$cv) {
						return;
					}
					
					$sql = 'DELETE FROM _bio_auth
						WHERE auth_bio = ?
							AND auth_field = ?';
					_sql(sql_filter($sql, $uid, $field['field_id']));
					
					unset($this->auth[$uid][$field['field_alias']]);
					break;
			}
			
			$core->cache_unload();
		}
		
		return;
	}
	
	public function auth_remove($f, $uid = false) {
		global $core;
		
		if ($uid === false) {
			$uid = $this->v('bio_id');
		}
		
		$field = $this->auth_field($f);
		if ($field !== false) {
			if (!isset($this->auth[$uid][$field['field_alias']])) {
				return;
			}
			
			$sql = 'DELETE FROM _bio_auth
				WHERE auth_bio = ?
					AND auth_field = ?';
			_sql(sql_filter($sql, $uid, $field['field_id']));
			
			unset($this->auth[$uid][$field['field_alias']]);
			$core->cache_unload();
		}
		
		return;
	}
	
	public function auth_field($f) {
		$ff = (is_numb($f)) ? 'id' : 'alias';
		
		$sql = 'SELECT *
			FROM _bio_auth_fields
			WHERE field_?? = ?';
		if (!$field = _fieldrow(sql_filter($sql, $ff, $f))) {
			return false;
		}
		return $field;
	}
}

?>