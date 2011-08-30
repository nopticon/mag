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

class emailer {
	private $message;
	private $subject;
	private $extra_headers;
	private $addresses;
	private $reply_to;
	private $from;
	private $format;
	
	private $template = array();
	
	public function __construct() {
		return $this->reset();
	}
	
	// Resets all the data (address, template file, etc etc to default
	public function reset() {
		$this->reply_to = '';
		$this->from = '';
		$this->vars = '';
		$this->message = '';
		$this->extra_headers = '';
		$this->addresses = array();
		$this->format = 'html';
	}
	
	public function init($from, $template = '', $vars = array()) {
		$this->from = trim($from);
		
		$template_parts = explode(':', $template);
		
		if (isset($template_parts[0])) {
			$this->use_template($template_parts[0]);
		}
		
		if (isset($template_parts[1])) {
			$this->format = $template_parts[1];
		}
		
		$this->vars = $vars;
		
		return;
	}
	
	// Sets an email address to send to
	public function email_address($address) {
		$this->addresses['to'] = trim($address);
	}

	public function cc($address) {
		$this->addresses['cc'][] = trim($address);
	}

	public function bcc($address) {
		$this->addresses['bcc'][] = trim($address);
	}

	public function replyto($address) {
		$this->reply_to = trim($address);
	}

	public function subject($subject = '') {
		$this->subject = trim(preg_replace('#[\n\r]+#s', '', $subject));
	}
	
	public function extra_headers($headers) {
		$this->extra_headers .= trim($headers) . "\n";
	}

	public function use_template($template) {
		global $bio, $core, $database;
		
		$template_parts = array_map('trim', explode('/', $template));
		
		$template_file = (isset($template_parts[0])) ? $template_parts[0] : 'default';
		$template_lang = ($bio->v('bio_lang')) ? $bio->v('bio_lang') : $core->v('site_lang');
		
		if (!isset($this->template[$template_lang][$template_file])) {
			// Load template
			if (!$contents = $core->cache->load('email_' . $template_lang . '_' . $template_file)) {
				$sql = 'SELECT *
					FROM _email_template
					WHERE email_template = ?
						AND email_lang = ?';
				if (!$contents = $core->cache->store(_rowset($sql, $template_file, $template_lang))) {
					$this->use_template();
				}
			}
			
			$this->template[$template_lang][$template_file] = $contents;
		}
		
		$this->message = $this->template[$template_lang][$template_file];
		
		return true;
	}

