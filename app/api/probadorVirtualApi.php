<?php

require_once __DIR__."/../../config/app.php";
require_once __DIR__."/../../autoload.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try{
    $controller = new \app\controllers\probadorVirtualController();
    $result = $controller->registrarProbadorDesdeApi();

    $status = (int)($result['status'] ?? 500);
    $body = $result['body'] ?? ['success'=>false, 'error'=>'unknown_error'];

    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
}catch(\Throwable $e){
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'internal_server_error',
    ], JSON_UNESCAPED_UNICODE);
}
