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

/*
 * if email and key exists then login
 * if email and not key then key recovery
 * if not email and not key then new account
 */

interface i_sign
{
	public function home();
	public function fb();		// signfb
	public function fbn();	// signfbn
	public function in();		// signin
	public function out();	// signout
	public function up();		// signup
	public function ed();		// signed
	public function el();		// signel
}

class __sign extends xmd implements i_sign 
{
	public function __construct()
	{
		parent::__construct();
		
		$this->auth(false);
		$this->_m(_array_keys(w('fb up ed in out')));
	}
	
	public function home()
	{
		_fatal();
	}
	
	public function fb()
	{
		return $this->method();
	}
	
	protected function _fb_home()
	{
		return;
	}
	
	public function in()
	{
		return $this->method();
	}
	
	protected function _in_home()
	{
		global $bio, $core;
		
		$v = $this->__(w('page address key'));
		
		if ($bio->v('auth_member'))
		{
			redirect($v->page);
		}
		
		if (empty($v->address))
		{
			$this->warning->set('LOGIN_ERROR');
		}
		
		if (_button('recovery'))
		{
			$sql = 'SELECT bio_id, bio_name, bio_address, bio_recovery
				FROM _bio
				WHERE bio_address = ?
					AND bio_id <> ?
					AND bio_id NOT IN (
						SELECT ban_userid
						FROM _banlist
					)';
			if ($recovery = sql_fieldrow(sql_filter($sql, $v->address, 1)))
			{
				$email = array(
					'USERNAME' => $recovery->bio_name,
					'U_RECOVERY' => _link('my', array('recovery', 'k' => _rainbow_create($recovery->bio_id))),
					'U_PROFILE' => _link('-', $recovery->bio_nickname)
				);
				
				$core->email->init('info', 'bio_recovery', $email);
				$core->email->send($recovery->bio_address);
				
				$sql = 'UPDATE _bio SET bio_recovery = bio_recovery + 1
					WHERE bio_id = ?';
				_sql(sql_filter($sql, $recovery->bio_id));
			}
			
			$this->_stop('RECOVERY_LEGEND');
		}

		if (empty($v->key))
		{
			$this->warning->set('login_fail');
		}

		$v->register = false;
		$v->field = (is_email($v->address)) ? 'address' : 'name';
		
		$sql = 'SELECT bio_id, bio_key, bio_fails
			FROM _bio
			WHERE bio_?? = ?
				AND bio_blocked = ?';
		if ($_bio = _fieldrow(sql_filter($sql, $v->field, $v->address, 0)))
		{
			if ($_bio->bio_key === _password($v->key))
			{
				if ($_bio->bio_fails)
				{
					$sql = 'UPDATE _bio SET bio_fails = 0
						WHERE bio_id = ?';
					_sql(sql_filter($sql, $_bio->bio_id));
				}
				
				$bio->session_create($_bio->bio_id);
				redirect($v->page);
			}
			
			if ($_bio->bio_fails == $core->v('account_failcount'))
			{
				// TODO: Captcha system if failcount reached
				// TODO: Notification about blocked account
				_fatal(508);
			}
			
			$sql = 'UPDATE _bio SET bio_fails = bio_fails + 1
				WHERE bio_id = ?';
			_sql(sql_filter($sql, $_bio->bio_id));
			
			sleep(5);
			$this->warning->set('login_fail');
		}
		else
		{
			$v->register = true;
		}
		
		if ($v->register)
		{
			$this->_up_home();
		}
		
		return;
	}
	
	public function out()
	{
		return $this->method();
	}
	
	protected function _out_home()
	{
		global $bio;
		
		if ($bio->v('auth_member'))
		{
			$bio->session_kill();
			
			$bio->v('auth_member', false);
			$bio->v('session_page', '');
			$bio->v('session_time', time());
		}
		
		redirect(_link());
	}
	
	public function up()
	{
		return $this->method();
	}
	
	protected function _up_home()
	{
		$v = $this->__(w('address'));
		
		if (_button())
		{
			$v = array_merge($v, $this->__(array_merge(w('alias nickname ref_in'), _array_keys(w('gender country birth_day birth_month birth_year aup ref'), 0))));
			
			if (empty($v->nickname) && !empty($v->address) && !is_email($v->address))
			{
				$v->nickname = $v->address;
			}
			
			if (empty($v->nickname))
			{
				$warning->set('empty_username');
			}
			
			if (bio_length($v-nickname))
			{
				$warning->set('len_alias');
			}
			
			if (!$v->alias = _low($v->nickname))
			{
				$warning->set('bad_alias');
			}
			
			if ($this->alias_exists($v->alias))
			{
				$warning->set('record_alias');
			}
			
			if (!$this->country_exists($v->country))
			{
				$warning->set('bad_country');
			}
			
			if (!$v->birth_day || !$v->birth_month || !$v->birth_year)
			{
				$this->_error('BAD_BIRTH');
			}
			
			$v->birth = _timestamp($v->birth_month, $v->birth_day, $v->birth_year);
			
			$sql_insert = array(
				'alias' => $v->alias,
				'nickname' => $v->nickname,
				'address' => $v->address,
				'gender' => $v->gender,
				'country' => $v->country,
				'birth' => $v->birth
			);
			sql_put('_bio', prefix('user', $sql_insert));
		}
		
		// GeoIP
		require_once(XFS.XCOR . 'geoip.php');
		
		$gi = geoip_open(XFS.XCOR . 'store/geoip.dat', GEOIP_STANDARD);
		$geoip_code = strtolower(geoip_country_code_by_addr($gi, $bio->ip));
		
		$sql = 'SELECT *
			FROM _countries
			ORDER BY country_name';
		$countries = _rowset($sql);
		
		$v2->country = ($v2->country) ? $v2->country : ((isset($country_codes[$geoip_code])) ? $country_codes[$geoip_code] : $country_codes['gt']);
		
		foreach ($countries as $i => $row)
		{
			if (!$i) _style('countries');
			
			_style('countries.row', array(
				'V_ID' => $row->country_id,
				'V_NAME' => $row->country_name,
				'V_SEL' => 0)
			);
		}
		
		return;
	}
	
	public function ed()
	{
		return $this->method();
	}
	
	protected function _ed_home()
	{
		global $bio;
		
		$v = $this->__(w('k'));
		
		if (empty($v->k) || (!$rainbow = _rainbow_check($v->k)))
		{
			_fatal();
		}
		
		$sql = 'UPDATE _bio SET bio_active = 1
			WHERE bio_id = ?';
		_sql(sql_filter($sql, $rainbow->rainbow_uid));
		
		_rainbow_remove($rainbow->rainbow_code);
		
		if (!$bio->v('auth_member'))
		{
			$bio->session_create($rainbow->rainbow_uid);
		}
		
		redirect(_link('-', $bio->v('bio_alias')));
		return;
	}
}

?>