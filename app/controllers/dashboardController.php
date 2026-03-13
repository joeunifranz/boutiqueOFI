<?php

	namespace app\controllers;
	use app\models\mainModel;
	use \PDO;

	class dashboardController extends mainModel{

		private function fetchOneValue($sql, $default=0){
			try{
				$stmt = $this->conectar()->prepare($sql);
				$stmt->execute();
				$value = $stmt->fetchColumn();
				if($value===false || $value===null){
					return $default;
				}
				return $value;
			}catch(\Exception $e){
				return $default;
			}
		}

		private function fetchAllAssoc($sql, $params=[]){
			try{
				$stmt = $this->conectar()->prepare($sql);
				foreach($params as $k=>$v){
					$stmt->bindValue($k, $v);
				}
				$stmt->execute();
				return $stmt->fetchAll(PDO::FETCH_ASSOC);
			}catch(\Exception $e){
				return [];
			}
		}

		public function obtenerTotales(){
			return [
				'cajas'      => (int)$this->fetchOneValue("SELECT COUNT(*) FROM caja", 0),
				'usuarios'   => (int)$this->fetchOneValue("SELECT COUNT(*) FROM usuario", 0),
				'clientes'   => (int)$this->fetchOneValue("SELECT COUNT(*) FROM cliente", 0),
				'categorias' => (int)$this->fetchOneValue("SELECT COUNT(*) FROM categoria", 0),
				'productos'  => (int)$this->fetchOneValue("SELECT COUNT(*) FROM producto", 0),
				'ventas'     => (int)$this->fetchOneValue("SELECT COUNT(*) FROM venta", 0),
				'reservas'   => (int)$this->fetchOneValue("SELECT COUNT(*) FROM reserva", 0)
			];
		}

		public function obtenerIngresosTotales(){
			return (float)$this->fetchOneValue("SELECT COALESCE(SUM(venta_total),0) FROM venta", 0);
		}

		public function obtenerVentasPorMes($anio=null){
			if($anio===null){
				$anio = (int)date('Y');
			}

			$ventasPorMes = array_fill(1, 12, 0.0);
			$rows = $this->fetchAllAssoc(
				"SELECT MONTH(venta_fecha) AS mes, COALESCE(SUM(venta_total),0) AS total\n\t\t\t\t FROM venta\n\t\t\t\t WHERE YEAR(venta_fecha)=:anio\n\t\t\t\t GROUP BY MONTH(venta_fecha)",
				[':anio'=>$anio]
			);

			foreach($rows as $row){
				$mes = (int)($row['mes'] ?? 0);
				if($mes>=1 && $mes<=12){
					$ventasPorMes[$mes] = (float)($row['total'] ?? 0);
				}
			}

			return $ventasPorMes;
		}

		public function obtenerProductosMasVendidosPorCategoria($anio=null, $limite=8){
			if($anio===null){
				$anio = (int)date('Y');
			}
			$limite = (int)$limite;
			if($limite<=0){
				$limite = 8;
			}

			$sql = "
				SELECT c.categoria_nombre AS categoria, COALESCE(SUM(vd.venta_detalle_cantidad),0) AS cantidad
				FROM venta_detalle vd
				JOIN producto p ON vd.producto_id = p.producto_id
				JOIN categoria c ON p.categoria_id = c.categoria_id
				JOIN venta v ON v.venta_codigo = vd.venta_codigo
				WHERE YEAR(v.venta_fecha)=:anio
				GROUP BY c.categoria_nombre
				ORDER BY cantidad DESC
				LIMIT {$limite}
			";

			return $this->fetchAllAssoc($sql, [':anio'=>$anio]);
		}

		public function obtenerUltimasVentas($limite=8){
			$limite = (int)$limite;
			if($limite<=0){
				$limite = 8;
			}

			$sql = "
				SELECT
					v.venta_fecha AS fecha,
					CONCAT(cl.cliente_nombre,' ',cl.cliente_apellido) AS cliente,
					p.producto_nombre AS producto,
					(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad) AS monto
				FROM venta v
				JOIN cliente cl ON v.cliente_id = cl.cliente_id
				JOIN venta_detalle vd ON v.venta_codigo = vd.venta_codigo
				JOIN producto p ON vd.producto_id = p.producto_id
				ORDER BY v.venta_fecha DESC, v.venta_id DESC
				LIMIT {$limite}
			";

			return $this->fetchAllAssoc($sql);
		}

		public function obtenerStockBajo($umbral=10, $limite=8){
			$umbral = (int)$umbral;
			$limite = (int)$limite;
			if($limite<=0){
				$limite = 8;
			}

			$sql = "
				SELECT producto_nombre AS nombre, producto_stock_total AS stock
				FROM producto
				WHERE producto_stock_total <= :umbral
				ORDER BY producto_stock_total ASC
				LIMIT {$limite}
			";

			return $this->fetchAllAssoc($sql, [':umbral'=>$umbral]);
		}

		public function obtenerCostoYGananciaNetaPorSemana($semanas=8){
			$semanas = (int)$semanas;
			if($semanas<=0){
				$semanas = 8;
			}

			$sql = "
				SELECT
					YEARWEEK(v.venta_fecha, 1) AS yw,
					COALESCE(SUM(vd.venta_detalle_precio_compra * vd.venta_detalle_cantidad),0) AS costo,
					COALESCE(SUM(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad),0) AS ingresos
				FROM venta v
				JOIN venta_detalle vd ON v.venta_codigo = vd.venta_codigo
				WHERE v.venta_fecha >= DATE_SUB(CURDATE(), INTERVAL :semanas WEEK)
				GROUP BY YEARWEEK(v.venta_fecha, 1)
				ORDER BY YEARWEEK(v.venta_fecha, 1) ASC
			";

			$rows = $this->fetchAllAssoc($sql, [':semanas'=>$semanas]);
			$labels = [];
			$costos = [];
			$ganancias = [];

			foreach($rows as $row){
				$yw = (int)($row['yw'] ?? 0);
				$anio = (int)floor($yw/100);
				$semana = (int)($yw % 100);
				$labels[] = ($semana>0 && $anio>0) ? ("Sem ".$semana." ".$anio) : (string)$yw;

				$costo = (float)($row['costo'] ?? 0);
				$ingresos = (float)($row['ingresos'] ?? 0);
				$ganancia = $ingresos - $costo;

				$costos[] = $costo;
				$ganancias[] = $ganancia;
			}

			return [
				'labels' => $labels,
				'costos' => $costos,
				'ganancias' => $ganancias
			];
		}

		public function obtenerCostoYGananciaNetaSemana($yearweek){
			$yearweek = (int)$yearweek;
			if($yearweek<=0){
				return ['labels'=>[], 'costos'=>[], 'ganancias'=>[]];
			}

			$sql = "
				SELECT
					YEARWEEK(v.venta_fecha, 1) AS yw,
					COALESCE(SUM(vd.venta_detalle_precio_compra * vd.venta_detalle_cantidad),0) AS costo,
					COALESCE(SUM(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad),0) AS ingresos
				FROM venta v
				JOIN venta_detalle vd ON v.venta_codigo = vd.venta_codigo
				WHERE YEARWEEK(v.venta_fecha, 1) = :yw
				GROUP BY YEARWEEK(v.venta_fecha, 1)
				LIMIT 1
			";

			$rows = $this->fetchAllAssoc($sql, [':yw'=>$yearweek]);
			if(empty($rows)){
				$anio = (int)floor($yearweek/100);
				$semana = (int)($yearweek % 100);
				$label = ($semana>0 && $anio>0) ? ("Sem ".$semana." ".$anio) : (string)$yearweek;
				return ['labels'=>[$label], 'costos'=>[0.0], 'ganancias'=>[0.0]];
			}

			$row = $rows[0];
			$costo = (float)($row['costo'] ?? 0);
			$ingresos = (float)($row['ingresos'] ?? 0);
			$ganancia = $ingresos - $costo;

			$anio = (int)floor($yearweek/100);
			$semana = (int)($yearweek % 100);
			$label = ($semana>0 && $anio>0) ? ("Sem ".$semana." ".$anio) : (string)$yearweek;

			return [
				'labels' => [$label],
				'costos' => [$costo],
				'ganancias' => [$ganancia]
			];
		}

		public function obtenerDetalleProductosCostoYGananciaPeriodo($semanas=8, $limite=20){
			$semanas = (int)$semanas;
			if($semanas<=0){
				$semanas = 8;
			}
			$limite = (int)$limite; // <=0 significa: sin límite

			$limitSql = "";
			if($limite > 0){
				$limitSql = "\n\t\t\t\tLIMIT {$limite}";
			}

			$sql = "
				SELECT
					p.producto_id,
					p.producto_nombre AS producto,
					COALESCE(SUM(vd.venta_detalle_cantidad),0) AS unidades,
					COALESCE(SUM(vd.venta_detalle_precio_compra * vd.venta_detalle_cantidad),0) AS costo,
					COALESCE(SUM(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad),0) AS ingresos
				FROM venta v
				JOIN venta_detalle vd ON v.venta_codigo = vd.venta_codigo
				JOIN producto p ON vd.producto_id = p.producto_id
				WHERE v.venta_fecha >= DATE_SUB(CURDATE(), INTERVAL :semanas WEEK)
				GROUP BY p.producto_id, p.producto_nombre
				ORDER BY (COALESCE(SUM(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad),0) - COALESCE(SUM(vd.venta_detalle_precio_compra * vd.venta_detalle_cantidad),0)) DESC
				{$limitSql}
			";

			$rows = $this->fetchAllAssoc($sql, [':semanas'=>$semanas]);
			foreach($rows as &$row){
				$costo = (float)($row['costo'] ?? 0);
				$ingresos = (float)($row['ingresos'] ?? 0);
				$row['ganancia'] = $ingresos - $costo;
			}
			unset($row);

			return $rows;
		}

		public function obtenerDetalleProductosCostoYGananciaSemana($yearweek, $limite=0){
			$yearweek = (int)$yearweek;
			if($yearweek<=0){
				return [];
			}

			$limite = (int)$limite; // <=0 sin límite
			$limitSql = "";
			if($limite > 0){
				$limitSql = "\n\t\t\t\tLIMIT {$limite}";
			}

			$sql = "
				SELECT
					p.producto_id,
					p.producto_nombre AS producto,
					COALESCE(SUM(vd.venta_detalle_cantidad),0) AS unidades,
					COALESCE(SUM(vd.venta_detalle_precio_compra * vd.venta_detalle_cantidad),0) AS costo,
					COALESCE(SUM(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad),0) AS ingresos
				FROM venta v
				JOIN venta_detalle vd ON v.venta_codigo = vd.venta_codigo
				JOIN producto p ON vd.producto_id = p.producto_id
				WHERE YEARWEEK(v.venta_fecha, 1) = :yw
				GROUP BY p.producto_id, p.producto_nombre
				ORDER BY (COALESCE(SUM(vd.venta_detalle_precio_venta * vd.venta_detalle_cantidad),0) - COALESCE(SUM(vd.venta_detalle_precio_compra * vd.venta_detalle_cantidad),0)) DESC
				{$limitSql}
			";

			$rows = $this->fetchAllAssoc($sql, [':yw'=>$yearweek]);
			foreach($rows as &$row){
				$costo = (float)($row['costo'] ?? 0);
				$ingresos = (float)($row['ingresos'] ?? 0);
				$row['ganancia'] = $ingresos - $costo;
			}
			unset($row);

			return $rows;
		}
	}
