<?php

namespace app\services;

class SmtpClient{
	private ?string $lastError = null;

	public function getLastError(): ?string{
		return $this->lastError;
	}

	private function fail(string $message): bool{
		$this->lastError = $message;
		return false;
	}

	private function readResponse($socket): string{
		$response = '';
		while(!feof($socket)){
			$line = fgets($socket, 515);
			if($line === false){
				break;
			}
			$response .= $line;
			if(preg_match('/^\d{3}\s/', $line)){
				break;
			}
		}
		return $response;
	}

	private function sendCmd($socket, string $cmd): bool{
		$written = fwrite($socket, $cmd."\r\n");
		return ($written !== false);
	}

	private function expectCode(string $response, int $expectedCode): bool{
		return preg_match('/^'.preg_quote((string)$expectedCode, '/').'/', trim($response)) === 1;
	}

	private function dotStuff(string $data): string{
		$data = str_replace("\r\n", "\n", $data);
		$data = str_replace("\r", "\n", $data);
		$lines = explode("\n", $data);
		foreach($lines as &$line){
			if(isset($line[0]) && $line[0] === '.'){
				$line = '.'.$line;
			}
		}
		unset($line);
		return implode("\r\n", $lines);
	}

	/**
	 * @param array<int,array{filename:string,contentType:string,data:string}> $attachments
	 */
	public function send(array $cfg, string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody, array $attachments = []): bool{
		$this->lastError = null;

		$host = trim((string)($cfg['host'] ?? ''));
		$port = (int)($cfg['port'] ?? 25);
		$secure = $cfg['secure'] ?? null; // tls|ssl|null
		$username = $cfg['username'] ?? null;
		$password = $cfg['password'] ?? null;
		$timeout = (int)($cfg['timeout'] ?? 15);
		$verifyPeer = $cfg['verify_peer'] ?? true;
		$cafile = $cfg['cafile'] ?? null;
		$capath = $cfg['capath'] ?? null;

		if($host===''){
			return $this->fail('SMTP host vacío');
		}
		if($port <= 0){
			return $this->fail('SMTP port inválido');
		}
		if($timeout <= 0){
			$timeout = 15;
		}

		$verifyPeer = filter_var($verifyPeer, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
		if($verifyPeer === null){
			$verifyPeer = true;
		}

		if(is_string($username)){
			$username = trim($username);
		}
		if(is_string($password)){
			$password = trim($password);
			$password = preg_replace('/\s+/', '', $password); // por si se pega con espacios
			// Gmail app password suele ser 16 chars alfanuméricos; al copiar a veces se cuelan caracteres invisibles.
			$password = preg_replace('/[^A-Za-z0-9]/', '', $password);
		}

		if(is_string($username) && $username !== '' && (!is_string($password) || $password === '')){
			return $this->fail('SMTP password vacío (define BOUTIQUE_SMTP_PASSWORD o smtp.password)');
		}
		if(is_string($password) && $password !== '' && (!is_string($username) || $username === '')){
			return $this->fail('SMTP username vacío');
		}
		if(is_string($username) && $username !== '' && is_string($password) && $password !== '' && strlen($password) < 12){
			return $this->fail('SMTP password parece incompleto (revisa que sea la contraseña de aplicación de Gmail)');
		}

		$remote = $host.':'.$port;
		if($secure === 'ssl'){
			$remote = 'ssl://'.$remote;
		}

		$sslContext = [
			'sni_enabled' => true,
			'peer_name' => $host,
			'verify_peer' => $verifyPeer,
			'verify_peer_name' => $verifyPeer,
			'allow_self_signed' => !$verifyPeer,
		];
		if(is_string($cafile) && trim($cafile) !== ''){
			$sslContext['cafile'] = $cafile;
		}
		if(is_string($capath) && trim($capath) !== ''){
			$sslContext['capath'] = $capath;
		}

		$context = stream_context_create(['ssl' => $sslContext]);
		$errno = 0;
		$errstr = '';
		$socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
		if(!$socket){
			return $this->fail('No se pudo conectar a SMTP: '.$errstr.' ('.$errno.')');
		}
		stream_set_timeout($socket, $timeout);

		$banner = $this->readResponse($socket);
		if(!$this->expectCode($banner, 220)){
			fclose($socket);
			return $this->fail('SMTP banner inválido: '.trim($banner));
		}

		$localHost = gethostname();
		if(!is_string($localHost) || trim($localHost)===''){
			$localHost = 'localhost';
		}

		$this->sendCmd($socket, 'EHLO '.$localHost);
		$ehlo = $this->readResponse($socket);
		if(!$this->expectCode($ehlo, 250)){
			$this->sendCmd($socket, 'HELO '.$localHost);
			$helo = $this->readResponse($socket);
			if(!$this->expectCode($helo, 250)){
				fclose($socket);
				return $this->fail('SMTP HELO/EHLO falló: '.trim($ehlo.' '.$helo));
			}
		}

		if($secure === 'tls'){
			$this->sendCmd($socket, 'STARTTLS');
			$resp = $this->readResponse($socket);
			if(!$this->expectCode($resp, 220)){
				fclose($socket);
				return $this->fail('SMTP STARTTLS falló: '.trim($resp));
			}

			$method = null;
			if(defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') && defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')){
				$method = STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
			}elseif(defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')){
				$method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
			}elseif(defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')){
				$method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
			}

			$cryptoOk = @stream_socket_enable_crypto($socket, true, $method);
			if($cryptoOk !== true){
				fclose($socket);
				$hint = $verifyPeer ? ' (prueba verify_peer=false en config/mail.php para test)' : '';
				return $this->fail('No se pudo habilitar TLS en SMTP'.$hint);
			}

			$this->sendCmd($socket, 'EHLO '.$localHost);
			$ehlo2 = $this->readResponse($socket);
			if(!$this->expectCode($ehlo2, 250)){
				fclose($socket);
				return $this->fail('SMTP EHLO después de TLS falló: '.trim($ehlo2));
			}
		}

		if(is_string($username) && $username !== '' && is_string($password) && $password !== ''){
			$this->sendCmd($socket, 'AUTH LOGIN');
			$auth1 = $this->readResponse($socket);
			if(!$this->expectCode($auth1, 334)){
				fclose($socket);
				return $this->fail('SMTP AUTH LOGIN no aceptado: '.trim($auth1));
			}

			$this->sendCmd($socket, base64_encode($username));
			$auth2 = $this->readResponse($socket);
			if(!$this->expectCode($auth2, 334)){
				fclose($socket);
				return $this->fail('SMTP usuario rechazado: '.trim($auth2));
			}

			$this->sendCmd($socket, base64_encode($password));
			$auth3 = $this->readResponse($socket);
			if(!$this->expectCode($auth3, 235)){
				fclose($socket);
				return $this->fail('SMTP password/credenciales inválidas: '.trim($auth3));
			}
		}

		$this->sendCmd($socket, 'MAIL FROM:<'.$fromEmail.'>');
		$mf = $this->readResponse($socket);
		if(!$this->expectCode($mf, 250)){
			fclose($socket);
			return $this->fail('SMTP MAIL FROM falló: '.trim($mf));
		}

		$this->sendCmd($socket, 'RCPT TO:<'.$to.'>');
		$rt = $this->readResponse($socket);
		if(!($this->expectCode($rt, 250) || $this->expectCode($rt, 251))){
			fclose($socket);
			return $this->fail('SMTP RCPT TO falló: '.trim($rt));
		}

		$this->sendCmd($socket, 'DATA');
		$dataResp = $this->readResponse($socket);
		if(!$this->expectCode($dataResp, 354)){
			fclose($socket);
			return $this->fail('SMTP DATA falló: '.trim($dataResp));
		}

		$encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
		$encodedFromName = '=?UTF-8?B?'.base64_encode($fromName).'?=';

		$headers = [];
		$headers[] = 'From: '.$encodedFromName.' <'.$fromEmail.'>';
		$headers[] = 'To: <'.$to.'>';
		$headers[] = 'Subject: '.$encodedSubject;
		$headers[] = 'MIME-Version: 1.0';

		$hasAttachments = is_array($attachments) && count($attachments) > 0;
		if(!$hasAttachments){
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$headers[] = 'Content-Transfer-Encoding: 8bit';
			$message = implode("\r\n", $headers)."\r\n\r\n".$htmlBody;
		}else{
			$boundary = '=_BOUTIQUE_'.bin2hex(random_bytes(12));
			$headers[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
			$message = implode("\r\n", $headers)."\r\n\r\n";

			$body = '';
			$body .= '--'.$boundary."\r\n";
			$body .= "Content-Type: text/html; charset=UTF-8\r\n";
			$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
			$body .= $htmlBody."\r\n";

			foreach($attachments as $att){
				if(!is_array($att)){
					continue;
				}
				$filename = trim((string)($att['filename'] ?? 'archivo'));
				$contentType = trim((string)($att['contentType'] ?? 'application/octet-stream'));
				$data = (string)($att['data'] ?? '');
				if($data === ''){
					continue;
				}

				// Sanitizar filename para headers
				$filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
				if($filename === ''){
					$filename = 'archivo';
				}
				if($contentType === ''){
					$contentType = 'application/octet-stream';
				}

				$encoded = chunk_split(base64_encode($data));
				$body .= '--'.$boundary."\r\n";
				$body .= 'Content-Type: '.$contentType.'; name="'.$filename.'"' . "\r\n";
				$body .= "Content-Transfer-Encoding: base64\r\n";
				$body .= 'Content-Disposition: attachment; filename="'.$filename.'"' . "\r\n\r\n";
				$body .= $encoded."\r\n";
			}

			$body .= '--'.$boundary."--";
			$message .= $body;
		}

		$message = $this->dotStuff($message);

		$this->sendCmd($socket, $message."\r\n.");
		$end = $this->readResponse($socket);
		if(!$this->expectCode($end, 250)){
			fclose($socket);
			return $this->fail('SMTP envío falló: '.trim($end));
		}

		$this->sendCmd($socket, 'QUIT');
		$this->readResponse($socket);
		fclose($socket);
		return true;
	}
}
