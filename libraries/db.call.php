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

function prefix($prefix, $arr) {
	$prefix = ($prefix != '') ? $prefix . '_' : '';
	
	$a = w();
	foreach ($arr as $k => $v) {
		$a[$prefix . $k] = $v;
	}
	return $a;
}

// Database filter layer
// Idea from http://us.php.net/manual/en/function.sprintf.php#93156
function sql_filter() {
	if (!$args = func_get_args()) {
		return false;
	}
	
	$sql = array_shift($args);
	$count_args = count($args);
	
	$sql = str_replace('%', '[!]', $sql);
	
	if (!$count_args || $count_args < 1) {
		return str_replace('[!]', '%', $sql);
	}
	
	if ($count_args == 1 && is_array($args[0])) {
		$args = $args[0];
	}
	
	$args = array_map('sql_escape', $args);
	
	foreach ($args as $i => $row) {
		if (strpos($row, 'addquotes') !== false) {
			$e_row = explode(',', $row);
			array_shift($e_row);
			
			foreach ($e_row as $j => $jr) {
				$e_row[$j] = "'" . $jr . "'";
			}
			
			$args[$i] = implode(',', $e_row);
		}
	}
	
	array_unshift($args, str_replace(w('?? ?'), w("%s '%s'"), $sql));
	
	// Conditional deletion of lines if input is zero
	if (strpos($args[0], '-- ') !== false) {
		$e_sql = explode("\n", $args[0]);
		
		$matches = 0;
		foreach ($e_sql as $i => $row) {
			$e_sql[$i] = str_replace('-- ', '', $row);
			if (strpos($row, '%s')) {
				$matches++;
			}
			
			if (strpos($row, '-- ') !== false && !$args[$matches]) {
				unset($e_sql[$i], $args[$matches]);
			}
		}
		
		$args[0] = implode($e_sql);
	}
	
	return str_replace('[!]', '%', hook('sprintf', $args));
}

function sql_run() {
	global $database;
	
	$queries = func_get_args();
	$result = array();
	$run = array();
	
	if (is_array($queries[0])) {
		$run = $queries[0];
	} else {
		$run = $queries;
	}
	
	foreach ($run as $query) {
		$result[] = $database->query($query);
	}
	
	return $result;
}

function sql_get($select, $table, $attr = array()) {
	global $database;
	
	if (!is_array($select)) {
		$select = w($select);
	}
	
	$attr_def = array(
		'where' => false,
		'order' => false,
		'start' => 0,
		'end' => 0,
		'default' => false,
		'type' => MYSQL_ASSOC,
		'single' => false,
		'group' => false,
		'complex' => false
	);
	foreach ($attr_def as $name => $value) {
		if (!isset($attr[$name])) {
			$attr[$name] = $value;
		}
	}
	
	$attr = (object) $attr;
	$field_count = count($select);
	
	$fields = '';
	$field_name = '';
	$field_default = '';
	
	foreach ($select as $field) {
		if (strpos(':', $field) !== false) {
			$field_part = explode(':', $field);
			
			$field = $field_part[0] . ' AS ' . $field_part[1];
			
			if ($field_count == 1) {
				$field_name = $field_part[1];
			} 
		} elseif ($field_count == 1) {
			$field_name = $field;
		}
		
		$fields .= (($fields) ? ', ' : '') . $field;
	}
	
	$sql = array(
		'SELECT ' . $fields,
		'FROM ' . $table
	);
	
	if ($attr->where !== false) {
		$sql[] = 'WHERE' . $attr->where;
	}
	
	if ($attr->order !== false) {
		$sql[] = 'ORDER BY ' . $attr->order;
	}
	
	if ($attr->start) {
		$sql[] = 'LIMIT ' . $attr->start . (($attr->end) ? ', ' . $attr->end : '');
	}
	
	$sql = implode("\n", $sql);
	$result = $database->query($sql);
	
	if (!$size = sql_size()) {
		return false;
	}
	
	$data = false;
	switch ($size) {
		case 0:
			return false;
		case 1:
			switch ($field_count) {
				case 1:
					$fetch = $database->fetchfield($field_name);
					$database->freeresult($result);
					
					$data = ($fetch !== false) ? (object) $fetch : $attr->default;
					break;
				default:
					$fetch = $database->fetchrow($result, $attr->type);
					$database->freeresult($result);
					
					$data = ($fetch !== false) ? (object) $fetch : false;
					break;
			}
			break;
		default:
			$rows = array();
			while ($row = $database->fetchrow($result, $attr->type)) {
				$z = ($attr->group === false) ? (object) $row : $row[$attr->group];
				
				if ($attr->single === false) {
					$rows[] = $z;
				} else {
					if ($attr->complex) {
						$rows[$row[$attr->single]][] = $z;
					} else {
						$rows[$row[$attr->single]] = $z;
					}
				}
			}
			$database->free();
			
			$data = $rows;
			break;
	}
	
	return $data;
}

