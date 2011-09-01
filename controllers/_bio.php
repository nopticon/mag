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

interface i_bio {
	public function home();
	
	public function page();
	public function messages();
	public function account();
	public function password();
	public function unread();
	
	public function journal();
	public function record();
	public function auth();
	public function gallery();
	public function biography();
	public function lyrics();
	public function media();
	public function analytics();
	
	public function posts();
}

class __bio extends xmd implements i_bio {
	private $messages_a;
	
	public function __construct() {
		global $bio;
		
		parent::__construct();
		
		$this->auth(false);
		
		$this->_m(array(
			'page' => w(),
			'messages' => w(),
			'account' => w(),
			'password' => w(),
			
			'analytics' => w(),
			'biography' => w('modify'),
			'fans' => w('add remove'),
			'interviews' => w('create modify remove'),
			'journal' => w('create modify remove'),
			'messages' => w('modify remove'),
			'options' => w('modify'),
			'permission' => w('create remove'),
			'record' => w('view remove'),
			
			'gallery' => array(
				'set' => w('create modify remove'),
				'photo' => w('create modify remove')
			),
			'music' => array(
				'cd' => w('create modify remove'),
				'song' => w('create modify remove'),
				'lyrics' => w('create modify remove')
			)
		));
		
		$this->m(w('home write sent draft'), 'messages_a');
		
		//
		// This bio!
		//
		
		$v = $this->__(w('domain alias tab:home'));
		
		if (empty($v->domain) && empty($v->alias)) {
			$warning->fatal();
		}
		
		if (!empty($v->domain)) {
			$v->domain = $this->strip_domain($v->domain);
		} elseif (!empty($v->alias)) {
			$v->alias = _low($v->alias, true);
			
			if ($v->alias === false) {
				$warning->fatal();
			}
		}
		
		//
		$sql = 'SELECT *
			FROM _bio b, _bio_type t
			WHERE (b.bio_alias = ?
				OR b.bio_domain = ?)
				AND b.bio_active = ?
				AND b.bio_type = t.type_id
			LIMIT 1';
		if (!$_bio = sql_fieldrow(sql_filter($sql, $v->alias, $v->domain, 1))) {
			$warning->fatal();
		}
		
		if ($v->tab != 'home') {
			$sql = 'SELECT relation_id
				FROM _bio_relation r, _bio_modules m, _bio_publish p
				WHERE p.publish_local = ?
					AND r.relation_alias = ?
					AND r.relation_id = m.module_relation
					AND p.publish_module = m.module_id';
			if (!sql_field(sql_filter($sql, $_bio->bio_id, $v->tab), 'relation_id', 0)) {
				$warning->fatal();
			}
		}
		
		switch ($_bio->type_alias) {
			case 'artist':
			case 'page':
				if ($bio->v('auth_member')) {
					$sql = 'SELECT auth_id
						FROM _bio_auth
						WHERE auth_local = ?
							AND auth_remote = ?
						LIMIT 1';
					if (sql_field(sql_filter($sql, $_bio->bio_id, $bio->v('bio_id')))) {
						$bio->v('auth_page', true);
					}
				}
				break;
			default:
				break;
		}
		
		//
		// Access Control
		switch ($_bio->bio_access) {
			case BIO_ACCESS_FOLLOWER:
				if (!$bio->v('auth_member')) {
					$bio->login();
					_login();
				}
				
				if (!$this->bio_follower($_bio->bio_id) && !$bio->v('auth_page')) {
					_fatal();
				}
				break;
			case BIO_ACCESS_ALL:
				break;
			default:
				break;
		}
		
		return;
	}
	
	public function home() {
		global $bio, $core, $style;
		
		/*
		$sql = 'SELECT *
			FROM _bio_auth
			WHERE auth_assoc = ?
				AND auth_bio = ?';
		if (!_fieldrow(sql_filter($sql, $_bio->bio_id, $bio->v('bio_id'))))
		{
			// TODO: Admin notification if not authed
			_fatal();
		}
		*/
		
		//
		//
		$sql = 'SELECT bio_id, bio_alias, bio_name, bio_avatar, bio_avatar_up
			FROM _bio b, _followers f
			WHERE f.follower_local = ?
				AND f.follower_active = ?
				AND f.follower_remote = b.bio_id
			ORDER BY f.follower_top
			LIMIT ??';
		$followers = sql_rowset(sql_filter($sql, $_bio->bio_id, 1, $_bio->bio_top_follow));
		
		foreach ($followers as $i => $row) {
			if (!$i) _style('followers');
			
			_style('followers.row', array(
				
			));
		}
		
		/*
		SELECT *
		FROM _bio_relation r, _bio_modules m, _bio_publish p
		WHERE r.relation_alias = ?
			AND r.relation_id = m.module_relation
			AND p.publish_module = m.module_id
		*/
		
		switch ($v->tab) {
			case 'home':
				$sql = 'SELECT *
					FROM _bio_timeline
					WHERE timeline_bio = ?
					ORDER BY timeline_time DESC
					LIMIT ??, ??';
				$posts = sql_rowset(sql_filter($sql));
				break;
			default:
				$sql = 'SELECT *
					FROM _bio_timeline
					WHERE timeline_bio = ?
						AND timeline_module = ?
					ORDER BY timeline_time DESC
					LIMIT ??, ??';
				$posts = sql_rowset(sql_filter($sql));
				break;
		}
		
		$sql = 'SELECT relation_id
			FROM _bio_relation r, _bio_modules m, _bio_publish p
			WHERE p.publish_local = ?
				AND r.relation_alias = ?
				AND r.relation_id = m.module_relation
				AND p.publish_module = m.module_id
			ORDER BY p.publish_time DESC';
		$publish = sql_rowset(sql_filter($sql, $_bio->bio_id, $v->tab));
		
		foreach ($posts as $i => $row) {
			if (!$i) _style('timeline');
			
			$module = 'module_' . $row->module_alias;
			if (@method_exists($this, $module)) {
				$this->{$module}($_bio, $row);
			}
			
			//
			$style->set_filenames(array('module' => 'modules/' . $row->module_alias . '.htm'));
			$style->assign_var_from_handle('S_MODULE', 'module');
			
			_style('modules.row', _style_var('S_MODULE'));
		}
		
		return;
	}
	
