<?php

namespace app\services;

use app\models\mainModel;

class AgenteIaClientContextService extends mainModel{
	private static ?bool $tablaProductoExiste = null;
	private static ?bool $tablaCategoriaExiste = null;
	private static ?bool $tablaReservaExiste = null;

	public function build(string $message, array $session, ?string $pagePath = null): array{
		$message = trim($message);
		$pagePath = is_string($pagePath) ? trim($pagePath) : null;

		// 1) Bloqueo explícito de temas de administración
		if($this->isAdminOnlyQuestion($message)){
			return [
				'blocked' => true,
				'response' => "Puedo ayudarte con catálogo, tallas, precios, disponibilidad y pasos para tu reserva. Pero no tengo permisos para ver información administrativa (ventas, ganancias, usuarios, reportes o datos internos).",
				'db_context' => '',
				'page_context' => $this->buildPageContext($pagePath),
				'user_context' => $this->buildUserContext($session),
			];
		}

		$dbContextParts = [];

		// 2) Contexto por página (si aplica)
		$pageCtx = $this->buildPageContext($pagePath);
		if($pageCtx !== ''){
			$dbContextParts[] = "CONTEXTO_DE_PAGINA:\n".$pageCtx;
		}

		// 3) Categorías
		if($this->wantsCategories($message)){
			$cats = $this->getCategorias();
			if(!empty($cats)){
				$lines = [];
				foreach($cats as $c){
					$lines[] = "- (ID {$c['categoria_id']}) {$c['categoria_nombre']}";
				}
				$dbContextParts[] = "CATEGORIAS_DISPONIBLES:\n".implode("\n", $lines);
			}
		}

		// 4) Productos (búsqueda simple)
		if($this->wantsProducts($message) || $this->pageLooksLikeCatalog($pagePath)){
			$prods = $this->searchProductos($message, $pagePath);
			if(!empty($prods)){
				$lines = [];
				foreach($prods as $p){
					$precio = (float)($p['producto_precio_venta'] ?? 0);
					$talla = (string)($p['producto_talla'] ?? '');
					$cat = (string)($p['categoria_nombre'] ?? '');
					$dispo = ((int)($p['producto_stock_total'] ?? 0) > 0) ? 'Disponible' : 'Agotado';
					$link = (defined('APP_URL') ? (string)APP_URL : '').'productoDetalle/'.((int)$p['producto_id']).'/';
					$lines[] = "- {$p['producto_nombre']} (Talla {$talla}, {$cat}) — ".(defined('MONEDA_SIMBOLO') ? MONEDA_SIMBOLO : 'Bs').number_format($precio, (int)(defined('MONEDA_DECIMALES') ? MONEDA_DECIMALES : 2), (defined('MONEDA_SEPARADOR_DECIMAL') ? MONEDA_SEPARADOR_DECIMAL : '.'), (defined('MONEDA_SEPARADOR_MILLAR') ? MONEDA_SEPARADOR_MILLAR : ',' ))." — {$dispo} — Link: {$link}";
				}
				$dbContextParts[] = "PRODUCTOS_RELACIONADOS (CATALOGO_PUBLICO):\n".implode("\n", $lines);
			}
		}

		// 5) Reservas del cliente (solo si está logueado como cliente)
		$clienteId = isset($session['cliente_id']) ? (int)$session['cliente_id'] : 0;
		if($clienteId > 0 && $this->wantsReservations($message)){
			$codigo = $this->extractReservaCodigo($message);
			if($codigo !== ''){
				$res = $this->getReservaClientePorCodigo($clienteId, $codigo);
				if($res){
					$linkPagar = (defined('APP_URL') ? (string)APP_URL : '').'reservaPagar/'.urlencode((string)$res['reserva_codigo']).'/';
					$dbContextParts[] = "RESERVA_DEL_CLIENTE:\n".
						"- Código: ".(string)$res['reserva_codigo']."\n".
						"- Estado: ".(string)$res['reserva_estado']."\n".
						"- Fecha: ".(string)$res['reserva_fecha']."\n".
						"- Hora: ".(string)$res['reserva_hora']."\n".
						"- Producto: ".(string)$res['producto_nombre']."\n".
						"- Total: ".(defined('MONEDA_SIMBOLO') ? MONEDA_SIMBOLO : 'Bs').number_format((float)$res['reserva_total'], (int)(defined('MONEDA_DECIMALES') ? MONEDA_DECIMALES : 2), (defined('MONEDA_SEPARADOR_DECIMAL') ? MONEDA_SEPARADOR_DECIMAL : '.'), (defined('MONEDA_SEPARADOR_MILLAR') ? MONEDA_SEPARADOR_MILLAR : ','))."\n".
						"- Link para pagar/ver: {$linkPagar}";
				}
			}
		}

		$dbContext = trim(implode("\n\n", $dbContextParts));

		return [
			'blocked' => false,
			'response' => '',
			'db_context' => $dbContext,
			'page_context' => $pageCtx,
			'user_context' => $this->buildUserContext($session),
		];
	}

