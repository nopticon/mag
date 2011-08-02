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

interface i_dev
{
	public function home();
	public function layout();
	public function artists();
	public function corp();
	public function services();
	public function tos();
	public function feed();
	public function jobs();
	public function uptime();
	public function random();
	public function emoticon();
	public function fetch();
}

class __dev extends xmd implements i_dev
{
	public function __construct()
	{
		parent::__construct();
		
		$this->auth(false);
		$this->_m(_array_keys(w('artists corp emoticon feed fetch jobs uptime random services tos')));
	}
	
	public function home()
	{
		global $warning;
		
		$warning->now();
	}
	
	public function layout()
	{
		return $this->method();
	}
	
	protected function _layout_home()
	{
		global $bio, $core, $warning;
		
		$v = $this->__(w('path ext'));
		
		if (array_empty($v)) {
			$warning->now();
		}
		
		$location = XFS.XHTM . _tbrowser() . '/' . $v->ext . '/';
		
		if (!@is_dir($location)) {
			$warning->now();
		}
		
		$filename = _filename($v->path, $v->ext);
		if ($v->ext == 'css' && $v->path != 'default')
		{
			$v->field = (!is_numb($v->path)) ? 'alias' : 'id';
			
			$sql = 'SELECT *
				FROM _tree
				WHERE tree_?? = ?
				LIMIT 1';
			if (!$tree = sql_fieldrow(sql_filter($sql, $v->field, $v->path))) {
				$warning->now();
			}
			
			$filetree = _rewrite($tree);
			$filename = _filename('_tree_' . $filetree, $v->ext);
		}
		
		//
		// 304 Not modified response header
		if (@file_exists($location . $filename))
		{
			$f_last_modified = gmdate('D, d M Y H:i:s', filemtime($location . $filename)) . ' GMT';
			$http_if_none_match = v_server('HTTP_IF_NONE_MATCH');
			$http_if_modified_since = v_server('HTTP_IF_MODIFIED_SINCE');
			
			header('Last-Modified: ' . $f_last_modified);
			
			if ($f_last_modified == $http_if_modified_since)
			{
				header('HTTP/1.0 304 Not Modified');
				header('Content-Length: 0');
				exit;
			}
		}
		
		switch ($v->ext)
		{
			case 'css':
				if ($v->path != 'default')
				{
					$filetree = _rewrite($tree);
					$filename = _filename('_tree_' . $filetree, $v->ext);
					
					if (!@file_exists($location . $filename)) {
						$warning->now();
					}
				}
				
				$browser = _browser();
				
				if (!empty($browser['browser'])) {
					$custom = array($browser['browser'] . '-' . $browser['version'], $browser['browser']);
					
					foreach ($custom as $row) {
						$handler = _filename('_tree_' . $row, 'css');
						
						if (@file_exists($location . $handler)) {
							_style('includes', array(
								'CSS' => _style_handler('css/' . $handler))
							);
						}
					}
				}
				break;
			case 'js':
				if (!@file_exists($location . $filename)) {
					$warning->now();
				}
				
				_style_vreplace(false);
				break;
		}
		
		v_style(array(
			'DOMAIN' => LIBD . LIB_VISUAL)
		);
		sql_close();
		
		//
		// Headers
		$ext = _style_handler($v->ext . '/' . $filename);
		
		switch ($v->ext)
		{
			case 'css':
				$content_type = 'text/css; charset=utf-8';
				
				$ext = preg_replace('#(border-radius\-?.*?)\: ?(([0-9]+)px;)#is', ((_browser('firefox')) ? '-moz-\1: \2' : ''), $ext);
				$ext = preg_replace('/(#([0-9A-Fa-f]{3})\b)/i', '#\2\2', $ext);
				$ext = preg_replace('#\/\*(.*?)\*\/#is', '', $ext);
				$ext = str_replace(array("\r\n", "\n", "\t"), '', $ext);
				break;
			case 'js':
				$content_type = 'application/x-javascript';
				
				require_once(XFS.XCOR . 'jsmin.php');
				
				$ext = JSMin::minify($ext);
				break;
		}
		
		ob_start('ob_gzhandler');
		
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT'); // 30 days = 60 * 60 * 24 * 30
		header('Content-type: ' . $content_type);
		
		echo $ext;
		exit;
	}
	
