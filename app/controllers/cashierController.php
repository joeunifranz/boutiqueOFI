<?php

	namespace app\controllers;
	use app\models\mainModel;

	class cashierController extends mainModel{

		/*----------  Controlador registrar caja  ----------*/
		public function registrarCajaControlador(){

			# Almacenando datos#
		    $numero=$this->limpiarCadena($_POST['caja_numero']);
		    $nombre=$this->limpiarCadena($_POST['caja_nombre']);
		    $efectivo=$this->limpiarCadena($_POST['caja_efectivo']);

		    # Verificando campos obligatorios #
		    if($numero=="" || $nombre=="" || $efectivo==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Verificando integridad de los datos #
		    if($this->verificarDatos("[0-9]{1,5}",$numero)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NUMERO DE CAJA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ:# ]{3,70}",$nombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE DE CAJA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9.]{1,25}",$efectivo)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El EFECTIVO DE CAJA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Comprobando numero de caja #
		    $check_numero=$this->ejecutarConsulta("SELECT caja_numero FROM caja WHERE caja_numero='$numero'");
		    if($check_numero->rowCount()>0){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El número de caja ingresado ya se encuentra registrado en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Comprobando nombre de caja #
		    $check_nombre=$this->ejecutarConsulta("SELECT caja_nombre FROM caja WHERE caja_nombre='$nombre'");
		    if($check_nombre->rowCount()>0){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El nombre o código de caja ingresado ya se encuentra registrado en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Comprobando que el efectivo sea mayor o igual a 0 #
			$efectivo=number_format($efectivo,2,'.','');
			if($efectivo<0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No puedes colocar una cantidad de efectivo menor a 0",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}


			$caja_datos_reg=[
				[
					"campo_nombre"=>"caja_numero",
					"campo_marcador"=>":Numero",
					"campo_valor"=>$numero
				],
				[
					"campo_nombre"=>"caja_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"caja_efectivo",
					"campo_marcador"=>":Efectivo",
					"campo_valor"=>$efectivo
				]
			];

			$registrar_caja=$this->guardarDatos("caja",$caja_datos_reg);

			if($registrar_caja->rowCount()==1){
				$this->registrarLogAccion("Alta de caja: ".$nombre." #".$numero);
				$alerta=[
					"tipo"=>"limpiar",
					"titulo"=>"Caja registrada",
					"texto"=>"La caja ".$nombre." #".$numero." se registro con exito",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No se pudo registrar la caja, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}


		/*----------  Controlador listar cajas  ----------*/
		public function listarCajaControlador($pagina,$registros,$url,$busqueda){

			$pagina=$this->limpiarCadena($pagina);
			$registros=$this->limpiarCadena($registros);

			$url=$this->limpiarCadena($url);
			$url=APP_URL.$url."/";

			$busqueda=$this->limpiarCadena($busqueda);
			$tabla="";

			$pagina = (isset($pagina) && $pagina>0) ? (int) $pagina : 1;
			$inicio = ($pagina>0) ? (($pagina * $registros)-$registros) : 0;

			if(isset($busqueda) && $busqueda!=""){

				$consulta_datos="SELECT * FROM caja WHERE caja_numero LIKE '%$busqueda%' OR caja_nombre LIKE '%$busqueda%' ORDER BY caja_numero ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(caja_id) FROM caja WHERE caja_numero LIKE '%$busqueda%' OR caja_nombre LIKE '%$busqueda%'";

			}else{

				$consulta_datos="SELECT * FROM caja ORDER BY caja_numero ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(caja_id) FROM caja";

			}

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();

			$total = $this->ejecutarConsulta($consulta_total);
			$total = (int) $total->fetchColumn();

			$numeroPaginas =ceil($total/$registros);

			$tabla.='
		        <div class="table-container">
		        <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
		            <thead>
		                <tr>
		                    <th class="has-text-centered">Numero</th>
		                    <th class="has-text-centered">Nombre</th>
		                    <th class="has-text-centered">Efectivo</th>
		                    <th class="has-text-centered">Entregar</th>
		                    <th class="has-text-centered">Actualizar</th>
		                    <th class="has-text-centered">Eliminar</th>
		                </tr>
		            </thead>
		            <tbody>
		    ';

		    if($total>=1 && $pagina<=$numeroPaginas){
				$contador=$inicio+1;
				$pag_inicio=$inicio+1;
				foreach($datos as $rows){
					$btnEntregar = '<span class="has-text-grey">-</span>';
					$esAdmin = $this->sessionEsAdmin();
					$cajaId = (int)($rows['caja_id'] ?? 0);
					if($esAdmin && ($cajaId===2 || $cajaId===3)){
						$btnEntregar = '
							<form class="FormularioAjax" action="'.APP_URL.'app/ajax/cajaAjax.php" method="POST" autocomplete="off" style="display:inline-block;">
								<input type="hidden" name="modulo_caja" value="entregar">
								<input type="hidden" name="caja_id" value="'.$cajaId.'">
								<button type="submit" class="button is-warning is-light is-rounded is-small" title="Transferir efectivo a la Caja Principal">
									<i class="fas fa-hand-holding-usd"></i> &nbsp; Entregar caja
								</button>
							</form>
						';
					}
					$tabla.='
						<tr class="has-text-centered" >
							<td>'.$rows['caja_numero'].'</td>
							<td>'.$rows['caja_nombre'].'</td>
							<td>'.$rows['caja_efectivo'].'</td>
							<td>'.$btnEntregar.'</td>
			                <td>
			                    <a href="'.APP_URL.'cashierUpdate/'.$rows['caja_id'].'/" class="button is-success is-rounded is-small">
			                    	<i class="fas fa-sync fa-fw"></i>
			                    </a>
			                </td>
			                <td>
			                	<form class="FormularioAjax" action="'.APP_URL.'app/ajax/cajaAjax.php" method="POST" autocomplete="off" >

			                		<input type="hidden" name="modulo_caja" value="eliminar">
			                		<input type="hidden" name="caja_id" value="'.$rows['caja_id'].'">

			                    	<button type="submit" class="button is-danger is-rounded is-small">
			                    		<i class="far fa-trash-alt fa-fw"></i>
			                    	</button>
			                    </form>
			                </td>
						</tr>
					';
					$contador++;
				}
				$pag_final=$contador-1;
			}else{
				if($total>=1){
					$tabla.='
						<tr class="has-text-centered" >
			                <td colspan="6">
			                    <a href="'.$url.'1/" class="button is-link is-rounded is-small mt-4 mb-4">
			                        Haga clic acá para recargar el listado
			                    </a>
			                </td>
			            </tr>
					';
				}else{
					$tabla.='
						<tr class="has-text-centered" >
			                <td colspan="6">
			                    No hay registros en el sistema
			                </td>
			            </tr>
					';
				}
			}

			$tabla.='</tbody></table></div>';

			### Paginacion ###
			if($total>0 && $pagina<=$numeroPaginas){
				$tabla.='<p class="has-text-right">Mostrando cajas <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';

				$tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
			}

			return $tabla;
		}


		/*----------  Exportar cajas a PDF  ----------*/
		public function exportarCajasPDF($busqueda=""){
			// Requiere sesión admin
			if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
				if(!headers_sent()){
					header('Location: '.APP_URL.'adminLogin/');
				}
				exit();
			}

			if(ob_get_length()){
				@ob_end_clean();
			}

			require_once __DIR__ . '/../pdf/TableReportPDF.php';

			$busqueda = $this->limpiarCadena($busqueda);
			if(isset($busqueda) && $busqueda!=""){
				$consulta = "SELECT caja_numero, caja_nombre, caja_efectivo FROM caja WHERE caja_numero LIKE '%$busqueda%' OR caja_nombre LIKE '%$busqueda%' ORDER BY caja_numero ASC";
			}else{
				$consulta = "SELECT caja_numero, caja_nombre, caja_efectivo FROM caja ORDER BY caja_numero ASC";
			}
			$datos = $this->ejecutarConsulta($consulta);
			$rows = $datos ? $datos->fetchAll() : [];

			$pdf = new \TableReportPDF('P','mm','A4');
			$pdf->AliasNbPages();
			$pdf->SetMargins(10, 12, 10);
			$pdf->SetAutoPageBreak(true, 15);
			$pdf->titulo = APP_NAME.' - Reporte de Cajas';
			$pdf->subtitulo = 'Generado: '.date('d/m/Y H:i:s').'  |  Total registros: '.count($rows);
			$pdf->setTable(['Número','Nombre','Efectivo'], [25,105,60], ['C','L','R']);
			$pdf->AddPage();
			$pdf->SetFont('Arial','',8);

			$fill = false;
			foreach($rows as $r){
				$ef = $r['caja_efectivo'] ?? '';
				$ef = is_numeric($ef) ? number_format((float)$ef, 2, '.', '') : (string)$ef;
				$pdf->addRow([
					(string)($r['caja_numero'] ?? ''),
					(string)($r['caja_nombre'] ?? ''),
					$ef,
				], $fill);
				$fill = !$fill;
			}

			$pdf->Output('D', 'reporte_cajas_'.date('Ymd').'.pdf');
			exit();
		}


		/*----------  Entregar caja (transferir efectivo a Caja Principal)  ----------*/
		public function entregarCajaControlador(){
			if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL."adminLogin/"
				];
				return json_encode($alerta);
			}

			if(!$this->sessionEsAdmin()){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Acceso restringido",
					"texto"=>"Solo el administrador puede entregar cajas.",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$cajaId = (int)$this->limpiarCadena($_POST['caja_id'] ?? '0');
			if(!in_array($cajaId,[2,3],true)){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Operación inválida",
					"texto"=>"Solo se puede entregar la Caja 2 o la Caja 3 hacia la Caja Principal.",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			try{
				$pdo = $this->conectar();
				$pdo->beginTransaction();

				// Bloquear Caja Principal
				$stmt1 = $pdo->prepare("SELECT caja_id, caja_numero, caja_nombre, caja_efectivo FROM caja WHERE caja_id=1 FOR UPDATE");
				$stmt1->execute();
				$caja1 = $stmt1->fetch();

				// Bloquear Caja origen
				$stmt2 = $pdo->prepare("SELECT caja_id, caja_numero, caja_nombre, caja_efectivo FROM caja WHERE caja_id=:id FOR UPDATE");
				$stmt2->bindValue(':id', $cajaId, \PDO::PARAM_INT);
				$stmt2->execute();
				$cajaOrigen = $stmt2->fetch();

				if(!$caja1 || !$cajaOrigen){
					$pdo->rollBack();
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"No encontrado",
						"texto"=>"No encontramos la caja principal o la caja indicada.",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				$monto = (float)($cajaOrigen['caja_efectivo'] ?? 0);
				$monto = (float)number_format($monto, 2, '.', '');

				if($monto<=0){
					$pdo->rollBack();
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Sin efectivo",
						"texto"=>"La caja seleccionada no tiene efectivo para entregar.",
						"icono"=>"warning"
					];
					return json_encode($alerta);
				}

				$up1 = $pdo->prepare("UPDATE caja SET caja_efectivo = caja_efectivo + :monto WHERE caja_id=1");
				$up1->bindValue(':monto', $monto);
				$up1->execute();

				$up2 = $pdo->prepare("UPDATE caja SET caja_efectivo = 0 WHERE caja_id=:id");
				$up2->bindValue(':id', $cajaId, \PDO::PARAM_INT);
				$up2->execute();

				$pdo->commit();

				$nombreOrigen = (string)($cajaOrigen['caja_nombre'] ?? '');
				$numeroOrigen = (string)($cajaOrigen['caja_numero'] ?? $cajaId);
				$this->registrarLogAccion("Entregó caja: ".$nombreOrigen." #".$numeroOrigen." -> Caja Principal | Monto: ".$monto);

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Caja entregada",
					"texto"=>"Se transfirió ".MONEDA_SIMBOLO.number_format($monto, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)." ".MONEDA_NOMBRE." a la Caja Principal.",
					"icono"=>"success"
				];
				return json_encode($alerta);
			}catch(\Throwable $e){
				try{
					if(isset($pdo) && $pdo->inTransaction()){
						$pdo->rollBack();
					}
				}catch(\Throwable $e2){
					// ignore
				}

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No se pudo entregar la caja. Intenta nuevamente.",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}
		}


		/*----------  Controlador eliminar caja  ----------*/
		public function eliminarCajaControlador(){

			$id=$this->limpiarCadena($_POST['caja_id']);

			if($id==1){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos eliminar la caja principal del sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Verificando caja #
		    $datos=$this->ejecutarConsulta("SELECT * FROM caja WHERE caja_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado la caja en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Verificando ventas #
		    $check_ventas=$this->ejecutarConsulta("SELECT caja_id FROM venta WHERE caja_id='$id' LIMIT 1");
		    if($check_ventas->rowCount()>0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos eliminar la caja del sistema ya que tiene ventas asociadas",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Verificando usuarios #
		    $check_usuarios=$this->ejecutarConsulta("SELECT caja_id FROM usuario WHERE caja_id='$id' LIMIT 1");
		    if($check_usuarios->rowCount()>0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos eliminar la caja del sistema ya que tiene usuarios asociados",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    $eliminarCaja=$this->eliminarRegistro("caja","caja_id",$id);

		    if($eliminarCaja->rowCount()==1){
				$this->registrarLogAccion("Eliminó caja: ".$datos['caja_nombre']." #".$datos['caja_numero']." (ID: ".$id.")");
		        $alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Caja eliminada",
					"texto"=>"La caja ".$datos['caja_nombre']." #".$datos['caja_numero']." ha sido eliminada del sistema correctamente",
					"icono"=>"success"
				];
		    }else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar la caja ".$datos['caja_nombre']." #".$datos['caja_numero']." del sistema, por favor intente nuevamente",
					"icono"=>"error"
				];
		    }

		    return json_encode($alerta);
		}


		/*----------  Controlador actualizar caja  ----------*/
		public function actualizarCajaControlador(){

			$id=$this->limpiarCadena($_POST['caja_id']);

			# Verificando caja #
		    $datos=$this->ejecutarConsulta("SELECT * FROM caja WHERE caja_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado la caja en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Almacenando datos#
		    $numero=$this->limpiarCadena($_POST['caja_numero']);
		    $nombre=$this->limpiarCadena($_POST['caja_nombre']);
		    $efectivo=$this->limpiarCadena($_POST['caja_efectivo']);

		    # Verificando campos obligatorios #
		    if($numero=="" || $nombre=="" || $efectivo==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Verificando integridad de los datos #
		    if($this->verificarDatos("[0-9]{1,5}",$numero)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NUMERO DE CAJA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ:# ]{3,70}",$nombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE DE CAJA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9.]{1,25}",$efectivo)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El EFECTIVO DE CAJA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Comprobando numero de caja #
		    if($datos['caja_numero']!=$numero){
			    $check_numero=$this->ejecutarConsulta("SELECT caja_numero FROM caja WHERE caja_numero='$numero'");
			    if($check_numero->rowCount()>0){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El número de caja ingresado ya se encuentra registrado en el sistema",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    # Comprobando nombre de caja #
		    if($datos['caja_nombre']!=$nombre){
			    $check_nombre=$this->ejecutarConsulta("SELECT caja_nombre FROM caja WHERE caja_nombre='$nombre'");
			    if($check_nombre->rowCount()>0){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El nombre o código de caja ingresado ya se encuentra registrado en el sistema",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    # Comprobando que el efectivo sea mayor o igual a 0 #
			$efectivo=number_format($efectivo,2,'.','');
			if($efectivo<0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No puedes colocar una cantidad de efectivo menor a 0",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			$caja_datos_up=[
				[
					"campo_nombre"=>"caja_numero",
					"campo_marcador"=>":Numero",
					"campo_valor"=>$numero
				],
				[
					"campo_nombre"=>"caja_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"caja_efectivo",
					"campo_marcador"=>":Efectivo",
					"campo_valor"=>$efectivo
				]
			];

			$condicion=[
				"condicion_campo"=>"caja_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$id
			];

			if($this->actualizarDatos("caja",$caja_datos_up,$condicion)){
				$this->registrarLogAccion("Modificó caja: ".$datos['caja_nombre']." #".$datos['caja_numero']." (ID: ".$id.")");
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Caja actualizada",
					"texto"=>"Los datos de la caja ".$datos['caja_nombre']." #".$datos['caja_numero']." se actualizaron correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos de la caja ".$datos['caja_nombre']." #".$datos['caja_numero'].", por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

	}