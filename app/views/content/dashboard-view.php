<?php
	use app\controllers\dashboardController;

	$esAdmin = $insLogin->sessionEsAdmin();
	if(!$esAdmin){
		if(headers_sent()){
			echo "<script> window.location.href='".APP_URL."saleNew/'; </script>";
		}else{
			header("Location: ".APP_URL."saleNew/");
		}
		exit();
	}

	$insDash = new dashboardController();
	$totales = $insDash->obtenerTotales();
	$ingresosTotales = $insDash->obtenerIngresosTotales();
	$ingresosFormateados = "Bs".number_format((float)$ingresosTotales, 2);

	$anio = (int)date('Y');
	$ventasPorMes = $insDash->obtenerVentasPorMes($anio);
	$productosMasVendidos = $insDash->obtenerProductosMasVendidosPorCategoria($anio, 8);
	$ultimasVentas = $insDash->obtenerUltimasVentas(8);
	$stockBajo = $insDash->obtenerStockBajo(10, 8);

	$semanasPeriodo = 8;
	$ywSeleccionado = $insLogin->limpiarCadena($_GET['yw'] ?? "");
	$ywSeleccionado = preg_match('/^[0-9]{6}$/', $ywSeleccionado) ? (int)$ywSeleccionado : 0;
	$exportUrl = APP_URL."exportarDashboard/";
	if($ywSeleccionado>0){
		$exportUrl .= "?yw=".urlencode((string)$ywSeleccionado);
	}

	if($ywSeleccionado>0){
		$resumenSemanal = $insDash->obtenerCostoYGananciaNetaSemana($ywSeleccionado);
		$detalleProductosPeriodo = $insDash->obtenerDetalleProductosCostoYGananciaSemana($ywSeleccionado, 0);
		$tituloPeriodo = "Semana seleccionada";
	}else{
		$resumenSemanal = $insDash->obtenerCostoYGananciaNetaPorSemana($semanasPeriodo);
		$detalleProductosPeriodo = $insDash->obtenerDetalleProductosCostoYGananciaPeriodo($semanasPeriodo, 0);
		$tituloPeriodo = "Últimas ".$semanasPeriodo." semanas";
	}

	$totalCostoPeriodo = array_sum($resumenSemanal['costos'] ?? []);
	$totalGananciaPeriodo = array_sum($resumenSemanal['ganancias'] ?? []);
	$totalIngresosPeriodo = (float)$totalCostoPeriodo + (float)$totalGananciaPeriodo;

	$totalUnidadesProductos = 0;
	$totalIngresosProductos = 0.0;
	$totalCostoProductos = 0.0;
	$totalGananciaProductos = 0.0;
	foreach($detalleProductosPeriodo as $r){
		$totalUnidadesProductos += (int)($r['unidades'] ?? 0);
		$totalIngresosProductos += (float)($r['ingresos'] ?? 0);
		$totalCostoProductos += (float)($r['costo'] ?? 0);
		$totalGananciaProductos += (float)($r['ganancia'] ?? 0);
	}

	// Opciones de semanas (últimas 26 semanas ISO)
	$semanasOpciones = [];
	$dt = new DateTimeImmutable('now');
	$dt = $dt->modify('monday this week');
	for($i=0; $i<26; $i++){
		$isoYear = (int)$dt->format('o');
		$isoWeek = (int)$dt->format('W');
		$yw = ($isoYear*100) + $isoWeek;
		$semanasOpciones[] = [
			'value' => (string)$yw,
			'label' => 'Sem '.$isoWeek.' '.$isoYear
		];
		$dt = $dt->modify('-7 days');
	}
?>