	public function send() {
		global $core, $bio;
		
		// Escape all quotes, else the eval will fail.
		$this->message = str_replace ("'", "\'", $this->message);
		$this->message = preg_replace('#\{([a-z0-9\-_]*?)\}#is', "' . $\\1 . '", $this->message);
		
		foreach ($this->vars as $k => $v) {
			${$k} = $v;
		}

		eval("\$this->msg = '$this->msg';");

		// Clear vars
		foreach ($this->vars as $k => $v) {
			unset($$k);
		}

		// We now try and pull a subject from the email body ... if it exists,
		// do this here because the subject may contain a variable
		$drop_header = '';
		$match = array();
		if (preg_match('#^(Subject:(.*?))$#m', $this->message, $match)) {
			$this->subject = (trim($match[2]) != '') ? trim($match[2]) : (($this->subject != '') ? $this->subject : 'No Subject');
			$drop_header .= '[\r\n]*?' . preg_quote($match[1], '#');
		} else {
			$this->subject = (($this->subject != '') ? $this->subject : 'No Subject');
		}

		if (preg_match('#^(Charset:(.*?))$#m', $this->message, $match)) {
			$this->encoding = (trim($match[2]) != '') ? trim($match[2]) : _lang('ENCODING');
			$drop_header .= '[\r\n]*?' . preg_quote($match[1], '#');
		} else {
			$this->encoding = _lang('ENCODING');
		}

		if ($drop_header != '') {
			$this->message = trim(preg_replace('#' . $drop_header . '#s', '', $this->message));
		}

		$to = $this->addresses['to'];

		$cc = (isset($this->addresses['cc']) && count($this->addresses['cc'])) ? implode(', ', $this->addresses['cc']) : '';
		$bcc = (isset($this->addresses['bcc']) && count($this->addresses['bcc'])) ? implode(', ', $this->addresses['bcc']) : '';
		
		if (empty($this->from)) {
			$this->from = $core->v('site_email');
		}

		// Build header
		$headers = array();
		
		if (!empty($this->reply_to)) {
			$headers[] = 'Reply-to: ' . $this->reply_to;
		}
		
		if (!empty($this->from)) {
			$headers[] = 'From: ' . $this->from;
		}
		
		$headers[] = "Return-Path: " . $core->v('site_email');
		$headers[] = "Message-ID: <" . md5(uniqid(time())) . "@" . get_host() . ">";
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-type: text/" . $this->format . "; charset=" . $this->encoding;
		$headers[] = "Content-transfer-encoding: 8bit";
		$headers[] = "Date: " . date('r', time());
		$headers[] = "X-Priority: 2";
		$headers[] = "X-MSMail-Priority: High";
		
		$this->extra_headers = implode("\n", $headers);
		
		$this->extra_headers = $this->extra_headers . (($cc != '') ? "Cc: $cc\n" : '')  . (($bcc != '') ? "Bcc: $bcc\n" : ''); 

		// Send message ... removed $this->encode() from subject for time being
		$empty_to_header = ($to == '') ? true : false;
		$to = ($to == '') ? 'Undisclosed-recipients:;' : $to;
		
		if ($this->htmle) {
			$this->msg = entity_decode($this->msg);
		}
		
		if ($core->v('mail_use_smtp')) {
			require_once(XFS.XCOR . 'mail.smtp.php');
			$mail = new PHPMailer(true);
			
			$mail->IsSMTP();
			
			try {
				$mail->SMTPDebug = 0;
				$mail->Host = 'ssl://smtp.gmail.com:465';
				$mail->Port = 465;
				$mail->Username = $core->v('mail_ticket_login');
				$mail->Password = $core->v('mail_ticket_key');
				$mail->SMTPAuth = TRUE;
				$mail->AddAddress($to);
				
				if ($this->reply_to != '') {
					$mail->AddReplyTo($this->reply_to);
				}
				
				if (isset($this->addresses['cc']) && count($this->addresses['cc'])) {
					foreach ($this->addresses['cc'] as $row) {
						$mail->AddCC($row);
					}
				}
				
				if (isset($this->addresses['bcc']) && count($this->addresses['bcc'])) {
					foreach ($this->addresses['bcc'] as $row) {
						$mail->AddBCC($row);
					}
				}
				
				$mail->SetFrom($this->from);
				$mail->Subject = _utf8($this->subject);
				
				$this->msg = _utf8($this->msg);
				
				$mail->MsgHTML(str_replace("\n", '<br />', $this->msg));
				$mail->AltBody = $this->msg;
				$mail->Send();
				
				return true;
			} catch (phpmailerException $e) {
				echo $e->errorMessage(); //Pretty error messages from PHPMailer
			} catch (Exception $e) {
				echo $e->getMessage(); //Boring error messages from anything else!
			}
			
			return;
		}
		
		$result = @mail($to, $this->subject, preg_replace("#(?<!\r)\n#s", "\n", $this->msg), $this->extra_headers, "-f{$core->v('site_email')}");
		
		// Did it work?
		if (!$result)
		{
			trigger_error('Failed sending email :: PHP :: ' . $result);
		}
		
		return true;
	}

