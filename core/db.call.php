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

function prefix($prefix, $arr)
{
	$prefix = ($prefix != '') ? $prefix . '_' : '';
	
	$a = w();
	foreach ($arr as $k => $v)
	{
		$a[$prefix . $k] = $v;
	}
	return $a;
}

// Database filter layer
// Idea from http://us.php.net/manual/en/function.sprintf.php#93156
function sql_filter()
{
	if (!$args = func_get_args())
	{
		return false;
	}
	
	$sql = array_shift($args);
	$count_args = count($args);
	
	$sql = str_replace('%', '[!]', $sql);
	
	if (!$count_args || $count_args < 1)
	{
		return str_replace('[!]', '%', $sql);
	}
	
	if ($count_args == 1 && is_array($args[0]))
	{
		$args = $args[0];
	}
	
	$args = array_map('_escape', $args);
	
	foreach ($args as $i => $row)
	{
		if (strpos($row, 'addquotes') !== false)
		{
			$e_row = explode(',', $row);
			array_shift($e_row);
			
			foreach ($e_row as $j => $jr)
			{
				$e_row[$j] = "'" . $jr . "'";
			}
			
			$args[$i] = implode(',', $e_row);
		}
	}
	
	array_unshift($args, str_replace(w('?? ?'), w("%s '%s'"), $sql));
	
	// Conditional deletion of lines if input is zero
	if (strpos($args[0], '-- ') !== false)
	{
		$e_sql = explode("\n", $args[0]);
		
		$matches = 0;
		foreach ($e_sql as $i => $row)
		{
			$e_sql[$i] = str_replace('-- ', '', $row);
			if (strpos($row, '%s'))
			{
				$matches++;
			}
			
			if (strpos($row, '-- ') !== false && !$args[$matches])
			{
				unset($e_sql[$i], $args[$matches]);
			}
		}
		
		$args[0] = implode($e_sql);
	}
	
	return str_replace('[!]', '%', hook('sprintf', $args));
}

function sql_query($sql)
{
	global $database;
	
	return $database->query($sql);
}

function sql_transaction($status = 'begin')
{
	global $database;
	
	return $database->transaction($status);
}

function sql_field($sql, $field, $def = false)
{
	global $database;
	
	$result = $database->query($sql);
	$response = $database->fetchfield($field);
	$database->freeresult($result);
	
	if ($response === false)
	{
		$response = $def;
	}
	
	return (object) $response;
}

function sql_fieldrow($sql, $result_type = MYSQL_ASSOC)
{
	global $database;
	
	$result = $database->query($sql);
	
	$response = false;
	if ($row = $database->fetchrow($result, $result_type))
	{
		$row['_numrows'] = $database->numrows($result);
		$response = $row;
	}
	$database->freeresult($result);
	
	return (object) $response;
}

function sql_rowset($sql, $a = false, $b = false, $g = false, $rt = MYSQL_ASSOC)
{
	global $database;
	
	$result = $database->query($sql);
	
	$arr = w();
	while ($row = $database->fetchrow($result, $rt))
	{
		$z = ($b === false) ? (object) $row : $row[$b];
		
		if ($a === false)
		{
			$arr[] = $z;
		}
		else
		{
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

function _rowset_style($sql, $style, $prefix = '')
{
	$a = sql_rowset($sql);
	_rowset_foreach($a, $style, $prefix);
	
	return $a;
}

function _rowset_foreach($rows, $style, $prefix = '')
{
	$i = 0;
	foreach ($rows as $row)
	{
		if (!$i) _style($style);
		
		_rowset_style_row($row, $style, $prefix);
		$i++;
	}
	
	return;
}

function _rowset_style_row($row, $style, $prefix = '')
{
	if (f($prefix)) $prefix .= '_';
	
	$f = w();
	foreach ($row as $_f => $_v)
	{
		$g = array_key(array_slice(explode('_', $_f), -1), 0);
		$f[strtoupper($prefix . $g)] = $_v;
	}
	
	return _style($style . '.row', $f);
}

function sql_close()
{
	global $database;
	
	if (isset($database))
	{
		$database->close();
		
		return true;
	}
	
	return false;
}

function sql_queries()
{
	global $database;
	
	return $database->num_queries();
}

function sql_query_nextid($sql)
{
	global $database;
	
	$database->query($sql);

	return $database->nextid();
}

function sql_nextid()
{
	global $database;
	
	return $database->nextid();
}

function sql_affected($sql)
{
	global $database;
	
	$database->query($sql);
	
	return $database->affectedrows();
}

function sql_affectedrows()
{
	global $database;
	
	return $database->affectedrows();
}

function sql_escape($sql)
{
	global $database;
	
	return $database->escape($sql);
}

function sql_build($cmd, $a, $b = false)
{
	global $database;
	
	return $database->build_array($cmd, $a, $b);
}

function sql_cache($sql, $sid = '', $private = true)
{
	global $database;
	
	return $database->cache($sql, $sid, $private);
}

function sql_cache_limit(&$arr, $start, $end = 0)
{
	global $database;
	
	return $database->cache_limit($arr, $start, $end);
}

function sql_numrows(&$a)
{
	$response = $a['_numrows'];
	unset($a['_numrows']);
	
	return $response;
}

function sql_history()
{
	global $database;
	
	return $database->history();
}

?>