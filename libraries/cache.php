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

class cache {
	protected $allow;
	protected $folder;
	protected $last;
	
	public function __construct() {
		$this->last = '';
		$this->allow = false;
		$this->folder = XFS.XCOR . 'cache/';
		
		if (is_remote() && @file_exists($this->folder) && @is_readable($this->folder) && @is_writable($this->folder)) {
			$this->allow = true;
		}
		
		return;
	}
	
	public function encrypt($str) {
		return sha1($str);
	}
	
	public function allowed() {
		return $this->allow;
	}
	
	public function load($v, $force = false) {
		if (!$this->allow && !$force) {
			return;
		}
		
		$filepath = $this->folder . $this->encrypt($v);
		$this->last = $v;
		
		if (!@file_exists($filepath)) {
			return false;
		}
		
		// Cache expiration time
		if (time() - @filemtime($filepath) < 3600) {
			if ($plain = get_file($filepath)) {
				return json_decode($plain[0], true);
			}
		}
		
		return $this->unload($v);
	}
	
	public function unload() {
		if (!$this->allow) {
			return;
		}
		
		$files = w();
		if ($a = func_get_args()) {
			foreach ($a as $row) {
				if (!f($row)) continue;
				
				$files[] = $this->encrypt($row);
			}
		} else {
			$files = _dirlist($this->folder, '^([a-z0-9]+)$', 'files');
		}
		
		foreach ($files as $row) {
			$row = $this->folder . $row;
			if (@file_exists($row)) {
				@unlink($row);
			}
		}
		return false;
	}
	
	public function store($v, $k = false, $force = false) {
		if (!$this->allow && !$force) {
			return $v;
		}
		
		$k = ($k === false) ? $this->last : $k;
		
		if (!f($k)) return;
		
		$this->unload($k);
		$filepath = $this->folder . $this->encrypt($k);
		
		if ($fp = @fopen($filepath, 'w')) {
			if (@flock($fp, LOCK_EX)) {
				fputs($fp, json_encode($v));
				@flock($fp, LOCK_UN);
			}
			
			fclose($fp);
			@chmod($filepath, 0777);
		}
		return $v;
	}
}

?>