function sql_put($table, $assoc) {
	global $database;
	
	$sql = 'INSERT INTO ' . $table . sql_build('INSERT', $assoc);
	if (!$result = $database->query($sql)) {
		return false;
	}
	
	return $database->nextid();
}

function sql_size() {
	
}

function sql_id($sql = false) {
	global $database;
	
	if ($sql !== false) {
		$database->query($sql);
	}
	
	return $database->nextid();
}

function sql_close() {
	global $database;
	
	if (isset($database)) {
		$database->close();
		
		return true;
	}
	
	return false;
}

/*
 * Deprecated functions, will be removed soon.
 */

function sql_query($sql) {
	global $database;
	
	return $database->query($sql);
}

function sql_transaction($status = 'begin') {
	global $database;
	
	return $database->transaction($status);
}

function sql_field($sql, $field, $def = false) {
	global $database;
	
	$result = $database->query($sql);
	$response = $database->fetchfield($field);
	$database->freeresult($result);
	
	if ($response === false) {
		$response = $def;
	}
	
	if ($response !== false) {
		$response = (object) $response;
	}
	
	return $response;
}

function sql_fieldrow($sql, $result_type = MYSQL_ASSOC) {
	global $database;
	
	$result = $database->query($sql);
	
	$response = false;
	if ($row = $database->fetchrow($result, $result_type)) {
		$row['_numrows'] = $database->numrows($result);
		$response = (object) $row;
	}
	$database->freeresult($result);
	
	return $response;
}

function sql_rowset($sql, $a = false, $b = false, $g = false, $rt = MYSQL_ASSOC) {
	global $database;
	
	$result = $database->query($sql);
	
	$arr = w();
	while ($row = $database->fetchrow($result, $rt)) {
		$z = ($b === false) ? (object) $row : $row[$b];
		
		if ($a === false) {
			$arr[] = $z;
		} else {
			if ($g) {
				$arr[$row[$a]][] = $z;
			} else {
				$arr[$row[$a]] = $z;
			}
		}
	}
	$database->freeresult($result);
	
	return (object) $arr;
}

function _rowset_style($sql, $style, $prefix = '') {
	$a = sql_rowset($sql);
	_rowset_foreach($a, $style, $prefix);
	
	return $a;
}

function _rowset_foreach($rows, $style, $prefix = '') {
	$i = 0;
	foreach ($rows as $row) {
		if (!$i) _style($style);
		
		_rowset_style_row($row, $style, $prefix);
		$i++;
	}
	
	return;
}

function _rowset_style_row($row, $style, $prefix = '') {
	if (f($prefix)) $prefix .= '_';
	
	$f = w();
	foreach ($row as $_f => $_v) {
		$g = array_key(array_slice(explode('_', $_f), -1), 0);
		$f[strtoupper($prefix . $g)] = $_v;
	}
	
	return _style($style . '.row', $f);
}

function sql_queries() {
	global $database;
	
	return $database->num_queries();
}

function sql_query_nextid($sql) {
	global $database;
	
	$database->query($sql);

	return $database->nextid();
}

function sql_nextid() {
	global $database;
	
	return $database->nextid();
}

function sql_affected($sql) {
	global $database;
	
	$database->query($sql);
	
	return $database->affectedrows();
}

function sql_affectedrows() {
	global $database;
	
	return $database->affectedrows();
}

function sql_escape($sql) {
	global $database;
	
	return $database->escape($sql);
}

function sql_build($cmd, $a, $b = false) {
	global $database;
	
	return $database->build($cmd, $a, $b);
}

function sql_cache($sql, $sid = '', $private = true) {
	global $database;
	
	return $database->cache($sql, $sid, $private);
}

function sql_cache_limit(&$arr, $start, $end = 0) {
	global $database;
	
	return $database->cache_limit($arr, $start, $end);
}

function sql_numrows(&$a) {
	$response = $a['_numrows'];
	unset($a['_numrows']);
	
	return $response;
}

function sql_history() {
	global $database;
	
	return $database->history();
}

?>