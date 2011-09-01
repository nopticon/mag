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

abstract class project
{
	private $_bio = array();
	
	protected function alias_exists($alias)
	{
		$sql = 'SELECT alias_id
			FROM _alias
			WHERE alias_name = ?';
		return sql_fieldrow(sql_filter($sql, $alias));
	}
	
	protected function country_exists($country)
	{
		$sql = 'SELECT country_id
			FROM _countries
			WHERE country_id = ?';
		return sql_fieldrow(sql_filter($sql, $country));
	}
	
	protected function _bio_publish($address, $key)
	{
		global $warning;
		
		if (empty($address))
		{
			$warning->set('no_bio_address');
		}
		
		if (empty($key))
		{
			$warning->set('no_bio_key');
		}
		
		$v['field'] = (email_format($address) !== false) ? 'address' : 'alias';
		
		// sql
		$sql = 'SELECT bio_id, bio_key, bio_fails
			FROM _bio
			WHERE bio_?? = ?
				AND bio_status = ?';
		if ($_bio = sql_fieldrow(sql_filter($sql, $v['field'], $address, 1)))
		{
			if ($_bio->bio_key === _password($key))
			{
				if ($_bio->bio_fails)
				{
					$sql = 'UPDATE _bio SET bio_fails = 0
						WHERE bio_id = ?';
					sql_query(sql_filter($sql, $_bio->bio_id));
				}
				
				$bio->session_create($_bio->bio_id);
				
				return true;
			}
			
			if ($_bio->bio_fails == $core->v('bio_maxfails'))
			{
				// TODO: Captcha system if maxfail reached
				_fatal(508);
			}
			
			$sql = 'UPDATE _bio SET bio_fails = bio_fails + 1
				WHERE bio_id = ?';
			sql_query(sql_filter($sql, $_bio->bio_id));
			
			sleep(5);
			$warning->set('login_error');
		}
		
		$alias = _low($this->extract_alias($address));
		$alias_len = strlen($v['nickname']);
		
		if (($alias_len < 1) || ($alias_len > 20))
		{
			$warning->set('alias_len');
		}
		
		// TODO: Continue work
		
		return;
	}
	
	function page_query($v)
	{
		$sql = 'SELECT *
			FROM _pages
			WHERE page_alias = ?';
		if (!$page = sql_fieldrow(sql_filter($sql, $v))) {
			return false;
		}
		
		return $page;
	}
	
	//
	// TODO: Check runtime expected operation on every combination!
	//
	protected function auth_forum($forum, $mode)
	{
		global $bio;
		static $groups;
		
		// BOARD_AUTH_GUEST | BOARD_AUTH_BIO | BOARD_AUTH_GROUP | BOARD_AUTH_ADMIN
		
		if ($forum->forum_access != BOARD_AUTH_GUEST)
		{
			if (!$bio->v('is_bio')) {
				return false;
			}
			
			if ($bio->v('auth_topic_' . $mode . '_' . $forum['forum_id'])) {
				return false;
			}
		}
		
		$arg = (object) json_decode($forum->forum_access_arg, true);
		
		switch ($forum->forum_access)
		{
			case BOARD_AUTH_GUEST:
				return true;
				break;
			case BOARD_AUTH_BIO:
				return true;
				break;
			case BOARD_AUTH_GROUP:
				if (!isset($arg->group)) {
					return false;
				}
				
				if (!isset($groups)) {
					$sql = 'SELECT group_id
						FROM _groups g, _groups_assoc a
						WHERE g.group_id = a.assoc_group
							AND a.assoc_bio = ?';
					$groups = sql_rowset(sql_filter($sql, $bio->v('bio_id')));
				}
				
				if (is_array($groups)) {
					foreach ($arg->group as $row) {
						if (in_array($row, $groups)) return true;
					}
				}
				break;
			case BOARD_AUTH_ADMIN:
				return $bio->v('auth_admin');
				break;
		}
		return false;
	}
	
