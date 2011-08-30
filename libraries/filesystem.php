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

class filesystem {
	public $socket;
	
	public function __construct() {
		return;
	}
	
	public function read() {
		return;
	}
	
	public function write() {
		return;
	}
	
	public function write_line($path, $data, $mode = 'a+') {
		$socket = @fopen($path, $mode);
		fwrite($socket, $data . "\n");
		fclose($socket);
		
		return $data;
	}
	
	public function open_socket($path = false, $callback = 'opendir') {
		if ($path !== false) {
			$this->socket = @$callback($path);
		}
		
		return $this->socket;
	}
	
	public function read_dir($path, $filter = false, $sd = false) {
		if (substr($path, -1) != '/') {
			$path .= '/';
		}
		
		if (!$this->open_socket($path)) {
			return false;
		}
		
		$r = w();
		while (false !== ($row = @readdir($this->socket))) {
			if ($row == '.' || $row == '..') {
				continue;
			}
			
			if (is_dir($path . $row)) {
				if ($sd === 'files') continue;
				
				$r[$f] = $this->read_dir($path . $row . '/', $filter. $sd);
			} else {
				if (($sd === 'dir') || ($filter !== false && !preg_match('#' . $filter . '#', trim($f)))) continue;
				
				$r[] = $f;
			}
		}
		@closedir($fp);
		
		if (count($r)) {
			array_multisort($r);
		}
		return $r;
	}
	
	public function close_socket($callback = 'closedir') {
		if ($this->socket) {
			@$callback($this->socket);
		}
		
		return true;
	}
}

?>