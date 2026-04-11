<?php

namespace app\controllers;

use app\models\mainModel;

class recommendationController extends mainModel{
	private const CACHE_VERSION = 1;
	private const H_BINS = 12;
	private const S_BINS = 3;
	private const V_BINS = 3;
	private const MAX_PRODUCTS = 220;
	private const MAX_RESULTADOS = 8;
	private const MAX_UPLOAD_MB = 8;

	public function recomendarVestidosPorFotoControlador(): string{
		if(!isset($_SESSION['cliente_id']) || (int)$_SESSION['cliente_id'] <= 0){
			return json_encode(['ok'=>false,'error'=>'login_required','message'=>'Debes iniciar sesión como cliente.'], JSON_UNESCAPED_UNICODE);
		}

		if(!isset($_FILES['foto']) || !is_array($_FILES['foto'])){
			return json_encode(['ok'=>false,'error'=>'no_file','message'=>'No se subió ninguna foto.'], JSON_UNESCAPED_UNICODE);
		}
		if(($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
			return json_encode(['ok'=>false,'error'=>'upload_error','message'=>'Error al subir la foto.'], JSON_UNESCAPED_UNICODE);
		}

		$size = (int)($_FILES['foto']['size'] ?? 0);
		if($size <= 0){
			return json_encode(['ok'=>false,'error'=>'empty_file','message'=>'La foto está vacía.'], JSON_UNESCAPED_UNICODE);
		}
		if(($size / 1024 / 1024) > self::MAX_UPLOAD_MB){
			return json_encode(['ok'=>false,'error'=>'file_too_large','message'=>'La imagen es demasiado grande. Máximo '.self::MAX_UPLOAD_MB.'MB.'], JSON_UNESCAPED_UNICODE);
		}

		$tmpPath = (string)($_FILES['foto']['tmp_name'] ?? '');
		if($tmpPath === '' || !is_file($tmpPath)){
			return json_encode(['ok'=>false,'error'=>'tmp_missing','message'=>'No se encontró el archivo temporal de la imagen.'], JSON_UNESCAPED_UNICODE);
		}

		$mime = @mime_content_type($tmpPath);
		$mime = is_string($mime) ? strtolower(trim($mime)) : '';
		if(!in_array($mime, ['image/jpeg','image/png'], true)){
			return json_encode(['ok'=>false,'error'=>'invalid_type','message'=>'Formato no válido. Solo JPG o PNG.'], JSON_UNESCAPED_UNICODE);
		}

		if(!function_exists('imagecreatetruecolor')){
			return json_encode(['ok'=>false,'error'=>'gd_missing','message'=>'GD no está habilitado en PHP. Habilita la extensión GD para usar esta función.'], JSON_UNESCAPED_UNICODE);
		}

		$categoriaId = isset($_POST['categoria_id']) ? (int)$this->limpiarCadena($_POST['categoria_id']) : 0;
		$talla = isset($_POST['talla']) ? (string)$this->limpiarCadena($_POST['talla']) : '';
		$talla = strtoupper(trim($talla));
		if($talla !== '' && (!preg_match('/^[A-Z0-9]{1,10}$/', $talla))){
			$talla = '';
		}
		$tipoCuerpo = isset($_POST['tipo_cuerpo']) ? (string)$this->limpiarCadena($_POST['tipo_cuerpo']) : '';
		$tipoCuerpo = strtolower(trim($tipoCuerpo));
		$tiposValidos = ['reloj_arena','pera','manzana','rectangular','triangulo_invertido','no_se'];
		if($tipoCuerpo === ''){
			$tipoCuerpo = 'no_se';
		}
		if(!in_array($tipoCuerpo, $tiposValidos, true)){
			$tipoCuerpo = 'no_se';
		}
		$cinturaCm = null;
		if(isset($_POST['cintura_cm'])){
			$rawC = (string)$this->limpiarCadena($_POST['cintura_cm']);
			$rawC = str_replace(',', '.', $rawC);
			if($rawC !== '' && is_numeric($rawC)){
				$val = (float)$rawC;
				if($val >= 40 && $val <= 160){
					$cinturaCm = $val;
				}
			}
		}
		// cintura_cm es opcional; si no viene, se usa score neutral
		$maxPrecio = null;
		if(isset($_POST['max_price'])){
			$raw = (string)$this->limpiarCadena($_POST['max_price']);
			$digits = preg_replace('/\D+/', '', $raw);
			if($digits !== ''){
				$val = (float)((int)$digits);
				if($val > 0){
					$maxPrecio = $val;
				}
			}
		}

		$vectorUser = $this->featureVectorFromImageFile($tmpPath, $mime);
		if(!is_array($vectorUser) || empty($vectorUser)){
			return json_encode(['ok'=>false,'error'=>'img_read_failed','message'=>'No se pudo procesar la imagen.'], JSON_UNESCAPED_UNICODE);
		}

		$productos = $this->obtenerProductosParaRecomendacion($categoriaId, $talla, $maxPrecio, self::MAX_PRODUCTS);
		if(empty($productos)){
			return json_encode(['ok'=>false,'error'=>'no_products','message'=>'No hay productos disponibles para recomendar.'], JSON_UNESCAPED_UNICODE);
		}

		$baseDir = dirname(dirname(__DIR__));
		$tempDir = $baseDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'temp_reco'.DIRECTORY_SEPARATOR;
		if(!file_exists($tempDir)){
			@mkdir($tempDir, 0777, true);
		}
		$cachePath = $tempDir.'reco_cache_v'.self::CACHE_VERSION.'.json';
		$cache = $this->loadCache($cachePath);

		$resultados = [];
		foreach($productos as $prod){
			$pid = (int)($prod['producto_id'] ?? 0);
			$foto = (string)($prod['producto_foto'] ?? '');
			$modelo = (string)($prod['producto_modelo'] ?? '');
			$nombre = (string)($prod['producto_nombre'] ?? '');

			$fotoPath = $baseDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'productos'.DIRECTORY_SEPARATOR.$foto;
			if($foto === '' || !is_file($fotoPath)){
				$fotoPath = $baseDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'productos'.DIRECTORY_SEPARATOR.'default.png';
			}

			$vecProd = $this->getOrComputeProductVector($cache, $pid, $foto, $fotoPath);
			if(!is_array($vecProd) || empty($vecProd)){
				continue;
			}

			$scoreColor = $this->cosineSimilarity($vectorUser, $vecProd);
			$scoreTipo = $this->bodyModelCompatibilityScore($tipoCuerpo, $modelo, $nombre);
			$scoreCintura = ($cinturaCm !== null) ? $this->waistModelCompatibilityScore((float)$cinturaCm, $modelo, $nombre) : 0.55;
			$scoreCuerpo = (0.70 * $scoreTipo) + (0.30 * $scoreCintura);
			$score = (0.40 * $scoreColor) + (0.60 * $scoreCuerpo);
			$resultados[] = [
				'producto_id' => $pid,
				'producto_nombre' => $nombre,
				'producto_precio_venta' => (float)($prod['producto_precio_venta'] ?? 0),
				'producto_foto' => (string)($prod['producto_foto'] ?? ''),
				'score' => $score,
			];
		}

		$this->saveCache($cachePath, $cache);

		if(empty($resultados)){
			return json_encode(['ok'=>false,'error'=>'no_matches','message'=>'No se pudieron calcular recomendaciones con el catálogo actual.'], JSON_UNESCAPED_UNICODE);
		}

		usort($resultados, function($a, $b){
			return ($b['score'] <=> $a['score']);
		});
		$resultados = array_slice($resultados, 0, self::MAX_RESULTADOS);

		$items = [];
		foreach($resultados as $r){
			$foto = (string)($r['producto_foto'] ?? '');
			$fotoPathAbs = $baseDir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'productos'.DIRECTORY_SEPARATOR.$foto;
			$fotoUrl = ($foto !== '' && is_file($fotoPathAbs))
				? (APP_URL.'app/views/productos/'.$foto)
				: (APP_URL.'app/views/productos/default.png');

			$items[] = [
				'id' => (int)$r['producto_id'],
				'nombre' => (string)$r['producto_nombre'],
				'precio' => (float)$r['producto_precio_venta'],
				'foto_url' => $fotoUrl,
				'detalle_url' => APP_URL.'productoDetalle/'.(int)$r['producto_id'].'/',
				'score' => (float)$r['score'],
			];
		}

		$mensaje = 'Sugerencias generadas en base a tu tipo de cuerpo y tu foto.';
		return json_encode([
			'ok'=>true,
			'message'=>$mensaje,
			'items'=>$items,
		], JSON_UNESCAPED_UNICODE);
	}

	private function obtenerProductosParaRecomendacion(int $categoriaId, string $talla, $maxPrecio, int $limit): array{
		if($limit < 1){ $limit = 120; }
		if($limit > 600){ $limit = 600; }

		$where = "p.producto_estado = 'Habilitado' AND p.producto_stock_total > 0";
		$params = [];

		if($categoriaId > 0){
			$where .= " AND p.categoria_id = :cat";
			$params[':cat'] = $categoriaId;
		}

		if($talla !== ''){
			$regex = '(^|[ ,;\\/\-])'.$talla.'([ ,;\\/\-]|$)';
			$where .= " AND (UPPER(p.producto_talla) = :talla OR UPPER(p.producto_talla) REGEXP :regex)";
			$params[':talla'] = $talla;
			$params[':regex'] = $regex;
		}

		$maxPrecioNorm = null;
		if($maxPrecio !== null && $maxPrecio !== ''){
			$maxPrecioNorm = (float)$maxPrecio;
			if($maxPrecioNorm <= 0){
				$maxPrecioNorm = null;
			}
		}
		if($maxPrecioNorm !== null){
			$where .= " AND p.producto_precio_venta <= :maxPrecio";
			$params[':maxPrecio'] = $maxPrecioNorm;
		}

		// Intentar incluir producto_modelo (corte/modelo) si existe en la BD
		$sql = "SELECT p.producto_id, p.producto_nombre, p.producto_precio_venta, p.producto_foto, p.producto_talla, p.producto_modelo
			FROM producto p
			WHERE $where
			ORDER BY p.producto_id DESC
			LIMIT :lim";

		try{
			$stmt = $this->conectar()->prepare($sql);
			foreach($params as $k => $v){
				$paramType = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
				$stmt->bindValue($k, $v, $paramType);
			}
			$stmt->bindValue(':lim', (int)$limit, \PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return is_array($rows) ? $rows : [];
		}catch(\Throwable $e){
			// Fallback por si la columna producto_modelo no existe
			try{
				$sql2 = "SELECT p.producto_id, p.producto_nombre, p.producto_precio_venta, p.producto_foto, p.producto_talla
					FROM producto p
					WHERE $where
					ORDER BY p.producto_id DESC
					LIMIT :lim";
				$stmt = $this->conectar()->prepare($sql2);
				foreach($params as $k => $v){
					$paramType = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
					$stmt->bindValue($k, $v, $paramType);
				}
				$stmt->bindValue(':lim', (int)$limit, \PDO::PARAM_INT);
				$stmt->execute();
				$rows = $stmt->fetchAll();
				return is_array($rows) ? $rows : [];
			}catch(\Throwable $e2){
				return [];
			}
		}
	}

	private function bodyModelCompatibilityScore(string $tipoCuerpo, string $modelo, string $nombre): float{
		$tipoCuerpo = strtolower(trim($tipoCuerpo));
		if($tipoCuerpo === '' || $tipoCuerpo === 'no_se'){
			return 0.55; // neutral
		}

		$text = strtolower(trim($modelo.' '.$nombre));
		$text = $this->normalizeText($text);

		// Tokens típicos en "Corte o Modelo": sirena, princesa, a-line, imperio, recto, evasé...
		$match = function(array $needles) use ($text): bool{
			foreach($needles as $n){
				if($n === ''){ continue; }
				if(strpos($text, $n) !== false){
					return true;
				}
			}
			return false;
		};

		$score = 0.50;
		switch($tipoCuerpo){
			case 'pera':
				if($match(['a line','aline','a-line','princesa','imperio','evase','evasé','falda amplia','corte a'])){ $score += 0.40; }
				if($match(['sirena','entallado','ceñido','tubo','recto'])){ $score -= 0.15; }
				break;
			case 'manzana':
				if($match(['imperio','a line','aline','a-line','evase','evasé','corte a','falda amplia','cintura alta'])){ $score += 0.40; }
				if($match(['muy ceñido','entallado','sirena','tubo'])){ $score -= 0.10; }
				break;
			case 'reloj_arena':
				if($match(['sirena','entallado','cintura','corte sirena','wrap','cruzado'])){ $score += 0.35; }
				if($match(['recto','tubo'])){ $score += 0.10; }
				break;
			case 'rectangular':
				if($match(['princesa','a line','aline','a-line','evase','evasé','volante','cintura'])){ $score += 0.35; }
				if($match(['recto','tubo'])){ $score -= 0.05; }
				break;
			case 'triangulo_invertido':
				if($match(['a line','aline','a-line','evase','evasé','falda amplia','princesa'])){ $score += 0.35; }
				if($match(['strapless','sin tirantes','hombros'])){ $score -= 0.05; }
				break;
		}

		if($score < 0.0){ $score = 0.0; }
		if($score > 1.0){ $score = 1.0; }
		return (float)$score;
	}

	private function waistModelCompatibilityScore(float $cinturaCm, string $modelo, string $nombre): float{
		$text = strtolower(trim($modelo.' '.$nombre));
		$text = $this->normalizeText($text);

		$match = function(array $needles) use ($text): bool{
			foreach($needles as $n){
				if($n === ''){ continue; }
				if(strpos($text, $n) !== false){
					return true;
				}
			}
			return false;
		};

		// Categorías amplias (solo para recomendación de cortes)
		$score = 0.55;
		if($cinturaCm >= 90){
			if($match(['imperio','a line','aline','a-line','evase','evasé','corte a','falda amplia','cintura alta'])){ $score += 0.35; }
			if($match(['entallado','muy ceñido','sirena','tubo'])){ $score -= 0.12; }
		}elseif($cinturaCm <= 70){
			if($match(['entallado','sirena','cintura','wrap','cruzado','tubo','recto'])){ $score += 0.18; }
			if($match(['imperio'])){ $score -= 0.03; }
		}else{
			// rango medio: neutral, pequeño empuje a cortes versátiles
			if($match(['a line','aline','a-line','princesa','evase','evasé','recto'])){ $score += 0.10; }
		}

		if($score < 0.0){ $score = 0.0; }
		if($score > 1.0){ $score = 1.0; }
		return (float)$score;
	}

	private function normalizeText(string $s): string{
		$s = strtolower($s);
		$map = [
			'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'
		];
		$s = strtr($s, $map);
		$s = preg_replace('/[^a-z0-9\s\-]/', ' ', $s);
		$s = preg_replace('/\s+/', ' ', (string)$s);
		return trim((string)$s);
	}

	private function loadCache(string $path): array{
		try{
			if(!is_file($path)){
				return [
					'version' => self::CACHE_VERSION,
					'bins' => ['h'=>self::H_BINS,'s'=>self::S_BINS,'v'=>self::V_BINS],
					'items' => []
				];
			}
			$raw = file_get_contents($path);
			$data = json_decode((string)$raw, true);
			if(!is_array($data) || !isset($data['items']) || !is_array($data['items'])){
				return [
					'version' => self::CACHE_VERSION,
					'bins' => ['h'=>self::H_BINS,'s'=>self::S_BINS,'v'=>self::V_BINS],
					'items' => []
				];
			}
			return $data;
		}catch(\Throwable $e){
			return [
				'version' => self::CACHE_VERSION,
				'bins' => ['h'=>self::H_BINS,'s'=>self::S_BINS,'v'=>self::V_BINS],
				'items' => []
			];
		}
	}

	private function saveCache(string $path, array $cache): void{
		try{
			$cache['version'] = self::CACHE_VERSION;
			$cache['bins'] = ['h'=>self::H_BINS,'s'=>self::S_BINS,'v'=>self::V_BINS];
			$raw = json_encode($cache, JSON_UNESCAPED_UNICODE);
			if(is_string($raw)){
				@file_put_contents($path, $raw);
			}
		}catch(\Throwable $e){
			// no-op
		}
	}

	private function getOrComputeProductVector(array &$cache, int $productoId, string $fotoName, string $fotoPath){
		$items = isset($cache['items']) && is_array($cache['items']) ? $cache['items'] : [];
		$mtime = @filemtime($fotoPath);
		$mtime = is_int($mtime) ? $mtime : 0;

		$key = (string)$productoId;
		if(isset($items[$key]) && is_array($items[$key])){
			$entry = $items[$key];
			if(
				isset($entry['foto']) && (string)$entry['foto'] === $fotoName &&
				isset($entry['mtime']) && (int)$entry['mtime'] === (int)$mtime &&
				isset($entry['vec']) && is_array($entry['vec'])
			){
				return $entry['vec'];
			}
		}

		$mime = @mime_content_type($fotoPath);
		$mime = is_string($mime) ? strtolower(trim($mime)) : '';
		if(!in_array($mime, ['image/jpeg','image/png'], true)){
			// default.png suele ser png; si por algo no, intentamos igual como png
			$mime = 'image/png';
		}

		$vec = $this->featureVectorFromImageFile($fotoPath, $mime);
		if(!is_array($vec) || empty($vec)){
			return null;
		}

		$items[$key] = ['foto'=>$fotoName,'mtime'=>$mtime,'vec'=>$vec];
		$cache['items'] = $items;
		return $vec;
	}

	private function featureVectorFromImageFile(string $path, string $mime){
		$img = $this->loadImage($path, $mime);
		if(!$img){
			return null;
		}

		$w = imagesx($img);
		$h = imagesy($img);
		if($w <= 0 || $h <= 0){
			imagedestroy($img);
			return null;
		}

		$target = 72;
		$dst = imagecreatetruecolor($target, $target);
		imagecopyresampled($dst, $img, 0,0,0,0, $target,$target, $w,$h);
		imagedestroy($img);

		$bins = self::H_BINS * self::S_BINS * self::V_BINS;
		$hist = array_fill(0, $bins, 0.0);

		for($y=0; $y<$target; $y++){
			for($x=0; $x<$target; $x++){
				$rgb = imagecolorat($dst, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;

				$hsv = $this->rgbToHsv($r, $g, $b);
				$hb = (int)floor($hsv['h'] * self::H_BINS);
				if($hb >= self::H_BINS){ $hb = self::H_BINS - 1; }
				$sb = (int)floor($hsv['s'] * self::S_BINS);
				if($sb >= self::S_BINS){ $sb = self::S_BINS - 1; }
				$vb = (int)floor($hsv['v'] * self::V_BINS);
				if($vb >= self::V_BINS){ $vb = self::V_BINS - 1; }

				$idx = ($hb * self::S_BINS * self::V_BINS) + ($sb * self::V_BINS) + $vb;
				$hist[$idx] += 1.0;
			}
		}
		imagedestroy($dst);

		// Normalizar
		$norm = 0.0;
		foreach($hist as $v){
			$norm += $v*$v;
		}
		$norm = sqrt($norm);
		if($norm <= 0){
			return null;
		}
		for($i=0; $i<count($hist); $i++){
			$hist[$i] = $hist[$i] / $norm;
		}

		return $hist;
	}

	private function loadImage(string $path, string $mime){
		$img = null;
		try{
			if($mime === 'image/jpeg'){
				$img = @imagecreatefromjpeg($path);
				$img = $this->applyExifOrientationIfPossible($img, $path);
			}else{
				$img = @imagecreatefrompng($path);
			}
		}catch(\Throwable $e){
			$img = null;
		}
		return $img;
	}

	private function applyExifOrientationIfPossible($img, string $path){
		if(!$img){
			return $img;
		}
		if(!function_exists('exif_read_data')){
			return $img;
		}
		try{
			$exif = @exif_read_data($path);
			if(!is_array($exif) || !isset($exif['Orientation'])){
				return $img;
			}
			$orientation = (int)$exif['Orientation'];
			switch($orientation){
				case 3:
					$rot = imagerotate($img, 180, 0);
					if($rot){ imagedestroy($img); return $rot; }
					break;
				case 6:
					$rot = imagerotate($img, -90, 0);
					if($rot){ imagedestroy($img); return $rot; }
					break;
				case 8:
					$rot = imagerotate($img, 90, 0);
					if($rot){ imagedestroy($img); return $rot; }
					break;
			}
		}catch(\Throwable $e){
			return $img;
		}
		return $img;
	}

	private function rgbToHsv(int $r, int $g, int $b): array{
		$rf = $r / 255.0;
		$gf = $g / 255.0;
		$bf = $b / 255.0;

		$max = max($rf, $gf, $bf);
		$min = min($rf, $gf, $bf);
		$delta = $max - $min;

		$h = 0.0;
		if($delta > 0){
			if($max === $rf){
				$h = fmod((($gf - $bf) / $delta), 6.0);
			}elseif($max === $gf){
				$h = (($bf - $rf) / $delta) + 2.0;
			}else{
				$h = (($rf - $gf) / $delta) + 4.0;
			}
			$h = $h / 6.0;
			if($h < 0){ $h += 1.0; }
		}

		$s = ($max <= 0) ? 0.0 : ($delta / $max);
		$v = $max;

		return ['h'=>$h,'s'=>$s,'v'=>$v];
	}

	private function cosineSimilarity(array $a, array $b): float{
		$len = min(count($a), count($b));
		if($len <= 0){
			return 0.0;
		}
		$dot = 0.0;
		for($i=0; $i<$len; $i++){
			$dot += ((float)$a[$i]) * ((float)$b[$i]);
		}
		return (float)$dot;
	}
}