	// Encodes the given string for proper display for this encoding ... nabbed 
	// from php.net and modified. There is an alternative encoding method which 
	// may produce lesd output but it's questionable as to its worth in this 
	// scenario IMO
	private function encode($str) {
		if ($this->encoding == '') {
			return $str;
		}

		// define start delimimter, end delimiter and spacer
		$end = "?=";
		$start = "=?$this->encoding?B?";
		$spacer = "$end\r\n $start";

		// determine length of encoded text within chunks and ensure length is even
		$length = 75 - strlen($start) - strlen($end);
		$length = floor($length / 2) * 2;

		// encode the string and split it into chunks with spacers after each chunk
		$str = chunk_split(base64_encode($str), $length, $spacer);

		// remove trailing spacer and add start and end delimiters
		$str = preg_replace('#' . preg_quote($spacer, '#') . '$#', '', $str);

		return $start . $str . $end;
	}

	//
	// Attach files via MIME.
	//
	private function attachFile($filename, $mimetype = "application/octet-stream", $szFromAddress, $szFilenameToDisplay) {
		$mime_boundary = "--==================_846811060==_";

		$this->msg = '--' . $mime_boundary . "\nContent-Type: text/plain;\n\tcharset=\"" . _lang('ENCODING') . "\"\n\n" . $this->msg;

		if ($mime_filename) {
			$filename = $mime_filename;
			$encoded = $this->encode_file($filename);
		}

		$fd = fopen($filename, "r");
		$contents = fread($fd, filesize($filename));

		$this->mimeOut = "--" . $mime_boundary . "\n";
		$this->mimeOut .= "Content-Type: " . $mimetype . ";\n\tname=\"$szFilenameToDisplay\"\n";
		$this->mimeOut .= "Content-Transfer-Encoding: quoted-printable\n";
		$this->mimeOut .= "Content-Disposition: attachment;\n\tfilename=\"$szFilenameToDisplay\"\n\n";

		if ($mimetype == 'message/rfc822') {
			$this->mimeOut .= "From: ".$szFromAddress."\n";
			$this->mimeOut .= "To: ".$this->emailAddress."\n";
			$this->mimeOut .= "Date: ".date("D, d M Y H:i:s") . " UT\n";
			$this->mimeOut .= "Reply-To:".$szFromAddress."\n";
			$this->mimeOut .= "Subject: ".$this->mailSubject."\n";
			$this->mimeOut .= "X-Mailer: PHP/".phpversion()."\n";
			$this->mimeOut .= "MIME-Version: 1.0\n";
		}

		$this->mimeOut .= $contents."\n";
		$this->mimeOut .= "--" . $mime_boundary . "--" . "\n";

		return $this->mimeout;
		// added -- to notify email client attachment is done
	}

	private function getMimeHeaders($filename, $mime_filename = '') {
		$mime_boundary = "--==================_846811060==_";

		if ($mime_filename) {
			$filename = $mime_filename;
		}

		$out = "MIME-Version: 1.0\n";
		$out .= "Content-Type: multipart/mixed;\n\tboundary=\"$mime_boundary\"\n\n";
		$out .= "This message is in MIME format. Since your mail reader does not understand\n";
		$out .= "this format, some or all of this message may not be legible.";

		return $out;
	}

	//
   // Split string by RFC 2045 semantics (76 chars per line, end with \r\n).
	//
	private function myChunkSplit($str) {
		$stmp = $str;
		$len = strlen($stmp);
		$out = "";

		while ($len > 0) {
			if ($len >= 76) {
				$out .= substr($stmp, 0, 76) . "\r\n";
				$stmp = substr($stmp, 76);
				$len = $len - 76;
			} else {
				$out .= $stmp . "\r\n";
				$stmp = "";
				$len = 0;
			}
		}
		return $out;
	}

	//
   // Split the specified file up into a string and return it
	//
	private function encode_file($sourcefile) {
		if (is_readable(@realpath($sourcefile))) {
			$fd = fopen($sourcefile, "r");
			$contents = fread($fd, filesize($sourcefile));
			$encoded = $this->myChunkSplit(base64_encode($contents));
			fclose($fd);
		}

		return $encoded;
	}

}

?>