	private function module_video($_bio, $d) {
		// parse
		
		$d->video_added = _format_date($d->video_added);
		return;
	}
	
	private function module_notes($a) {
		// parse
		
		_style('notes', _vs(array(
			'subject' => $note->note_subject,
			'message' => _message($note->note_content)
		), 'note'));
		
		return;
	}
	
	private function module_tweet($a) {
		// parse
		
		return;
	}
	
	private function module_links($a) {
		// parse dynamic
		
		_style('links_' . $a->rel_id, _vs(array(
			'name' => $row->link_name,
			'address' => $row->link_address
		), 'link'));
		
		return;
	}
	
	private function module_picture($a) {
		// parse
		// get gallery id from image
		
		/*
		SELECT *
		FROM _bio_images i, _bio_images_group g
		WHERE i.image_id = ?
			AND i.image_group = g.group_id
		
		
		SELECT *
		FROM _artists_images
		WHERE image_a = ?
			AND image_group = ?
		ORDER BY image_order
		*/
		
		$sql = 'SELECT *
			FROM _module_pictures
			WHERE picture_module = ?
				AND picture_bio = ?
			ORDER BY picture_order';
		$pictures = sql_rowset(sql_filter($sql, $a->rel_id, $a->rel_bio));
		
		foreach ($pictures as $row) {
			
		}
		
		return;
	}
	
	//
	// ----------------------------------------------------------------------------------------------------------------
	// ----------------------------------------------------------------------------------------------------------------
	//
	
	public function unread() {
		global $bio;
		
		$v = $this->__(array('s' => 0, 'area' => 0, 'ls' => array(0)));
		
		if (_button('delete')) {
			if (!$bio->v('auth_member')) {
				_fatal();
			}
			
			if (count($v['ls'])) {
				$v_assoc = _implode(',', $v['ls']);
				
				$sql = 'SELECT n.notify_id
					FROM _bio_notify n, _bio_notify_area a
					WHERE a.area_id = ?
						AND n.notify_bio = ?
						AND n.notify_assoc = ?
						AND n.notify_area = a.area_id';
				if (!_field(sql_filter($sql, $v['area'], $bio->v('bio_id'), $v_assoc), 'notify_id', 0)) {
					_fatal();
				}
				
				$sql = 'DELETE FROM _bio_notify
					WHERE notify_area = ?
						AND notify_bio = ?
						AND notify_assoc IN (??)';
				_sql(sql_filter($sql, $v['area'], $bio->v('bio_id'), $v_assoc));
			}
			
			if (is_ghost()) {
				return $this->e('~OK');
			}
			
			redirect(_link('my', 'home'));
		}
		
		//
		// Show notifications
		$notify = w();
		
		if ($bio->v('is_bio')) {
			$sql = 'SELECT a.area_alias, n.notify_assoc
				FROM _bio_notify n, _bio_notify_area a
				WHERE n.notify_bio = ?
				ORDER BY a.area_alias, n.notify_time
				LIMIT ??, ??';
			$notify = _rowset(sql_filter($sql, $bio->v('bio_id'), $v['s'], $core->v('notify_pagination')), 'area_alias', 'notify_assoc', true);
		}
		
		if (count($notify)) {
			$i = 0;
			foreach ($notify as $notify_area => $notify_assoc) {
				$notify_set = _implode(',', $notify_assoc);
				
				// TODO: Improve select fields
				
				switch ($notify_area) {
					case 'talk':
						$sql = 'SELECT *
							FROM _bio_talk
							WHERE talk_id IN (??)
							ORDER BY talk_time DESC';
						$talk = _rowset(sql_filter($sql, $notify_set));
						break;
					case 'friends':
						$sql = 'SELECT b.bio_alias, b.bio_name
							FROM _bio_requests r, _bio b
							WHERE r.request_from IN (??)
								AND r.request_from = b.bio_id
							ORDER BY r.request_time';
						$requests = _rowset(sql_filter($sql, $notify_set));
						break;
					case 'images':
					case 'posts':
						break;
					case 'reference':
						$sql = 'SELECT *
							FROM _reference
							WHERE ref_id IN (??)
							ORDER BY ref_time';
						$reference = _rowset(sql_filter($sql, $notify_set));
						break;
					case 'events':
						$sql = 'SELECT *
							FROM _bio b, _events e, _events_invite i
							WHERE i.invite_id IN (??)
								AND i.invite_event = e.event_id
								AND i.invite_from = b.bio_id';
						$events = _rowset(sql_filter($sql, $notify_set));
						break;
					case 'contest':
						$sql = 'SELECT *
							FROM _contest
							WHERE contest_id IN (??)
							ORDER BY contest_time';
						$contest = _rowset(sql_filter($sql, $notify_set));
						break;
					case 'board':
						break;
					case 'groups':
						break;
					case '':
						// TODO: Nuevos artistas, fotos, musica y video
						break;
				}
				
				$i++;
			}
		} else {
			// Default data for guests and if bio has no notifications.
		}
		
		return;
	}
	
	public function page() {
		global $bio;
		
		return redirect(_link($this->bio($bio->v('bio_alias'))));
	}
	
	public function messages() {
		return $this->method();
	}
	
