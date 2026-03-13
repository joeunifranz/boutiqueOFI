<?php

namespace app\services;

class MailService{
	private ?string $lastError = null;
	private array $config = [];
	private SmtpClient $smtp;

	public function __construct(?array $config = null){
		$this->smtp = new SmtpClient();
		$this->config = is_array($config) ? $config : $this->loadConfig();
	}

	public function getLastError(): ?string{
		if(is_string($this->lastError) && $this->lastError !== ''){
			return $this->lastError;
		}
		return $this->smtp->getLastError();
	}

	private function fail(string $message): bool{
		$this->lastError = $message;
		return false;
	}

	private function loadConfig(): array{
		$path = __DIR__."/../../config/mail.php";
		if(!file_exists($path)){
			return [];
		}
		$cfg = require $path;
		return is_array($cfg) ? $cfg : [];
	}

	private function env(string $key): ?string{
		$v = getenv($key);
		if($v === false){
			return null;
		}
		$v = trim((string)$v);
		return $v === '' ? null : $v;
	}

	private function buildSmtpConfig(): array{
		$smtp = $this->config['smtp'] ?? [];
		if(!is_array($smtp)){
			$smtp = [];
		}

		$host = $this->env('BOUTIQUE_SMTP_HOST');
		$port = $this->env('BOUTIQUE_SMTP_PORT');
		$secure = $this->env('BOUTIQUE_SMTP_SECURE');
		$username = $this->env('BOUTIQUE_SMTP_USERNAME');
		$password = $this->env('BOUTIQUE_SMTP_PASSWORD');
		$verifyPeer = $this->env('BOUTIQUE_SMTP_VERIFY_PEER');
		$timeout = $this->env('BOUTIQUE_SMTP_TIMEOUT');
		$cafile = $this->env('BOUTIQUE_SMTP_CAFILE');
		$capath = $this->env('BOUTIQUE_SMTP_CAPATH');

		// Prioriza config/mail.php. Usa env solo si el valor en config falta o está vacío.
		if($host !== null && (!isset($smtp['host']) || trim((string)$smtp['host']) === '')){
			$smtp['host'] = $host;
		}
		if($port !== null && ctype_digit($port) && (!isset($smtp['port']) || (int)$smtp['port'] <= 0)){
			$smtp['port'] = (int)$port;
		}
		if($secure !== null && (!isset($smtp['secure']) || trim((string)$smtp['secure']) === '')){
			$smtp['secure'] = $secure;
		}
		if($username !== null && (!isset($smtp['username']) || trim((string)$smtp['username']) === '')){
			$smtp['username'] = $username;
		}
		if($password !== null && (!isset($smtp['password']) || trim((string)$smtp['password']) === '')){
			$smtp['password'] = $password;
		}
		if($timeout !== null && ctype_digit($timeout) && (!isset($smtp['timeout']) || (int)$smtp['timeout'] <= 0)){
			$smtp['timeout'] = (int)$timeout;
		}
		if($verifyPeer !== null && !isset($smtp['verify_peer'])){
			$smtp['verify_peer'] = filter_var($verifyPeer, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
		}
		if($cafile !== null && (!isset($smtp['cafile']) || trim((string)$smtp['cafile']) === '')){
			$smtp['cafile'] = $cafile;
		}
		if($capath !== null && (!isset($smtp['capath']) || trim((string)$smtp['capath']) === '')){
			$smtp['capath'] = $capath;
		}

		return $smtp;
	}

	private function resolveFrom(): array{
		$from = $this->config['from'] ?? [];
		if(!is_array($from)){
			$from = [];
		}

		$fromEmail = trim((string)($from['email'] ?? ''));
		$fromName = trim((string)($from['name'] ?? ''));

		$envFromEmail = $this->env('BOUTIQUE_MAIL_FROM_EMAIL');
		$envFromName = $this->env('BOUTIQUE_MAIL_FROM_NAME');
		if($envFromEmail !== null){
			$fromEmail = $envFromEmail;
		}
		if($envFromName !== null){
			$fromName = $envFromName;
		}

		if($fromName === ''){
			$fromName = defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE';
		}

		if($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)){
			$smtp = $this->buildSmtpConfig();
			$candidate = trim((string)($smtp['username'] ?? ''));
			if($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)){
				$fromEmail = $candidate;
			}
		}