	public function artists()
	{
		return $this->method();
	}
	
	protected function _artists_home()
	{
		// TODO: Add sorting methods by genre, country & more.
		
		$v = $this->__(w('by'));
		
		switch ($v->by) {
			case 'genre':
				// TODO: Add query
				$sql = 'SELECT b.bio_id, b.bio_alias, b.bio_name, b.bio_avatar, b.bio_avatar_up
					FROM _bio b, _bio_type t
					WHERE t.type_alias = ?
						AND b.bio_type = t.type_id
					ORDER BY b.bio_name';
				$artists = sql_rowset(sql_filter($sql, 'artist'));
				break;
			default:
				$allow_by = array(
					'country' => 'c.country_name'
				);
				
				$s_country = isset($allow_by[$v->by]) ? $allow_by[$v->by] . ',' : '';
				
				$sql = 'SELECT b.bio_id, b.bio_alias, b.bio_name, b.bio_avatar, b.bio_avatar_up
					FROM _bio b, _bio_type t, _countries c
					WHERE t.type_alias = ?
						AND b.bio_type = t.type_id
						AND b.bio_country = c.country_id
					ORDER BY ?? b.bio_name';
				$artists = sql_rowset(sql_filter($sql, 'artist', $s_country));
				break;
		}
		
		// Genres
		$sql = 'SELECT g.genre_alias, g.genre_name, r.relation_artist
			FROM _genres g, _genres_relation r
			WHERE g.genre_id = r.relation_genre
				AND r.relation_artist IN (??)
			ORDER BY g.genre_name';
		$genres = sql_rowset(sql_filter($sql, _implode(',', array_subkey($artists, 'bio_id'))), 'relation_artist', false, true);
		
		$i = 0;
		foreach ($artists as $row)
		{
			$first_letter = $row->bio_alias{0};
			if (f($v->sort) && $first_letter != $v->sort) {
				continue;
			}
			
			if (!$i) _style('artists');
			
			_style('artists.row', _vs(array(
				'URL' => _link_bio($row->bio_alias),
				'NAME' => $row->bio_name,
				'IMAGE' => _avatar($row),
				'GENRE' => _implode(', ', $genres[$row->bio_id])
			), 'v'));
			$i++;
		}
		
		if (!$i) _style('artists_none');
		
		return;
	}
	
	public function corp()
	{
		return $this->method();
	}
	
	protected function _corp_home()
	{
		$sql = 'SELECT *
			FROM _groups
			WHERE group_special = ?
			ORDER BY group_order';
		$groups = sql_rowset(sql_filter($sql, 1));
		
		$sql = 'SELECT g.group_id, b.bio_alias, b.bio_name, b.bio_firstname, b.bio_lastname, b.bio_life, b.bio_avatar, b.bio_avatar_up
			FROM _groups g, _group_joint j, _bio b
			WHERE g.group_id = j.joint_group
				AND j.joint_bio = b.bio_id
			ORDER BY j.joint_order, b.bio_alias';
		$members = sql_rowset($sql, 'group_id', false, true);
		
		$i = 0;
		foreach ($groups as $row)
		{
			if (!isset($members[$row->group_id])) continue;
			
			if (!$i) _style('groups');
			
			_style('groups.list', array(
				'GROUP_NAME' => $row->group_name)
			);
			
			foreach ($members[$row->group_id] as $row2)
			{
				_style('groups.list.member', _vs(array(
					'LINK' => _link_bio($row2->bio_alias),
					'NAME' => $row2->bio_name,
					'REALNAME' => _fullname($row2),
					'BIO' => _message($row2->bio_life),
					'AVATAR' => _avatar($row2))
				), 'USER');
			}
			$i++;
		}
		
		if ($corp = $this->page_query('corp'))
		{
			v_style(array(
				'CORP_CONTENT' => _message($corp->page_content))
			);
		}
		
		return;
	}
	
