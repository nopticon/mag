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

class core {
	public $email;
	public $cache;
	
	protected $input = array();
	
	protected $config;
	protected $sf;
	
	public function __construct() {
		$sql = 'SELECT *
			FROM _config';
		$this->config = sql_rowset($sql, 'config_name', 'config_value');
		
		if ($this->v('site_disable')) {
			exit('not_running');
		}
		
		$address = $this->v('site_address');
		$host_addr = array_key(explode('/', array_key(explode('://', $address), 1)), 0);
		
		if ($host_addr != get_host()) {
			$allow_hosts = get_file(XFS.XCOR . 'store/domain_alias');
			
			foreach ($allow_hosts as $row) {
				if (substr($row, 0, 1) == '#') continue;
				
				$remote = (strpos($row, '*') === false);
				$row = (!$remote) ? str_replace('*', '', $row) : $row;
				$row = str_replace('www.', '', $row);
				
				if ($row == get_host()) {
					$sub = str_replace($row, '', get_host());
					$sub = (f($sub)) ? $sub . '.' : ($remote ? 'www.' : '');
					
					$address = str_replace($host_addr, $sub . $row, $address);
					$this->v('site_address', $address, true);
					break;
				}
			}
		}
		
		if (strpos($address, 'www.') !== false && strpos(get_host(), 'www.') === false && strpos($address, get_host())) {
			$a = $this->v('site_address') . str_replace(str_replace('www.', '', $address), '', _page());
			redirect($a, false);
		}
		
		$this->cache_dir = XFS.XCOR . 'cache/';
		
		if (is_remote() && @file_exists($this->cache_dir) && @is_writable($this->cache_dir) && @is_readable($this->cache_dir)) {
			$this->cache_f = true;
		}
		
		//
		// Load additional objects.
		//
		$this->email = _import('emailer');
		$this->cache = _import('cache');
		
		return;
	}
	
	public function v($k, $v = false, $nr = false) {
		$a = (isset($this->config->$k)) ? $this->config->$k : false;
		
		if ($nr !== false && $v !== false) {
			$this->config->$k = $v;
			return $v;
		}
		
		if ($v !== false) {
			$sql_update = array('config_value' => $v);
			
			if ($a !== false) {
				$sql = 'UPDATE _config SET ' . sql_build('UPDATE', $sql_update) . sql_filter('
					WHERE config_name = ?', $k);
			} else {
				$sql_update['config_name'] = $k;
				$sql = 'INSERT INTO _config' . sql_build('INSERT', $sql_update);
			}
			sql_query($sql);
			$this->config->$k = $a = $v;
		}
		
		return $a;
	}
	
	public function run($mod = false) {
		global $bio, $core, $file, $warning;
		
		if (!$rewrite = enable_rewrite()) {
			$warning->now('Enable mod_rewrite on Apache.');
		}
		
		require_once(XFS.XCOR . 'modules.php');
		
		if ($mod === false) {
			$mod = request_var('module', '');
		}
		$mod = (!empty($mod)) ? $mod : 'home';
		
		if (!$_module = $core->cache->load('module_' . str_replace('/', '_', $mod))) {
			$sql = 'SELECT *
				FROM _modules
				WHERE module_alias = ?';
			if (!$_module = $core->cache->store(sql_fieldrow(sql_filter($sql, $mod)))) {
				$warning->now('no_module');
			}
		}
		
		$_module->module_path = XFS.XMOD . $_module->module_path . $_module->module_basename;
		
		if (!@file_exists($_module->module_path)) {
			$warning->now('no_path: ' . $_module->module_path);
		}
		
		@require_once($_module->module_path);
		
		$_object = '__' . $mod;
		if (!class_exists($_object)) {
			$warning->now();
		}
		$module = new $_object();
		
		$module->m($mod);
		
		if (@method_exists($module, 'install')) {
			$module->_install();
		}
		
		if (!defined('ULIB')) {
			define('ULIB', _link() . str_replace(w('../ ./'), '', LIB));
		}
		
		if (empty($this->input)) {
			$_input = array();
			
			if ($arg = request_var('args')) {
				foreach (explode('.', $arg) as $str_pair) {
					$pair = explode(':', $str_pair);
					
					if (isset($pair[0]) && isset($pair[1]) && !empty($pair[0])) {
						$this->input[$pair[0]] = $pair[1];
					}
				}
			}
			
			if (isset($_POST) && count($_POST)) {
				$_POST = _utf8($_POST);
				$this->input = array_merge($this->input, $_POST);
			}
		}
		
		$module->levels($this->input);
		
		if (!method_exists($module, $module->x(1))) {
			$warning->now();
		}
		
		if ($module->auth() && (!$module->x(1) || !in_array($module->x(1), $module->exclude))) {
			$module->signin();
		}
		
		//
		// All verifications passed, so start session for the request
		$bio->start(true);
		$bio->setup();
		
		if (!$module->auth_access() && $module->auth()) {
			$warning->now();
		}
		
		$module->navigation('home', '', '');
		$module->navigation($module->m(), '');
		
		if ($module->x(1) != 'home' && @method_exists($module, 'init')) {
			$module->init();
		}
		
		hook(array($module, $module->x(1)));
		
		if (!$module->_template()) {
			$module->_template($mod);
		}
		
		//
		// Output template
		$page_module = 'MODULE_' . $mod;
		if ($bio->is_lang($page_module)) {
			$module->page_title($page_module);
		}
		
		$browser_upgrade = false;
		if (!$core->v('skip_browser_detect') && ($list_browser = $file->read(XFS.XCOR . 'store/need_browser'))) {
			$browser_list = w();
			
			foreach ($list_browser as $row) {
				$e = explode(' :: ', $row);
				$browser_list[$e[0]] = $e[1];
			}
			
			foreach ($browser_list as $browser => $version) {
				if (_browser($browser) && _browser($browser, $version)) {
					v_style(array(
						'visual' => ULIB . LIB_VISUAL)
					);
					$module->_template('browsers');
					$browser_upgrade = true;
				}
			}
		}
		
		$sv = array(
			'X1' => $module->x(1),
			'X2' => $module->x(2),
			'NAVIGATION' => $module->get_navigation(),
			'BROWSER_UPGRADE' => $browser_upgrade
		);
		_layout($module->_template(), $module->page_title(), $sv);
	}
	
	// Used by template system $A[]
	public function auth($a) {
		return _auth_get($a);
	}
	
	public function _sf($a = false) {
		if ($a !== false) {
			$this->sf[] = $a;
		}
		
		if (!count($this->sf)) {
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

class fff {
	public function __construct($a = null) {
		$this->val = isset($a) ? $a : null;
	}
	
	public function __call($fun, $pars) {
		if (!count($pars)) {
			$pars = array(this);
		}
		
		foreach ($pars as &$v) {
			if ($v === this) {
				$v = $this->val;
				break;
			}
		}
		
		$tmp = eval(sprintf('return defined("%1$s") ? constant("%1$s") : "%1$s";', $fun));
		if ($tmp == 'x') {
			return $this->val;
		}
		
		$this->val = @hook($tmp, $pars);
		return $this;
	}
}

?>