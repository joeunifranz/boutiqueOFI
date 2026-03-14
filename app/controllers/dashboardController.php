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


		/*----------  Exportar reporte general del dashboard a PDF  ----------*/
		public function exportarDashboardPDF($yw=""){
			if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
				if(!headers_sent()){
					header('Location: '.APP_URL.'adminLogin/');
				}
				exit();
			}

			if(ob_get_length()){
				@ob_end_clean();
			}

			require_once __DIR__ . '/../pdf/DashboardReportPDF.php';

			$yw = $this->limpiarCadena((string)$yw);
			$ywSeleccionado = preg_match('/^[0-9]{6}$/', $yw) ? (int)$yw : 0;

			$totales = $this->obtenerTotales();
			$ingresosTotales = $this->obtenerIngresosTotales();

			$anio = (int)date('Y');
			$ventasPorMes = $this->obtenerVentasPorMes($anio);
			$productosMasVendidos = $this->obtenerProductosMasVendidosPorCategoria($anio, 8);
			$ultimasVentas = $this->obtenerUltimasVentas(8);
			$stockBajo = $this->obtenerStockBajo(10, 8);

			$semanasPeriodo = 8;
			if($ywSeleccionado > 0){
				$resumenSemanal = $this->obtenerCostoYGananciaNetaSemana($ywSeleccionado);
				$detalleProductosPeriodo = $this->obtenerDetalleProductosCostoYGananciaSemana($ywSeleccionado, 0);
				$tituloPeriodo = 'Semana seleccionada';
			}else{
				$resumenSemanal = $this->obtenerCostoYGananciaNetaPorSemana($semanasPeriodo);
				$detalleProductosPeriodo = $this->obtenerDetalleProductosCostoYGananciaPeriodo($semanasPeriodo, 0);
				$tituloPeriodo = 'Últimas '.$semanasPeriodo.' semanas';
			}

			$pdf = new \DashboardReportPDF('P','mm','A4');
			$pdf->AliasNbPages();
			$pdf->SetMargins(10, 12, 10);
			$pdf->SetAutoPageBreak(true, 15);
			$pdf->titulo = APP_NAME.' - Reporte General del Dashboard';
			$pdf->subtitulo = 'Generado: '.date('d/m/Y H:i:s').'  |  Año: '.$anio.'  |  Período: '.$tituloPeriodo;
			$pdf->AddPage();
			$lm = $pdf->getLeftMargin();
			$rm = $pdf->getRightMargin();
			$pageW = $pdf->getPageWidth();
			$usableW = $pageW - $lm - $rm;
			$printNote = function(string $text) use ($pdf, $usableW): void{
				$pdf->SetFont('Arial','',8);
				$pdf->SetTextColor(110,110,110);
				$pdf->MultiCell($usableW, 4, $pdf->encode($text), 0, 'L');
				$pdf->SetTextColor(0,0,0);
				$pdf->Ln(1);
			};

			$pdf->sectionTitle('Totales');
			$printNote('Conteo general de registros del sistema (cajas, reservas, usuarios, clientes, categorías, productos y ventas).');
			$pdf->drawStatBoxes([
				['label'=>'Cajas', 'value'=>(string)(int)($totales['cajas'] ?? 0)],
				['label'=>'Reservas', 'value'=>(string)(int)($totales['reservas'] ?? 0)],
				['label'=>'Usuarios', 'value'=>(string)(int)($totales['usuarios'] ?? 0)],
				['label'=>'Clientes', 'value'=>(string)(int)($totales['clientes'] ?? 0)],
				['label'=>'Categorías', 'value'=>(string)(int)($totales['categorias'] ?? 0)],
				['label'=>'Productos', 'value'=>(string)(int)($totales['productos'] ?? 0)],
				['label'=>'Ventas', 'value'=>(string)(int)($totales['ventas'] ?? 0)],
			], 3);

			$pdf->SetFont('Arial','B',10);
			$pdf->Cell(0, 6, $pdf->encode('Ingresos totales: Bs'.number_format((float)$ingresosTotales, 2)), 0, 1, 'L');
			$printNote('Suma histórica de las ventas registradas (no es un valor mensual, es acumulado).');
			$pdf->Ln(2);

			$pdf->sectionTitle('Ventas por mes ('.$anio.')');
			$printNote('Gráfico de barras que muestra el total vendido por cada mes del año seleccionado.');
			$pdf->drawBarChart(
				$lm,
				$pdf->GetY(),
				$usableW,
				55,
				['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
				array_values($ventasPorMes),
				[50, 115, 220]
			);
			$pdf->Ln(60);

			$pdf->sectionTitle('Más vendidos por categoría ('.$anio.')');
			$printNote('Ranking de categorías según unidades vendidas (suma de cantidades en los detalles de venta).');
			if(empty($productosMasVendidos)){
				$pdf->SetFont('Arial','',9);
				$pdf->SetTextColor(120,120,120);
				$pdf->Cell(0, 6, $pdf->encode('Sin datos suficientes para graficar.'), 0, 1, 'L');
				$pdf->SetTextColor(0,0,0);
			}else{
				$labels = array_map(fn($r) => (string)($r['categoria'] ?? ''), $productosMasVendidos);
				$vals = array_map(fn($r) => (float)($r['cantidad'] ?? 0), $productosMasVendidos);
				$y0 = $pdf->GetY();
				$hUsed = $pdf->drawHorizontalBars(
					$lm,
					$y0,
					$usableW,
					$labels,
					$vals,
					[0, 209, 178]
				);
				$pdf->Ln($hUsed + 2);
			}

			$pdf->AddPage();
			$pdf->sectionTitle('Costo elaboración vs ganancia neta ('.$tituloPeriodo.')');
			$printNote('Comparación por semana: costo de elaboración (amarillo) vs ganancia neta (verde). Si hay ganancia negativa, se marca en rojo.');
			if(empty($resumenSemanal['labels'])){
				$pdf->SetFont('Arial','',9);
				$pdf->SetTextColor(120,120,120);
				$pdf->Cell(0, 6, $pdf->encode('Sin datos suficientes para este período.'), 0, 1, 'L');
				$pdf->SetTextColor(0,0,0);
			}else{
				$pdf->SetFont('Arial','',8);
				$pdf->Cell(0, 5, $pdf->encode('Leyenda: Costo (amarillo) | Ganancia neta (verde) | Ganancia negativa (rojo)'), 0, 1, 'L');
				$pdf->drawGroupedBarChart(
					$lm,
					$pdf->GetY(),
					$usableW,
					70,
					(array)($resumenSemanal['labels'] ?? []),
					(array)($resumenSemanal['costos'] ?? []),
					(array)($resumenSemanal['ganancias'] ?? [])
				);
				$pdf->Ln(75);
			}

			$pdf->sectionTitle('Alertas de stock bajo');
			$printNote('Lista de productos cuyo stock total está por debajo del umbral configurado en el dashboard (por defecto: 10).');
			if(empty($stockBajo)){
				$pdf->SetFont('Arial','',9);
				$pdf->SetTextColor(120,120,120);
				$pdf->Cell(0, 6, $pdf->encode('No hay productos con stock bajo.'), 0, 1, 'L');
				$pdf->SetTextColor(0,0,0);
			}else{
				$rows = [];
				foreach($stockBajo as $r){
					$rows[] = [
						(string)($r['nombre'] ?? ''),
						(string)(int)($r['stock'] ?? 0)
					];
				}
				$pdf->drawSimpleTable($lm, $pdf->GetY(), ['Producto','Stock'], $rows, [140, 40]);
				$pdf->Ln(2);
			}

			$pdf->sectionTitle('Últimas ventas');
			$printNote('Muestra un resumen de las últimas ventas registradas (fecha, cliente, producto y monto).');
			if(empty($ultimasVentas)){
				$pdf->SetFont('Arial','',9);
				$pdf->SetTextColor(120,120,120);
				$pdf->Cell(0, 6, $pdf->encode('Sin ventas registradas.'), 0, 1, 'L');
				$pdf->SetTextColor(0,0,0);
			}else{
				$rows = [];
				foreach($ultimasVentas as $r){
					$monto = (float)($r['monto'] ?? 0);
					$rows[] = [
						(string)($r['fecha'] ?? ''),
						(string)($r['cliente'] ?? ''),
						(string)($r['producto'] ?? ''),
						'Bs'.number_format($monto, 2)
					];
				}
				$pdf->drawSimpleTable($lm, $pdf->GetY(), ['Fecha','Cliente','Producto','Monto'], $rows, [28, 58, 78, 26]);
			}

			$pdf->titulo = APP_NAME.' - Reporte General del Dashboard';
			$pdf->subtitulo = 'Productos vendidos ('.$tituloPeriodo.')  |  Generado: '.date('d/m/Y H:i:s');
			$pdf->setTable(
				['Producto','Unidades','Ingresos','Costo elaboración','Ganancia neta'],
				[80, 20, 30, 30, 30],
				['L','R','R','R','R']
			);
			$pdf->AddPage();
			$pdf->SetFont('Arial','',8);
			$printNote('Tabla detallada por producto para el período: Unidades (cantidad vendida), Ingresos (precio venta*cantidad), Costo (precio compra*cantidad) y Ganancia neta (Ingresos - Costo).');

			if(empty($detalleProductosPeriodo)){
				$pdf->SetTextColor(120,120,120);
				$pdf->Cell(0, 6, $pdf->encode('Sin datos de productos para este período.'), 0, 1, 'L');
				$pdf->SetTextColor(0,0,0);
			}else{
				$totalUnidadesProductos = 0;
				$totalIngresosProductos = 0.0;
				$totalCostoProductos = 0.0;
				$totalGananciaProductos = 0.0;

				$fill = false;
				foreach($detalleProductosPeriodo as $row){
					$unidades = (int)($row['unidades'] ?? 0);
					$ingresos = (float)($row['ingresos'] ?? 0);
					$costo = (float)($row['costo'] ?? 0);
					$ganancia = (float)($row['ganancia'] ?? ($ingresos - $costo));

					$totalUnidadesProductos += $unidades;
					$totalIngresosProductos += $ingresos;
					$totalCostoProductos += $costo;
					$totalGananciaProductos += $ganancia;

					$pdf->addRow([
						(string)($row['producto'] ?? ''),
						(string)$unidades,
						'Bs'.number_format($ingresos, 2),
						'Bs'.number_format($costo, 2),
						'Bs'.number_format($ganancia, 2)
					], $fill);
					$fill = !$fill;
				}

				$pdf->SetFont('Arial','B',8);
				$pdf->addRow([
					'TOTAL',
					(string)$totalUnidadesProductos,
					'Bs'.number_format($totalIngresosProductos, 2),
					'Bs'.number_format($totalCostoProductos, 2),
					'Bs'.number_format($totalGananciaProductos, 2)
				], true);
			}

			$pdf->Output('D', 'reporte_dashboard_'.date('Ymd').'.pdf');
			exit();
		}
	}