	public function uptime()
	{
		global $bio, $warning;
		
		if (!$bio->v('auth_uptime') || !$uptime = @exec('uptime')) {
			$warning->now();
		}
		
		if (strstr($uptime, 'day')) {
			if (strstr($uptime, 'min')) {
				preg_match('/up\s+(\d+)\s+(days,|days|day,|day)\s+(\d{1,2})\s+min/', $uptime, $times);
				$days = $times[1];
				$hours = 0;
				$mins = $times[3];
			} else {
				preg_match('/up\s+(\d+)\s+(days,|days|day,|day)\s+(\d{1,2}):(\d{1,2}),/', $uptime, $times);
				$days = $times[1];
				$hours = $times[3];
				$mins = $times[4];
			}
		} else {
			if (strstr($uptime, 'min')) {
				preg_match('/up\s+(\d{1,2})\s+min/', $uptime, $times);
				$days = 0;
				$hours = 0;
				$mins = $times[1];
			} else {
				preg_match('/up\s+(\d+):(\d+),/', $uptime, $times);
				$days = 0;
				$hours = $times[1];
				$mins = $times[2];
			}
		}
		
		preg_match('/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/', $uptime, $avgs);
		$load = $avgs[1] . ', ' . $avgs[2] . ', ' . $avgs[3];
		
		$tv = array(
			'SERVER_UPTIME' => sprintf(_lang('SERVER_UPTIME'), $days, $hours, $mins),
			'SERVER_LOAD' => sprintf(_lang('SERVER_LOAD'), $load)
		);
		return v_style($tv);
	}
	
	public function tos()
	{
		return $this->method();
	}
	
