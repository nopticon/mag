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

class core
{
	protected $email;
	protected $config;
	protected $sf;
	
	public function __construct()
	{
		$sql = 'SELECT *
			FROM _config';
		$this->config = sql_rowset($sql, 'config_name', 'config_value');
		
		if ($this->v('site_disabled'))
		{
			exit('not_running');
		}
		
		$address = $this->v('site_address');
		$host_addr = array_key(explode('/', array_key(explode('://', $address), 1)), 0);
		
		if ($host_addr != get_host())
		{
			$allow_hosts = get_file('./base/domain_alias');
			
			foreach ($allow_hosts as $row)
			{
				if (substr($row, 0, 1) == '#') continue;
				
				$remote = (strpos($row, '*') === false);
				$row = (!$remote) ? str_replace('*', '', $row) : $row;
				$row = str_replace('www.', '', $row);
				
				if ($row == get_host())
				{
					$sub = str_replace($row, '', get_host());
					$sub = (f($sub)) ? $sub . '.' : ($remote ? 'www.' : '');
					
					$address = str_replace($host_addr, $sub . $row, $address);
					$this->v('site_address', $address, true);
					break;
				}
			}
		}
		
		if (strpos($address, 'www.') !== false && strpos(get_host(), 'www.') === false && strpos($address, get_host()))
 		{
			$a = $this->v('site_address') . str_replace(str_replace('www.', '', $address), '', _page());
			redirect($a, false);
		}
		
		$this->cache_dir = XFS . 'core/cache/';
		
		if (is_remote() && @file_exists($this->cache_dir) && @is_writable($this->cache_dir) && @is_readable($this->cache_dir))
		{
			$this->cache_f = true;
		}
		
		//
		// Load additional objects.
		//
		$this->email = $this->import('emailer');
		$this->cache = $this->import('cache');
		
		return;
	}
	
	public function import($filename, $object  = false)
	{
		global $$object;
		
		if (!$object) $object = $filename;
		
		require_once(XFS . 'core/' . $filename . '.php');
		
		return new $object;
	}
	
	public function v($k, $v = false, $nr = false)
	{
		$a = (isset($this->config[$k])) ? $this->config[$k] : false;
		
		if ($nr !== false && $v !== false)
		{
			$this->config[$k] = $v;
			return $v;
		}
		
		if ($v !== false)
		{
			$sql_update = array('config_value' => $v);
			
			if ($a !== false)
			{
				$sql = 'UPDATE _config SET ' . _build_array('UPDATE', $sql_update) . sql_filter('
					WHERE config_name = ?', $k);
			}
			else
			{
				$sql_update['config_name'] = $k;
				$sql = 'INSERT INTO _config' . _build_array('INSERT', $sql_update);
			}
			_sql($sql);
			$this->config[$k] = $a = $v;
		}
		
		return $a;
	}
	
	// Used by template system $A[]
	public function auth($a)
	{
		return _auth_get($a);
	}
	
	public function _sf($a = false)
	{
		if ($a !== false)
		{
			$this->sf[] = $a;
		}
		
		if (!count($this->sf))
		{
			return false;
		}
		
		return $this->sf;
	}
}

/*
Code from: kexianbin at diyism dot com
http://www.php.net/manual/en/language.oop5.overloading.php#93072

By using __call, we can use php as using jQuery.
*/
define('this', mt_rand());
define('echo', '_echo');

class fff
{
	public function __construct($a = null)
	{
		$this->val = isset($a) ? $a : null;
	}
	
	public function __call($fun, $pars)
	{
		if (!count($pars))
		{
			$pars = array(this);
		}
		
		foreach ($pars as &$v)
		{
			if ($v === this)
			{
				$v = $this->val;
				break;
			}
		}
		
		$tmp = eval(sprintf('return defined("%1$s") ? constant("%1$s") : "%1$s";', $fun));
		if ($tmp == 'x')
		{
			return $this->val;
		}
		
		$this->val = @hook($tmp, $pars);
		return $this;
	}
}

?>