	protected function _analytics_store()
	{
		global $bio, $core;
		
		list($h, $d, $m, $y) = explode(' ', gmdate('G j n Y', _localtime()));
		
		$sql = 'SELECT *
			FROM _stats
			WHERE stat_page = ?
				AND stat_hour = ?
				AND stat_day = ?
				AND stat_month = ?
				AND stat_year = ?';
		if ($stat = sql_fieldrow(sql_filter($sql, $bio->page, $h, $d, $m, $y)))
		{
			$field_stat = ($bio->v('auth_member')) ? 'member' : 'guest';
			
			$sql = 'UPDATE _stats SET stat_' . $field_stat . ' = stat_' . $field_stat . ' + 1
				WHERE stat_id = ?';
			$sql = sql_filter($sql, $stat->stat_id);
			sql_run($sql);
		}
		else
		{
			if ($bio->v('auth_member')) {
				$sql_insert = array(
					
				);
			}
			else
			{
				$sql_insert = array(
					
				);
			}
			
			sql_put('_stats', $sql_insert);
		}
		
		return;
	}
	
	final protected function query_bio($bio, $exclude = false, $sql = false)
	{
		if ($sql === false)
		{
			$sql = 'SELECT *
				FROM _bio
				WHERE bio_id = ?
					AND bio_status = ?';
		}
		
		if (!$_bio = sql_fieldrow(sql_filter($sql, $bio, 1))) {
			return false;
		}
		
		if ($exclude === false) {
			$exclude = w();
		}
		$exclude[] = 'bio_key';
		
		foreach ($exclude as $row)
		{
			if ($_bio->$row) unset($_bio->$row);
		}
		
		return $_bio;
	}
	
	final protected function bio_exists($bio)
	{
		$f = is_numb($bio) ? 'id' : 'alias';
		
		$sql = 'SELECT bio_id
			FROM _bio
			WHERE bio_?? = ?
				AND bio_status = ?';
		if (!sql_field(sql_filter($sql, $f, $bio), 'bio_id', 1))
		{
			return false;
		}
		
		return true;
	}
	
	final protected function bio_follower($local, $remote = false)
	{
		if ($remote === false)
		{
			global $bio;
			
			$remote = $bio->v('bio_id');
		}
		
		$sql = 'SELECT follower_id
			FROM _bio_followers
			WHERE follower_local = ?
				AND follower_remote = ?
				AND follower_active = ?
			LIMIT 1';
		if (sql_field(sql_filter($sql, $local, $remote, 1), 'follower_id', 0))
		{
			return true;
		}
		
		return false;
	}
	
	protected function announce($block = false)
	{
		global $bio, $core;
		
		if (!$announce = $core->cache_load('announce'))
		{
			$sql = 'SELECT *
				FROM _announce a, _announce_block b
				WHERE a.announce_block = b.block_id
				ORDER BY a.announce_order';
			$announce = $core->cache_store(sql_rowset($sql));
		}
		
		$i = 0;
		foreach ($announce as $row)
		{
			if ($block != $row['block_alias']) continue;
			
			if (!$i)
			{
				_style('announce');
				_style('announce.' . $row['block_alias']);
			}
			
			_style('announce.' . $row['block_alias'] . '.row', _vs(array(
				'URL' => _link('announce', $row['announce_alias']),
				'IMAGE' => _lib(LIB_BASE, $row['banner_id'], 'gif'),
				'ALT' => $row['announce_alt']
			), 'v'));
			$i++;
		}
		
		return;
	}
	
	protected function bio_premium()
	{
		global $bio;
		
		$response = ($bio->v('auth_premium') && (!$bio->v('bio_premium_til') || $bio->v('bio_premium_til') > time())) ? true : false;
		return $response;
	}
	
	final protected function monetize()
	{
		if (!$this->bio_premium())
		{
			_style('monetize');
		}
		return;
	}
	
