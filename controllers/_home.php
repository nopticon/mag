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

interface i_home
{
	public function home();
	public function like();
	public function status();
	public function friend();
}

class __home extends xmd implements i_home
{
	public function __construct()
	{
		parent::__construct();
		
		$this->auth(false);
		$this->_m(_array_keys(w('like')));
	}
	
	public function home()
	{
		global $core, $bio;
		
		$page = 15;
		$today = _htimestamp('md');
		
		_pre($bio->v('is_bio'), true);
		
		// Personal status
		//if ($bio->v('bio_active'))
		{
			_style('status_post');
		}
		
		// Friends birthday
		if ($bio->v('auth_member'))
		{
			$sql = "SELECT bio_id, bio_alias, bio_name
				FROM _bio
				WHERE bio_id IN (
						SELECT fan_of
						FROM _bio_fans
						WHERE fan_assoc = ?
					)
					AND bio_active = ?
					AND bio_birth LIKE '%??'
				ORDER BY bio_name";
			$birthday = _rowset(sql_filter($sql, $bio->v('bio_id'), 1, $today));
		}
		else
		{
			$sql = "SELECT bio_id, bio_alias, bio_name, bio_avatar, bio_avatar_up
				FROM _bio
				WHERE bio_level = ?
					AND bio_birth LIKE '%??'
				ORDER BY bio_name";
			$birthday = _rowset(sql_filter($sql, 1, $today));
		}
		
		foreach ($birthday as $i => $row)
		{
			if (!$i) _style('birthday');
			
			_style('birthday.row', array(
				'A' => _a($row),
				'NAME' => $row['bio_name'],
				'AVATAR' => _avatar($row))
			);
		}
		
		// Board topics
		if ($bio->v('auth_member'))
		{
			$sql = 'SELECT t.topic_id, t.topic_alias, t.topic_title, h.highlight_class
				FROM _board_topics t
				INNER JOIN _board_forums f ON f.forum_id = t.topic_forum
				LEFT JOIN _board_highlight h ON t.topic_highlight = h.highlight_id
				RIGHT JOIN _board_disallow d ON t.topic_id = d.disallow_topic AND d.disallow_bio = ?
				WHERE t.topic_show = ?
				ORDER BY t.topic_shine DESC, t.topic_time DESC
				LIMIT ??';
			$topics = _rowset(sql_filter($sql, $bio->v('bio_id'), 1, 10));
		}
		else
		{
			$sql = 'SELECT t.topic_id, t.topic_alias, t.topic_title, h.highlight_class
				FROM _board_topics t
				INNER JOIN _board_forums f ON f.forum_id = t.topic_forum
				LEFT JOIN _board_highlight h ON t.topic_highlight = h.highlight_id
				WHERE t.topic_show = ?
				ORDER BY t.topic_shine DESC, t.topic_time DESC
				LIMIT ??';
			$topics = _rowset(sql_filter($sql, 1, 10));
		}
		
		foreach ($topics as $i => $row)
		{
			if (!$i) _style('board_topics');
			
			_style('board_topics.row', _vs(array(
				'ID' => $row['topic_id'],
				'TITLE' => $row['topic_title'],
				'CLASS' => $row['highlight_class']
			), 'TOPIC'));
		}
		
		if ($bio->v('auth_member'))
		{
			// Messages
			$sql = 'SELECT *
				FROM _bio_messages
				INNER JOIN _bio ON message_from = bio_id
				INNER JOIN _bio_messages_type ON message_type = type_id
				WHERE message_to = ?
					AND message_active = ?
				ORDER BY message_time DESC';
			$messages = _rowset(sql_filter($sql, $bio->v('bio_id'), 1));
			
			foreach ($messages as $i => $row)
			{
				if (!$i) _style('messages');
				
				_style('messages.row', array(
					'U_MESSAGE' => _link(),
					'' => ''
				));
			}
			
			// Friend requests
			$sql = 'SELECT b.bio_alias, b.bio_name
				FROM _bio_friends
				INNER JOIN _bio ON friend_assoc = bio_id
				WHERE friend_bio = ?
					AND friend_pending = ?
				ORDER BY friend_time DESC';
			$requests = _rowset(sql_filter($sql, $bio->v('bio_id'), 1));
			
			foreach ($requests as $i => $row)
			{
				if (!$i) _style('friend_request');
				
				_style('friend_request.row', array(
					'U_APPROVE' => _link('home', array('x1' => 'friend', 'x2' => 'approve', 'a' => $row['bio_alias'])),
					'U_DENY' => _link('home', array('x1' => 'friend', 'x2' => 'deny', 'a' => $row['bio_alias'])),
					'A' => _a($row),
					'BIO_NAME' => $row['bio_name']
				));
			}
		}
		
		// Banners
		$this->announce('home');
		
		return;
	}
	