		return [$fromEmail, $fromName];
	}

	public function sendHtml(string $to, string $subject, string $htmlBody): bool{
		$this->lastError = null;

		$to = trim($to);
		if($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)){
			return $this->fail('Email destino inválido');
		}
		if(trim($subject) === ''){
			return $this->fail('Subject vacío');
		}
		if(trim($htmlBody) === ''){
			return $this->fail('Body vacío');
		}

		$driver = $this->config['driver'] ?? 'smtp';
		if(!is_string($driver) || trim($driver) === ''){
			$driver = 'smtp';
		}
		$driver = strtolower(trim($driver));
		if($driver !== 'smtp'){
			return $this->fail('Driver de correo no soportado: '.$driver.' (usa smtp)');
		}

		if(!function_exists('stream_socket_client')){
			return $this->fail('No disponible stream_socket_client() en PHP');
		}

		$smtpCfg = $this->buildSmtpConfig();
		$secure = strtolower((string)($smtpCfg['secure'] ?? ''));
		if(($secure === 'tls' || $secure === 'ssl') && !extension_loaded('openssl')){
			return $this->fail('Extensión openssl no habilitada en PHP (necesaria para TLS/SSL)');
		}

		[$fromEmail, $fromName] = $this->resolveFrom();
		if($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)){
			return $this->fail('Email remitente inválido (config/mail.php -> from.email o BOUTIQUE_MAIL_FROM_EMAIL)');
		}

		$ok = $this->smtp->send($smtpCfg, $fromEmail, $fromName, $to, $subject, $htmlBody, []);
		if(!$ok){
			return $this->fail($this->smtp->getLastError() ?: 'Falló envío SMTP');
		}
		return true;
	}

	/**
	 * @param array<int,array{filename:string,contentType:string,data:string}> $attachments
	 */
	public function sendHtmlWithAttachments(string $to, string $subject, string $htmlBody, array $attachments): bool{
		$this->lastError = null;

		$to = trim($to);
		if($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)){
			return $this->fail('Email destino inválido');
		}
		if(trim($subject) === ''){
			return $this->fail('Subject vacío');
		}
		if(trim($htmlBody) === ''){
			return $this->fail('Body vacío');
		}
		if(!is_array($attachments) || count($attachments) === 0){
			return $this->sendHtml($to, $subject, $htmlBody);
		}

		$driver = $this->config['driver'] ?? 'smtp';
		if(!is_string($driver) || trim($driver) === ''){
			$driver = 'smtp';
		}
		$driver = strtolower(trim($driver));
		if($driver !== 'smtp'){
			return $this->fail('Driver de correo no soportado: '.$driver.' (usa smtp)');
		}

		if(!function_exists('stream_socket_client')){
			return $this->fail('No disponible stream_socket_client() en PHP');
		}

		$smtpCfg = $this->buildSmtpConfig();
		$secure = strtolower((string)($smtpCfg['secure'] ?? ''));
		if(($secure === 'tls' || $secure === 'ssl') && !extension_loaded('openssl')){
			return $this->fail('Extensión openssl no habilitada en PHP (necesaria para TLS/SSL)');
		}

		[$fromEmail, $fromName] = $this->resolveFrom();
		if($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)){
			return $this->fail('Email remitente inválido (config/mail.php -> from.email o BOUTIQUE_MAIL_FROM_EMAIL)');
		}

		$normalized = [];
		foreach($attachments as $att){
			if(!is_array($att)){
				continue;
			}
			$filename = trim((string)($att['filename'] ?? ''));
			$contentType = trim((string)($att['contentType'] ?? 'application/octet-stream'));
			$data = (string)($att['data'] ?? '');
			if($filename === '' || $data === ''){
				continue;
			}
			$normalized[] = [
				'filename' => $filename,
				'contentType' => ($contentType !== '' ? $contentType : 'application/octet-stream'),
				'data' => $data,
			];
		}
		if(count($normalized) === 0){
			return $this->sendHtml($to, $subject, $htmlBody);
		}

		$ok = $this->smtp->send($smtpCfg, $fromEmail, $fromName, $to, $subject, $htmlBody, $normalized);
		if(!$ok){
			return $this->fail($this->smtp->getLastError() ?: 'Falló envío SMTP');
		}
		return true;
	}
}