	protected function _tos_home()
	{
		global $warning;
		
		$v = $this->__(array('view' => 'tos'));
		
		if (!$page = $this->page_query($v['view'])) {
			$warning->now();
		}
		
		$temporal_content = array(
			'Pol&iacute;tica de Privacidad' => array(
				'Con el acceso al servidor, el usuario manifiesta su total conformidad con los t&eacute;rminos de servicio establecidos en este documento y se compromete a observarlos durante su estad&iacute;a.',
				'El usuario libera a RK Networks de cualquier responsabilidad, a&uacute;n la responsabilidad impl&iacute;cita de cualquier da&ntilde;o que pudiera surgir ya sea t&eacute;cnico, moral o de otra naturaleza, durante el tiempo en que &eacute;ste est&eacute; conectado al servidor.',
				'Si el usuario no esta de acuerdo con estos t&eacute;rminos de uso deber&aacute; cerrar cualquier tipo de conexi&oacute;n que tenga con el servidor.',
				'RK Networks se reserva el derecho de cambiar, modificar, agregar o quitar cualquier porci&oacute;n de estos t&eacute;rminos peri&oacute;dicamente. Tales modificaciones entrar&aacute;n en vigencia inmediatamente una vez que &eacute;ste sea publicado.',
				'El usuario se compromete a utilizar los servicios de RK de forma diligente, correcta, l&iacute;cita, de conformidad con la Ley, as&iacute; como con la moral, buenas costumbres generalmente aceptadas y el orden p&uacute;blico.',
				'RK Networks reconoce la importancia de la privacidad de las personas, por lo que sus sistemas est&aacute;n dise&ntilde;ados considerando la protecci&oacute;n de la informaci&oacute;n que es prove&iacute;da.',
				'En RK no obtenemos su informaci&oacute;n personal sin consentimiento y lo que se obtiene es lo que el usuario nos proporciona y no exigimos proveerla.',
				'RK no vende ni comparte informaci&oacute;n personal con alg&uacute;n otro sitio o empresa por ning&uacute;n motivo.',
				'Los servicios que no requieren identificar al usuario, recolectan informaci&oacute;n general y no informaci&oacute;n personal, relativa al navegador que se utiliza, el tipo de conexi&oacute;n a Internet, el sistema operativo y otros elementos de configuraci&oacute;n destinados a mejorar nuestros servicios.',
				'Algunos de nuestros servicios requieren expl&iacute;citamente que el usuario abra una cuenta, en virtud de la funcionalidad del sitio. En este caso, el sitio solicita datos para la cuenta como 
				nombre de usuario, direcci&oacute;n de correo, edad, sexo, entre otros. Esta informaci&oacute;n personal puede solicitarse al usuario al entrar a ciertas &aacute;reas.',
				'RK toma las medidas necesarias para proteger la informaci&oacute;n personal y garantizar la seguridad de sus bases de datos, contra acceso o alteraci&oacute;n no autorizados, divulgaci&oacute;n o destrucci&oacute;n de informaci&oacute;n.',
			),
			'T&eacute;rminos de servicio' => array(
				'Obligaciones al crear una cuenta de usuario:' => array(
					'Proporcionar informaci&oacute;n v&aacute;lida, exacta, actual y completa.',
					'Mantener actualizada dicha informaci&oacute;n.',
					'RK se reserva el derecho a deshabilitar y/o borrar si se encontrara que la informaci&oacute;n proporcionada por el usuario es falsa, inexacta o incompleta, denegando de esta forma al usuario cualquier tipo de comunicaci&oacute;n con el sitio.'
				),
				'Est&aacute; prohibido publicar lo siguiente en las &aacute;reas de participaci&oacute;n de usuarios:' => array(	
					'Pornograf&iacute;a y material obsceno. Cualquier tipo de sexo explicito no est&aacute; permitido.',
					'Lenguaje violento y/u ofensivo hacia la integridad de las personas.',
					'Racismo o apolog&iacute;a del terrorismo.',
					'Distribuci&oacute;n de materiales protegidos por derechos de autor.',
					'Contenido de pirateo inform&aacute;tico.',
					'Contenido de distribuci&oacute;n pirata, virus o mp3 ilegal.',
					'Compartir contrase&ntilde;as, seriales o cracks de p&aacute;ginas o programas.',
					'Juegos de apuesta y/o contenido relacionado con casinos.',
					'Drogas ilegales o art&iacute;culos relacionados.',
					'Cualquier contenido que promueva actividades ilegales.',
					'Publicar contenido, falso, enga&ntilde;oso o ambiguo.',
					'Contenido que viole los secretos empresariales de terceros.',
					'Contenido de correos privados o mensajes privados sin el permiso expreso del usuario.',
					'Contenido que pueda difamar, insultar, molestar, amenazar, acosar o violar en cualquier manera los derechos de otras personas.',
					'Venta o promoci&oacute;n de armas, alcohol, tabaco, medicamentos o imitaciones de productos de marca.',
					'Cualquier tipo de spam / env&iacute;o de correo no solicitado.',
					'Cualquier tipo de invasi&oacute;n de la privacidad de terceras personas ni publicar datos personales sin consentimiento.'
				),
				'El usuario se compromete a respetar a todos los usuarios dentro de la comunidad:' => array(
					'No molestar ni amenazar a los usuarios.',
					'Mostrar tolerancia hacia otras formas de pensar, tendencias musicales, de culturas, de credos y de conducta sexual.',
					'Establecer conversaciones privadas &uacute;nicamente con el consentimiento del otro usuario.',
					'Existe total libertad de conversaci&oacute;n en las conversaciones privadas, mientras sean establecidas con pleno consentimiento de ambos usuarios.'
				),
				'Moderaci&oacute;n del foro:' => array(
					'Los usuarios est&aacute;n obligados a seguir las indicaciones de los administradores y miembros del equipo de trabajo de RK.',
					'No se permitir&aacute;n los mensajes que &uacute;nicamenete contengan emoticonos en los temas del foro.',
					'No publicar t&iacute;tulos y/o mensajes del foro todo con may&uacute;sculas.',
					'Los temas nuevos del foro relacionados con otros ya existentes ser&aacute;n unidos en un &uacute;nico tema.',
					'Las im&aacute;genes que se publiquen en los mensajes y que no tengan relaci&oacute;n con el tema ser&aacute;n borradas del mensaje.',
					'Si las firmas de usuarios contienen im&aacute;genes, &eacute;stas deben ser de dimensiones m&aacute;ximas de 600px de ancho por 200px de alto. Cualquier firma que exceda estas dimensiones ser&aacute; borrada, con o sin previo aviso.',
					'Los moderadores pueden eliminar contenido inaceptable e impartir advertencias o excluirlo del foro, en este &uacute;ltimo dando aviso a un administrador.',
					'Estas moderaciones comparar&aacute;n el contenido inaceptable publicado por el autor con su expediente disciplinario previo. La actitud del usuario con respecto a la publicaci&oacute;n, as&iacute; como las subsiguientes acciones con respecto al incidente ser&aacute;n tambi&eacute;n un factor a tener en cuenta para bloquear a un usuario.',
					'Nunca se debe evitar de cualquier manera los comentarios de un moderador ya que tienen como prop&oacute;sito aplicar el reglamento de este foro.',
					'Si cree que debe discutir cualquier asunto contactar a un miembro del equipo de moderadores, hacerlo exclusivamente por medio de conversaci&oacute;n privada.',
					'Ning&uacute;n moderador debe alentar de cualquier forma una conversaci&oacute;n p&uacute;blica de contenido falso, violento, enga&ntilde;oso o ambiguo.',
					'No se puede volver a publicar temas que hayan sido cerrados o borrados. No se puede volver a publicar contenido borrado por los moderadores.',
					'Cada tema debe ser publicado en la categor&iacute;a que corresponde. No se puede publicar copias de temas en varias categor&iacute;as. Dichas copias ser&aacute;n borradas para mantener el orden.'
				),
				'RK podr&aacute; bloquear el acceso al sitio al usuario que viole estos t&eacute;rminos de servicio sin previa notificaci&oacute;n.',
				'Es responsabilidad del autor la informaci&oacute;n que se genera como texto, datos, software, m&uacute;sica, fotograf&iacute;as, im&aacute;genes, video, mensajes o cualquier otro material. El usuario es responsable por el contenido que publique.',
				'RK se reserva el derecho a deshabilitar y/o borrar cualquier contenido que viole los t&eacute;rminos expuestos en este documento.',
				'Queda absolutamente prohibido cualquier tipo de atentado contra la seguridad del sistema de RK. Si se da este caso se podr&aacute; bloquear al usuario, IP o conexi&oacute;n de origen por tiempo indefinido.',
				'RK no se hace responsable por cualquier da&ntilde;o que pueda sufrir el equipo del usuario, al utilizar el sitio web y sus servicios.',
				'Artistas y m&uacute;sicos' => array(
					'Todo el material art&iacute;stico publicado en RK es propiedad de sus respectivos autores y est&aacute; cubierto por el acto de derechos de propiedad de Guatemala y por las leyes internacionales de derechos del autor.',
					'Toda la informaci&oacute;n en la secci&oacute;n correspondiente al artista, puede ser modificada &uacute;nicamente por un miembro autorizado y administraci&oacute;n de RK, siendo el artista el &uacute;nico responsable de la informaci&oacute;n en las &aacute;reas de publicaci&oacute;n oficial.',
					'El material no puede ser copiado, modificado, editado, distribuido o vendido sin previa autorizaci&oacute;n del artista o por el representante legal.'
				)
			)
		);
		
		return v_style(array(
			'TOS_CONTENT' => _message($page->page_content))
		);
	}
	