	protected function _messages_home() {
		global $bio, $core;
		
		$v = $this->__(array('i' => 'home', 'u' => '', 'a' => array(0 => ''), 's' => 0));
		
		if (!in_array($v['i'], $this->messages_a)) {
			_fatal();
		}
		
		switch ($v['i']) {
			case 'write':
				if (!$bio->v('bio_active')) {
					$this->_error('PLEASE_CONFIRM_ACCOUNT');
				}
				
				if (f($v['u']) && $v['u'] != $bio->v('bio_alias')) {
					$v['a'][] = $v['u'];
				}
				unset($v['u']);
				
				if (_button()) {
					$v = array_merge($v, $this->__(array('subject', 'message', 'parent' => 0)));
					
					if ($v['parent']) {
						$sql = 'SELECT *
							FROM _bio_talk
							WHERE talk_id = ?';
						if (!$talk = _fieldrow(sql_filter($sql, $v['parent']))) {
							_fatal();
						}
						
						$sql = 'SELECT *
							FROM _bio_talkers
							WHERE talker_talk = ?
								AND talker_bio = ?';
						if (!$talkers = _rowset(sql_filter($sql, $v['parent'], $bio->v('bio_id')))) {
							_fatal();
						}
					} else {
						if (!f($v['subject'])) {
							$this->_error('#TALK_NO_SUBJECT');
						}
						
						$sql = 'SELECT bio_alias, bio_name, bio_email
							FROM bio
							WHERE bio_id IN (??)
							ORDER BY bio_alias';
						if (!$talkers = _rowset(sql_filter($sql, _implode(',', $v['a'])))) {
							$this->_error('#TALK_NO_TALKERS');
						}
					}
					
					if (!f($v['message'])) {
						$this->_error('#NO_MESSAGE');
					}
					
					$sql_insert = array(
						'parent' => $v['parent'],
						'subject' => _prepare($v['subject']),
						'message' => _prepare($v['message']),
						'time' => time()
					);
					$v->talk_id = sql_put('_bio_talk', $sql_insert);
					
					foreach ($talkers as $row) {
						$sql_insert = array(
							'talk' => ($v['parent']) ? $v['parent'] : $v->talk_id,
							'bio' => $row['bio_id']
						);
						sql_put('_bio_talkers', $sql_insert);
						
						$properties = array(
							'from' => 'info',
							'to' => $row['bio_email'],
							'subject' => '',
							'body' => '',
							'template' => ''
						);
						_sendmail($properties);
					}
					
					if (is_ghost() && $v['parent']) {
						$response = array(
							'message_id' => $message_id,
							'message_content' => $v['message'],
							'message_time' => _format_date()
						);
						return $this->e(json_encode($response));
					}
					
					redirect('my', array('messages', 'm' => $message_id));
				}
				break;
			default:
				$v = array_merge($v, $this->__(array('m' => 0)));
				
				if ($v['m']) {
					$sql = 'SELECT *
						FROM _bio_talk t, _bio_talkers r
						WHERE t.talk_id = ?
							AND r.talker_bio = ?
							AND t.talk_id = r.talker_talk';
					if (!$talk = _fieldrow(sql_filter($sql, $v['m'], $bio->v('bio_id')))) {
						_fatal();
					}
					
					$sql = 'SELECT t.*, b.bio_id, b.bio_alias, b.bio_name
						FROM _bio_talk t, _bio_talkers r, _bio b
						WHERE t.talk_parent = ?
							AND t.talk_id = r.talker_talk
							AND t.talk_author = r.talker_bio
							AND r.talker_bio = b.bio_id
						ORDER BY t.talk_time';
					$messages = _rowset(sql_filter($sql, $talk['talk_parent']));
					
					foreach ($messages as $i => $row) {
						if (!$i) _style('messages');
						
						_style('messages.row');
					}
					
					return;
				}
				
				//
				// Message lists
				$is_draft = 0;
				
				switch ($v['i']) {
					case 'sent':
						$sql_total = 'SELECT COUNT(talk_id) AS total
							FROM _bio_talk
							WHERE talk_author = ?
								AND talk_draft = ?
								AND talk_id = talk_parent';
						
						$sql_list = 'SELECT *
							FROM _bio_talk
							WHERE talk_author = ?
								AND talk_draft = ?
								AND talk_id = t.talk_parent
							ORDER BY talk_lasttime DESC
							LIMIT ??, ??';
						break;
					case 'draft':
						$sql_total = 'SELECT COUNT(talk_id) AS total
							FROM _bio_talk
							WHERE talk_author = ?
								AND talk_draft = ?
								AND talk_id = talk_parent';
						
						$sql_list = 'SELECT *
							FROM _bio_talk
							WHERE talk_author = ?
								AND talk_draft = ?
								AND talk_id = talk_parent
							ORDER BY talk_lasttime DESC
							LIMIT ??, ??';
						
						$is_draft = 1;
						break;
					default:
						$sql_total = 'SELECT COUNT(talk_id) AS total
							FROM _bio_talk t, _bio_talkers r
							WHERE r.talker_bio = ?
								AND t.talk_draft = ?
								AND t.talk_id = t.talk_parent
								AND t.talk_id = r.talker_talk';
						
						$sql_list = 'SELECT *
							FROM _bio_talk t, _bio_talkers r
							WHERE r.talker_bio = ?
								AND t.talk_draft = ?
								AND t.talk_id = t.talk_parent
								AND t.talk_id = r.talker_talk
							ORDER BY t.talk_lasttime DESC
							LIMIT ??, ??';
						break;
				}
				
				$talk_total = _field(sql_filter($sql_total, $bio->v('bio_id'), $is_draft), 'total', 0);
				$talk_list = _rowset(sql_filter($sql_list, $bio->v('bio_id'), $is_draft, $v['s'], $core->v('talk_pager')));
				
				if ($talk_total && !count($talk_list)) {
					redirect(_link($this->m(), array('messages', 'i' => $v['i'])));
				}
				
				foreach ($messages as $i => $row) {
					if (!$i) _style('talks', _pagination(_link('my', array('messages', 'i' => $v['i'])), 's:%d', $messages_total, $core->v('talk_pager'), $v['s']));
					
					if (!$row['message_last']) {
						$row['message_last'] = $row['message_id'];
						$row['message_last_time'] = $row['message_time'];
					}
					
					_style('talks.row', _vs(array(
						'PARENT' => $row['talk_parent'],
						'SUBJECT' => $row['talk_subject'],
						'READ' => _link($this->m(), array('messages', 'i' => $v['i'], 'm' => $row['talk_last'])),
						'TIME' => _format_date($row['talk_lasttime']),
						'ROOT' => $row['talk_root']
					), 'TALK'));
				}
				
				break;
		}
		
		return;
	}
	
