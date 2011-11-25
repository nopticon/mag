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

function _import($filename, $object  = false) {
	if (!$object) $object = $filename;
	
	$filepath = XFS.XCOR . $filename . '.php';
	
	if (!file_exists($filepath)) {
		echo 'Unable to import: ' . $filename;
		exit;
	}
	
	require_once($filepath);
	
	return new $object;
}

function html_encode($str, $multibyte = false) {
	$result = trim(htmlentities(str_replace(array("\r\n", "\r", '\xFF'), array("\n", "\n", ' '), $str)));
	$result = (get_magic_quotes_gpc()) ? stripslashes($result) : $result;
	if ($multibyte) {
		$result = preg_replace('#&amp;(\#\d+;)#', '&\1', $result);
	}
	$result = preg_replace('#&amp;((.*?);)#', '&\1', $result);
	
	return $result;
}

function set_var(&$result, $var, $type, $multibyte = false, $regex = '') {
	settype($var, $type);
	$result = $var;
	
	if ($type == 'string') {
		$result = html_encode($result, $multibyte);
	}
}

function request_var($var_name, $default = '', $multibyte = false, $regex = '') {
	if (REQC) {
		global $core;
		
		if (strstr($var_name, $core->v('cookie_name')) && isset($_COOKIE[$var_name])) {
			$_REQUEST[$var_name] = $_COOKIE[$var_name];
		}
	}
	
	// TODO: Parse $_FILES format, (files:name)
	
	if (!isset($_REQUEST[$var_name]) || (is_array($_REQUEST[$var_name]) && !is_array($default)) || (is_array($default) && !is_array($_REQUEST[$var_name]))) {
		return (is_array($default)) ? w() : $default;
	}
	
	$var = $_REQUEST[$var_name];
	if (!is_array($default)) {
		$type = gettype($default);
		$var = ($var);
	} else {
		list($key_type, $type) = each($default);
		$type = gettype($type);
		$key_type = gettype($key_type);
	}
	
	if (is_array($var)) {
		$_var = $var;
		$var = w();

		foreach ($_var as $k => $v) {
			if (is_array($v)) {
				foreach ($v as $_k => $_v) {
					set_var($k, $k, $key_type);
					set_var($_k, $_k, $key_type);
					set_var($var[$k][$_k], $_v, $type, $multibyte);
				}
			} else {
				set_var($k, $k, $key_type);
				set_var($var[$k], $v, $type, $multibyte);
			}
		}
	} else {
		set_var($var, $var, $type, $multibyte);
	}
	
	return $var;
}

/*
Code from: kexianbin at diyism dot com
http://www.php.net/manual/en/language.oop5.overloading.php#93072

By using __call, we can use php as using jQuery.
*/
function __($a = null) {
	if (isset($a)) {
		return new fff($a);
	} else {
		if (!isset($GLOABALS['__'])) {
			$GLOABALS['__'] = new fff();
		} else {
			$GLOABALS['__']->val = null;
		}
		return $GLOABALS['__'];
	}
}

function _utf8($a, $e = false) {
	if (is_array($a)) {
		foreach ($a as $k => $v) {
			$a[$k] = _utf8($v, $e);
		}
	} else {
		if ($e !== false) {
			$a = utf8_encode($a);
		} else {
			$a = utf8_decode($a);
		}
	}
	
	return $a;
}

function _pre($a, $d = false) {
	echo '<pre>';
	print_r($a);
	echo '</pre>';
	
	if ($d === true) {
		exit;
	}
}

function email_format($email) {
	if (preg_match('/^[a-z0-9&\'\.\-_\+]+@[a-z0-9\-]+\.([a-z0-9\-]+\.)*?[a-z]+$/is', $email)) {
		return true;
	}
	return false;
}

function enable_rewrite() {
	global $core;
	
	if (!$rewrite = $core->cache->load('rewrite_enabled')) {
		ob_start();
		phpinfo(INFO_MODULES);
		$contents = ob_get_contents();
		ob_end_clean();
		
		$rewrite = strpos($contents, 'mod_rewrite') !== false;
		$core->cache->store('rewrite_enabled', $rewrite);
	}
	
	return $rewrite;
}

function _tbrowser($tpl = '') {
	if (!f($tpl)) {
		$a = _browser();
		$_a = array($a['type'] . '-' . $a['platform'] . '-' . $a['browser'] . '-' . $a['version'], $a['type'] . '-' . $a['platform'] . '-' . $a['browser'], $a['type'] . '-' . $a['platform'], $a['type']);
		
		foreach ($a as $row) {
			if (@file_exists(XFS . XHTM . $row)) {
				$tpl = $row;
				break;
			}
		}
	}
	
	if (!f($tpl)) $tpl = 'desktop';
	
	return $tpl;
}

