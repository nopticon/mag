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

class objects {
	protected $composite = array();
	protected $use_reference;
	protected $first_precedence;
	
	public function __construct($use_reference = FALSE, $first_precedence = FALSE) {
		$this->use_reference = $use_reference === TRUE ? TRUE : FALSE;
		$this->first_precedence = $first_precedence === TRUE ? TRUE : FALSE;
	}
	
	public function & merge() {
		$objects = func_get_args();
		foreach($objects as &$object) $this->with(&$object);
		unset($object);
		
		return $this;
	}
	
	public function & with(&$object) {
		if (is_object($object)) {
			if ($this->use_reference) {
				if ($this->first_precedence) array_push($this->composite, &$object);
					else array_unshift($this->composite, &$object);
			}
			else {
				if ($this->first_precedence) array_push($this->composite, clone $object);
					else array_unshift($this->composite, clone $object);
			}
		}
		
		return $this;
	}
	
	public function & __get($name) {
		$return = NULL;
		foreach ($this->composite as &$object) {
			if (isset($object->$name)) {
				$return =& $object->$name;
				break;
			}
		}
		
		unset($object);
		return $return;
	}
	
	public function & all() {
		$a = new stdClass;
		
		foreach ($this->composite as $k => $v) {
			foreach ($v as $k1 => $v1) {
				$a->$k1 = $v1;
			}
		}
		
		return $a;
	}
}

?>