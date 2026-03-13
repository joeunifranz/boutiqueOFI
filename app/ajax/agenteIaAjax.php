<?php

require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

use app\services\AgenteIaService;
use app\services\AgenteIaClientContextService;

header('Content-Type: application/json; charset=utf-8');

if(!defined('AGENTE_IA_ENABLED') || AGENTE_IA_ENABLED !== true){
	http_response_code(404);
	echo json_encode(['ok'=>false, 'error'=>'Agente IA deshabilitado'], JSON_UNESCAPED_UNICODE);
	exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
	http_response_code(405);
	echo json_encode(['ok'=>false, 'error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
	exit;
}

$raw = file_get_contents('php://input');
$body = json_decode((string)$raw, true);
if(!is_array($body)){
	http_response_code(400);
	echo json_encode(['ok'=>false, 'error'=>'JSON inválido'], JSON_UNESCAPED_UNICODE);
	exit;
}

$message = isset($body['message']) ? (string)$body['message'] : '';
$message = trim($message);
if($message === ''){
	http_response_code(400);
	echo json_encode(['ok'=>false, 'error'=>'Mensaje vacío'], JSON_UNESCAPED_UNICODE);
	exit;
}

// Límite simple para evitar abuso
if(mb_strlen($message) > 2000){
	http_response_code(400);
	echo json_encode(['ok'=>false, 'error'=>'Mensaje demasiado largo'], JSON_UNESCAPED_UNICODE);
	exit;
}

$pagePath = isset($body['page']) ? (string)$body['page'] : '';
$pagePath = trim($pagePath);

$ctxSvc = new AgenteIaClientContextService();
$ctx = $ctxSvc->build($message, $_SESSION ?? [], ($pagePath!=='' ? $pagePath : null));

// Si detecta pregunta de admin, respondemos sin llamar al LLM
if(isset($ctx['blocked']) && $ctx['blocked'] === true){
	http_response_code(200);
	echo json_encode([
		'ok'=>true,
		'response'=>(string)($ctx['response'] ?? 'No tengo permisos para esa información.'),
	], JSON_UNESCAPED_UNICODE);
	exit;
}

$service = new AgenteIaService();
$result = $service->chat($message, [
	'db_context' => (string)($ctx['db_context'] ?? ''),
	'page_context' => (string)($ctx['page_context'] ?? ''),
	'user_context' => (array)($ctx['user_context'] ?? []),
]);

if(!isset($result['ok']) || $result['ok'] !== true){
	http_response_code(502);
	echo json_encode(['ok'=>false, 'error'=>$result['error'] ?? 'Error del agente'], JSON_UNESCAPED_UNICODE);
	exit;
}

// Respuesta normalizada
http_response_code(200);
echo json_encode([
	'ok'=>true,
	'response'=>(string)$result['response'],
], JSON_UNESCAPED_UNICODE);
