<?php

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

$a = array(
	'a' => '1',
	'b' => 2,
	'c' => 3,
	'd' => 4,
	'e' => 5,
	'f' => 6,
	'g' => 7
);

$b = array(
	'h' => 8,
	'i' => 9,
	'j' => 10,
	'k' => 11,
	'l' => 12,
	'm' => 13
);

$c = (object) $a;
$d = (object) $b;

$obj3 = new objects(true);
$obj3->merge($c, $d);

echo '<pre>';
var_dump($obj3->all());
echo '</pre>';

?>