	public function services()
	{
		return $this->method();
	}
	
	protected function _services_home()
	{
		global $core, $bio, $warning;
		
		$v = $this->__(w('service'));
		
		if (empty($v['service']))
		{
			$sql = 'SELECT *
				FROM _services
				WHERE service_alias = ?';
			if (!$service = sql_fieldrow(sql_filter($sql, $v->service))) {
				$warning->now();
			}
		}
		
		$sql = 'SELECT *
			FROM _services
			ORDER BY service_order';
		$services = sql_rowset($sql);
		
		foreach ($services as $i => $row)
		{
			if (!$i) _style('services');
			
			_style('services.row', array(
				
			));
		}
		
		return;
	}
	
	public function feed()
	{
		return $this->method();
	}
	
	protected function _feed_home()
	{
		global $core;
		
		$format = '<?xml version="1.0" encoding="iso-8859-1"?>
<rss version="2.0">
<channel>
	<title>%s</title>
	<link>%s</link>
	<language>es-gt</language>
	<description><![CDATA[%s]]></description>
	<lastBuildDate>%s</lastBuildDate>
	<webMaster>%s</webMaster>
%s
</channel>
</rss>';
		
		$tags = w('author title link guid description pubDate');
		
		$last_entry = time();
		$feed = '';
		
		$sql = 'SELECT r.ref_subject, r.ref_content, r.ref_time, r.ref_link, b.bio_name
			FROM _reference r, _reference_type t, _bio b
			WHERE r.ref_bio = b.bio_id
				AND r.ref_type = t.type_id
			ORDER BY r.ref_time DESC
			LIMIT 20';
		$reference = sql_rowset($sql);
		
		foreach ($reference as $i => $row)
		{
			if (!$i) $last_entry = $row->ref_time;
			
			$a = array(
				$row->username,
				'<![CDATA[' . entity_decode($row->ref_subject, false) . ']]>',
				$row->ref_link,
				$row->ref_link,
				'<![CDATA[' . entity_decode($row->ref_content, false) . ']]>',
				date('D, d M Y H:i:s \G\M\T', $row->ref_time)
			);
			
			$feed .=  "\t<item>";
			
			foreach ($a as $j => $v)
			{
				$feed .= '<' . $tags[$j] . '>' . $v . '</' . $tags[$j] . '>';
			}
			
			$feed .= "</item>\n";
		}
		
		//
		header('Content-type: text/xml');
		
		$ref_title = entity_decode($core->v('site_name'), false);
		$ref_desc = entity_decode($core->v('site_details'), false);
		
		$this->e(sprintf($format, $ref_title, _link(), $ref_desc, date('D, d M Y H:i:s \G\M\T', $last_entry), $core->v('site_email'), $feed));
	}
	