//function msg_handler($errno, $msg_text, $errfile, $errline)
function _fatal($code = 404, $errfile = '', $errline = '', $errmsg = '', $errno = 0) {
	global $core, $file, $warning;
	
	sql_close();
	
	// TODO: Check if is ghost request.
	
	$warning = '<b>%s</b>: in file <b>%s</b> on line <b>%s</b>: <b>%s</b><br>';
	
	switch ($code) {
		case 504:
		case 505:
			echo sprintf($warning, 'PHP Notice', $errfile, $errline, $errmsg);
			break;
		case 506:
			exit('USER_ERROR: ' . $errmsg);
			break;
		default:
			$error_path = XFS.XHTM . _tbrowser() . '/warnings/%s.htm';
			$sql_message = 'SQL ERROR @ %s # %s<br /><br />' . "\n" . ' %s<br /><br />' . "\n";
			
			if ($errno) {
				$code .= '-' . $errno;
			}

			$filepath = sprintf($error_path, $code . ((is_ghost()) ? '-ghost' : ''));
			if (!@file_exists($filepath)) {
				$filepath = sprintf($error_path, 'default');
			}
			
			$v_host = get_protocol() . get_host();
			
			// SQL error
			if ($code == 507) {
				$sql_time = date('r');
				$sql_format = str_replace(array("\n", "\t"), array('<br />', '&nbsp;&nbsp;&nbsp;'), $errmsg['sql']);
				$sql_message = sprintf($sql_message, get_host(), $sql_time, _page());
				
				if (!empty($errmsg['message'])) {
					$sql_message .= $errmsg['message'] . '<br /><br />' . "\n";
				}
				$sql_message .= $sql_format;
				
				$errmsg = '';
				if (!is_remote()) {
					$errmsg = '<br /><br />' . $sql_message;
				}
				$sql_message = _utf8($sql_message);
				
				if (!$report_to = $file->read(XFS.XCOR . 'store/server_admin')) {
					$report_to = array(v_server('SERVER_ADMIN'));
				}
				
				// Send report to server admins @ XFS.XCOR . store/server_admin
				if (count($report_to)) {
					/*
					$core->email->init($report_to[0]);
					$core->email->subject('PHP/SQL error @ ' . get_host());
					$core->email->message($sql_message);
					$core->email->send($report_to);
					*/
					echo $sql_message;
					exit;
				}
			}
			
			$v_self = v_server('PHP_SELF');
			
			$replace = array(
				'{ERROR_LINE}' => $errline,
				'{ERROR_FILE}' => $errfile,
				'{ERROR_MSG}' => $errmsg,
				'{HTTP_HOST}' => $v_host . str_replace(basename($v_self), '', $v_self),
				'{REQUEST_URL}' => v_server('REQUEST_URI'),
				'{REQUEST_METHOD}' => v_server('REQUEST_METHOD')
			);
			$header_code = array(
				'0' => '404 Not Found',
				'507' => '501 Internal Error',
				'405' => '405 Method Not Allowed',
			);
			
			if (!isset($header_code[$code])) {
				$code = 0;
			}
			header('HTTP/1.1 ' . $header_code[$code]);
			
			echo str_replace(array_keys($replace), array_values($replace), implode('', @file($filepath)));
			exit();
			break;
	}
	
	return false;
}

function msg_handler($errno, $msg_text, $errfile, $errline) {
	global $bio;
	
	switch ($errno) {
		case E_NOTICE:
		case E_WARNING:
			_fatal(504, $errfile, $errline, $msg_text, $errno);
			break;
		case E_USER_ERROR:
			_fatal(506, '', '', $msg_text, $errno);
			break;
		case E_USER_NOTICE:
			_fatal(503, '', '', $bio->_lang($msg_text), $errno);
			break;
		default:
			_fatal(505, $errfile, $errline, $msg_text, $errno);
			break;
	}
	return;
}

function gfatal($c = 404) {
	if (!is_ghost()) _fatal($c);
}

function hook($name, $args = array(), $arr = false) {
	switch ($name) {
		case 'isset':
			eval('$a = ' . $name . '($args' . ((is_array($args)) ? '[0]' . $args[1] : '') . ');');
			return $a;
			break;
		case 'in_array':
			if (is_array($args[1])) {
				if (hook('isset', array($args[1][0], $args[1][1]))) {
					eval('$a = ' . $name . '($args[0], $args[1][0]' . $args[1][1] . ');');
				}
			} else {
				eval('$a = ' . $name . '($args[0], $args[1]);');
			}
			
			return (isset($a)) ? $a : false;
			break;
	}
	
	$f = 'call_user_func' . ((!$arr) ? '_array' : '');
	return $f($name, $args);
}

function netsock($host, $param = '', $port = 80, $advanced = false, $useragent = false) {
	if (!$fp = @fsockopen($host, $port, $errno, $errstr, 10)) {
		return false;
	}
	
	$call = 'GET ' . $param . " HTTP/1.1\r\n";
	
	if ($useragent !== false) {
		$call.= 'User-Agent: ' . $useragent . "\r\n";
	}
	
	$call .= "Connection: Close\r\n\r\n";
	
	$response = '';
	@fputs($fp, $call);
	while (!feof($fp)) {
		$response .= @fgets($fp, 8192);
	}
	@fclose($fp);
	
	if ($advanced) {
		$response = parse_http_response($response);
	} else {
		$response = ltrim(substr($response, strpos($response, "\r\n\r\n")));
	}
	
	return $response;
}

function parse_http_response($content) {
	if (!f($content)) return false;
	
	// split into array, headers and content.
	$hunks = explode("\r\n\r\n", trim($content));
	
	if (!is_array($hunks) || count($hunks) < 2) return false;
	
	$header = $hunks[0];
	$headers = array_map('trim', explode("\n", $header));
	
	unset($hunks[0]);
	$body = implode('', $hunks);
	//$body = $hunks[count($hunks) - 1];
	
	unset($hunks, $header);
	
	if (!validate_http_response($headers)) return false;
	
	if (in_array('Transfer-Encoding: chunked', $headers)) {
		return trim(unchunk_http_response($body));
	}	
	return trim($body);
}

function validate_http_response($headers) {
	if (!is_array($headers) || count($headers) < 1) return false;
	
	$headers = trim(strtolower($headers[0]));
	
	switch ($headers) {
		case 'http/1.0 100 ok':
		case 'http/1.0 200 ok':
		case 'http/1.1 100 ok':
		case 'http/1.1 200 ok':
			return true;
			break;
	}
	
	return false;
}

function unchunk_http_response($str) {
	if (!is_string($str) || strlen($str) < 1) return false;
	
	$eol = "\r\n";
	$add = strlen($eol);
	$tmp = $str;
	$str = '';
	
	do {
		$tmp = ltrim($tmp);
		$pos = strpos($tmp, $eol);
		if ($pos === false) { return false; }
		
		$len = hexdec(substr($tmp, 0, $pos));
		if (!is_numeric($len) || $len < 0) { return false; }
		
		$str .= substr($tmp, ($pos + $add), $len);
		$tmp  = substr($tmp, ($len + $pos + $add));
		$check = trim($tmp);
	}
	while(f($check));
	
	unset($tmp);
	return $str;
}