<div class="container is-fluid">
	<div class="level">
		<div class="level-left">
			<div>
				<h1 class="title">Dashboard</h1>
				<p class="subtitle">Resumen administrativo de Boutique Dorita</p>
			</div>
		</div>
		<div class="level-right">
			<a href="<?php echo htmlspecialchars($exportUrl,ENT_QUOTES,'UTF-8'); ?>" class="button is-small is-link is-rounded" title="Descargar reporte general del dashboard en PDF">
				<span class="icon"><i class="fas fa-file-pdf"></i></span>
				<span>Reporte PDF</span>
			</a>
			<a href="<?php echo APP_URL; ?>logList/" class="button is-small is-light is-rounded" title="Ver logs de acceso">
				<span class="icon"><i class="fas fa-eye"></i></span>
				<span>Logs</span>
			</a>
		</div>
	</div>

	<div class="columns is-multiline">
		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-cash-register fa-fw"></i> Cajas</p>
				<p class="title"><?php echo (int)$totales['cajas']; ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>cashierList/">Ver detalle</a>
			</div>
		</div>
		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-clipboard-check fa-fw"></i> Reservas</p>
				<p class="title"><?php echo (int)($totales['reservas'] ?? 0); ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>reservaList/">Ver detalle</a>
			</div>
		</div>
		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-users fa-fw"></i> Usuarios</p>
				<p class="title"><?php echo (int)$totales['usuarios']; ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>userList/">Ver detalle</a>
			</div>
		</div>
		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-address-book fa-fw"></i> Clientes</p>
				<p class="title"><?php echo (int)$totales['clientes']; ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>clientList/">Ver detalle</a>
			</div>
		</div>
		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-tags fa-fw"></i> Categorías</p>
				<p class="title"><?php echo (int)$totales['categorias']; ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>categoryList/">Ver detalle</a>
			</div>
		</div>

		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-cubes fa-fw"></i> Productos</p>
				<p class="title"><?php echo (int)$totales['productos']; ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>productList/">Ver detalle</a>
			</div>
		</div>
		<div class="column is-3">
			<div class="box">
				<p class="heading"><i class="fas fa-shopping-cart fa-fw"></i> Ventas</p>
				<p class="title"><?php echo (int)$totales['ventas']; ?></p>
				<a class="is-size-7" href="<?php echo APP_URL; ?>saleList/">Ver detalle</a>
			</div>
		</div>
		<div class="column is-6">
			<div class="box">
				<p class="heading"><i class="fas fa-coins fa-fw"></i> Ingresos totales</p>
				<p class="title"><?php echo $ingresosFormateados; ?></p>
				<p class="is-size-7 has-text-grey">Acumulado histórico</p>
			</div>
		</div>
	</div>

	<div class="columns is-multiline">
		<div class="column is-8">
			<div class="box">
				<div class="level">
					<div class="level-left">
						<p class="title is-5">Ventas por mes (<?php echo $anio; ?>)</p>
					</div>
				</div>
				<canvas id="ventasMesChart" height="90"></canvas>
			</div>
		</div>
		<div class="column is-4">
			<div class="box">
				<p class="title is-5">Más vendidos por categoría (<?php echo $anio; ?>)</p>
				<canvas id="productosChart" height="200"></canvas>
				<?php if(empty($productosMasVendidos)){ ?>
					<p class="has-text-grey is-size-7 mt-2">Aún no hay datos suficientes para graficar.</p>
				<?php } ?>
			</div>
		</div>

		<div class="column is-12">
			<div class="box">
				<div class="level">
					<div class="level-left">
						<p class="title is-5">Costo de elaboración vs ganancia neta (<?php echo htmlspecialchars($tituloPeriodo,ENT_QUOTES,'UTF-8'); ?>)</p>
					</div>
					<div class="level-right">
						<form method="GET" action="<?php echo APP_URL; ?>dashboard/" class="mr-3">
							<div class="field has-addons">
								<div class="control">
									<div class="select is-small">
										<select name="yw">
											<option value="">(Últimas <?php echo (int)$semanasPeriodo; ?> semanas)</option>
											<?php foreach($semanasOpciones as $opt){
												$sel = ($ywSeleccionado>0 && (string)$ywSeleccionado===$opt['value']) ? 'selected' : '';
												echo '<option value="'.htmlspecialchars($opt['value'],ENT_QUOTES,'UTF-8').'" '.$sel.'>'.htmlspecialchars($opt['label'],ENT_QUOTES,'UTF-8').'</option>';
											} ?>
										</select>
									</div>
								</div>
								<div class="control">
									<button class="button is-small is-link" type="submit">Ver</button>
								</div>
							</div>
						</form>

						<div class="buttons are-small">
							<span class="button is-static">Ingresos: <strong class="ml-1">Bs<?php echo number_format((float)$totalIngresosPeriodo, 2); ?></strong></span>
							<span class="button is-static">Costo: <strong class="ml-1">Bs<?php echo number_format((float)$totalCostoPeriodo, 2); ?></strong></span>
							<span class="button is-static">Ganancia neta: <strong class="ml-1">Bs<?php echo number_format((float)$totalGananciaPeriodo, 2); ?></strong></span>
						</div>
					</div>
				</div>
				<canvas id="gananciaSemanalChart" height="80"></canvas>
				<?php if(empty($resumenSemanal['labels'])){ ?>
					<p class="has-text-grey is-size-7 mt-2">Aún no hay ventas suficientes para calcular semanas.</p>
				<?php } ?>
			</div>
		</div>

		<div class="column is-12">
			<div class="box">
				<p class="title is-5">Productos vendidos (composición de costo y ganancia)</p>
				<p class="subtitle is-7 has-text-grey">Todas las ventas agrupadas por producto en las últimas <?php echo (int)$semanasPeriodo; ?> semanas</p>
				<div class="table-container">
					<table class="table is-fullwidth is-striped is-hoverable is-size-7">
						<thead>
							<tr>
								<th>Producto</th>
								<th class="has-text-right">Unidades</th>
								<th class="has-text-right">Ingresos</th>
								<th class="has-text-right">Costo elaboración</th>
								<th class="has-text-right">Ganancia neta</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($detalleProductosPeriodo)){ ?>
								<tr><td colspan="5" class="has-text-centered has-text-grey">Sin datos de productos para este período.</td></tr>
							<?php }else{ foreach($detalleProductosPeriodo as $row){ ?>
								<tr>
									<td><?php echo htmlspecialchars($row['producto'] ?? '',ENT_QUOTES,'UTF-8'); ?></td>
									<td class="has-text-right"><?php echo (int)($row['unidades'] ?? 0); ?></td>
									<td class="has-text-right">Bs<?php echo number_format((float)($row['ingresos'] ?? 0), 2); ?></td>
									<td class="has-text-right">Bs<?php echo number_format((float)($row['costo'] ?? 0), 2); ?></td>
									<td class="has-text-right"><strong>Bs<?php echo number_format((float)($row['ganancia'] ?? 0), 2); ?></strong></td>
								</tr>
							<?php } } ?>
						</tbody>
						<?php if(!empty($detalleProductosPeriodo)){ ?>
						<tfoot>
							<tr>
								<th class="has-text-right">TOTAL</th>
								<th class="has-text-right"><?php echo (int)$totalUnidadesProductos; ?></th>
								<th class="has-text-right">Bs<?php echo number_format((float)$totalIngresosProductos, 2); ?></th>
								<th class="has-text-right">Bs<?php echo number_format((float)$totalCostoProductos, 2); ?></th>
								<th class="has-text-right"><strong>Bs<?php echo number_format((float)$totalGananciaProductos, 2); ?></strong></th>
							</tr>
						</tfoot>
						<?php } ?>
					</table>
				</div>
			</div>
		</div>

		<div class="column is-8">
			<div class="box">
				<p class="title is-5">Últimas ventas</p>
				<div class="table-container">
					<table class="table is-fullwidth is-striped is-hoverable is-size-7">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Cliente</th>
								<th>Producto</th>
								<th class="has-text-right">Monto</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($ultimasVentas)){ ?>
								<tr><td colspan="4" class="has-text-centered has-text-grey">Sin ventas registradas.</td></tr>
							<?php }else{ foreach($ultimasVentas as $row){ ?>
								<tr>
									<td><?php echo htmlspecialchars($row['fecha'] ?? '',ENT_QUOTES,'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row['cliente'] ?? '',ENT_QUOTES,'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row['producto'] ?? '',ENT_QUOTES,'UTF-8'); ?></td>
									<td class="has-text-right">Bs<?php echo number_format((float)($row['monto'] ?? 0), 2); ?></td>
								</tr>
							<?php } } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="column is-4">
			<div class="box">
				<p class="title is-5">Alertas de stock bajo</p>
				<?php if(empty($stockBajo)){ ?>
					<p class="has-text-grey is-size-7">No hay productos con stock bajo.</p>
				<?php }else{ foreach($stockBajo as $row){
					$stock = (int)($row['stock'] ?? 0);
					$clase = ($stock <= 2) ? 'is-danger' : (($stock <= 5) ? 'is-warning' : 'is-light');
				?>
					<div class="notification <?php echo $clase; ?> is-light p-3 mb-2">
						<strong><?php echo htmlspecialchars($row['nombre'] ?? '',ENT_QUOTES,'UTF-8'); ?></strong><br>
						<span class="is-size-7">Stock: <?php echo $stock; ?> unidades</span>
					</div>
				<?php } } ?>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
	const ventasMesCtx = document.getElementById('ventasMesChart');
	if (ventasMesCtx) {
		new Chart(ventasMesCtx, {
			type: 'bar',
			data: {
				labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
				datasets: [{
					label: 'Ventas',
					data: <?php echo json_encode(array_values($ventasPorMes)); ?>,
					backgroundColor: '#3273dc'
				}]
			},
			options: {
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true } }
			}
		});
	}

	const productosChartCtx = document.getElementById('productosChart');
	if (productosChartCtx) {
		new Chart(productosChartCtx, {
			type: 'doughnut',
			data: {
				labels: <?php echo json_encode(array_column($productosMasVendidos, 'categoria')); ?>,
				datasets: [{
					data: <?php echo json_encode(array_map('floatval', array_column($productosMasVendidos, 'cantidad'))); ?>,
					backgroundColor: ['#00d1b2','#3273dc','#ff3860','#ffdd57','#7957d5','#23d160','#363636','#b86bff']
				}]
			},
			options: {
				plugins: { legend: { position: 'bottom' } }
			}
		});
	}

	const gananciaSemanalCtx = document.getElementById('gananciaSemanalChart');
	if (gananciaSemanalCtx) {
		new Chart(gananciaSemanalCtx, {
			type: 'bar',
			data: {
				labels: <?php echo json_encode($resumenSemanal['labels'] ?? []); ?>,
				datasets: [
					{
						label: 'Costo elaboración',
						data: <?php echo json_encode($resumenSemanal['costos'] ?? []); ?>,
						backgroundColor: '#ffdd57'
					},
					{
						label: 'Ganancia neta',
						data: <?php echo json_encode($resumenSemanal['ganancias'] ?? []); ?>,
						backgroundColor: '#23d160'
					}
				]
			},
			options: {
				responsive: true,
				plugins: { legend: { position: 'bottom' } },
				scales: { y: { beginAtZero: true } }
			}
		});
	}
</script>