	public function status()
	{
		return $this->method();
	}
	
	protected function _status_home()
	{
		gfatal();
		
		global $bio;
		
		if (!$bio->v('auth_logged'))
		{
			_fatal();
		}
		
		$v = $this->__(array('status', 'bio' => 0));
		
		if (!$v['bio'])
		{
			$v['bio'] = $bio->v('bio_id');
		}
		
		if ($v['bio'] !== $bio->v('bio_id'))
		{
			if (!$this->bio_exists($v['bio']))
			{
				_fatal();
			}
			
			if (!$bio->v('auth_status_update_others', false, $v['bio']))
			{
				_fatal();
			}
		}
		
		$sql_insert = array(
			'bio' => $v['bio'],
			'time' => time(),
			'text' => _prepare($v['status']),
			'ip' => $bio->v('session_ip')
		);
		sql_put('_bio_status', prefix('status', $sql_insert));
		
		$response = array(
			'time' => $sql_insert['time'],
			'text' => $sql_insert['text']
		);
		return $this->e(json_encode($response));
	}
	
	public function like()
	{
		return $thsi->method();
	}
	
	protected function _like_home()
	{
		global $bio;
		
		if (!is_ghost())
		{
			_fatal();
		}
		
		$v = $this->__(array('ref' => 0));
		
		if (!$v['ref'])
		{
			_fatal();
		}
		
		if (!$bio->v('auth_member'))
		{
			_login();
		}
		
		// like_time
		
		$sql = 'SELECT *
			FROM _reference
			WHERE ref_id = ?';
		if (!$ref = _fieldrow(sql_filter($sql, $v['ref'])))
		{
			_fatal();
		}
		
		$sql = 'SELECT like_id
			FROM _reference_likes
			WHERE like_ref = ?
				AND like_uid = ?';
		if (!_field(sql_filter($sql, $ref['ref_id'], $bio->v('bio_id')), 'like_id', 0))
		{
			$sql_insert = array(
				'ref' => $ref['ref_id'],
				'uid' => $bio->v('bio_id')
			);
			sql_put('_reference_likes', prefix('like', $sql_insert));
		}
		
		return $this->e('~OK');
	}
	
	public function friend()
	{
		return $this->method();
	}
	
	protected function _friend_approve()
	{
		global $bio;
		
		if (!$bio->v('auth_member'))
		{
			_login();
		}
		
		$v = $this->__(w('a'));
		
		$sql = 'SELECT bio_id
			FROM _bio
			WHERE bio_alias = ?';
		if (!$bio_id = _field(sql_filter($sql, $v['a']), 'bio_id', 0))
		{
			_fatal();
		}
		
		$sql = 'SELECT friend_id, friend_pending
			FROM _bio_friends
			WHERE friend_assoc = ?
				AND friend_bio = ?';
		if (!$friend = _fieldrow(sql_filter($sql, $bio_id, $bio->v('bio_id'))))
		{
			_fatal();
		}
		
		if (!$friend['friend_pending'])
		{
			_fatal();
		}
		
		$sql = 'UPDATE _bio_friends SET friend_pending = ?
			WHERE friend_id = ?';
		_sql(sql_filter($sql, 0, $friend['friend_id']));
		
		// TODO: Email notification about new friend.
		
		return;
	}
	
	protected function _friend_deny()
	{
		global $bio;
		
		if (!$bio->v('auth_member'))
		{
			_login();
		}
		
		$v = $this->__(w('a'));
		
		$sql = 'SELECT bio_id
			FROM _bio
			WHERE bio_alias = ?';
		if (!$bio_id = _field(sql_filter($sql, $v['a']), 'bio_id', 0))
		{
			_fatal();
		}
		
		$sql = 'SELECT friend_id, friend_pending
			FROM _bio_friends
			WHERE friend_assoc = ?
				AND friend_bio = ?';
		if (!$friend = _fieldrow(sql_filter($sql, $bio_id, $bio->v('bio_id'))))
		{
			_fatal();
		}
		
		if (!$friend['friend_pending'])
		{
			_fatal();
		}
		
		$sql = 'DELETE FROM _bio_friends
			WHERE friend_id = ?';
		_sql(sql_filter($sql, $friend['friend_id']));
		
		return;
	}
}

?>