	private function buildUserContext(array $session): array{
		$clienteId = isset($session['cliente_id']) ? (int)$session['cliente_id'] : 0;
		$clienteNombre = isset($session['cliente_nombre']) ? trim((string)$session['cliente_nombre']) : '';
		return [
			'client_logged_in' => ($clienteId > 0),
			'client_name' => $clienteNombre,
		];
	}

	private function buildPageContext(?string $pagePath): string{
		if(!$pagePath){
			return '';
		}
		// Normalizar a ruta simple
		$pagePath = preg_replace('~[\?#].*$~', '', $pagePath);
		$pagePath = trim((string)$pagePath);
		if($pagePath === ''){
			return '';
		}

		$parts = [];
		$parts[] = "Ruta: {$pagePath}";

		if(preg_match('~productoDetalle/(\d+)~', $pagePath, $m)){
			$parts[] = 'Vista: detalle de producto (ID '.$m[1].')';
		}elseif(preg_match('~productosCliente/(\d+)~', $pagePath, $m)){
			$parts[] = 'Vista: catálogo por categoría (ID '.$m[1].')';
		}elseif(stripos($pagePath, 'productosCliente') !== false){
			$parts[] = 'Vista: catálogo';
		}elseif(stripos($pagePath, 'reservaNueva') !== false){
			$parts[] = 'Vista: crear reserva';
		}elseif(stripos($pagePath, 'reservaPagar') !== false){
			$parts[] = 'Vista: pagar reserva';
		}elseif(stripos($pagePath, 'inicio') !== false || $pagePath === '/' || $pagePath === ''){
			$parts[] = 'Vista: inicio';
		}

		return implode("\n", $parts);
	}

	private function pageLooksLikeCatalog(?string $pagePath): bool{
		if(!$pagePath){
			return false;
		}
		return (stripos($pagePath, 'productosCliente') !== false || stripos($pagePath, 'productoDetalle') !== false);
	}

	private function wantsCategories(string $message): bool{
		$m = mb_strtolower($message);
		return (str_contains($m, 'categoria') || str_contains($m, 'categoría') || str_contains($m, 'categorias') || str_contains($m, 'categorías'));
	}

	private function wantsProducts(string $message): bool{
		$m = mb_strtolower($message);
		$keys = ['producto', 'productos', 'vestido', 'vestidos', 'sirena', 'princesa', 'talla', 'precio', 'presupuesto', 'bs'];
		foreach($keys as $k){
			if(str_contains($m, $k)){
				return true;
			}
		}
		return false;
	}

	private function wantsReservations(string $message): bool{
		$m = mb_strtolower($message);
		$keys = ['reserva', 'reservar', 'cita', 'citas', 'pagar', 'qr', 'abono', 'confirmar'];
		foreach($keys as $k){
			if(str_contains($m, $k)){
				return true;
			}
		}
		return false;
	}

	private function isAdminOnlyQuestion(string $message): bool{
		$m = mb_strtolower($message);
		$patterns = [
			'venta', 'ventas', 'ganancia', 'ganancias', 'utilidad', 'utilidades', 'reporte', 'reportes',
			'caja', 'cajas', 'usuario', 'usuarios', 'empleado', 'empleados', 'admin', 'administrador',
			'cliente email', 'correos de clientes', 'lista de clientes', 'proveedor', 'proveedores',
			'precio de compra', 'costo', 'costos', 'inventario completo', 'log', 'token', 'api key', 'contraseña', 'password'
		];
		foreach($patterns as $p){
			if(str_contains($m, $p)){
				return true;
			}
		}
		return false;
	}