	public function account() {
		return $this->method();
	}
	
	protected function _account_home() {
		global $bio, $core;
		
		if (_button()) {
			$sql = 'SELECT *
				FROM _bio_fields
				ORDER BY field_alias';
			$fields = _rowset($sql, 'field_alias');
			
			$v = $this->__(array_merge(w('address password password_verify gender' . _implode(' ', array_subkey($fields, 'field_alias'))), array('timezone' => 0, 'birthday' => array(0))));
			
			$field_error = array(
				'address' => 'NO_ADDRESS',
				'password' => 'NO_PASSWORD',
				'password_verify' => 'NO_PASSWORD_VERIFY'
			);
			
			foreach ($v as $k => $vv) {
				if (!f($vv)) {
					$this->error('#');
				}
			}
			
			if (is_ghost() && $this->errors()) {
				$this->e('!');
			}
			
			redirect(_link('my', 'page'));
		}
		
		return;
		
		/*
		if (_button())
		{
			if (!$this->errrors())
			{
				$avatar_changed = (isset($this->data['old_avatar'])) ? true : false;
				
				// Update DB if something was changed
				if (sizeof($sql_update))
				{
					if (isset($sql_update['bio_avatar']))
					{
						if (f($bio->v('bio_avatar')))
						{
							@unlink('..' . $core->v('avatar_path') . $bio->v('bio_avatar'));
						}
						@rename($this->data['old_avatar'], '..' . $core->v('avatar_path') . $sql_update['bio_avatar']);
					}
				}
				
				// Redirect to userpage
			}
		} // IF submit
		
		// Selects
		$this->ss_build('dateformat', 'timezone', 'gender', 'birthday', 'topic_order', 'mark_items');
		
		// Vars
		$sv = array(
			'AVATAR_MAXSIZE' => $core->v('avatar_filesize'),
			'L_AVATAR_EXPLAIN' => sprintf(_lang('AVATAR_EXPLAIN'), $core->v('avatar_max_width'), $core->v('avatar_max_height'))
		)
		+ $this->fields_fvars();
		*/
	}
	
	public function password() {
		return $this->method();
	}
	
	protected function _password_home() {
		global $bio;
		
		$v = $this->__(w('k'));
		
		if (f($v['k'])) {
			// TODO: Password reset from email link
		}
		
		if (_button()) {
			$v = $this->__(w('address'));
			
			if (!f($v['address'])) {
				$this->_error('#NO_SUCH_BIO');
			}
			
			$v['field'] = (email_format($v['address']) !== false) ? 'address' : 'alias';
			
			if ($v['field'] == 'alias' && !_low($v['address'])) {
				$this->_error('#NO_SUCH_BIO');
			}
			
			$sql = 'SELECT bio_alias, bio_name, bio_email, bio_lang
				FROM _bio
				WHERE bio_?? = ?
					AND bio_active = ?';
			if (!$_bio = _fieldrow(sql_filter($sql, $v['field'], $v['address'], 1))) {
				$this->_error('#NO_SUCH_BIO');
			}
			
			$actkey = substr(unique_id(), 0, 6);
			
			$sql = 'UPDATE _bio SET bio_actkey = ?
				WHERE bio_id = ?';
			_sql(sql_filter($sql, $actkey, $_bio['bio_id']));
			
			//
			$properties = array(
				'to' => $userdata['bio_address'],
				'template' => 'user_activate_passwd',
				
				'vars' => array(
					'USERNAME' => $userdata['username'],
					'PASSWORD' => $user_password,
					'U_ACTIVATE' => _link('my', array('password', 'k' => $user_actkey))
				)
			);
			_sendmail($properties);
			
			$this->_error('PASSWD_SENT');
		}
		
		return;
	}
	
	public function journal() {
		return $this->method();
	}
	
	protected function _journal_create() {
		if (!_button()) {
			redirect(_link_bio($this->a('bio_alias')));
		}
		
		global $bio, $core;
		
		$v = $this->__(array('subject', 'content', 'locked' => 0, 'draft' => 0));
		
		foreach ($v as $k => $j) {
			if (!f($j)) $this->_error('#NO_' , $k);
		}
		
		$sql_insert = array(
			'bio' => (int) $this->a('bio_id'),
			'subject' => _subject($v->subject),
			'content' => _prepare($v->content),
			'poster' => (int) $bio->v('bio_id'),
			'time' => time(),
			'modified' => time(),
			'locked' => (int) $v->locked,
			'draft' => (int) $v->draft,
			'ip' => $bio->v('ip')
		);
		$blog_id = sql_put('_bio_journal', prefix('blog', $sql_insert));
		$bio->notify->store('blog', $blog_id);
		
		return redirect(_link('alias', $this->a('a_alias')));
	}
	