//
// Thanks to:
// SNEAK: Snarkles.Net Encryption Assortment Kit
// Copyright (c) 2000, 2001, 2002 Snarkles (webgeek@snarkles.net)
//
// Used Functions: hex2asc()
//
function hex2asc($str) {
	$str2 = '';
	for ($n = 0, $end = strlen($str); $n < $end; $n += 2) {
		$str2 .=  pack('C', hexdec(substr($str, $n, 2)));
	}
	
	return $str2;
}

function encode($str) {
	return bin2hex(base64_encode($str));
}

function decode($str) {
	return base64_decode(hex2asc($str));
}

function f($s) {
	return !empty($s);
}

function array_strpos($haystack, $needle) {
	if (!is_array($needle) || !f($haystack)) return false;
	
	foreach ($needle as $row) {
		if ($pos = strpos($haystack, $row) !== false) return $pos;
	}
	return false;
}

function array_key($a, $k) {
	return (isset($a[$k])) ? $a[$k] : false;
}

function strpos_pad(&$haystack, $needle, $remove_needle = false) {
	if ($pos = strpos(' ' . $haystack, $needle) !== false) {
		if ($remove_needle) {
			$haystack = str_replace($needle, '', $haystack);
		}
		
		return (int) $pos;
	}
	
	return false;
}

function array_isset($a, $f) {
	foreach ($f as $fk => $fv) {
		if (!isset($a[$fk])) {
			$a[$fk] = $fv;
		}
	}
	return $a;
}

function array_compare($needle, $haystack, $match_all = true) {
	if (!is_array($needle) || count($haystack) > count($needle)) {
		return false;
	}
	
	$count = 0;
	$result = false;
	foreach ($needle as $k => $v) {
		if (!isset($haystack[$k])) {
			if ($match_all) {
				return false;
			}
			continue;
		}
		
		if (is_array($v)) {
			$result = array_compare($v, $haystack[$k], $match_all);
		}
		
		$result = ($haystack[$k] === $v) && (($match_all && (!$count || $result)) || !$match_all) || (!$match_all && $result);
		$count++;
	}
	
	return $result;
}

function array_row($a) {
	$w = w();
	foreach ($a as $k => $v) {
		if (!is_numb($k)) {
			$w[$k] = $v;
		}
	}
	return $w;
}

function _array_keys($ary, $d = array()) {
	if (!is_array($ary)) {
		$ary = w($ary);
	}
	
	$a = w();
	foreach ($ary as $k => $v) {
		if (!is_string($k)) {
			$k = $v;
			$v = $d;
		}
		$a[$k] = $v;
	}
	
	return $a;
}

function array_subkey($a, $k) {
	$list = w();
	foreach ($a as $row) {
		$list[] = $row[$k];
	}
	return $list;
}

function array_empty($a) {
	$response = true;
	
	foreach ($a as $k => $v) {
		if (!f($v) && $response) {
			$response = false;
			break;
		}
	}
	
	return !$response;
}

function array_least_key($a) {
	$response = false;
	
	foreach ($a as $k => $v) {
		if (f($v)) {
			$response = $k;
			break;
		}
	}
	
	return $response;
}

function array_alias($arr, $alias, $map = false) {
	if (!is_array($arr) || !is_array($alias)) {
		return false;
	}
	
	if (count($arr[0]) != count($alias)) {
		return false;
	}
	
	$a = w();
	foreach ($arr as $k => $v) {
		$a[$k] = w();
		
		foreach ($v as $k2 => $v2) {
			if (isset($alias[$k2])) {
				$k2 = $alias[$k2];
			}
			
			if ($map !== false & isset($map[$k2])) {
				if (is_array($map[$k2])) {
					foreach ($map[$k2] as $f) {
						$v2 = $f($v2);
					}
				} else {
					$v2 = $map[$k2]($v2);
				}
			}
			
			$a[$k][$k2] = trim($v2);
		}
	}
	
	return $a;
}

function array_push_before(&$src, $in, $pos, $force = true) {
	return _array_push($src, $in, $pos, 'before', $force);
}

function array_push_after(&$src, $in, $pos, $force = true) {
	return _array_push($src, $in, $pos, 'after', $force);
}

function _array_push(&$src, $in, $pos, $mode = 'after', $force = true) {
	static $ik;
	
	$before = ($mode == 'before');
	$after = ($mode == 'after');
	
	if (!isset($ik)) $ik = 0;
	
	if (!is_array($in)) {
		$in = array($ik => $in);
		$ik++;
	}
	
	if (is_int($pos) && !$force) {
		$posd = ($after) ? 1 : 0;
		$src_size = count($src);
		
		if ($pos > $ik) {
			$pos = $ik - 1;
		}
		
		$src = array_merge(array_slice($src, 0, $pos + $posd), $in, array_slice($src, $pos + $posd));
	} else {
		$r = array();
		foreach ($src as $k => $v) {
			if ($after) $r[$k] = $v;
			
			if ($k == $pos) {
				foreach ($in as $k2 => $v2) {
					$r[$k2] = $v2;
				}
 			}
			
			if ($before) $r[$k] = $v;
		}
		$src = $r;
	}
	
	$slice = array_slice($in, -1);
	$last_v = array_key(array_keys($slice), 0);
	
	if (is_string($last_v)) {
		return $last_v;
	}
	
	return array_search(array_key(array_values($slice), 0), $src);
}

function string_to_array_assoc($rows, $keys) {
	$a = w();
	$i = 0;
	
	foreach ($rows as $k => $v) {
		$d = array($k, $v);
		
		$a[$i] = w();
		foreach ($keys as $j => $key) {
			$a[$i][$key] = $d[$j];
		}
		$i++;
	}
	return $a;
}

function preg_array($pattern, $ary) {
	$a = w();
	foreach ($ary as $each) {
		$a[] = sprintf($pattern, $each);
	}
	
	return $a;
}