	private function tablaExisteCached(string $tabla, ?bool &$cache): bool{
		if($cache !== null){
			return $cache;
		}
		try{
			$stmt = $this->conectar()->prepare('SHOW TABLES LIKE :t');
			$stmt->bindValue(':t', $tabla);
			$stmt->execute();
			$cache = ($stmt->rowCount() >= 1);
			return $cache;
		}catch(\Throwable $e){
			$cache = false;
			return false;
		}
	}

	private function getCategorias(): array{
		if(!$this->tablaExisteCached('categoria', self::$tablaCategoriaExiste)){
			return [];
		}
		try{
			$stmt = $this->conectar()->prepare('SELECT categoria_id, categoria_nombre FROM categoria ORDER BY categoria_nombre ASC LIMIT 50');
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return is_array($rows) ? $rows : [];
		}catch(\Throwable $e){
			return [];
		}
	}

	private function searchProductos(string $message, ?string $pagePath): array{
		if(!$this->tablaExisteCached('producto', self::$tablaProductoExiste)){
			return [];
		}
		if(!$this->tablaExisteCached('categoria', self::$tablaCategoriaExiste)){
			return [];
		}

		$like = $this->buildLikeQuery($message);
		if($like === ''){
			return [];
		}

		// Si estamos en catálogo por categoría, intentamos usarla como filtro
		$categoriaId = 0;
		if($pagePath && preg_match('~productosCliente/(\d+)~', $pagePath, $m)){
			$categoriaId = (int)$m[1];
		}

		$sql = "SELECT p.producto_id, p.producto_nombre, p.producto_talla, p.producto_precio_venta, p.producto_stock_total, c.categoria_nombre
				FROM producto p
				INNER JOIN categoria c ON c.categoria_id = p.categoria_id
				WHERE p.producto_estado='Habilitado'
				AND (p.producto_nombre LIKE :q OR p.producto_codigo LIKE :q OR p.producto_talla LIKE :q OR c.categoria_nombre LIKE :q)";
		$params = [':q' => $like];

		if($categoriaId > 0){
			$sql .= " AND p.categoria_id = :cat";
			$params[':cat'] = $categoriaId;
		}

		$sql .= " ORDER BY (p.producto_stock_total>0) DESC, p.producto_precio_venta ASC LIMIT 8";

		try{
			$stmt = $this->conectar()->prepare($sql);
			foreach($params as $k => $v){
				if($k === ':cat'){
					$stmt->bindValue($k, (int)$v, \PDO::PARAM_INT);
				}else{
					$stmt->bindValue($k, (string)$v);
				}
			}
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return is_array($rows) ? $rows : [];
		}catch(\Throwable $e){
			return [];
		}
	}

	private function buildLikeQuery(string $message): string{
		$m = trim($message);
		if($m === ''){
			return '';
		}
		// Recortar longitud para que LIKE sea manejable
		if(mb_strlen($m) > 80){
			$m = mb_substr($m, 0, 80);
		}
		// Quitar símbolos que no ayudan
		$m = preg_replace('/[^\p{L}\p{N}\s\-\.]/u', ' ', $m);
		$m = trim(preg_replace('/\s+/', ' ', (string)$m));
		if($m === ''){
			return '';
		}
		return '%'.$m.'%';
	}

	private function extractReservaCodigo(string $message): string{
		// Busca algo tipo ABC123 o similar (4-32 chars)
		if(preg_match('/\b([A-Z0-9]{4,32})\b/i', $message, $m)){
			return (string)$m[1];
		}
		return '';
	}

	private function getReservaClientePorCodigo(int $clienteId, string $codigo): array|false{
		if(!$this->tablaExisteCached('reserva', self::$tablaReservaExiste)){
			return false;
		}
		if(!$this->tablaExisteCached('producto', self::$tablaProductoExiste)){
			return false;
		}
		try{
			$sql = "SELECT r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_estado,
						p.producto_nombre
					FROM reserva r
					INNER JOIN producto p ON p.producto_id = r.producto_id
					WHERE r.cliente_id = :cid AND r.reserva_codigo = :cod
					LIMIT 1";
			$stmt = $this->conectar()->prepare($sql);
			$stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
			$stmt->bindValue(':cod', $codigo);
			$stmt->execute();
			$row = $stmt->fetch();
			return $row ?: false;
		}catch(\Throwable $e){
			return false;
		}
	}
}