	//
	// Messaging
	final protected function _replies($f)
	{
		global $bio;
		
		$rf_k = $rf_v = w();
		foreach ($f as $k => $v)
		{
			$rf_k[] = '{' . strtoupper($k) . '}';
		}
		$rf_v = array_values($f);
		
		$f['sql'] = str_replace($rf_k, $rf_v, $f['sql']);
		
		if (!$rows = _rowset($f['sql']))
		{
			return;
		}
		
		// TODO: Control Panel. Modify & remove comments
		
		$bio = w();
		foreach ($rows as $i => $row)
		{
			if (!$i)
			{
				_style($f['block'], _vs(_pagination($f['ref'], $f['start_f'] . ':%d', $f['rows'], $f['rows_page'], $f['start'])));
			}
			
			$uid = $row['bio_id'];
			$row['is_member'] = ($uid != 1) ? 1 : 0;
			
			if (!isset($bio[$uid]) || !$row['is_member'])
			{
				$bio[$uid] = $this->_profile($row);
			}
			
			$s_row = array(
				//'V_POST' => $row['post_id'],
				'V_MEMBER' => $row['is_member'],
				'V_TIME' => _format_date($row['post_time']),
				'V_MESSAGE' => _message($row['post_text'])
			);
			_style($f['block'] . '.row', array_merge($s_row, _vs($bio[$uid], 'v')));
		}
		
		return;
	}

	final protected function share_list()
	{
		global $core;
		
		if (!$share_sites = $core->cache->load('share_sites'))
		{
			$sql = 'SELECT share_id, share_site, share_alias, share_name
				FROM _share_sites
				ORDER BY share_order';
			$share_sites = $core->cache->store(sql_rowset($sql, 'share_alias'));
		}
		
		return $share_sites;
	}
	
	final protected function share($site, $page)
	{
		$share = $this->share_list();
		$page = urlencode($page);
		
		if (!isset($share[$site]))
		{
			return $page;
		}
		
		return sprintf($share[$site]['share_site'], $page);
	}
	
	protected function select_string()
	{
		$response = '';
		
		$a = func_get_args();
		foreach ($a as $e)
		{
			if (is_string($e) && f($e))
			{
				$response = $e;
				break;
			}
		}
		
		return $response;
	}
	
	protected function bio_online()
	{
		global $bio, $core;
		
		$total = (object) array(
			'now' => (object) w('active 0 inactive 0 robot 0 alien 0'),
			'day' => (object) w('active 0 inactive 0 robot 0 alien 0')
		);
		
		$today = _timestamp();
		$robots = $bio-bots();
		
		foreach ($total as $period => &$object)
		{
			$this->bio_online_query($period, $object, $today, $robots);
		}
		
		return $total;
	}
	
	protected function bio_online_query($period, &$object, $today, $robots)
	{
		global $bio, $core;
		
		switch ($period) {
			case 'now':
				$sql = 'SELECT b.bio_id, b.bio_alias, b.bio_name, b.bio_status, s.session_ip
					FROM _bio b, _sessions s
					WHERE s.session_time >= ??
						AND b.bio_id = s.session_bio
					ORDER BY b.bio_name, s.session_ip';
				$sql = sql_filter($sql, ($local_time[0] - ($core->v('bio_now') * 60)));
				break;
			case 'day':
				$sql = 'SELECT bio_id, bio_alias, bio_name, bio_status
					FROM _bio
					WHERE bio_id <> ?
						AND bio_lastvisit >= ?
						AND bio_lastvisit < ?
					ORDER BY bio_name';
				$sql = sql_filter($sql, 1, $time_today, ($time_today + 86400));
				break;
			default:
				$sql = 'SELECT b.bio_id, b.bio_alias, b.bio_name, b.bio_level, b.bio_show, s.session_ip
					FROM _bio b, _sessions s
					WHERE ((s.session_time >= ?
							AND b.bio_id = s.session_bio)
						OR (b.bio_lastvisit >= ?
							AND b.bio_lastvisit < ?)
						)
					ORDER BY b.bio_name';
				$sql = sql_filter($sql);
				break;
		}
		
		
		$sessions = sql_rowset($sql);
		
		$i = 0;
		foreach ($sessions as $row)
		{
			// Guest
			if ($row->bio_id == 1) {
				if ($row->session_ip != $last_ip) {
					$object->alien++;
				}
				
				$last_ip = $row->session_ip;
				continue;
			}
			
			// Member
			if ($row->bio_id != $last_bio_id) {
				$is_bot = isset($bots[$row->bio_id]);
				
				if ($is_bot) {
					$object->robot++;
				} else if ($row->bio_status) {
					$object->active++;
				} else {
					$object->inactive++;
				}
			}
			
			//
			$last_bio_id = $row->bio_id;
			$i++;
		}
		
		return;
	}
}