function array_construct($arr, $k, $i = 0) {
	if (!is_array($arr) || count($k) == $i) {
		return $arr;
	}
	
	$r = (isset($k[$i]) && isset($arr[$k[$i]])) ? $arr[$k[$i]] : false;
	if (is_array($r)) {
		$i++;
		$r = array_construct($r, $k, $i);
	}
	
	return $r;
}

function _implode($glue, $pieces, $empty = false) {
	if (!is_array($pieces) || !count($pieces)) {
		return -1;
	}
	
	foreach ($pieces as $i => $v) {
		if (!f($v) && !$empty) unset($pieces[$i]);
	}
	
	if (!count($pieces)) {
		return -1;
	}
	
	return implode($glue, $pieces);
}

function _implode_and($glue, $last_glue, $pieces, $empty = false) {
	$response = _implode($glue, $pieces, $empty);
	
	$last = strrpos($response, $glue);
	if ($last !== false) {
		$response = substr_replace($response, $last_glue, $last, count($glue) + 1);
	}
	
	return $response;
}

function entity_decode($s, $compat = true) {
	if ($compat) {
		return html_entity_decode($s, ENT_COMPAT, 'UTF-8');
	}
	return html_entity_decode($s);
}

function w($a = '', $d = false) {
	if (!f($a) || !is_string($a)) return array();
	
	$e = explode(' ', $a);
	if ($d !== false) {
		foreach ($e as $i => $v) {
			$e[$v] = $d;
			unset($e[$i]);
		}
	}
	
	return $e;
}

function csv_quotes($s)
{
	return preg_replace('/^"(.*?)"$/i', '\1', $s);
}

function _push(&$v, $a)
{
	if (!is_array($a) || !count($a))
	{
		return w();
	}
	
	if (!isset($v) || !is_array($v) || !count($v))
	{
		$v = w();
	}
	$v = array_merge($v, $a);
	return;
}

function _hash($v, $t = 1)
{
	return _password($v, $t, 'md5');
}

function _selected($a, $b, $bool = false)
{
	if ($bool === false)
	{
		return ($a == $b) ? ' selected="selected"' : '';
	}
	return ($a == $b) ? true : false;
}

function _sf($a = false)
{
	global $core;
	
	return $core->_sf($a);
}

function _sf_read()
{
	if (($sf = _sf()) !== false)
	{
		foreach ($sf as $i => $row)
		{
			if (!$i) _style('style_functions');
			
			_style('style_functions.row', array(
				'F' => $row)
			);
		}
	}
	
	return;
}

function _sf_option($in, $v, $dv = false)
{
	global $bio;
	
	$d = false;
	$dvv = ($dv === false);
	$code = _sf_fh();
	
	$in2 = $in;
	if (strpos($in, '[') !== false)
	{
		$in2 = preg_replace('#\[(.*?)\]#is', '_\\1', $in);
	}
	
	_sf("_.input.option('sf_option_" . $in2 . "');");
	
	foreach ($v as $k1 => $k)
	{
		$active = '';
		if (($d === false && $dvv) || (!$dvv && $dv === $k1)) {
			$active = ' sf_selectd';
			$d = $k1;
		}
		
		$code .= ' <span id="option_' . $k1 . '" class="sf_option_' . $in2 . ' sf_option' . $active . '">' . $bio->_lang($k) . '</span>';
	}
	$code = '<div class="m_fix">' . $code . '</div>';
	
	return sprintf($code, $in2, $in, $d);
}

function _sf_check($in, $d)
{
	return _sf_option($in, array(1 => 'YES', 0 => 'NO'), (int) $d);
}

function _sf_fh()
{
	return '<input type="hidden" id="%s" name="%s" value="%s" />';
}

function is_numb($v)
{
	return @preg_match('/^\d+$/', $v);
}

function get_file($f)
{
	if (!f($f)) return false;
	
	if (!@file_exists($f))
	{
		return w();
	}
	
	return array_map('trim', @file($f));
}

function max_upload_size()
{
	return intval(ini_get('upload_max_filesize')) * 1048576;
}

function is_upper($a)
{
	if (!@function_exists('ctype_upper'))
	{
		return false;
	}
	return ctype_upper($a);
}

function _substr($str, $length, $minword = 3)
{
	$sub = '';
	$len = 0;
	
	foreach (explode(' ', $str) as $word)
	{
		$part = (($sub != '') ? ' ' : '') . $word;
		$sub .= $part;
		$len += strlen($part);
		
		if (strlen($word) > $minword && strlen($sub) >= $length)
		{
			break;
		}
	}
	
	return $sub . (($len < strlen($str)) ? '...' : '');
}

// Code from:  @ php.net
function str_normalize($sentence_split)
{
	$sentence_split = preg_replace("/[!]+/","!",$sentence_split);
	$sentence_split = preg_replace("/[¡]+/","&iexcl;",$sentence_split);
	$sentence_split = preg_replace("/[?]+/","?",$sentence_split);
	$sentence_split = preg_replace("/[¿]+/","&iquest;",$sentence_split);
	
	$textbad = preg_split("/(\<[a-zA-Z0-9-]*\>" . "\!(\s)?|\.(\s)?|\?(\s)?|¿(\s)?|¡(\s)?" . "|&iquest;(\s)?|&iexcl;(\s)?)/", $sentence_split, -1, PREG_SPLIT_DELIM_CAPTURE);
	$newtext = w();
	$count = count($textbad);
	$prevStr = '';
	
	for ($i = 0; $i < $count; $i++)
	{
		$text = trim($textbad[$i]);
		$size = strlen($text);
		
		if ($size > 1)
		{
			$sentencegood = ucfirst(strtolower($text));
			
			if ($i > 0 && $prevStr != '¿' && $prevStr != '¡' && $prevStr != "&iquest;" && $prevStr != "&iexcl;")
			{
				$sentencegood = ' ' . $sentencegood;
			}
			$newtext[] = $sentencegood;
			$prevStr =$text;
		}
		elseif ($size == 1)
		{
			if ($i > 0 && ($text == '¿' || $text == '¡' || $prevStr == "&iquest;" || $prevStr == "&iexcl;"))
			{
				$newtext[] = ' ' . $text;
			}
			else
			{
				$newtext[] = $text;
			}
			$prevStr = $text;
		}
	}
	
	$textgood = implode($newtext);
	return $textgood;
}

