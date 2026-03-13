<?php

	namespace app\controllers;
	use app\models\mainModel;

	class clientController extends mainModel{

		/*----------  Controlador registrar cliente  ----------*/
		public function registrarClienteControlador(){

			$esAdmin = (isset($_SESSION['usuario']) && $_SESSION['usuario']!="");
			$redirect_to = $this->limpiarCadena($_POST['redirect_to'] ?? "");
			if($redirect_to!="" && !preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $redirect_to)){
				$redirect_to = "";
			}

			# Almacenando datos#
		    $tipo_documento=$this->limpiarCadena($_POST['cliente_tipo_documento']);
		    $numero_documento=$this->limpiarCadena($_POST['cliente_numero_documento']);
		    $nombre=$this->limpiarCadena($_POST['cliente_nombre']);
		    $apellido=$this->limpiarCadena($_POST['cliente_apellido']);

		   // $provincia=$this->limpiarCadena($_POST['cliente_provincia']);
		    $ciudad=$this->limpiarCadena($_POST['cliente_ciudad']);
		    $direccion=$this->limpiarCadena($_POST['cliente_direccion']);

		    $telefono=$this->limpiarCadena($_POST['cliente_telefono']);
		    $email=$this->limpiarCadena($_POST['cliente_email']);

		    # Verificando campos obligatorios #
            if($numero_documento=="" || $nombre=="" || $apellido=="" || $ciudad=="" || $direccion==""){
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
		    if($this->verificarDatos("[a-zA-Z0-9-]{7,30}",$numero_documento)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NUMERO DE DOCUMENTO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}",$nombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}",$apellido)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El APELLIDO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{4,30}",$ciudad)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La CIUDAD O PUEBLO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{4,70}",$direccion)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La DIRECCION O CALLE no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($telefono!=""){
		    	if($this->verificarDatos("[0-9()+]{8,20}",$telefono)){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El TELEFONO no coincide con el formato solicitado",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    # Comprobando tipo de documento #
			if(!in_array($tipo_documento, DOCUMENTOS_CLIENTE)){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El TIPO DE DOCUMENTO no es correcto o no lo ha seleccionado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

		    # Verificando email #
		    if(!$esAdmin && $email==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"Debes ingresar un email para registrarte",
					"icono"=>"error"
				];
				return json_encode($alerta);
				exit();
		    }
		    if($email!=""){
				if(filter_var($email, FILTER_VALIDATE_EMAIL)){
					$check_email=$this->ejecutarConsulta("SELECT cliente_email FROM cliente WHERE cliente_email='$email'");
					if($check_email->rowCount()>0){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"El EMAIL que acaba de ingresar ya se encuentra registrado en el sistema, por favor verifique e intente nuevamente",
							"icono"=>"error"
						];
						return json_encode($alerta);
						exit();
					}
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"Ha ingresado un correo electrónico no valido",
						"icono"=>"error"
					];
					return json_encode($alerta);
					exit();
				}
            }

            # Comprobando documento #
		    $check_documento=$this->ejecutarConsulta("SELECT cliente_id FROM cliente WHERE cliente_tipo_documento='$tipo_documento' AND cliente_numero_documento='$numero_documento'");
		    if($check_documento->rowCount()>0){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El número y tipo de documento ingresado ya se encuentra registrado en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }


		    $cliente_datos_reg=[
				[
					"campo_nombre"=>"cliente_tipo_documento",
					"campo_marcador"=>":TipoDocumento",
					"campo_valor"=>$tipo_documento
				],
				[
					"campo_nombre"=>"cliente_numero_documento",
					"campo_marcador"=>":NumeroDocumento",
					"campo_valor"=>$numero_documento
				],
				[
					"campo_nombre"=>"cliente_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"cliente_apellido",
					"campo_marcador"=>":Apellido",
					"campo_valor"=>$apellido
				],
				[
					"campo_nombre"=>"cliente_ciudad",
					"campo_marcador"=>":Ciudad",
					"campo_valor"=>$ciudad
				],
				[
					"campo_nombre"=>"cliente_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$direccion
				],
				[
					"campo_nombre"=>"cliente_telefono",
					"campo_marcador"=>":Telefono",
					"campo_valor"=>$telefono
				],
				[
					"campo_nombre"=>"cliente_email",
					"campo_marcador"=>":Email",
					"campo_valor"=>$email
				]
			];

			$registrar_cliente=$this->guardarDatos("cliente",$cliente_datos_reg);

			if($registrar_cliente->rowCount()==1){
				$this->registrarLogAccion("Alta de cliente: ".$nombre." ".$apellido." (Email: ".$email.")");

				/* 
				 * Si el registro viene del flujo de Google (correo coincide con el de sesión),
				 * iniciamos sesión de cliente automáticamente y redirigimos a productosCliente.
				 */
				if(isset($_SESSION['google_cliente_email']) && $_SESSION['google_cliente_email']==$email){

					$nuevo_cliente = $this->ejecutarConsulta("SELECT * FROM cliente WHERE cliente_email='$email' LIMIT 1");
					if($nuevo_cliente->rowCount()==1){
						$nuevo_cliente = $nuevo_cliente->fetch();

						$_SESSION['cliente_id']       = $nuevo_cliente['cliente_id'];
						$_SESSION['cliente_nombre']   = $nuevo_cliente['cliente_nombre'];
						$_SESSION['cliente_apellido'] = $nuevo_cliente['cliente_apellido'];
						$_SESSION['cliente_email']    = $nuevo_cliente['cliente_email'];
					}

					unset($_SESSION['google_cliente_email'], $_SESSION['google_cliente_nombre'], $_SESSION['google_cliente_apellido']);

					$alerta=[
						"tipo"=>"redireccionar",
						"titulo"=>"Cliente registrado",
						"texto"=>"Tu cuenta se creó y tu sesión se inició correctamente.",
						"icono"=>"success",
						"url"=>((!$esAdmin && $redirect_to!="") ? (APP_URL.$redirect_to) : (APP_URL."productosCliente/"))
					];

				}else{
					// Flujo público: iniciar sesión automáticamente y redirigir.
					if(!$esAdmin && $email!=""){
						$nuevo_cliente = $this->ejecutarConsulta("SELECT * FROM cliente WHERE cliente_email='$email' LIMIT 1");
						if($nuevo_cliente->rowCount()==1){
							$nuevo_cliente = $nuevo_cliente->fetch();
							$_SESSION['cliente_id']       = $nuevo_cliente['cliente_id'];
							$_SESSION['cliente_nombre']   = $nuevo_cliente['cliente_nombre'];
							$_SESSION['cliente_apellido'] = $nuevo_cliente['cliente_apellido'];
							$_SESSION['cliente_email']    = $nuevo_cliente['cliente_email'];
						}

						$destino = ($redirect_to!="") ? (APP_URL.$redirect_to) : (APP_URL."productosCliente/");
						$alerta=[
							"tipo"=>"redireccionar",
							"titulo"=>"Cliente registrado",
							"texto"=>"Tu cuenta se creó y tu sesión se inició correctamente.",
							"icono"=>"success",
							"url"=>$destino
						];
					}else{
						$alerta=[
							"tipo"=>"limpiar",
							"titulo"=>"Cliente registrado",
							"texto"=>"El cliente ".$nombre." ".$apellido." se registró con éxito",
							"icono"=>"success"
						];
					}
				}

			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No se pudo registrar el cliente, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}


		/*----------  Controlador listar cliente  ----------*/
		public function listarClienteControlador($pagina,$registros,$url,$busqueda){

			$pagina=$this->limpiarCadena($pagina);
			$registros=$this->limpiarCadena($registros);

			$url=$this->limpiarCadena($url);
			$url=APP_URL.$url."/";

			$busqueda=$this->limpiarCadena($busqueda);
			$tabla="";

			$pagina = (isset($pagina) && $pagina>0) ? (int) $pagina : 1;
			$inicio = ($pagina>0) ? (($pagina * $registros)-$registros) : 0;

			if(isset($busqueda) && $busqueda!=""){

				$consulta_datos="SELECT * FROM cliente WHERE ((cliente_id!='1') AND (cliente_tipo_documento LIKE '%$busqueda%' OR cliente_numero_documento LIKE '%$busqueda%' OR cliente_nombre LIKE '%$busqueda%' OR cliente_apellido LIKE '%$busqueda%' OR cliente_email LIKE '%$busqueda%'  LIKE '%$busqueda%' OR cliente_ciudad LIKE '%$busqueda%')) ORDER BY cliente_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(cliente_id) FROM cliente WHERE ((cliente_id!='1') AND (cliente_tipo_documento LIKE '%$busqueda%' OR cliente_numero_documento LIKE '%$busqueda%' OR cliente_nombre LIKE '%$busqueda%' OR cliente_apellido LIKE '%$busqueda%' OR cliente_email LIKE '%$busqueda%' LIKE '%$busqueda%' OR cliente_ciudad LIKE '%$busqueda%'))";

			}else{

				$consulta_datos="SELECT * FROM cliente WHERE cliente_id!='1' ORDER BY cliente_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(cliente_id) FROM cliente WHERE cliente_id!='1'";

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
		                    <th class="has-text-centered">#</th>
		                    <th class="has-text-centered">Documento</th>
		                    <th class="has-text-centered">Nombre</th>
		                    <th class="has-text-centered">Email</th>
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
					$tabla.='
						<tr class="has-text-centered" >
							<td>'.$contador.'</td>
							<td>'.$rows['cliente_tipo_documento'].': '.$rows['cliente_numero_documento'].'</td>
							<td>'.$rows['cliente_nombre'].' '.$rows['cliente_apellido'].'</td>
							<td>'.$rows['cliente_email'].'</td>
			                <td>
			                    <a href="'.APP_URL.'clientUpdate/'.$rows['cliente_id'].'/" class="button is-success is-rounded is-small">
			                    	<i class="fas fa-sync fa-fw"></i>
			                    </a>
			                </td>
			                <td>
			                	<form class="FormularioAjax" action="'.APP_URL.'app/ajax/clienteAjax.php" method="POST" autocomplete="off" >

			                		<input type="hidden" name="modulo_cliente" value="eliminar">
			                		<input type="hidden" name="cliente_id" value="'.$rows['cliente_id'].'">

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
				$tabla.='<p class="has-text-right">Mostrando clientes <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';

				$tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
			}

			return $tabla;
		}


		/*----------  Exportar clientes a PDF  ----------*/
		public function exportarClientesPDF($busqueda=""){
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
				$consulta = "SELECT cliente_tipo_documento, cliente_numero_documento, cliente_nombre, cliente_apellido, cliente_email, cliente_telefono, cliente_ciudad, cliente_direccion FROM cliente WHERE cliente_id!='1' AND (cliente_tipo_documento LIKE '%$busqueda%' OR cliente_numero_documento LIKE '%$busqueda%' OR cliente_nombre LIKE '%$busqueda%' OR cliente_apellido LIKE '%$busqueda%' OR cliente_email LIKE '%$busqueda%' OR cliente_telefono LIKE '%$busqueda%' OR cliente_ciudad LIKE '%$busqueda%' OR cliente_direccion LIKE '%$busqueda%') ORDER BY cliente_nombre ASC";
			}else{
				$consulta = "SELECT cliente_tipo_documento, cliente_numero_documento, cliente_nombre, cliente_apellido, cliente_email, cliente_telefono, cliente_ciudad, cliente_direccion FROM cliente WHERE cliente_id!='1' ORDER BY cliente_nombre ASC";
			}
			$datos = $this->ejecutarConsulta($consulta);
			$rows = $datos ? $datos->fetchAll() : [];

			$pdf = new \TableReportPDF('L','mm','A4');
			$pdf->AliasNbPages();
			$pdf->SetMargins(10, 12, 10);
			$pdf->SetAutoPageBreak(true, 15);
			$pdf->titulo = APP_NAME.' - Reporte de Clientes';
			$pdf->subtitulo = 'Generado: '.date('d/m/Y H:i:s').'  |  Total registros: '.count($rows);
			$pdf->setTable(
				['Documento','Nombre','Email','Teléfono','Ciudad','Dirección'],
				[40,50,65,35,40,47],
				['L','L','L','L','L','L']
			);
			$pdf->AddPage();
			$pdf->SetFont('Arial','',8);

			$fill = false;
			foreach($rows as $r){
				$doc = trim((string)($r['cliente_tipo_documento'] ?? '').': '.(string)($r['cliente_numero_documento'] ?? ''));
				$nombre = trim((string)($r['cliente_nombre'] ?? '').' '.(string)($r['cliente_apellido'] ?? ''));
				$pdf->addRow([
					$doc,
					$nombre,
					(string)($r['cliente_email'] ?? ''),
					(string)($r['cliente_telefono'] ?? ''),
					(string)($r['cliente_ciudad'] ?? ''),
					(string)($r['cliente_direccion'] ?? ''),
				], $fill);
				$fill = !$fill;
			}

			$pdf->Output('D', 'reporte_clientes_'.date('Ymd').'.pdf');
			exit();
		}


		/*----------  Controlador eliminar cliente  ----------*/
		public function eliminarClienteControlador(){

			$id=$this->limpiarCadena($_POST['cliente_id']);

			if($id==1){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos eliminar el cliente principal del sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Verificando cliente #
		    $datos=$this->ejecutarConsulta("SELECT * FROM cliente WHERE cliente_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el cliente en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Verificando ventas #
		    $check_ventas=$this->ejecutarConsulta("SELECT cliente_id FROM venta WHERE cliente_id='$id' LIMIT 1");
		    if($check_ventas->rowCount()>0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos eliminar el cliente del sistema ya que tiene ventas asociadas",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    $eliminarCliente=$this->eliminarRegistro("cliente","cliente_id",$id);

		    if($eliminarCliente->rowCount()==1){
				$this->registrarLogAccion("Eliminó cliente: ".$datos['cliente_nombre']." ".$datos['cliente_apellido']." (ID: ".$id.")");

		        $alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Cliente eliminado",
					"texto"=>"El cliente ".$datos['cliente_nombre']." ".$datos['cliente_apellido']." ha sido eliminado del sistema correctamente",
					"icono"=>"success"
				];

		    }else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el cliente ".$datos['cliente_nombre']." ".$datos['cliente_apellido']." del sistema, por favor intente nuevamente",
					"icono"=>"error"
				];
		    }

		    return json_encode($alerta);
		}


		/*----------  Controlador actualizar cliente  ----------*/
		public function actualizarClienteControlador(){

			$id=$this->limpiarCadena($_POST['cliente_id']);

			# Verificando cliente #
		    $datos=$this->ejecutarConsulta("SELECT * FROM cliente WHERE cliente_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el cliente en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Almacenando datos#
		    $tipo_documento=$this->limpiarCadena($_POST['cliente_tipo_documento']);
		    $numero_documento=$this->limpiarCadena($_POST['cliente_numero_documento']);
		    $nombre=$this->limpiarCadena($_POST['cliente_nombre']);
		    $apellido=$this->limpiarCadena($_POST['cliente_apellido']);

		   // $provincia=$this->limpiarCadena($_POST['cliente_provincia']);
		    $ciudad=$this->limpiarCadena($_POST['cliente_ciudad']);
		    $direccion=$this->limpiarCadena($_POST['cliente_direccion']);

		    $telefono=$this->limpiarCadena($_POST['cliente_telefono']);
		    $email=$this->limpiarCadena($_POST['cliente_email']);

		    # Verificando campos obligatorios #
            if($numero_documento=="" || $nombre=="" || $apellido=="" || $ciudad=="" || $direccion==""){
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
		    if($this->verificarDatos("[a-zA-Z0-9-]{7,30}",$numero_documento)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NUMERO DE DOCUMENTO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}",$nombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}",$apellido)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El APELLIDO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{4,30}",$ciudad)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La CIUDAD O PUEBLO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{4,70}",$direccion)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La DIRECCION O CALLE no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($telefono!=""){
		    	if($this->verificarDatos("[0-9()+]{8,20}",$telefono)){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El TELEFONO no coincide con el formato solicitado",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    # Comprobando tipo de documento #
			if(!in_array($tipo_documento, DOCUMENTOS_CLIENTE)){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El TIPO DE DOCUMENTO no es correcto",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Verificando email #
		    if($email!="" && $datos['cliente_email']!=$email){
				if(filter_var($email, FILTER_VALIDATE_EMAIL)){
					$check_email=$this->ejecutarConsulta("SELECT cliente_email FROM cliente WHERE cliente_email='$email'");
					if($check_email->rowCount()>0){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"El EMAIL que acaba de ingresar ya se encuentra registrado en el sistema, por favor verifique e intente nuevamente",
							"icono"=>"error"
						];
						return json_encode($alerta);
						exit();
					}
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"Ha ingresado un correo electrónico no valido",
						"icono"=>"error"
					];
					return json_encode($alerta);
					exit();
				}
            }

            # Comprobando documento #
            if($tipo_documento!=$datos['cliente_tipo_documento'] || $numero_documento!=$datos['cliente_numero_documento']){
			    $check_documento=$this->ejecutarConsulta("SELECT cliente_id FROM cliente WHERE cliente_tipo_documento='$tipo_documento' AND cliente_numero_documento='$numero_documento'");
			    if($check_documento->rowCount()>0){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El número y tipo de documento ingresado ya se encuentra registrado en el sistema",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
            }

            $cliente_datos_up=[
				[
					"campo_nombre"=>"cliente_tipo_documento",
					"campo_marcador"=>":TipoDocumento",
					"campo_valor"=>$tipo_documento
				],
				[
					"campo_nombre"=>"cliente_numero_documento",
					"campo_marcador"=>":NumeroDocumento",
					"campo_valor"=>$numero_documento
				],
				[
					"campo_nombre"=>"cliente_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"cliente_apellido",
					"campo_marcador"=>":Apellido",
					"campo_valor"=>$apellido
				],
				[
					"campo_nombre"=>"cliente_ciudad",
					"campo_marcador"=>":Ciudad",
					"campo_valor"=>$ciudad
				],
				[
					"campo_nombre"=>"cliente_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$direccion
				],
				[
					"campo_nombre"=>"cliente_telefono",
					"campo_marcador"=>":Telefono",
					"campo_valor"=>$telefono
				],
				[
					"campo_nombre"=>"cliente_email",
					"campo_marcador"=>":Email",
					"campo_valor"=>$email
				]
			];

			$condicion=[
				"condicion_campo"=>"cliente_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$id
			];

			if($this->actualizarDatos("cliente",$cliente_datos_up,$condicion)){
				$this->registrarLogAccion("Modificó cliente: ".$datos['cliente_nombre']." ".$datos['cliente_apellido']." (ID: ".$id.")");
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Cliente actualizado",
					"texto"=>"Los datos del cliente ".$datos['cliente_nombre']." ".$datos['cliente_apellido']." se actualizaron correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos del cliente ".$datos['cliente_nombre']." ".$datos['cliente_apellido'].", por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

	}