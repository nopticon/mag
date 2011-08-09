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

$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];

error_reporting(E_ALL);

if (@ini_get('register_globals')) {
	foreach ($_REQUEST as $var_name => $void) {
		unset(${$var_name});
	}
}

if (!defined('REQC')) {
	define('REQC', (strtolower(ini_get('request_order')) == 'gp'));
}

require_once(XFS.XCOR . 'constants.php');
require_once(XFS.XCOR . 'functions.php');

$database = _import('db.mysql', 'database');
$style = _import('style');
$bio = _import('bio');
$core = _import('core');
$warning = _import('warning');
$file = _import('filesystem');

if (!defined('XRUN')) {
	$core->run();
}

?>