function _dvar(&$v, $d)
{
	$v = (isset($v) && f($v)) ? $v : $d;
	return $v;
}

function dvar($v, $d)
{
	return _dvar($v, $d);
}

function _alias($a, $orig = array('-', '_'), $repl = '')
{
	$a = _rm_acute(str_replace(array_merge(array('.', ' '), $orig), $repl, strtolower(trim($a))));
	
	if (!preg_match('/^([a-z0-9])/i', $a)) {
		$a = '';
	}
	
	return $a;
}

function _ad_acute($a)
{
	foreach (w('a e i o u A E I O U') as $row)
	{
		$row = '&' . $row . 'acute;';
		$a = str_replace(entity_decode($row), $row, $a);
	}
	
	return $a;
}

function _rm_acute($a)
{
	return preg_replace('#\&(\w)(tilde|acute)\;#i', '\1', $a);
}

function _low($a, $match = false)
{
	if (!f($a) || ($match && !preg_match('#^([A-Za-z0-9\-\_\ ]+)$#is', $a)))
	{
		return false;
	}
	
	return _alias($a);
}

function _fullname($d)
{
	if (!isset($d['user_firstname']) || !isset($d['user_lastname']))
	{
		return '';
	}
	return _implode(' ', array_map('trim', array($d['user_firstname'], $d['user_lastname'])));
}

function _extension($file)
{
	return strtolower(str_replace('.', '', substr($file, strrpos($file, '.'))));
}

function is_post()
{
	return (request_method() == 'post');
}

function is_ghost()
{
	return request_var('ghost', 0);
}

function _ajax_callback($code, $url)
{
	global $core;
	
	if (is_ghost() === 1)
	{
		if (is_array($code))
		{
			$extra = $code;
			$code = array_push($extra);
		}
		echo $core->je[$code] . ((isset($extra) && count($extra)) ? ':' . implode(':', $extra) : '');
	}
	
	redirect($url);
}

function _echo($v)
{
	echo $v;
	return $v;
}

function _vs($a, $p = '')
{
	$b = w();
	foreach ($a as $k => $v)
	{
		$b[strtoupper((($p != '') ? $p . '_' : '') . $k)] = $v;
	}
	return $b;
}

function _linkp($attr, $slash = false, $c = '/')
{
	if (is_array($attr))
	{
		$attr = array_filter($attr, 'f');
		
		$arg = '';
		foreach ($attr as $k => $v)
		{
			$arg .= (($arg != '') ? ((is_string($k)) ? '.' : $c) : '') . ((is_string($k) && $k != '') ? $k . ':' : '') . $v;
		}
		$url = $arg . ((f($arg) && $slash === true) ? $c : '');
	} else {
		$url = $attr . (($slash === true) ? (f($attr) ? $c : '') : '');
	}
	return $url;
}

function _link($mod = '', $attr = false, $ts = true)
{
	global $core;
	
	$url = get_protocol() . array_key(explode('://', $core->v('site_address')), 1);
	
	if ($mod == 'alias' && $attr !== false && is_remote())
	{
		$alias = $attr;
		
		if (is_array($attr))
		{
			$alias = '';
			if (isset($attr['alias']))
			{
				$alias = $attr['alias'];
				unset($attr['alias']);
			}
			
			$attr = (count($attr)) ? $attr : false;
		}
		
		if ($alias != '') $url = str_replace('www', $alias, $url);
	}
	
	if (is_string($mod) && strpos($mod, ' ') !== false)
	{
		$attr_v = $attr;
		
		$attr = w();
		foreach (explode(' ', $mod) as $k => $v)
		{
			if (strpos($v, ':') !== false) list($k, $v) = explode(':', $v);
			
			$attr[$k] = $v;
		}
		$mod = array_shift($attr);
		
		if ($attr_v !== false)
		{
			if (!is_array($attr_v)) $attr_v = array($attr_v);
			
			$attr = array_merge($attr, $attr_v);
		}
	}
	
	$url .= (($mod != '') ? $mod . (($ts) ? '/' : '') : '');
	
	if ($attr !== false) $url .= _linkp($attr, ($mod != 'fetch' && $ts));
	
	return strtolower($url);
}

function _link_apnd($u, $a)
{
	$eu = array_values(array_filter(explode('/', $u), 'f'));
	$last = array_pop($eu);
	
	if (strpos($last, ':') !== false)
	{
		$eu[] = $last . '.' . $a;
	} else {
		$eu = array_merge($eu, array($last, $a));
	}
	
	$eu[0] .= '/';
	return implode('/', $eu) . '/';
}

function request_type_redirect($a = 'post')
{
	if (request_method() != $a)
	{
		redirect(_link());
	}
	return true;
}

function _filename($a, $b, $m = '.')
{
	return $a . $m . $b;
}

function _subject($s)
{
	$match = array('#\r\n?#', '#sid=[a-z0-9]*?&amp;?#', "#([\n][\s]+){3,}#", "#(\.){3,}#", '#(script|about|applet|activex|chrome):#i');
	$replace = array("\n", '', "\n\n", '...', "\\1&#058;");
	
	$s = preg_replace($match, $replace, trim($s));
	
	return $s;
}

function _filter_prepare($v)
{
	return (strpos($v, '_prepare_') !== false);
}

function _prepare($s)
{
	$s = _subject($s);
	
	$df = get_defined_functions();
	if (count($df) && isset($df['user']))
	{
		$f_list = array_filter($df['user'], '_filter_prepare');
		foreach ($f_list as $row)
		{
			$s = $row($s);
		}
	}
	
	return $s;
}

