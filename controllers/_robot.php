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

interface i_robot
{
	public function home();
	public function birthday();
	public function mfeed();
	public function optimize();
	public function contest();
	public function press();
}

class __robot extends xmd implements i_robot
{
	public function __construct()
	{
		parent::__construct();
		
		$this->auth(false);
		$this->_m(_array_keys(w('birthday mfeed optimize contest press')));
	}
	
	public function home()
	{
		_fatal();
	}
	
	public function birthday()
	{
		return $this->method();
	}
	
	protected function _birthday_home()
	{
		global $core;
		
		$birth_start = _timestamp();
		$birth_end = _timestamp();
		
		$sql = 'SELECT bio_id, bio_nickname, bio_email, bio_birth
			FROM _bio
			WHERE bio_birth >= ?
				AND bio_birth <= ?
				AND bio_birthlast < ?
			ORDER BY bio_nickname';
		$birthdays = _rowset(sql_filter($sql, $birthday_start, $birthday_end));
		
		foreach ($birthdays as $row)
		{
			$core->email->init('info', 'birthday:plain');
			$core->email->send($row->bio_email);
		}
		
		return;
	}
	
	public function mfeed()
	{
		return $this->method();
	}
	
	protected function _mfeed_home()
	{
		global $core;
		
		//
		// TODO: Filter by: Country, age range, gender
		//
		
		$sql = 'SELECT bio_id, bio_alias, bio_name, bio_email
			FROM _bio
			WHERE bio_active = ?
				AND bio_id NOT IN (
					SELECT ban_bio
					FROM _bio_ban
				)
			ORDER BY bio_alias
			LIMIT ??, ??';
		$mfeed = _rowset(sql_filter($sql, 1, 0, $core->v('mfeed_limit')));
		
		foreach ($mfeed as $row)
		{
			// TODO: Finish adding properties
			
			$properties = array(
				'to' => $row['bio_email'],
				'subject' => $current['subject'],
				'body' => $current['body'],
				'template' => $current['template']
			);
			_sendmail($properties);
		}
		
		return;
	}
	
	public function optimize()
	{
		return $this->method();
	}
	
	protected function _optimize_home()
	{
		global $core;
		
		$core->v('site_disable', 1);
		
		$tables = _rowset('SHOW TABLES', false, false, false, MYSQL_NUM);
		
		foreach ($tables as $row)
		{
			sql_query('OPTIMIZE TABLE ' . $row[0]);
		}

		$core->v('site_disable', 0);
		
		return $this->warning->set('optimized');
	}
	
	public function contest()
	{
		return $this->method();
	}
	
	protected function _contest_home()
	{
		return;
	}
	
	public function press()
	{
		return $this->method();
	}
	
	protected function _press_home()
	{
		global $bio;
		
		$sql = 'SELECT *
			FROM _newsletter
			WHERE newsletter_active = 1
			LIMIT 1';
		if (!$newsletter = _fieldrow($sql)) {
			$this->warning->set('no_newsletter');
		}
		
		set_time_limit(0);
		
		if (!$newsletter->newsletter_start) {
			$sql = 'UPDATE _newsletter SET newsletter_start = ?
				WHERE newsletter_id = ?';
			sql_query(sql_filter($sql, time(), $newsletter->newsletter_id));
		}
		
		$sql = 'SELECT bio_id, bio_alias, bio_name, bio_address, bio_lastvisit
			FROM _bio b
			??
			RIGHT JOIN _bio_newsletter bn ON b.bio_id = bn.newsletter_bio
				AND bn.newsletter_receive = ? 
			WHERE b.bio_lastvisit >= ?
				AND b.bio_status <> ?
			ORDER BY b.bio_name
			LIMIT ??, ??';
		
		$sql_country = '';
		if (!empty($newsletter->newsletter_country)) {
			$sql_country = sql_filter(' LEFT JOIN _countries ON bio_country = country_id
				AND country_id IN (??)', implode(', ', w($newsletter->newsletter_country)));
		}
		
		$members = _rowset(sql_filter($sql, $sql_country, 1, $newsletter['newsletter_lastvisit'], 2, $newsletter->newsletter_last, $core->v('newsletter_process')));
		
		$i = 0;
		foreach ($members as $row)
		{
			if (!is_email($row['user_email'])) {
				continue;
			}
			
			$email = array(
				'USERNAME' => $row->username,
				'MESSAGE' => entity_decode($email->email_message)
			);
			
			$core->email->init('press', 'mass:plain', $email);
			$core->email->subject(entity_decode($email['email_subject']));
			
			if (!empty($row['user_public_email']) && $row['user_email'] != $row['user_public_email'] && is_email($row['user_public_email']))
			{
				$core->email->cc($row->bio_address_public);
			}
			
			$core->email->send($row->user_email);
			
			$sql_history = array(
				'history_newsletter' => $newsletter->newsletter_id, 
				'history_bio' => $row->bio_id,
				'history_time' => time(),
			);
			sql_put('_newsletter_history', $sql_history);
			
			sleep(2);
			
			$i++;
		}
		
		if ($i) {
			$email['email_last'] += $i;
			
			$sql = 'UPDATE _newsletter SET newsletter_last = ?
				WHERE newsletter_id = ?';
			sql_query(sql_filter($sql, $newsletter->newsletter_last, $newsletter->newsletter_id));
		} else {
			$sql = 'UPDATE _newsletter SET newsletter_active = ?, newsletter_end = ?
				WHERE newsletter_id = ?';
			sql_query(sql_filter($sql, 0, time(), $newsletter->newsletter_id));
			
			$this->warning->set('finished: ' . $newsletter->newsletter_id);
		}
		
		return $this->warning->set('completed: ' . $i);
	}
}

?>