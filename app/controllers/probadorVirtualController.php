<?php

namespace app\controllers;

use app\models\mainModel;

class probadorVirtualController extends mainModel{

    private ?string $cachedApiKey = null;

    private function loadEnvFileValue(string $key): ?string{
        static $cache = null;
        if($cache === null){
            $cache = [];
            $envPath = __DIR__."/../../.env";
            if(is_file($envPath) && is_readable($envPath)){
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if(is_array($lines)){
                    foreach($lines as $line){
                        $line = trim((string)$line);
                        if($line === '' || str_starts_with($line, '#')){
                            continue;
                        }
                        $parts = explode('=', $line, 2);
                        if(count($parts) !== 2){
                            continue;
                        }
                        $k = trim((string)$parts[0]);
                        $v = trim((string)$parts[1]);
                        if($k === ''){
                            continue;
                        }
                        if((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))){
                            $v = substr($v, 1, -1);
                        }
                        $hashPos = strpos($v, ' #');
                        if($hashPos !== false){
                            $v = trim(substr($v, 0, $hashPos));
                        }
                        $cache[$k] = $v;
                    }
                }
            }
        }

        if(isset($cache[$key])){
            $v = trim((string)$cache[$key]);
            return $v === '' ? null : $v;
        }
        return null;
    }

    private function expectedApiKey(): string{
        if($this->cachedApiKey !== null){
            return $this->cachedApiKey;
        }

        $env = getenv('BOUTIQUE_PROBADOR_API_KEY');
        if(is_string($env) && trim($env) !== ''){
            $this->cachedApiKey = trim($env);
            return $this->cachedApiKey;
        }

        $fromFile = $this->loadEnvFileValue('BOUTIQUE_PROBADOR_API_KEY');
        $this->cachedApiKey = $fromFile !== null ? $fromFile : '';
        return $this->cachedApiKey;
    }

    private function requestApiKey(): string{
        $key = '';
        if(isset($_SERVER['HTTP_X_API_KEY'])){
            $key = (string)$_SERVER['HTTP_X_API_KEY'];
        }elseif(isset($_SERVER['REDIRECT_HTTP_X_API_KEY'])){
            $key = (string)$_SERVER['REDIRECT_HTTP_X_API_KEY'];
        }

        if($key === '' && function_exists('getallheaders')){
            $headers = getallheaders();
            if(is_array($headers)){
                foreach($headers as $k => $v){
                    if(strtolower((string)$k) === 'x-api-key'){
                        $key = (string)$v;
                        break;
                    }
                }
            }
        }

        return trim($key);
    }

    private function jsonBody(): array{
        $raw = file_get_contents('php://input');
        if(!is_string($raw) || trim($raw) === ''){
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function normalizeFecha(string $value): ?string{
        $value = trim($value);
        if($value === ''){
            return null;
        }

        try{
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d H:i:s');
        }catch(\Throwable $e){
            return null;
        }
    }

    private function clienteExiste(int $clienteId): bool{
        try{
            $stmt = $this->conectar()->prepare('SELECT cliente_id FROM cliente WHERE cliente_id=:id LIMIT 1');
            $stmt->bindValue(':id', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
            return ($stmt->rowCount() >= 1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function columnaExiste(string $tabla, string $columna): bool{
        try{
            $stmt = $this->conectar()->prepare("SHOW COLUMNS FROM {$tabla} LIKE :c");
            $stmt->bindValue(':c', $columna);
            $stmt->execute();
            return ($stmt->rowCount() >= 1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function asegurarColumnaProbadorImagen(): void{
        if($this->columnaExiste('probador_virtual', 'probador_imagen')){
            return;
        }
        try{
            $this->ejecutarConsulta("ALTER TABLE probador_virtual ADD COLUMN probador_imagen VARCHAR(255) NULL AFTER cliente_id");
        }catch(\Throwable $e){
            // Sin permisos / no soportado: ignorar
        }
    }

    private function guardarImagenProbador(string $base64Raw, string $sesion): ?string{
        $payload = trim($base64Raw);
        if($payload === ''){
            return null;
        }

        if(str_contains($payload, ',')){
            $parts = explode(',', $payload, 2);
            $payload = trim((string)($parts[1] ?? ''));
        }

        $binary = base64_decode($payload, true);
        if($binary === false || $binary === ''){
            return null;
        }

        if(strlen($binary) > (8 * 1024 * 1024)){
            return null;
        }

        $ext = 'jpg';
        $mime = 'image/jpeg';
        if(str_starts_with($binary, "\x89PNG\r\n\x1a\n")){
            $ext = 'png';
            $mime = 'image/png';
        }elseif(str_starts_with($binary, "RIFF") && substr($binary, 8, 4) === 'WEBP'){
            $ext = 'webp';
            $mime = 'image/webp';
        }

        $destDir = __DIR__.'/../views/fotos/probador/';
        if(!is_dir($destDir)){
            @mkdir($destDir, 0775, true);
        }
        if(!is_dir($destDir)){
            return null;
        }

        $safeSesion = preg_replace('/[^a-zA-Z0-9_-]/', '', $sesion);
        if($safeSesion === ''){
            $safeSesion = 'sesion';
        }
        $name = 'probador_'.$safeSesion.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $destPath = $destDir.$name;
        if(@file_put_contents($destPath, $binary) === false){
            return null;
        }

        return 'app/views/fotos/probador/'.$name;
    }

    public function registrarProbadorDesdeApi(): array{
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            return ['status'=>405, 'body'=>['success'=>false, 'error'=>'method_not_allowed']];
        }

        $expected = $this->expectedApiKey();
        if($expected === ''){
            return ['status'=>500, 'body'=>['success'=>false, 'error'=>'api_key_not_configured']];
        }

        $incoming = $this->requestApiKey();
        if($incoming === '' || !hash_equals($expected, $incoming)){
            return ['status'=>401, 'body'=>['success'=>false, 'error'=>'invalid_api_key']];
        }

        $data = $this->jsonBody();
        if(empty($data)){
            return ['status'=>400, 'body'=>['success'=>false, 'error'=>'invalid_json']];
        }

        $sesion = trim((string)($data['sesion'] ?? ''));
        $fechaIn = trim((string)($data['fecha'] ?? ''));
        $clienteId = (int)($data['cliente_id'] ?? 0);

        if($sesion === '' || strlen($sesion) > 255){
            return ['status'=>422, 'body'=>['success'=>false, 'error'=>'sesion_invalida']];
        }

        if(!preg_match('/^[a-zA-Z0-9_-]+$/', $sesion)){
            return ['status'=>422, 'body'=>['success'=>false, 'error'=>'sesion_formato_invalido']];
        }

        $fecha = $this->normalizeFecha($fechaIn);
        if($fecha === null){
            return ['status'=>422, 'body'=>['success'=>false, 'error'=>'fecha_invalida']];
        }

        if($clienteId <= 0){
            return ['status'=>422, 'body'=>['success'=>false, 'error'=>'cliente_id_invalido']];
        }

        if(!$this->clienteExiste($clienteId)){
            return ['status'=>404, 'body'=>['success'=>false, 'error'=>'cliente_no_encontrado']];
        }

        $imagenBase64 = trim((string)($data['imagen_base64'] ?? ''));
        $probadorImagen = null;
        if($imagenBase64 !== ''){
            $probadorImagen = $this->guardarImagenProbador($imagenBase64, $sesion);
            if($probadorImagen === null){
                return ['status'=>422, 'body'=>['success'=>false, 'error'=>'imagen_invalida']];
            }
        }

        $this->asegurarColumnaProbadorImagen();
        $tieneProbadorImagen = $this->columnaExiste('probador_virtual', 'probador_imagen');

        try{
            $pdo = $this->conectar();

            $sql = 'INSERT INTO probador_virtual (fecha, sesion, cliente_id';
            if($tieneProbadorImagen && $probadorImagen !== null){
                $sql .= ', probador_imagen';
            }
            $sql .= ') VALUES (:fecha, :sesion, :cliente_id';
            if($tieneProbadorImagen && $probadorImagen !== null){
                $sql .= ', :probador_imagen';
            }
            $sql .= ')';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':fecha', $fecha);
            $stmt->bindValue(':sesion', $sesion);
            $stmt->bindValue(':cliente_id', $clienteId, \PDO::PARAM_INT);
            if($tieneProbadorImagen && $probadorImagen !== null){
                $stmt->bindValue(':probador_imagen', $probadorImagen);
            }
            $stmt->execute();

            $probadorId = (int)$pdo->lastInsertId();
            if($probadorId <= 0){
                $tmp = $pdo->prepare('SELECT probador_id FROM probador_virtual WHERE sesion=:sesion AND cliente_id=:cliente_id ORDER BY probador_id DESC LIMIT 1');
                $tmp->bindValue(':sesion', $sesion);
                $tmp->bindValue(':cliente_id', $clienteId, \PDO::PARAM_INT);
                $tmp->execute();
                $row = $tmp->fetch();
                $probadorId = (int)($row['probador_id'] ?? 0);
            }

            $body = ['success'=>true, 'probador_id'=>$probadorId];
            if($probadorImagen !== null){
                $body['probador_imagen'] = $probadorImagen;
            }

            return ['status'=>201, 'body'=>$body];
        }catch(\Throwable $e){
            return ['status'=>500, 'body'=>['success'=>false, 'error'=>'db_insert_error']];
        }
    }
}