function _message($a)
{
	global $core;
	
	if ($core->v('markdown_enabled'))
	{
		require_once(XFS . XCOR . 'markdown.php');
		$a = Markdown($a);
	}
	else
	{
		$a = str_replace("\n", '<br />', $a);
		$a = '<p>' . $a . '</p>';
	}
	
	return $a;
}

function _postbox($ref = '', $prefix = 'postbox')
{
	global $bio;
	
	$u_block = ($bio->v('is_member')) ? 'in' : 'out';
	
	_style($prefix);
	_style($prefix . '.' . $u_block, array(
		'V_REF' => $ref,
		'V_OUT' => sprintf($bio->_lang('LOGIN_TO_POST'), _link('signup')))
	);
	
	return;
}

function _template_query($str, $arr)
{
	$repl = w();
	foreach ($arr as $k => $v)
	{
		$repl['a'][] = '{v_' . $k . '}';
		$repl['b'][] = $v;
	}
	
	return str_replace($repl['a'], $repl['b'], $str);
}

function _pagination($url_format_smp, $url_apnd, $total_items, $per_page, $offset)
{
	global $bio;
	
	$begin_end = 5;
	$from_middle = 2;
	
	$total_pages = ceil($total_items / $per_page);
	$on_page = floor($offset / $per_page) + 1;
	$url_format = _link_apnd($url_format_smp, $url_apnd);
	
	$tag = array(
		'strong' => '<strong>%d</strong>',
		'span' => '<span> ... </span>',
		'a' => '<a href="%s">%s</a>'
	);
	
	$pages = '';
	if ($total_pages > ((2 * ($begin_end + $from_middle)) + 2))
	{
		$init_page_max = ($total_pages > $begin_end) ? $begin_end : $total_pages;
		for ($i = 1; $i < $init_page_max + 1; $i++)
		{
			$pages .= _space($pages) . _pagination_multi($i, $on_page, $per_page, $tag, $url_format, $url_format_smp);
		}
		
		if ($total_pages > $begin_end)
		{
			if ($on_page > 1 && $on_page < $total_pages)
			{
				$pages .= ($on_page > ($begin_end + $from_middle + 1)) ? $tag['span'] : '';

				$init_page_min = ($on_page > ($begin_end + $from_middle)) ? $on_page : ($begin_end + $from_middle + 1);
				$init_page_max = ($on_page < $total_pages - ($begin_end + $from_middle)) ? $on_page : $total_pages - ($begin_end + $from_middle);

				for ($i = $init_page_min - $from_middle; $i < $init_page_max + ($from_middle + 1); $i++)
				{
					$pages .= _space($pages) . _pagination_multi($i, $on_page, $per_page, $tag, $url_format, $url_format_smp);
				}
				
				$pages .= ($on_page < $total_pages - ($begin_end + $from_middle)) ? $tag['span'] : '';
			} else {
				$pages .= $tag['span'];
			}
			
			for ($i = $total_pages - ($begin_end - 1); $i < $total_pages + 1; $i++)
			{
				$pages .= _space($pages) . _pagination_multi($i, $on_page, $per_page, $tag, $url_format, $url_format_smp);
			}
		}
	} elseif ($total_pages > 1) {
		for ($i = 1; $i < $total_pages + 1; $i++)
		{
			$pages .= _space($pages) . _pagination_multi($i, $on_page, $per_page, $tag, $url_format, $url_format_smp);
		}
	}
	
	$prev = ($on_page > 1) ? sprintf($tag['a'], sprintf($url_format, (($on_page - 2) * $per_page)), sprintf($bio->_lang('PAGES_PREV'), $per_page)) : '';
	$next = ($on_page < $total_pages) ? sprintf($tag['a'], sprintf($url_format, ($on_page * $per_page)), sprintf($bio->_lang('PAGES_NEXT'), $per_page)) : '';
	
	$rest = array(
		'NUMS' => $pages,
		'PREV' => $prev,
		'NEXT' => $next,
		'ON' => sprintf($bio->_lang('PAGES_ON'), $on_page, max($total_pages, 1))
	);
	return $rest;
}

function _pagination_multi($i, $on_page, $per_page, $tag, $url_format, $url_format_smp)
{
	if ($i == $on_page)
	{
		$page = sprintf($tag['strong'], $i);
	} else {
		$this_page = ($i > 1) ? sprintf($url_format, (($i - 1) * $per_page)) : (($url_format_smp == _link('home')) ? _link() : $url_format_smp);
		$page = sprintf($tag['a'], $this_page, $i);
	}
	return $page;
}

function _space($a)
{
	return (f($a)) ? ' ' : '';
}

function _countries($s = false)
{
	global $core;
	
	if (!$countries = $core->cache_load('countries'))
	{
		$sql = 'SELECT *
			FROM _countries
			ORDER BY country_id';
		$countries = $core->cache_store(_rowset($sql, 'country_id'));
	}
	
	if ($s !== false && isset($countries[$s]))
	{
		$countries = $countries[$s];
	}
	
	return $countries;
}

function _location($id, $extra = '', $s = '')
{
	if (!f($s))
	{
		$list = _countries();
	}
	
	return (($extra != '') ? $extra . ', ' : '') . ((isset($list[$id])) ? $list[$id] : $s);
}

function _login($message = false, $error = false)
{
	global $bio;
	
	if (!$bio->v())
	{
		$bio->start();
	}
	if (!$bio->_lang_check())
	{
		$bio->setup();
	}
	
	if ($bio->v('is_member'))
	{
		return;
	}
	
	if ($bio->v('is_bot'))
	{
		redirect(_link());
	}
	
	if ($error === false)
	{
		$error = ($message !== false) ? $bio->_lang($message) : false;
	}
	else
	{
		$error = $bio->_lang($error);
	}
	
	if ($error !== false && f($error))
	{
		_style('error', array(
			'MESSAGE' => $error)
		);
	}
	
	$sv = array(
		'REDIRECT_TO' => str_replace(_link(), '', $bio->v('session_page'))
	);
	_layout('login', 'LOGIN', $sv);
}