function _link_bio($alias, $attr = false, $ts = true)
{
	$arg = array('alias' => $alias);
	if ($attr !== false)
	{
		$arg = array_merge($arg, $attr);
	}
	
	if (isset($attr['x1']))
	{
		$x1 = $attr['x1'];
		unset($attr['x1']);
		
		$attr = array_unshift($attr, $x1);
	}
	
	return _link('bio', $attr, $ts);
}

function _prepare_extra($message)
{
	global $bio;
	
	if ($bio->v('auth_founder') && preg_match('#\[chown\:' . _alias_regex() . '\]#i', $message, $chown))
	{
		$sql = 'SELECT *
			FROM _bio
			WHERE bio_alias = ?';
		if ($row = sql_fieldrow(sql_filter($sql, $chown[1])))
		{
			$sql = 'UPDATE _bio SET bio_lastvisit = ?
				WHERE bio_id = ?';
			sql_query(sql_filter($sql, time(), $row['bio_id']));
			
			unset($row['bio_key']);
			$bio->replace($row);
			
			$message = str_replace('[chown:' . $chown[1] . ']', '', $message);
		}
	}
	
	if ($bio->v('auth_extra_html'))
	{
		$allow_tags = w('embed div span img table tr td th');
		
		$ptags = str_replace('*', '.*?', implode('|', $allow_tags));
		$message = preg_replace('#&lt;(\/?)(' . $ptags . ')&gt;#is', '<$1$2>', $message);
		
		if (preg_match_all('#&lt;(' . $ptags . ') (.*?)&gt;#is', $message, $in_quotes))
		{
			$repl = array('&lt;' => '<', '&gt;' => '>', '&quot;' => '"');
			
			foreach ($in_quotes[0] as $row)
			{
				$message = preg_replace('#' . preg_quote($row, '#') . '#is', str_replace(array_keys($repl), array_values($repl), $row), $message);
			}
		}
	}
	
	return $message;
}

function _a($v)
{
	if (!isset($v['bio_alias'])) return _link();
	
	return _link($v['bio_alias']);
}

function _avatar($v)
{
	if (!f($v['bio_avatar']))
	{
		return _lib('d', 'no', 'jpg');
	}
	
	$path = array($v['bio_alias']{0}, $v['bio_alias']{1}, $v['bio_alias']);
	return _lib(LIB_AVATAR, _implode('/', $path), $v['bio_avatar']) . '#' . $v['bio_avatar_up'];
}

function _rainbow_create($uid)
{
	$key = substr(unique_id(), 0, 6);
	
	$sql_insert = array(
		'key' => $key,
		'uid' => $uid,
		'time' => time()
	);
	sql_put('_rainbow', prefix('rainbow', $sql_insert));
	
	return $key;
}

function _lib($lib, $filename, $extension = false)
{
	if (is_array($lib))
	{
		$lib = implode('/', $lib);
	}
	return LIBD . $lib . '/' . $filename . (($extension !== false) ? '.' . $extension : '');
}

function _alias_regex()
{
	return '([0-9a-z\-\_]+)';
}

?>