	protected function _journal_modify() {
		global $bio, $warning;
		
		$v = $this->__(w('id 0 subject content locked 0 draft 0'));
		
		if (!$v->id) $warning->fatal();
		
		$sql = 'SELECT *
			FROM _bio_journal
			WHERE blog_id = ?
				AND blog_bio = ?';
		if (!$blog = sql_fieldrow(sql_filter($sql, $v->id, $this->a('bio_id')))) {
			$warning->fatal();
		}
		
		$subject = $blog->blog_subject;
		$content = $blog->blog_content;
		
		if (_button()) {
			$sql_update = w();
			foreach ($v as $k => $vv) {
				if (empty($vv)) $this->_error('#NO_' . $k);
				
				if ($vv != $$k) $sql_update[$k] = _prepare($vv);
			}
			
			$sql_update = array_merge($sql_update, array(
				'blog_time' => time(),
				'blog_poster' => $bio->v('bio_id'),
				'blog_ip' => $bio->v('ip'))
			);
			
			$sql = 'UPDATE _bio_journal SET ' . sql_build('UPDATE', $sql_update) . sql_filter('
				WHERE blog_id = ?', $v->id);
			sql_query($sql);
			
			$bio->notify->store('blog', $v->id);
			
			redirect(_link('alias', array('alias' => $this->a('bio_alias'))));
		}
		
		$this->navigation(array('alias' => $bio->v('bio_alias'), 'x1' => 'cp', 'x2' => $this->x(2), 'x3' => $this->x(3), 'id' => $v->id), 'A_NEWS_EDIT');
		
		$response = array(
			'action' => _link_bio('', array('x1' => $this->x(1), 'x2' => $this->x(2), 'x3' => $this->x(3), 'id' => $blog->blog_id)),
			'subject' => $subject,
			'content' => $content
		);
		return $this->output(json_encode($response));
	}
	
	protected function _journal_remove() {
		global $bio, $warning;
		
		gfatal();
		
		$v = $this->__(w('id 0'));
		
		if (!$v->id) {
			$warning->fatal();
		}
		
		$sql = 'SELECT blog_id
			FROM _bio_journal
			WHERE blog_id = ?';
		if (!sql_field(sql_filter($sql, $v->id), 'blog_id', 0)) {
			$warning->fatal();
		}
		
		$sql = 'DELETE FROM _bio_journal
			WHERE blog_id = ?';
		sql_query(sql_filter($sql, $v->id));
		
		$bio->notify->remove('blog', $v->id);
		
		redirect(_link('a', $bio->v('bio_alias')));
	}
	
	public function posts() {
		return $this->method();
	}
	
	protected function _posts_modify() {
		global $bio;
		
		gfatal();
		
		if (!$bio->v('auth_bio_post_modify')) {
			$warning->now();
		}
		
		$v = $this->__(w('id 0 content'));
		
		foreach ($v as $k => $vv) {
			if (empty($vv)) $warning->now('#NO_' . $k);
		}
		
		$sql = 'SELECT post_id
			FROM _bio_posts
			WHERE post_id = ?
				AND post_bio = ?';
		if (!$post = sql_fieldrow(sql_filter($sql, $v->id, $bio->v('bio_id')))) {
			$warning->now();
		}
		
		$sql_update = array(
			'post_content' => _prepare($v->content)
		);
		$sql = 'UPDATE _bio_posts SET ' . sql_build('UPDATE', $sql_update) . sql_filter('
			WHERE post_id = ?', $v->id);
		sql_query($sql);
		
		return redirect(_link('alias', array('alias' => $bio->v('bio_alias'), 'messages', $post->post_id)));
	}
	
	public function _posts_remove() {
		global $bio;
		
		gfatal();
		
		if (!$bio->v('auth_bio_post_remove')) {
			$warning->now();
		}
		
		$v = $this->__(w('id 0'));
		if (!$v->id) {
			$warning->now();
		}
		
		$sql = 'SELECT post_id
			FROM _bio_posts
			WHERE post_id = ?';
		if (!sql_field(sql_filter($sql, $v->id), 'post_id', 0)) {
			$warning->now();
		}
		
		$sql = 'DELETE FROM _bio_posts
			WHERE post_id = ?';
		sql_query(sql_filter($sql, $v->id));
		
		$bio->notify->remove('posts', $v->id);
		
		return redirect(_link('alias', array('alias' => $bio->v('bio_alias'))));
	}
	
	public function record() {
		return $this->method();
	}
	
	protected function _record_home() {
		global $bio;
		
		$v = $this->__(w('m alias 0'));
		
		if (!empty($v->m)) {
			$sql = 'SELECT bio_id, bio_alias, bio_name
				FROM _bio
				WHERE bio_alias = ?';
			if (!$_bio = sql_fieldrow(sql_filter($sql, $v->alias))) {
				$warning->now();
			}
			
			v_style(array(
				'BIO_NAME' => $_bio->bio_name,
				'BIO_LINK' => _link_bio($_bio->bio_alias))
			);
			
			//
			// Get record for selected bio
			$sql = 'SELECT *
				FROM _bio_record
				WHERE record_assoc = ?
					AND record_bio = ?
				ORDER BY record_time DESC';
			$records = sql_rowset($sql);
			
			$index = w();
			foreach ($records as $row) {
				$index[$row->record_module][] = $row->record_assoc;
			}
			
			foreach ($records as $i => $row) {
				$indexes = _implode(',', $index['record_module']);
				
				switch ($row->record_module) {
					case 'posts':
						$sql = 'SELECT *
							FROM _artists_posts
							WHERE post_id IN (??)';
						$sql = sql_filter($sql, $indexes);
						break;
					case 'lyrics':
						$sql = 'SELECT *
							FROM _artists_lyrics
							WHERE lyric_id IN (??)';
						$sql = sql_filter($sql, $indexes);
						break;
					case 'bio':
						break;
					case 'website':
						break;
				}
				
				if (!empty($sql)) {
					$records[$i]['sources'] = sql_rowset($sql);
				}
			}
		} else {
			$sql = 'SELECT COUNT(r.record_id) AS total, b.bio_id, b.bio_alias, b.bio_name, b.bio_avatar, b.bio_avatar_up
				FROM _bio b, _bio_record r
				WHERE r.record_assoc = ?
					AND r.record_bio = b.bio_id
				GROUP BY b.bio_id
				ORDER BY b.bio_name';
			$records = sql_rowset(sql_filter($sql, $_bio->bio_id));
			
			$no_results = false;
			$tcol = 0;
			
			foreach ($records as $i => $row) {
				if (!$i) _style('members');
				
				$row = $bio->vv($row);
				
				if (!$tcol) _style('members.row');
				
				_style('members.row.col', array(
					'USER_ID' => $row->bio_id,
					'USERNAME' => $row->bio_name,
					'AVATAR' => $row->bio_avatar,
					'U_VIEWLOG' => _link_control('a', array('a' => $bio->v('bio_alias'), 'x1' => $this->x(1), 'x2' => $this->x(2), 'm' => $row->bio_id)),
					'TOTAL' => $row->total,
					'ACTION' => _lang('CONTROL_A_LOG_ACTION' . (($row->total == 1) ? '' : 'S')))
				);
				
				$tcol = ($tcol == 3) ? 0 : $tcol + 1;
			}
		}
		
		v_style(array(
			'MEMBER' => ($member) ? $memberdata->username : '')
		);
	}
	
	//
	// Auth
	//
	public function permission() {
		return $this->method();
	}
	
	protected function _permission_home() {
		global $bio, $warning;
		
		$sql = 'SELECT b.bio_alias, b.bio_name, b.bio_firstname, b.bio_lastname, b.bio_avatar, b.bio_avatar_up
			FROM _bio_auth a, _bio b
			WHERE a.auth_assoc = ?
				AND a.auth_bio = b.bio_id
			ORDER BY b.bio_name';
		if (!$auth = sql_rowset(sql_filter($sql, $this->a('bio_id')))) {
			$waarning->now();
		}
		
		foreach ($auth as $i => $row) {
			if (!$i) _style('auth');
			
			_style('auth.row', array(
				'V_PROFILE' => $row->bio_link,
				'V_USERNAME' => $row->bio_name,
				'V_AVATAR' => $row->bio_avatar,
				'U_REMOVE' => _link('alias', array('alias' => $bio->v('bio_alias'), 'x1' => $this->x(1), 'x2' => 'remove')))
			);
		}
		
		v_style(array(
			'U_AUTH_CREATE' => _link('alias', array('alias' => $bio->v('bio_alias'), 'mode' => $this->mode, 'manage' => 'create')))
		);
		
		return;
	}
	
	protected function _permission_create() {
		gfatal();
		
		global $bio;
		
		$v = $this->__(array('alias'));
		
		$sql = 'SELECT bio_id
			FROM _bio
			WHERE bio_alias = ?
				AND bio_active = ?
				AND bio_id NOT IN (
					SELECT ban_bio
					FROM _bio_ban
				)';
		if (!$_bio = sql_fieldrow(sql_filter($sql, $v->alias, 1))) {
			_fatal();
		}
		
		$sql = 'SELECT auth_bio
			FROM _bio_auth
			WHERE auth_assoc = ?
				AND auth_bio = ?';
		if (sql_field(sql_filter($sql, $this->a('bio_id'), $_bio->bio_id), 'auth_bio', 0)) {
			$this->warning->ok();
		}
		
		$sql_insert = array(
			'auth_assoc' => $this->a('bio_id'),
			'auth_bio' => $_bio->bio_id,
			'auth_time' => time()
		);
		sql_put('_bio_auth', $sql_insert);
		
		redirect(_link('alias', array('alias' => $this->a('bio_alias'), 'x1' => $this->x(1), 'x2' => $this->x(2))));
	}
	
	protected function _permission_remove() {
		if (!is_ghost()) {
			_fatal();
		}
		
		$v = $this->__(array('bio' => 0));
		
		$sql = 'SELECT bio_id
			FROM _bio
			WHERE bio_id = ?';
		if (!sql_field(sql_filter($sql, $v->bio), 'bio_id', 0)) {
			_fatal();
		}
		
		$sql = 'SELECT auth_bio
			FROM _bio_auth
			WHERE auth_assoc = ?
				AND auth_bio = ?';
		if (!sql_field(sql_filter($sql, $v->bio), 'auth_bio', 0)) {
			_fatal();
		}
		
		$sql = 'DELETE FROM _bio_auth
			WHERE auth_assoc = ?
				AND auth_bio = ?';
		sql_query(sql_filter($sql, $this->a('bio_id'), $v->bio));
		
		redirect(_link('alias', array('alias' => $bio->v('bio_alias'), 'x1' => $this->x(1), 'x2' => $this->x(2))));
	}
	
	//
	// Gallery
	//
	public function gallery() {
		return $this->method();
	}
	
	protected function _gallery_home() {
		global $bio;
		
		// bio / g / [a-z0-9]{1} / [a-z0-9 ]{2} / alias / gid / size / rand.ext
		
		$sql = 'SELECT *
			FROM _bio b, _bio_images i
			WHERE b.bio_id = ?
				AND b.bio_id = i.image_bio
			ORDER BY i.image_id';
		if (!$images = sql_rowset(sql_filter($sql, $this->a('bio_id')))) {
			_style('empty', array(
				'MESSAGE' => _lang('CONTROL_A_GALLERY_EMPTY'))
			);
		}
		
		$col = 0;
		foreach ($images as $i => $row) {
			if (!$i) _style('gallery');
			
			if (!$col) _style('gallery.row');
			
			_style('gallery.row.col', array(
				'ITEM' => $row->image,
				'URL' => _link('a', array($bio->v('bio_alias'), 4, $row->image, 'view')),
				'IMAGE' => dd(false, true, true) . 'artists/' . $bio->v('bio_id') . '/thumbnails/' . $row->image . '.jpg',
				'WIDTH' => $row->width, 
				'HEIGHT' => $row->height,
				'VIEWS' => $row->views,
				'DOWNLOADS' => $row->downloads)
			);
			
			$col = ($col == 3) ? 0 : $col + 1;
		}
			
		return v_style(array(
			'U_CREATE' => _link_bio($this->a('bio_alias'), array('x1' => $this->x(1), 'create')),
			'U_REMOVE' => _link_bio($this->a('bio_alias'), array('x1' => $this->x(1), 'remove')))
		);
	}
	
	public function _gallery_create() {
		global $bio, $core;
		
		if (_button()) {
			$upload = _import('upload');
			
			// Start
			$sql = 'SELECT MAX(image_id) AS total
				FROM _bio_images
				WHERE image_bio = ?';
			$image = sql_field(sql_filter($sql, $this->a('bio_id')), 'total', 0) + 1;
			
			$upload->chmod(array(
				_lib(LIB_BIO . ' ' . $this->a('bio_id'))
			), 0777);
			
			$f = $upload->process(LIB . 'tmp/', request_var('files:picture'), w('jpg'), $core->v('max_upload'));
			
			if ($f === false && count($upload->error)) {
				$warning->set($upload->error);
			}
			
			if (!$warning->exist) {
				$total = 0;
				foreach ($f as $row) {
					//$row = $upload->_row($gallery, $image);
					
					$f2 = $upload->resize($row, LIB . 'tmp', LIB . 'events/future/', $v['e_id'], array(600, 400), false, false, true);
					if ($f2 === false) continue;
					
					$f3 = $upload->resize($row, LIB . 'events/future/', LIB . 'events/preview/', $v['e_id'], array(210, 210), false, false);
					$total++;
					
					//
					$sql_insert = array(
						'bio' => $bio->v('bio_id'),
						'image' => $image,
						'width' => $i_data['width'],
						'height' => $i_data['height'],
						'views' => 0,
						'downloads' => 0,
						'allow_dl' => (int) $allow_dl
					);
					sql_put('_bio_images', prefix('image', $sql_insert));
					
					$image++;
				}
				
				$sql = 'UPDATE _bio_store SET store_value = store_value + ??
					WHERE store_bio = ?';
				_sql(sql_filter($sql, $total, $bio->v('bio_id')));
			}
			
			if (!$error) {
				redirect(_link_control('a', array('a' => $bio->v('bio_alias'), 'x1' => $this->x(1))));
			}
		}
		
		return v_style(array(
			'U_CREATE' => _link_bio($bio->v('bio_alias'), array('x1' => $this->x(1), 'x2' => $this->x(2))),
			'MAX_FILESIZE' => $core-v('max_upload'))
		);
	}
	
	protected function _gallery_modify() {
		
	}
	
	protected function _gallery_remove() {
		global $bio, $warning;
		
		if (_button()) {
			$v = $this->__(array('picture' => array(0)));
			
			if (!count($v->picture)) {
				$warning->now();
			}
			
			$sql = 'SELECT *
				FROM _bio_images
				WHERE image_bio = ?
					AND image_assoc IN (??)
				ORDER BY image_id';
			if (!$images = sql_rowset(sql_filter($sql, $bio->v('bio_id'), _implode(',', $v->picture)))) {
				$warning->now();
			}
			
			$filepath = array(
				'original' => _lib(),
				'thumbnail' => _lib()
			);
			
			foreach ($images as $row) {
				foreach ($filepath as $path) {
					
				}
			}
		}
		
		if ($submit) {
			$v = $this->__(array('s_images' => array(0)));
			$s_images = $v->s_images;
			
			if (sizeof($s_images)) {
				if ($row = $db->sql_fetchrow($result))
				{
					$delete_images = w();
					do {
						$gfile = array(
							$gallery_path . $row['image'] . '.jpg',
							$thumbs_path . $row['image'] . '.jpg'
						);
						
						foreach ($gfile as $image) {
							if (@is_file($image) && @is_readable($image)) {
								@chmod($image, 0777);
								
								if (@unlink($image)) {
									if (!@file_exists($image)) {
										if (!isset($delete_images[$row['image']])) {
											$delete_images[$row['image']] = true;
										}
									}
								}
							}
						}
					}
					while ($row = $db->sql_fetchrow($result));
					
					if (sizeof($delete_images)) {
						$sql = 'DELETE FROM _bio_pictures 
							WHERE picture_bio = ?
								AND picture_id IN (??)';
						sql_query(sql_filter($sq, $bio->v('bio_id'), _implode(',', array_keys($delete_images))));
						
						if ($deleted_count = sql_affectedrows()) {
							$sql = 'UPDATE _bio_store
								SET store_value = store_value - ??
								WHERE store_bio = ?';
							sql_query(sql_filter($sql, $deleted_count, $bio->v('bio_id')));
						}
					}
				}
				$db->sql_freeresult($result);
			}
		}
		
		if (!$error) {
			redirect(_link_control('a', array('a' => $bio->v('bio_alias'), 'x1' => $this->x(1))));
		}
	}
	
	//
	// Biography
	//
	public function biography() {
		return $this->method();
	}
	
	protected function _biography_home() {
		global $bio;
		
		$v = $this->__(w('s'));
		
		$sql = 'SELECT bio_details
			FROM _bio
			WHERE bio_id = ?';
		$details = sql_field(sql_filter($sql, $bio->v('bio_id'), 'bio_details', ''));
		
		$s_hidden = array('module' => $this->control->module, 'a' => $bio->v('bio_alias'), 'x1' => $this->x(1), 'manage' => 'edit');
		
		v_style(array(
			'MESSAGE' => $details,
			'S_HIDDEN' => _hidden($s_hidden))
		);
		
		if ($v->s == 'u') {
			_style('updated');
		}
	}
	
	protected function _biography_modify() {
		global $bio;
		
		if (_button()) {
			$v = $this->__(w('message'));
			
			$sql = 'UPDATE _bio SET bio_details = ?
				WHERE bio_id = ?';
			sql_query(sql_filter($sql, _prepare($v->message), $bio->v('bio_id')));
		}
		
		redirect(_link_control('a', array('a' => $bio->v('bio_alias'), 'x1' => $this->x(1), 's' => 'u')));
	}
	
	//
	// Lyrics
	//
	public function lyrics() {
		return $this->method();
	}
	
	protected function _lyrics_create() {
		
	}
	
	protected function _lyrics_modify() {
		
	}
	
	protected function _lyrics_remove() {
		
	}
	
	//
	// Stats
	//
	public function analytics() {
		return $this->method();
	}
	
	protected function _analytics_home() {
		global $bio;
		
		$sql = 'SELECT *, SUM(stats_members + stats_guests) AS total
			FROM _bio_stats
			WHERE bio_id = ?
			GROUP BY date
			ORDER BY date DESC';
		$stats = sql_rowset(sql_filter($sql, $bio->v('bio_id')), 'stats_date');
		
		$years_sum = w();
		$years_temp = w();
		$years = w();
		
		foreach ($stats as $date => $void) {
			$year = substr($date, 0, 4);
			
			if (!isset($years_temp[$year])) {
				$years[] = $year;
				$years_temp[$year] = true;
			}
			
			if (!isset($years_sum[$year])) {
				$years_sum[$year] = 0;
			}
			
			$years_sum[$year] += $void['total'];
		}
		unset($years_temp);
		
		if (sizeof($years)) {
			rsort($years);
		} else {
			$years[] = date('Y');
		}
		
		$total_graph = 0;
		foreach ($years as $year) {
			_style('year', array(
				'YEAR' => $year)
			);
			
			if (!isset($years_sum[$year])) {
				$years_sum[$year] = 0;
			}
			
			for ($i = 1; $i < 13; $i++) {
				$month = (($i < 10) ? '0' : '') . $i;
				$monthdata = (isset($stats[$year . $month])) ? $stats[$year . $month] : (object) w();
				$monthdata->total = isset($monthdata->total) ? $monthdata->total : 0;
				$monthdata->percent = ($years_sum[$year] > 0) ? $monthdata->total / $years_sum[$year] : 0;
				$monthdata->members = isset($monthdata->members) ? $monthdata->members : 0;
				$monthdata->guests = isset($monthdata->guests) ? $monthdata->guests : 0;
				$monthdata->unix = _timestamp($i, 1, $year, 0, 0, 0);
				$total_graph += $monthdata->total;
				
				_style('year.month', array(
					'NAME' => _format_date($monthdata->unix, 'F'),
					'TOTAL' => $monthdata->total,
					'MEMBERS' => $monthdata->members,
					'GUESTS' => $monthdata->guests,
					'PERCENT' => sprintf("%.1d", ($monthdata->percent * 100)))
				);
			}
		}
		
		v_style(array(
			'BEFORE_VIEWS' => number_format($bio->v('bio_views')),
			'SHOW_VIEWS_LEGEND' => ($this->data['views'] > $total_graph))
		);
		
		return;
	}
	
	public function media() {
		return $this->method();
	}
	
	protected function _media_home() {
		global $bio;
		
		$sql = 'SELECT *
			FROM _bio_media
			WHERE media_bio = ?
			ORDER BY title';
		if (!$media = sql_rowset(sql_filter($sql, $bio->v('bio_id')))) {
			_style('empty', array(
				'MESSAGE' => _lang('CONTROL_A_DOWNLOADS_EMPTY'))
			);
		}
		
		$downloads_type = array(
			1 => '/net/icons/browse.gif',
			2 => '/net/icons/store.gif'
		);
		
		$col = 0;
		foreach ($media as $i => $row) {
			if (!$i) _style('downloads');
			
			if (!$col) _style('downloads.row');
			
			_style('downloads.row.col', array(
				'ITEM' => $row->media_id,
				'URL' => _link_control('a', array('a' => $this->data['subdomain'], 'mode' => $this->mode, 'manage' => 'edit', 'd' => $row['id'])),
				'POSTS_URL' => _link('a', array($this->data['subdomain'], 9, $row['id'])) . '#dpf',
				'IMAGE_TYPE' => $downloads_type[$row['ud']],
				'DOWNLOAD_TITLE' => $row['title'],
				'VIEWS' => $row['views'],
				'DOWNLOADS' => $row['downloads'],
				'POSTS' => $row['posts'])
			);
			
			$col = ($col == 2) ? 0 : $col + 1;
		}
		
		return;
	}
	
	protected function _media_create() {
		
	}
	
	protected function _media_modify() {
		
	}
	
	protected function _media_remove() {
		
	}
}

/*
$v = $this->__(w('alias tab'));

if (!f($v['alias'])) {
	$v['alias'] = $bio->v('bio_alias');
}

$internal_function = '_page_home_' . $v['tab'];

if (!method_exists($this, $internal_function)) {
	_fatal();
}

if ($v['alias'] != $bio->v('bio_alias')) {
	$v_profile = $bio->v();
} else {
	$sql = 'SELECT *
		FROM _bio
		WHERE bio_alias = ?
			AND bio_active = ?
			AND bio_id NOT IN (SELECT ban_bio
				FROM _bio_ban)
			AND bio_id NOT IN (SELECT ban_assoc
				FROM _bio_ban n
				WHERE n.ban_assoc = ?
					AND n.ban_bio = m.user_id)';
	if (!$v_profile = _fieldrow(sql_filter($sql, $v['alias'], 1, $bio->v('bio_id')))) {
		_fatal();
	}
}

//
// User access to this profile
switch ($v_profile['bio_profile_access']) {
	case 1:
		$sql = 'SELECT fan_id
			FROM _bio_fans
			WHERE fan_uid = ?
				AND fan_of = ?';
		if (!_fieldrow(sql_filter($sql, $bio->v('bio_id'), $v_profile['bio_id']))) {
			_fatal();
		}
		break;
}

//
// Global data
$sql = 'SELECT MAX(session_time) AS session_time
	FROM _sessions
	WHERE session_bio = ?';
$session_time = _field(sql_filter($sql, $memberdata['bio_id']), 'session_time', 0);

$session_online = ($v_profile['bio_show'] && $session_time >= (time() - $core->v('session_length'))) ? 'online' : 'offline';
*/

?>