function _format_date($d = false, $f = false)
{
	global $bio;
	
	if ($d === false)
	{
		$d = time();
	}
	
	return $bio->format_date($d, $f);
}

function redirect($url, $i = true)
{
	global $warning;
	
	sql_close();
	
	$url = trim($url);
	
	// Prevent external domain injection
	if ($i === true)
	{
		if (strpos($url, '://') !== false)
		{
			$url_path = parse_url($url, PHP_URL_HOST);
			if ($url_path === false || $url_path != get_host())
			{
				$warning->fatal();
			}
		}
		else
		{
			if (f($url) && substr($url, 0, 1) === '/')
			{
				$url = substr($url, 1);
			}
			$url = _link() . $url;
		}
	}
	
	$head = 'Location: ' . $url;
	
	if (is_ghost())
	{
		echo $head;
	}
	else
	{
		header($head);
	}
	exit();
}

function _htimestamp($format = 'Ymd')
{
	return date($format);
	return date('Y') . date('m') . date('d');
}

function _timestamp($m = false, $d = false, $y = false, $hh = 0, $mm = 0, $ss = 0)
{
	global $bio;
	
	if ($m === false && $d === false && $y === false)
	{
		$now = getdate();
		
		$m = $now['mon'];
		$d = $now['mday'];
		$y = $now['year'];
	}
	
	// TODO: Try, mktime
	return gmmktime($hh, $mm, $ss, $m, $d, $y) - $bio->timezone - $bio->dst;
}

function _localtime()
{
	global $bio;
	
	return time() + $bio->timezone + $bio->dst;
}

function _password($a, $j = 3, $u = false)
{
	if ($u === false)
	{
		$u = 'sha1';
	}
	$f = w($u);  
	
	for ($i = 0; $i < $j; $i++)
	{
		foreach ($f as $row)
		{
			$a = $row($a);
		}
	}
	return $a;
}

function unique_id()
{
	list($sec, $usec) = explode(' ', microtime());
	mt_srand((float) $sec + ((float) $usec * 100000));
	return uniqid(mt_rand(), true);
}

function is_remote($f = 'cache_skip')
{
	$is_remote = true;
	
	if ($host_list = get_file(XFS.XCOR . 'conf/' . $f))
	{
		foreach ($host_list as $row)
		{
			if ($row === get_host())
			{
				$is_remote = false;
				break;
			}
		}
	}
	return $is_remote;
}

function v_server($a)
{
	return (isset($_SERVER[$a])) ? $_SERVER[$a] : '';
}

function get_protocol($ssl = false)
{
	return ('http' . (($ssl !== false || v_server('SERVER_PORT') === 443) ? 's' : '') . '://');
}

function get_host()
{
	return v_server('HTTP_HOST');
}

function request_method()
{
	return strtolower(v_server('REQUEST_METHOD'));
}

// Current page
function _page()
{
	return get_protocol() . get_host() . v_server('REQUEST_URI');
}

//
function _hidden($ary)
{
	$tag = '<input type="hidden" name="%s" value="%s" />';
	
	$hidden = '';
	foreach ($ary as $k => $v)
	{
		$hidden .= sprintf($tag, $k, $v);
	}
	return $hidden;
}

function _button($name = 'submit')
{
	return (isset($_POST[$name])) ? true : false;
}

function _style_uv($a)
{
	if (!is_array($a)) $a = w();
	
	foreach ($a as $i => $v)
	{
		$a[strtoupper($i)] = $v;
	}
	
	return $a;
}

function _style($a, $b = array(), $i = false)
{
	if ($i !== false && $i)
	{
		return;
	}
	
	global $style;
	
	$style->assign_block_vars($a, _style_uv($b));
	return true;
}

function _style_handler($f)
{
	global $style;
	
	$style->set_filenames(array('tmp' => $f));
	$style->assign_var_from_handle('S_TMP', 'tmp');
	
	return _style_var('S_TMP');
}

function _style_vreplace($r = true)
{
	global $style;
	
	return $style->set_vreplace($r);
}

function v_style($a)
{
	global $style;
	
	$style->assign_vars(_style_uv($a));
	return true;
}

function _style_functions($arg)
{
	if (!isset($arg[1]) || !isset($arg[2]))
	{
		return $arg[0];
	}
	
	$f = '_sf_' . strtolower($arg[1]);
	if (!@function_exists($f))
	{
		return $arg[0];
	}
	
	$e = explode(':', $arg[2]);
	$f_arg = w();
	
	foreach ($e as $row)
	{
		if (preg_match('/\((.*?)\)/', $row, $reg))
		{
			$_row = array_map('trim', explode(',', str_replace("'", '', $reg[1])));
			$row = w();
			
			foreach ($_row as $each)
			{
				$j = explode(' => ', $each);
				$row[$j[0]] = $j[1];
			}
		}
		$f_arg[] = $row;
	}
	
	return hook($f, $f_arg);
}

function _rewrite($tree, $alias = 'tree_alias', $id = 'tree_id')
{
	if (!isset($tree['tree_cp']))
	{
		if ($tree[$alias] == 'home')
		{
			$tree[$alias] = false;
		}
		
		if ($tree[$alias] === false)
		{
			return '';
		}
	}
	
	return (isset($tree[$alias]) && f($tree[$alias])) ? $tree[$alias] : $tree[$id];
}

// User auth
function _auth_get($name, $uid = false, $global = false)
{
	global $bio;
	
	return $bio->auth_get($name, $uid, $global);
}

