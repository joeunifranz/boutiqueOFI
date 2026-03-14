<?php

	namespace app\controllers;
	use app\models\mainModel;

	class productController extends mainModel{

		/*----------  Controlador registrar producto  ----------*/
		public function registrarProductoControlador(){

			# Almacenando datos#
		    $codigo=$this->limpiarCadena($_POST['producto_codigo']);
		    $nombre=$this->limpiarCadena($_POST['producto_nombre']);

		    $precio_compra=$this->limpiarCadena($_POST['producto_precio_compra']);
		    $precio_venta=$this->limpiarCadena($_POST['producto_precio_venta']);
		    $stock=$this->limpiarCadena($_POST['producto_stock']);
			$talla=$this->limpiarCadena($_POST['producto_talla']);

		    $modelo=$this->limpiarCadena($_POST['producto_modelo']);
		    $unidad=$this->limpiarCadena($_POST['producto_unidad']);
		    $categoria=$this->limpiarCadena($_POST['producto_categoria']);

		    # Verificando campos obligatorios #
            if($codigo=="" || $nombre=="" || $precio_compra=="" || $precio_venta=="" || $stock==""){
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
		    if($this->verificarDatos("[a-zA-Z0-9- ]{1,77}",$codigo)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El CODIGO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,$#\-\/ ]{1,100}",$nombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9.]{1,25}",$precio_compra)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE COMPRA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9.]{1,25}",$precio_venta)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE VENTA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9]{1,22}",$stock)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El STOCK O EXISTENCIAS no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

			if($talla!=""){
				if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{1,255}",$talla)){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La MARCA no coincide con el formato solicitado",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    if($modelo!=""){
		    	if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{1,30}",$modelo)){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El MODELO no coincide con el formato solicitado",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    # Comprobando presentacion del producto #
			if(!in_array($unidad, PRODUCTO_UNIDAD)){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La PRESENTACION DEL PRODUCTO no es correcta o no la ha seleccionado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Verificando categoria #
		    $check_categoria=$this->ejecutarConsulta("SELECT categoria_id FROM categoria WHERE categoria_id='$categoria'");
		    if($check_categoria->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La categoría seleccionada no existe en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Verificando stock total o existencias #
            if($stock<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No puedes registrar un producto con stock o existencias en 0, debes de agregar al menos una unidad",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
            }

            # Comprobando precio de compra del producto #
            $precio_compra=number_format($precio_compra,MONEDA_DECIMALES,'.','');
            if($precio_compra<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE COMPRA no puede ser menor o igual a 0",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
            }

            # Comprobando precio de venta del producto #
            $precio_venta=number_format($precio_venta,MONEDA_DECIMALES,'.','');
            if($precio_venta<=0){
                $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE VENTA no puede ser menor o igual a 0",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Comprobando precio de compra y venta del producto #
			if($precio_compra>$precio_venta){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El precio de compra del producto no puede ser mayor al precio de venta",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Comprobando codigo de producto #
		    $check_codigo=$this->ejecutarConsulta("SELECT producto_codigo FROM producto WHERE producto_codigo='$codigo'");
		    if($check_codigo->rowCount()>=1){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El código de producto que ha ingresado ya se encuentra registrado en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Comprobando nombre de producto #
		    $check_nombre=$this->ejecutarConsulta("SELECT producto_nombre FROM producto WHERE producto_codigo='$codigo' AND producto_nombre='$nombre'");
		    if($check_nombre->rowCount()>=1){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"Ya existe un producto registrado con el mismo nombre y código de barras",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    # Directorios de imagenes #
			$img_dir='../views/productos/';

			# Comprobar si se selecciono una imagen #
    		if($_FILES['producto_foto']['name']!="" && $_FILES['producto_foto']['size']>0){

    			# Creando directorio #
		        if(!file_exists($img_dir)){
		            if(!mkdir($img_dir,0777)){
		            	$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
		                exit();
		            } 
		        }

		        # Verificando formato de imagenes #
		        if(mime_content_type($_FILES['producto_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['producto_foto']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		            exit();
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['producto_foto']['size']/1024)>5120){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		            exit();
		        }

		        # Nombre de la foto #
		        $foto=$codigo."_".rand(0,100);

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['producto_foto']['tmp_name'])){
		            case 'image/jpeg':
		                $foto=$foto.".jpg";
		            break;
		            case 'image/png':
		                $foto=$foto.".png";
		            break;
		        }

		        chmod($img_dir,0777);

		        # Moviendo imagen al directorio #
		        if(!move_uploaded_file($_FILES['producto_foto']['tmp_name'],$img_dir.$foto)){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"No podemos subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
		            exit();
		        }

    		}else{
    			$foto="";
    		}

    		$producto_datos_reg=[
				[
					"campo_nombre"=>"producto_codigo",
					"campo_marcador"=>":Codigo",
					"campo_valor"=>$codigo
				],
				[
					"campo_nombre"=>"producto_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"producto_stock_total",
					"campo_marcador"=>":Stock",
					"campo_valor"=>$stock
				],
				[
					"campo_nombre"=>"producto_talla",
					"campo_marcador"=>":Talla",
					"campo_valor"=>$talla
				],
				[
					"campo_nombre"=>"producto_tipo_unidad",
					"campo_marcador"=>":Unidad",
					"campo_valor"=>$unidad
				],
				[
					"campo_nombre"=>"producto_precio_compra",
					"campo_marcador"=>":PrecioCompra",
					"campo_valor"=>$precio_compra
				],
				[
					"campo_nombre"=>"producto_precio_venta",
					"campo_marcador"=>":PrecioVenta",
					"campo_valor"=>$precio_venta
				],
				[
					"campo_nombre"=>"producto_modelo",
					"campo_marcador"=>":Modelo",
					"campo_valor"=>$modelo
				],
				[
					"campo_nombre"=>"producto_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>"Habilitado"
				],
				[
					"campo_nombre"=>"producto_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				],
				[
					"campo_nombre"=>"categoria_id",
					"campo_marcador"=>":Categoria",
					"campo_valor"=>$categoria
				]
			];

			$registrar_producto=$this->guardarDatos("producto",$producto_datos_reg);

			if($registrar_producto->rowCount()==1){
				$this->registrarLogAccion("Alta de producto: ".$nombre." (Código: ".$codigo.")");
				$alerta=[
					"tipo"=>"limpiar",
					"titulo"=>"Producto registrado",
					"texto"=>"El producto ".$nombre." se registro con exito",
					"icono"=>"success"
				];
			}else{
				
				if(is_file($img_dir.$foto)){
		            chmod($img_dir.$foto,0777);
		            unlink($img_dir.$foto);
		        }

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No se pudo registrar el producto, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}


		/*----------  Controlador listar producto  ----------*/
		public function listarProductoControlador($pagina,$registros,$url,$busqueda,$categoria){

			$pagina=$this->limpiarCadena($pagina);
			$registros=$this->limpiarCadena($registros);
			$categoria=$this->limpiarCadena($categoria);

			$url=$this->limpiarCadena($url);
			if($categoria>0){
				$url=APP_URL.$url."/".$categoria."/";
			}else{
				$url=APP_URL.$url."/";
			}

			$busqueda=$this->limpiarCadena($busqueda);
			$tabla="";

			$pagina = (isset($pagina) && $pagina>0) ? (int) $pagina : 1;
			$inicio = ($pagina>0) ? (($pagina * $registros)-$registros) : 0;

			$campos="producto.producto_id,producto.producto_codigo,producto.producto_nombre,producto_stock_total,producto.producto_talla,producto.producto_precio_venta,producto.producto_foto,categoria.categoria_nombre";

			if(isset($busqueda) && $busqueda!=""){

				$consulta_datos="SELECT $campos FROM producto INNER JOIN categoria ON producto.categoria_id=categoria.categoria_id WHERE producto_codigo LIKE '%$busqueda%' OR producto_nombre LIKE '%$busqueda%' OR producto_talla LIKE '%$busqueda%' OR producto_modelo LIKE '%$busqueda%' ORDER BY producto_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(producto_id) FROM producto WHERE producto_codigo LIKE '%$busqueda%' OR producto_nombre LIKE '%$busqueda%' OR producto_talla LIKE '%$busqueda%' OR producto_modelo LIKE '%$busqueda%'";

			}elseif($categoria>0){

		        $consulta_datos="SELECT $campos FROM producto INNER JOIN categoria ON producto.categoria_id=categoria.categoria_id WHERE producto.categoria_id='$categoria' ORDER BY producto.producto_nombre ASC LIMIT $inicio,$registros";

		        $consulta_total="SELECT COUNT(producto_id) FROM producto WHERE categoria_id='$categoria'";

		    }else{

				$consulta_datos="SELECT $campos FROM producto INNER JOIN categoria ON producto.categoria_id=categoria.categoria_id ORDER BY producto_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(producto_id) FROM producto";

			}

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();

			$total = $this->ejecutarConsulta($consulta_total);
			$total = (int) $total->fetchColumn();

			$numeroPaginas =ceil($total/$registros);

		    if($total>=1 && $pagina<=$numeroPaginas){
				$contador=$inicio+1;
				$pag_inicio=$inicio+1;
				foreach($datos as $rows){
					$tabla.='
		            <article class="media pb-3 pt-3">
		                <figure class="media-left">
		                    <p class="image is-64x64">';
		                        if(is_file("./app/views/productos/".$rows['producto_foto'])){
		                            $tabla.='<img src="'.APP_URL.'app/views/productos/'.$rows['producto_foto'].'">';
		                        }else{
		                            $tabla.='<img src="'.APP_URL.'app/views/productos/default.png">';
		                        }
		            $tabla.='</p>
		                </figure>
		                <div class="media-content">
		                    <div class="content">
		                        <p>
		                            <strong>'.$contador.' - '.$rows['producto_nombre'].'</strong><br>
		                            <strong>CODIGO:</strong> '.$rows['producto_codigo'].', 
		                            <strong>PRECIO:</strong> '.MONEDA_SIMBOLO.number_format($rows['producto_precio_venta'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).', 
		                            <strong>STOCK:</strong> '.$rows['producto_stock_total'].',
									<strong>TALLA:</strong> '.$rows['producto_talla'].', 
		                            <strong>CATEGORIA:</strong> '.$rows['categoria_nombre'].'
		                        </p>
		                    </div>
		                    <div class="has-text-right">
		                        <a href="'.APP_URL.'productPhoto/'.$rows['producto_id'].'/" class="button is-info is-rounded is-small">
			                    	<i class="far fa-image fa-fw"></i>
			                    </a>

		                        <a href="'.APP_URL.'productUpdate/'.$rows['producto_id'].'/" class="button is-success is-rounded is-small">
		                        	<i class="fas fa-sync fa-fw"></i>
		                        </a>

		                        <form class="FormularioAjax is-inline-block" action="'.APP_URL.'app/ajax/productoAjax.php" method="POST" autocomplete="off" >

			                		<input type="hidden" name="modulo_producto" value="eliminar">
			                		<input type="hidden" name="producto_id" value="'.$rows['producto_id'].'">

			                    	<button type="submit" class="button is-danger is-rounded is-small">
			                    		<i class="far fa-trash-alt fa-fw"></i>
			                    	</button>
			                    </form>
		                    </div>
		                </div>
		            </article>


		            <hr>
		            ';
					$contador++;
				}
				$pag_final=$contador-1;
			}else{
				if($total>=1){
					$tabla.='
						<p class="has-text-centered pb-6"><i class="far fa-hand-point-down fa-5x"></i></p>
			            <p class="has-text-centered">
			                <a href="'.$url.'1/" class="button is-link is-rounded is-small mt-4 mb-4">
			                    Haga clic acá para recargar el listado
			                </a>
			            </p>
					';
				}else{
					$tabla.='
						<p class="has-text-centered pb-6"><i class="far fa-grin-beam-sweat fa-5x"></i></p>
						<p class="has-text-centered">No hay productos registrados en esta categoría</p>
					';
				}
			}

			### Paginacion ###
			if($total>0 && $pagina<=$numeroPaginas){
				$tabla.='<p class="has-text-right">Mostrando productos <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';

				$tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
			}

			return $tabla;
		}


		/*----------  Exportar productos a PDF  ----------*/
		public function exportarProductosPDF($busqueda="", $categoria=0){
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
			$categoria = (int)$this->limpiarCadena($categoria);

			$campos = "p.producto_codigo, p.producto_nombre, p.producto_talla, c.categoria_nombre, p.producto_precio_venta, p.producto_stock_total";
			if(isset($busqueda) && $busqueda!=""){
				$consulta = "SELECT $campos FROM producto p INNER JOIN categoria c ON p.categoria_id=c.categoria_id WHERE p.producto_codigo LIKE '%$busqueda%' OR p.producto_nombre LIKE '%$busqueda%' OR p.producto_talla LIKE '%$busqueda%' OR p.producto_modelo LIKE '%$busqueda%' ORDER BY p.producto_nombre ASC";
			}elseif($categoria>0){
				$consulta = "SELECT $campos FROM producto p INNER JOIN categoria c ON p.categoria_id=c.categoria_id WHERE p.categoria_id='$categoria' ORDER BY p.producto_nombre ASC";
			}else{
				$consulta = "SELECT $campos FROM producto p INNER JOIN categoria c ON p.categoria_id=c.categoria_id ORDER BY p.producto_nombre ASC";
			}

			$datos = $this->ejecutarConsulta($consulta);
			$rows = $datos ? $datos->fetchAll() : [];

			$pdf = new \TableReportPDF('L','mm','A4');
			$pdf->AliasNbPages();
			$pdf->SetMargins(10, 12, 10);
			$pdf->SetAutoPageBreak(true, 15);
			$pdf->titulo = APP_NAME.' - Reporte de Productos';
			$pdf->subtitulo = 'Generado: '.date('d/m/Y H:i:s').'  |  Total registros: '.count($rows);
			$pdf->setTable(
				['Código','Nombre','Talla','Categoría','Precio','Stock'],
				[35,100,20,55,30,37],
				['L','L','C','L','R','C']
			);
			$pdf->AddPage();
			$pdf->SetFont('Arial','',8);

			$fill = false;
			foreach($rows as $r){
				$precio = $r['producto_precio_venta'] ?? '';
				$precio = is_numeric($precio) ? (MONEDA_SIMBOLO.number_format((float)$precio, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)) : (string)$precio;
				$pdf->addRow([
					(string)($r['producto_codigo'] ?? ''),
					(string)($r['producto_nombre'] ?? ''),
					(string)($r['producto_talla'] ?? ''),
					(string)($r['categoria_nombre'] ?? ''),
					$precio,
					(string)($r['producto_stock_total'] ?? ''),
				], $fill);
				$fill = !$fill;
			}

			$pdf->Output('D', 'reporte_productos_'.date('Ymd').'.pdf');
			exit();
		}


		/*----------  Listado público de productos para clientes (solo disponibles)  ----------*/
		public function listarProductoPublicoControlador($pagina,$registros,$url,$busqueda,$categoria){

			$pagina=$this->limpiarCadena($pagina);
			$registros=$this->limpiarCadena($registros);
			$categoria=$this->limpiarCadena($categoria);

			$url=$this->limpiarCadena($url);
			if($categoria>0){
				$url=APP_URL.$url."/".$categoria."/";
			}else{
				$url=APP_URL.$url."/";
			}

			$busqueda=$this->limpiarCadena($busqueda);
			$tabla="";

			$pagina = (isset($pagina) && $pagina>0) ? (int) $pagina : 1;
			$inicio = ($pagina>0) ? (($pagina * $registros)-$registros) : 0;

			$campos="producto.producto_id,producto.producto_codigo,producto.producto_nombre,producto_stock_total,producto.producto_talla,producto.producto_precio_venta,producto.producto_foto,categoria.categoria_nombre";

			$condicion_base="producto.producto_estado='Habilitado' AND producto.producto_stock_total>0";

			if(isset($busqueda) && $busqueda!=""){

				$consulta_datos="SELECT $campos FROM producto INNER JOIN categoria ON producto.categoria_id=categoria.categoria_id WHERE $condicion_base AND (producto_codigo LIKE '%$busqueda%' OR producto_nombre LIKE '%$busqueda%' OR producto_talla LIKE '%$busqueda%' OR producto_modelo LIKE '%$busqueda%') ORDER BY producto_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(producto_id) FROM producto WHERE $condicion_base AND (producto_codigo LIKE '%$busqueda%' OR producto_nombre LIKE '%$busqueda%' OR producto_talla LIKE '%$busqueda%' OR producto_modelo LIKE '%$busqueda%')";

			}elseif($categoria>0){

				$consulta_datos="SELECT $campos FROM producto INNER JOIN categoria ON producto.categoria_id=categoria.categoria_id WHERE $condicion_base AND producto.categoria_id='$categoria' ORDER BY producto.producto_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(producto_id) FROM producto WHERE $condicion_base AND categoria_id='$categoria'";

			}else{

				$consulta_datos="SELECT $campos FROM producto INNER JOIN categoria ON producto.categoria_id=categoria.categoria_id WHERE $condicion_base ORDER BY producto_nombre ASC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(producto_id) FROM producto WHERE $condicion_base";

			}

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();

			$total = $this->ejecutarConsulta($consulta_total);
			$total = (int) $total->fetchColumn();

			$numeroPaginas =ceil($total/$registros);

			if($total>=1 && $pagina<=$numeroPaginas){
				$contador=$inicio+1;
				$pag_inicio=$inicio+1;
				$tabla.='<div class="columns is-multiline productos-publicos-grid">';
				foreach($datos as $rows){
					$tabla.='
						<div class="column is-4 productos-publicos-item">
							<div class="card productos-publicos-card">
								<div class="card-image">
									<figure class="image is-4by5">';
										if(is_file("./app/views/productos/".$rows['producto_foto'])){
											$tabla.='<img src="'.APP_URL.'app/views/productos/'.$rows['producto_foto'].'" alt="'.$rows['producto_nombre'].'">';
										}else{
											$tabla.='<img src="'.APP_URL.'app/views/productos/default.png" alt="Sin imagen">';
										}
					$tabla.='			</figure>
								</div>
								<div class="card-content">
									<p class="title is-6 mb-2">'.$rows['producto_nombre'].'</p>
									<p class="subtitle is-7 mb-2">'.$rows['categoria_nombre'].' • Talla '.$rows['producto_talla'].'</p>
									<p class="has-text-weight-semibold mb-1">'.MONEDA_SIMBOLO.number_format($rows['producto_precio_venta'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).'</p>
									<p class="is-size-7 has-text-grey">Stock disponible: '.$rows['producto_stock_total'].'</p>
								</div>
							</div>
						</div>
					';
					$contador++;
				}
				$tabla.='</div>';
				$pag_final=$contador-1;
			}else{
				if($total>=1){
					$tabla.='
						<p class="has-text-centered pb-6"><i class="far fa-hand-point-down fa-5x"></i></p>
						<p class="has-text-centered">
							<a href="'.$url.'1/" class="button is-link is-rounded is-small mt-4 mb-4">
								Haga clic acá para recargar el listado
							</a>
						</p>
					';
				}else{
					$tabla.='
						<p class="has-text-centered pb-6"><i class="far fa-grin-beam-sweat fa-5x"></i></p>
						<p class="has-text-centered">No hay productos disponibles en este momento</p>
					';
				}
			}

			### Paginacion ###
			if($total>0 && $pagina<=$numeroPaginas){
				$tabla.='<p class="has-text-right">Mostrando productos <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';

				$tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
			}

			return $tabla;
		}

		/*----------  Catálogo de inicio agrupado por categoría (público)  ----------*/
		public function catalogoInicioHTMLControlador($limite=30){

			$limite = (int)$limite;
			if($limite<=0){
				$limite = 30;
			}
		
			$campos_producto="producto_id,producto_nombre,producto_precio_venta,producto_talla,producto_foto,producto_stock_total";
			$condicion_base="producto_estado='Habilitado' AND producto_stock_total>0";
		
			$consulta_productos = "SELECT $campos_producto 
								   FROM producto 
								   WHERE $condicion_base 
								   ORDER BY producto_nombre ASC 
								   LIMIT $limite";
		
			$productos = $this->ejecutarConsulta($consulta_productos);
			$productos = $productos->fetchAll();
		
			if(!$productos){
				return '<p class="has-text-centered has-text-grey-lighter mt-5">No hay productos disponibles en este momento.</p>';
			}
		
			$html='
			<section class="inicio-catalogo-categoria">
				<div class="level inicio-catalogo-header">
					<div class="level-left">
						<h3 class="title is-4 has-text-white">Nuestros Vestidos Tendencias</h3>
					</div>
				</div>
				<div class="inicio-catalogo-row-wrapper">
					<div class="inicio-catalogo-row" data-autoscroll="true">
			';
		
			foreach($productos as $prod){
		
				if(is_file("./app/views/productos/".$prod['producto_foto'])){
					$foto_html = '<img src="'.APP_URL.'app/views/productos/'.$prod['producto_foto'].'" alt="'.htmlspecialchars($prod['producto_nombre'],ENT_QUOTES,'UTF-8').'">';
				}else{
					$foto_html = '<img src="'.APP_URL.'app/views/productos/default.png" alt="Sin imagen">';
				}
		
				$productoUrl = APP_URL.'productoDetalle/'.$prod['producto_id'].'/';
				$html.='
					<div class="inicio-catalogo-item">
						<a class="inicio-catalogo-link" href="'.htmlspecialchars($productoUrl,ENT_QUOTES,'UTF-8').'" aria-label="Ver producto '.htmlspecialchars($prod['producto_nombre'],ENT_QUOTES,'UTF-8').'">
							<div class="card inicio-catalogo-card">
								<div class="card-image">
									<figure class="image is-3by4">
										'.$foto_html.'
									</figure>
								</div>
								<div class="card-content">
									<p class="title is-6 mb-1">'.htmlspecialchars($prod['producto_nombre'],ENT_QUOTES,'UTF-8').'</p>
									<p class="is-size-7 has-text-grey-light mb-1">Talla '.$prod['producto_talla'].'</p>
									<p class="has-text-weight-semibold mb-1">'.MONEDA_SIMBOLO.number_format($prod['producto_precio_venta'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).'</p>
								</div>
							</div>
						</a>
					</div>
				';
			}
		
			$html.='
					</div>
				</div>
			</section>
			';
		
			return $html;
		}
		public function obtenerProductoPorIdControlador($id){

			$id = (int)$id;

			$sql = "SELECT * FROM producto 
					WHERE producto_id = :id 
					AND producto_estado = 'Habilitado'
					LIMIT 1";

			$stmt = $this->conectar()->prepare($sql);
			$stmt->bindParam(":id", $id, \PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->fetch();
		}

		/*----------  Controlador eliminar producto  ----------*/
		public function eliminarProductoControlador(){

			$id=$this->limpiarCadena($_POST['producto_id']);

			# Verificando producto #
		    $datos=$this->ejecutarConsulta("SELECT * FROM producto WHERE producto_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el producto en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Verificando ventas #
		    $check_ventas=$this->ejecutarConsulta("SELECT producto_id FROM venta_detalle WHERE producto_id='$id' LIMIT 1");
		    if($check_ventas->rowCount()>0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos eliminar el producto del sistema ya que tiene ventas asociadas",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    $eliminarProducto=$this->eliminarRegistro("producto","producto_id",$id);

		    if($eliminarProducto->rowCount()==1){

		    	if(is_file("../views/productos/".$datos['producto_foto'])){
		            chmod("../views/productos/".$datos['producto_foto'],0777);
		            unlink("../views/productos/".$datos['producto_foto']);
		        }

				$this->registrarLogAccion("Eliminó producto: ".$datos['producto_nombre']." (ID: ".$id.")");

		        $alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Producto eliminado",
					"texto"=>"El producto '".$datos['producto_nombre']."' ha sido eliminado del sistema correctamente",
					"icono"=>"success"
				];

		    }else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el producto '".$datos['producto_nombre']."' del sistema, por favor intente nuevamente",
					"icono"=>"error"
				];
		    }

		    return json_encode($alerta);
		}
		public function listarCategoriasInicio(){

			$categorias = $this->ejecutarConsulta(
				"SELECT categoria_id, categoria_nombre 
				 FROM categoria 
				 ORDER BY categoria_nombre ASC"
			);
		
			$categorias = $categorias->fetchAll();
		
			$html = '';
		
			foreach($categorias as $cat){
				$html .= '
				<a href="'.APP_URL.'productosCliente/'.$cat['categoria_id'].'/" 
				   class="dropdown-item">
				   '.$cat['categoria_nombre'].'
				</a>';
			}
		
			return $html;
		}

		public function obtenerNombreCategoriaPorIdControlador($categoria_id){
			$categoria_id = (int)$this->limpiarCadena($categoria_id);
			if($categoria_id<=0){
				return "";
			}

			$sql = "SELECT categoria_nombre FROM categoria WHERE categoria_id = :id LIMIT 1";
			$stmt = $this->conectar()->prepare($sql);
			$stmt->bindParam(":id", $categoria_id, \PDO::PARAM_INT);
			$stmt->execute();
			$row = $stmt->fetch();
			return $row["categoria_nombre"] ?? "";
		}

		/*----------  Controlador actualizar producto  ----------*/
		public function actualizarProductoControlador(){

			$id=$this->limpiarCadena($_POST['producto_id']);

			# Verificando producto #
		    $datos=$this->ejecutarConsulta("SELECT * FROM producto WHERE producto_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el producto en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Almacenando datos#
		    $codigo=$this->limpiarCadena($_POST['producto_codigo']);
		    $nombre=$this->limpiarCadena($_POST['producto_nombre']);

		    $precio_compra=$this->limpiarCadena($_POST['producto_precio_compra']);
		    $precio_venta=$this->limpiarCadena($_POST['producto_precio_venta']);
		    $stock=$this->limpiarCadena($_POST['producto_stock']);
			$talla=$this->limpiarCadena($_POST['producto_talla']);
		    $modelo=$this->limpiarCadena($_POST['producto_modelo']);
		    $unidad=$this->limpiarCadena($_POST['producto_unidad']);
		    $categoria=$this->limpiarCadena($_POST['producto_categoria']);

		    # Verificando campos obligatorios #
            if($codigo=="" || $nombre=="" || $precio_compra=="" || $precio_venta=="" || $stock==""){
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
		    if($this->verificarDatos("[a-zA-Z0-9- ]{1,77}",$codigo)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El CODIGO no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,$#\-\/ ]{1,100}",$nombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9.]{1,25}",$precio_compra)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE COMPRA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9.]{1,25}",$precio_venta)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE VENTA no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    if($this->verificarDatos("[0-9]{1,22}",$stock)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El STOCK O EXISTENCIAS no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

			if($talla!=""){
				if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{1,255}",$talla)){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La MARCA no coincide con el formato solicitado",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    if($modelo!=""){
		    	if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ().,#\- ]{1,30}",$modelo)){
			    	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El MODELO no coincide con el formato solicitado",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }

		    # Comprobando presentacion del producto #
			if(!in_array($unidad, PRODUCTO_UNIDAD)){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La PRESENTACION DEL PRODUCTO no es correcta o no la ha seleccionado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Verificando categoria #
			if($datos['categoria_id']!=$categoria){
			    $check_categoria=$this->ejecutarConsulta("SELECT categoria_id FROM categoria WHERE categoria_id='$categoria'");
			    if($check_categoria->rowCount()<=0){
			        $alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La categoría seleccionada no existe en el sistema",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
			}

		    # Verificando stock total o existencias #
            if($stock<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No puedes registrar un producto con stock o existencias en 0, debes de agregar al menos una unidad",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
            }

            # Comprobando precio de compra del producto #
            $precio_compra=number_format($precio_compra,MONEDA_DECIMALES,'.','');
            if($precio_compra<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE COMPRA no puede ser menor o igual a 0",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
            }

            # Comprobando precio de venta del producto #
            $precio_venta=number_format($precio_venta,MONEDA_DECIMALES,'.','');
            if($precio_venta<=0){
                $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO DE VENTA no puede ser menor o igual a 0",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Comprobando precio de compra y venta del producto #
			if($precio_compra>$precio_venta){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El precio de compra del producto no puede ser mayor al precio de venta",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
			}

			# Comprobando codigo de producto #
			if($datos['producto_codigo']!=$codigo){
			    $check_codigo=$this->ejecutarConsulta("SELECT producto_codigo FROM producto WHERE producto_codigo='$codigo'");
			    if($check_codigo->rowCount()>=1){
			        $alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"El código de producto que ha ingresado ya se encuentra registrado en el sistema",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
			}

		    # Comprobando nombre de producto #
		    if($datos['producto_nombre']!=$nombre){
			    $check_nombre=$this->ejecutarConsulta("SELECT producto_nombre FROM producto WHERE producto_codigo='$codigo' AND producto_nombre='$nombre'");
			    if($check_nombre->rowCount()>=1){
			        $alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"Ya existe un producto registrado con el mismo nombre y código de barras",
						"icono"=>"error"
					];
					return json_encode($alerta);
			        exit();
			    }
		    }


		    $producto_datos_up=[
				[
					"campo_nombre"=>"producto_codigo",
					"campo_marcador"=>":Codigo",
					"campo_valor"=>$codigo
				],
				[
					"campo_nombre"=>"producto_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"producto_stock_total",
					"campo_marcador"=>":Stock",
					"campo_valor"=>$stock
				],
				[
					"campo_nombre"=>"producto_talla",
					"campo_marcador"=>":Talla",
					"campo_valor"=>$talla
				],
				[
					"campo_nombre"=>"producto_tipo_unidad",
					"campo_marcador"=>":Unidad",
					"campo_valor"=>$unidad
				],
				[
					"campo_nombre"=>"producto_precio_compra",
					"campo_marcador"=>":PrecioCompra",
					"campo_valor"=>$precio_compra
				],
				[
					"campo_nombre"=>"producto_precio_venta",
					"campo_marcador"=>":PrecioVenta",
					"campo_valor"=>$precio_venta
				],
				[
					"campo_nombre"=>"producto_modelo",
					"campo_marcador"=>":Modelo",
					"campo_valor"=>$modelo
				],
				[
					"campo_nombre"=>"categoria_id",
					"campo_marcador"=>":Categoria",
					"campo_valor"=>$categoria
				]
			];

			$condicion=[
				"condicion_campo"=>"producto_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$id
			];

			if($this->actualizarDatos("producto",$producto_datos_up,$condicion)){
				$this->registrarLogAccion("Modificó producto: ".$datos['producto_nombre']." (ID: ".$id.")");
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Producto actualizado",
					"texto"=>"Los datos del producto '".$datos['producto_nombre']."' se actualizaron correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos del producto '".$datos['producto_nombre']."', por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}


		/*----------  Controlador eliminar foto producto  ----------*/
		public function eliminarFotoProductoControlador(){

			$id=$this->limpiarCadena($_POST['producto_id']);

			# Verificando producto #
		    $datos=$this->ejecutarConsulta("SELECT * FROM producto WHERE producto_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el producto en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Directorio de imagenes #
    		$img_dir="../views/productos/";

    		chmod($img_dir,0777);

    		if(is_file($img_dir.$datos['producto_foto'])){

		        chmod($img_dir.$datos['producto_foto'],0777);

		        if(!unlink($img_dir.$datos['producto_foto'])){
		            $alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"Error al intentar eliminar la foto del producto, por favor intente nuevamente",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        	exit();
		        }
		    }else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado la foto del producto en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }

		    $producto_datos_up=[
				[
					"campo_nombre"=>"producto_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>""
				]
			];

			$condicion=[
				"condicion_campo"=>"producto_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$id
			];

			if($this->actualizarDatos("producto",$producto_datos_up,$condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Foto eliminada",
					"texto"=>"La foto del producto '".$datos['producto_nombre']."' se elimino correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Foto eliminada",
					"texto"=>"No hemos podido actualizar algunos datos del producto '".$datos['producto_nombre']."', sin embargo la foto ha sido eliminada correctamente",
					"icono"=>"warning"
				];
			}

			return json_encode($alerta);
		}


		/*----------  Controlador actualizar foto producto  ----------*/
		public function actualizarFotoProductoControlador(){

			$id=$this->limpiarCadena($_POST['producto_id']);

			# Verificando producto #
		    $datos=$this->ejecutarConsulta("SELECT * FROM producto WHERE producto_id='$id'");
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el producto en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
		    }else{
		    	$datos=$datos->fetch();
		    }

		    # Directorio de imagenes #
    		$img_dir="../views/productos/";

    		# Comprobar si se selecciono una imagen #
    		if($_FILES['producto_foto']['name']=="" && $_FILES['producto_foto']['size']<=0){
    			$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No ha seleccionado una foto para el producto",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        exit();
    		}

    		# Creando directorio #
	        if(!file_exists($img_dir)){
	            if(!mkdir($img_dir,0777)){
	                $alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"Error al crear el directorio",
						"icono"=>"error"
					];
					return json_encode($alerta);
	                exit();
	            } 
	        }

	        # Verificando formato de imagenes #
	        if(mime_content_type($_FILES['producto_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['producto_foto']['tmp_name'])!="image/png"){
	            $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
					"icono"=>"error"
				];
				return json_encode($alerta);
	            exit();
	        }

	        # Verificando peso de imagen #
	        if(($_FILES['producto_foto']['size']/1024)>5120){
	            $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La imagen que ha seleccionado supera el peso permitido",
					"icono"=>"error"
				];
				return json_encode($alerta);
	            exit();
	        }

	        # Nombre de la foto #
	        if($datos['producto_foto']!=""){
		        $foto=explode(".", $datos['producto_foto']);
		        $foto=$foto[0];
	        }else{
	        	$foto=$datos['producto_codigo']."_".rand(0,100);
	        }
	        

	        # Extension de la imagen #
	        switch(mime_content_type($_FILES['producto_foto']['tmp_name'])){
	            case 'image/jpeg':
	                $foto=$foto.".jpg";
	            break;
	            case 'image/png':
	                $foto=$foto.".png";
	            break;
	        }

	        chmod($img_dir,0777);
			
	        # Moviendo imagen al directorio #
	        if(!move_uploaded_file($_FILES['producto_foto']['tmp_name'],$img_dir.$foto)){
	            $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No podemos subir la imagen al sistema en este momento",
					"icono"=>"error"
				];
				return json_encode($alerta);
	            exit();
	        }

	        # Eliminando imagen anterior #
	        if(is_file($img_dir.$datos['producto_foto']) && $datos['producto_foto']!=$foto){
		        chmod($img_dir.$datos['producto_foto'], 0777);
		        unlink($img_dir.$datos['producto_foto']);
		    }

		    $producto_datos_up=[
				[
					"campo_nombre"=>"producto_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				]
			];

			$condicion=[
				"condicion_campo"=>"producto_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$id
			];

			if($this->actualizarDatos("producto",$producto_datos_up,$condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Foto actualizada",
					"texto"=>"La foto del producto '".$datos['producto_nombre']."' se actualizo correctamente",
					"icono"=>"success"
				];
			}else{

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Foto actualizada",
					"texto"=>"No hemos podido actualizar algunos datos del producto '".$datos['producto_nombre']."', sin embargo la foto ha sido actualizada",
					"icono"=>"warning"
				];
			}

			return json_encode($alerta);
		}
		/*----------  Productos por categoría  ----------*/
		public function productosPorCategoriaControlador($categoria_id){

			$categoria_id = (int)$this->limpiarCadena($categoria_id);

			$condicion_categoria = "";
			if($categoria_id>0){
				$condicion_categoria = "AND p.categoria_id = '".$categoria_id."'";
			}

			$consulta = "
				SELECT p.*, c.categoria_nombre 
				FROM producto p
				INNER JOIN categoria c 
					ON p.categoria_id = c.categoria_id
				WHERE p.producto_estado = 'Habilitado'
					AND p.producto_stock_total > 0
					$condicion_categoria
				ORDER BY p.producto_nombre ASC
			";

			$datos = $this->ejecutarConsulta($consulta);
			$productos = $datos->fetchAll();

			return $productos;
		}
	}