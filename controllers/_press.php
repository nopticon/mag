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

interface i_press
{
	public function home();
	public function create();
	public function clear();
	public function check();
	public function report();
	public function total();
	public function modifiy();
}

class __press extends xmd implements i_press
{
	public function __construct()
	{
		parent::__construct();
		
		$this->auth(false);
		$this->_m(w('create clear check report total modify'));
		
		$this->load('objects');
	}
	
	public function home()
	{
		return;
	}
	
	public function check()
	{
		return $this->method();
	}
	
	protected function _check_home()
	{
		global $bio, $warning;
		
		$v = $this->__(w('id 0'));
		
		$sql = 'SELECT *
			FROM _press
			WHERE press_id = ?';
		if (!$press = sql_fieldrow(sql_filter($sql, $v->id))) {
			$warning->now();
		}
		
		foreach (w('lastvisit start end') as $k)
		{
			$k = 'press_' . $k;
			$press->$k = !empty($press->$k) ? $user->format_date($press->$k) : '';
		}
		
		foreach ($press as $k => $v)
		{
			if (is_numb($k)) unset($press->$k);
		}
		
		$warning->list($press);
	}
	
	public function create()
	{
		return $this->method();
	}
	
	protected function _create_home()
	{
		global $warning;
		
		if (_button())
		{
			$v = $this->__(w('subject message lastvisit'));
			
			$sql = 'SELECT press_id
				FROM _press
				WHERE pres_subject = ?
					AND press_message = ?';
			if (!sql_fieldrow(sql_filter($sql, $v->subject, $v->message))) {
				$warning->now();
			}
					
			// d m y 
			$vs = explode(' ', $v->lastvisit);
			$v->lastvisit = mktime(0, 0, 0, $vs[1], $vs[0], $vs[2]);
			$v->active = 1;
			
			sql_put('_press', prefix('email', $v));
			
			$warning->now('ok');
		}
		
		$s = array(
			'SUBJECT' => '',
			'MESSAGE' => '',
			'LASTVISIT' => ''
		);
		_style_vars($s);
	}
	
	public function modify()
	{
		return $this->method();
	}
	
	protected function _modify_home()
	{
		global $bio;
		
		$v = $this->__(w('id 0'));
		
		$sql = 'SELECT *
			FROM _press
			WHERE press_id = ?';
		if (!$press = sql_fieldrow(sql_filter($sql, $v->id))) {
			$warning->now();
		}	
		
		if (_button())
		{
			$v2 = $this->__(array('subject', 'message', 'lastvisit'));
			
			$this->objects->merge($v, $v2);
			$v = $this->objects->all();
			
			$vs = explode(' ', $v->lastvisit);
			$v->lastvisit = mktime(0, 0, 0, $vs[1], $vs[0], $vs[2]);
			
			$sql = 'UPDATE _press SET ' . sql_build('UPDATE', prefix('email', $v)) . sql_filter('
				WHERE press_id = ?', $v->id);
			sql_build($sql);
			
			$warning->now('ok');
		}
		
		$lastvisit = $user->format_date($press->press_lastvisit, 'j n Y');
		
		$s = array(
			'SUBJECT' => $press->press_subject,
			'MESSAGE' => $press->press_message,
			'LASTVISIT' => $lastvisit
		);
		_style_vars($s);
	}
	
	public function clear()
	{
		return $this->method();
	}
	
	protected function _clear_home()
	{
		global $bio;
		
		$v = $this->__(w('id 0'));
		
		if ($v->id)
		{
			$sql = 'SELECT *
				FROM _press
				WHERE press_id = ?';
			if (!$press = sql_fieldrow(sql_filter($sql, $v->id))){
				$warning->now();
			}
			
			$sql_update = array(
				'active' => 0,
				'start' => 0,
				'end' => 0,
				'last' => 0
			);
			
			$sql = 'UPDATE _press SET ' . sql_build('UPDATE', prefix('press', $sql_update)) . sql_filter('
				WHERE press_id = ?', $v->id);
			sql_query($sql);
			
			$warning->now('ok');
		}
		
		$sql = 'SELECT press_id, press_subject
			FROM _press
			ORDER BY press_start';
		$press = sql_rowset($sql);
		
		$response = '';
		foreach ($press as $i => $row)
		{
			if (!$i) _style('press');
			
			_style('press.row', array(
				'LINK' => _link($this->m(), array('x1' => 'clear', 'id' => $row->press_id)),
				'SUBJECT' => $row->subject)
			);
		}
		
		return true;
	}
	
	public function report()
	{
		return $this->method();
	}
	
	protected function _report_home()
	{
		$report = $this->implode('', @file(XFS.XCOR . 'store/newsletter'));
		
		$list = explode("\n", $report);
		
		$a = '';
		foreach ($list as $i => $row)
		{
			$a .= ($i + 1) . ' > ' . $row . '<br />';
		}
		
		$this->e($a);
	}
	
	public function total()
	{
		return $this->method();
	}
	
	protected function _total_home()
	{
		global $warning;
		
		$v = $this->__(w('id 0'));
		
		$sql = 'SELECT *
			FROM _press
			WHERE press_id = ?';
		if (!$press = sql_fieldrow(sql_filter($sql, $v->id))) {
			$warning->now();
		}
		
		$sql = 'SELECT COUNT(user_id) AS total
			FROM _bio
			WHERE bio_lastvisit >= ?
				AND bio_country = 90
				AND bio_status = 1
				AND bio_id <> 1';
		$total = sql_field(sql_filter($sql, $press->press_lastvisit), 'total', 0);
		
		$sql = 'SELECT COUNT(bio_id) AS total
			FROM _bio';
		$all = sql_field($sql, 'total', 0);
		
		$this->e($total . ' . ' . $all);
	}
}

?>