function _browser($a_browser = false, $a_version = false, $name = false, $d_name = false)
{
	global $bio;
	
	$browser_list  = 'nokia motorola samsung sonyericsson blackberry iphone htc ';
	$browser_list .= 'flock firefox namoroka shiretoko konqueror lobo msie netscape navigator mosaic netsurf lynx amaya omniweb ';
	$browser_list .= 'googlebot googlebot-image feedfetcher-google gigabot msnbot thunderbird shredder fennec minimo ';
	$browser_list .= 'minefield chrome wget cheshire safari avant camino seamonkey aol bloglines ';
	$browser_list .= 'wii playstation netfront opera mozilla gecko ubuntu';
	
	$browser_type = array(
		'mobile' => 'nokia motorola samsung sonyericsson blackberry iphone fennec minimo htc',
		'console' => 'wii playstation',
		'bot' => 'googlebot googlebot-image feedfetcher-google gigabot msnbot bloglines'
	);
	
	$platforms = array(
		'linux' => w('linux'),
		'mac' => array('macintosh', 'mac platform x', 'mac os x'),
		'windows' => w('windows win32')
	);
	
	$user_browser = strtolower($bio->browser());
	$this_version = $this_browser = $this_platform = '';
	
	if ($a_browser == '*') {
		$a_browser = $a_version = $name = false;
		$d_name = true;
	}
	
	if ($a_browser === false && $a_version === false && $name === false && $d_name !== false)
	{
		return $user_browser;
	}
	
	foreach (w('user_browser a_browser a_version name d_name') as $row)
	{
		$vrow = $$row;
		if (is_string($vrow)) {
			$$row = strtolower($vrow);
		}
	}
	
	$browser_limit = strlen($user_browser);
	foreach (w($browser_list) as $row)
	{
		$row = ($a_browser !== false) ? $a_browser : $row;
		$n = stristr($user_browser, $row);
		if (!$n || f($this_browser)) continue;
		
		$this_browser = $row;
		$j = strpos($user_browser, $row) + strlen($row);
		$j2 = substr($user_browser, $j, 1);
		if (preg_match('#[\/\_\-\ ]#', $j2)) {
			$j += 1;
		}
		
		for (; $j <= $browser_limit; $j++)
		{
			$s = trim(substr($user_browser, $j, 1));
			if (!preg_match('/[\w\.\-]/', $s)) break;
			
			$this_version .= $s;
		}
	}
	
	if ($a_browser !== false && ($d_name === false || $name === true))
	{
		$ret = false;
		if (strtolower($a_browser) == $this_browser)
		{
			$ret = true;
			if ($a_version !== false)
			{
				if (f($this_version))
				{
					$a_sign = explode(' ', $a_version);
					if (version_compare($this_version, $a_sign[1], $a_sign[0]) === false) {
						$ret = false;
						$vf = true;
					}
				}
				else
				{
					$ret = false;
				}
			}
		}
		
		if ($name !== true)
		{
			return $ret;
		}
	}
	
	foreach ($platforms as $os => $match)
	{
		foreach ($match as $os_name)
		{
			if (strpos($user_browser, $os_name) !== false)
			{
				$this_platform = $os;
				break 2;
			}
		}
	}
	
	$this_type = '';
	if (f($this_browser))
	{
		foreach ($browser_type as $type => $browsers)
		{
			foreach (w($browsers) as $row)
			{
				if (strpos($this_browser, $row) !== false)
				{
					$this_type = $type;
					break 2;
				}
			}
		}
		
		if (!$this_type) $this_type = 'desktop';
	}
	
	if ($name !== false)
	{
		if ($a_browser !== false && $a_version !== false && $ret === false)
		{
			return false;
		}
		
		$s_browser = '';
		$s_data = array($this_type, $this_platform, $this_browser, $this_version);
		foreach ($s_data as $row)
		{
			if (f($row)) $s_browser .= (($s_browser != '') ? ' ' : '') . $row;
		}
		
		return $s_browser;
	}
	
	return array(
		'browser' => $this_browser,
		'version' => $this_version,
		'platform' => $this_platform,
		'type' => $this_type,
		'useragent' => $user_browser
	);
}

function _layout($template, $page_title = false, $v_custom = false)
{
	global $core, $bio, $style, $starttime;
	
	// GZip
	if (_browser('gecko'))
	{
		ob_start('ob_gzhandler');
	}
	
	// Headers
	if (!headers_sent())
	{
		header('Cache-Control: private, no-cache="set-cookie", pre-check=0, post-check=0');
		header('Expires: 0');
		header('Pragma: no-cache');
	}
	
	if ($page_title !== false)
	{
		if (!is_array($page_title))
		{
			$page_title = w($page_title);
		}
		
		foreach ($page_title as $k => $v)
		{
			$page_title[$k] = $bio->_lang($v);
		}
		$page_title = implode(' . ', $page_title);
	}
	
	//
	$filename = (strpos($template, '#') !== false) ? str_replace('#', '.', $template) : $template . '.htm';
	$style->set_filenames(array(
		'body' => $filename)
	);
	
	// SQL History
	if ($core->v('show_sql_history'))
	{
		foreach (sql_history() as $i => $row)
		{
			if (!$i) _style('sql_history');
			
			_style('sql_history.row', array(
				'QUERY' => str_replace(array("\n", "\t"), array('<br />', '&nbsp;&nbsp;'), $row))
			);
		}
	}
	
	//
	$v_assign = array(
		'SITE_TITLE' => $core->v('site_title'),
		'PAGE_TITLE' => $page_title,
		'G_ANALYTICS' => $core->v('google_analytics'),
		'S_REDIRECT' => $bio->v('session_page'),
		'F_SQL' => _sql_queries()
	);
	if ($v_custom !== false)
	{
		$v_assign += $v_custom;
	}
	
	$mtime = explode(' ', microtime());
	$v_assign['F_TIME'] = sprintf('%.2f', ($mtime[0] + $mtime[1] - $starttime));
	
	v_style($v_assign);
	$style->pparse('body');
	
	sql_close();
	exit();
}

set_error_handler('msg_handler');

?>