	public function jobs()
	{
		return $this->method();
	}
	
	protected function _jobs_home()
	{
		$sql = 'SELECT *
			FROM _jobs
			WHERE job_end > ??
			ORDER BY job_time';
		$jobs = sql_rowset(sql_filter($sql, time()));
		
		foreach ($jobs as $i => $row)
		{
			if (!$i) _style('jobs');
			
			_style('jobs.row'. _vs(array(
				'TITLE' => $row->job_title,
				'REQUIREMENT' => _message($row->job_requirement),
				'OFFER' => _message($row->job_offer),
				'RANGE' => $row->job_range
			), 'JOB'));
		}
		
		return;
	}
	
	public function random()
	{
		return $this->method();
	}
	
	protected function _random_home()
	{
		global $bio, $warning;
		
		$v = $this->__(w('type'));
		
		switch ($v->type)
		{
			case 'artist':
			case 'user':
				$sql = 'SELECT b.bio_alias
					FROM _bio b, _bio_type t
					WHERE t.type_alias = ?
						AND b.bio_type = t.type_id
					ORDER BY RAND()
					LIMIT 1';
				$alias = sql_field(sql_filter($sql, $v->type), 'bio_alias', '');
				
				$link = _link('alias', $alias);
				break;
			case 'event':
				$sql = 'SELECT *
					FROM _events
					WHERE
					ORDER BY RAND()
					LIMIT 1';
				break;
			default:
				$warning->now();
				break;
		}
		
		return;
	}
	
	public function emoticon()
	{
		return $this->method();
	}
	
	protected function _emoticon_home()
	{
		global $core;
		
		if (!$emoticons = $core->cache->load('emoticon'))
		{
			$sql = 'SELECT *
				FROM _smilies
				ORDER BY LENGTH(code) DESC';
			$emoticons = $core->cache->store(sql_rowset($sql));
		}
		
		foreach ($emoticons as $i => $row)
		{
			if (!$i) _style('emoticons');
			
			_style('emoticons.row', array(
				'CODE' => $row->code,
				'IMAGE' => _lib(LIB_VISUAL . '/emoticons', $rowsmile_url),
				'DESC' => $row->emoticon)
			);
		}
		
		return;
	}
	
	public function fetch()
	{
		return $this->method();
	}
	
	protected function _fetch_home()
	{
		global $bio, $warning;
		
		$v = $this->__(w('alias filename ext'));
		if (empty($v->alias) || empty($v->filename)) {
			$warning->now();
		}
		
		$sql = 'SELECT *
			FROM _fetch
			WHERE fetch_alias = ?';
		if (!$fetch = sql_fieldrow(sql_filter($sql, $v->filename))) {
			$warning->now();
		}
		
		if ($fetch->fetch_login) {
			$bio->login();
		}
		
		$filepath = LIB . 'fetch/' . _filename($fetch->fetch_id, $fetch->fetch_extension);
		
		return;
	}
}

?>