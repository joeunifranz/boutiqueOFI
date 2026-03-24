<?php

	namespace app\controllers;
	use app\models\mainModel;

	class fabricController extends mainModel{

		private function guardarTexturaSubida($file){
			if(!is_array($file) || !isset($file['error'])){
				return [ 'ok'=>false, 'error'=>'invalid_file' ];
			}
			if((int)$file['error'] === UPLOAD_ERR_NO_FILE){
				return [ 'ok'=>true, 'path'=>null ];
			}
			if((int)$file['error'] !== UPLOAD_ERR_OK){
				return [ 'ok'=>false, 'error'=>'upload_failed' ];
			}

			$maxBytes = 2 * 1024 * 1024; // 2MB
			if(isset($file['size']) && (int)$file['size'] > $maxBytes){
				return [ 'ok'=>false, 'error'=>'file_too_large' ];
			}

			$tmp = $file['tmp_name'] ?? '';
			if($tmp==='' || !is_uploaded_file($tmp)){
				return [ 'ok'=>false, 'error'=>'invalid_upload' ];
			}

			$mime = '';
			try{
				$finfo = new \finfo(FILEINFO_MIME_TYPE);
				$mime = (string)$finfo->file($tmp);
			}catch(\Exception $e){
				$mime = '';
			}

			$allowed = [
				'image/png' => 'png',
				'image/jpeg' => 'jpg',
				'image/webp' => 'webp'
			];
			if(!isset($allowed[$mime])){
				return [ 'ok'=>false, 'error'=>'invalid_type' ];
			}
			$ext = $allowed[$mime];

			$destDir = __DIR__ . '/../views/fotos/telas/';
			if(!is_dir($destDir)){
				@mkdir($destDir, 0775, true);
			}
			if(!is_dir($destDir)){
				return [ 'ok'=>false, 'error'=>'dest_dir_missing' ];
			}

			$name = 'tela_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
			$destPath = $destDir . $name;
			if(!@move_uploaded_file($tmp, $destPath)){
				return [ 'ok'=>false, 'error'=>'move_failed' ];
			}

			// Ruta pública relativa al proyecto (la usa el frontend)
			$publicPath = 'app/views/fotos/telas/' . $name;
			return [ 'ok'=>true, 'path'=>$publicPath ];
		}

		private function tablaTelasExiste(){
			try{
				$check = $this->ejecutarConsulta("SHOW TABLES LIKE 'tela'");
				return ($check && $check->rowCount() >= 1);
			}catch(\Exception $e){
				return false;
			}
		}


		/* ---------- Cliente: listado público de telas activas (JSON) ---------- */
		public function listarTelasPublicoControlador(){
			if(!$this->tablaTelasExiste()){
				return json_encode([
					'ok'=>true,
					'data'=>[],
					'message'=>'Tabla tela no existe (importa DB/tela.sql)'
				]);
			}

			try{
				$datos = $this->ejecutarConsulta("SELECT tela_id, tela_nombre, tela_descripcion, tela_precio, tela_stock, tela_textura_url FROM tela WHERE tela_activo=1 ORDER BY tela_nombre ASC");
				$rows = $datos ? $datos->fetchAll(\PDO::FETCH_ASSOC) : [];
				return json_encode([
					'ok'=>true,
					'data'=>$rows
				]);
			}catch(\Exception $e){
				return json_encode([
					'ok'=>false,
					'error'=>'list_failed'
				]);
			}
		}


		/* ---------- Admin: registrar tela ---------- */
		public function registrarTelaControlador(){
			if(!$this->sessionEsAdmin()){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Acceso denegado",
					"texto"=>"Solo administradores pueden gestionar telas",
					"icono"=>"error"
				]);
			}

			$nombre = $this->limpiarCadena($_POST['tela_nombre'] ?? '');
			$descripcion = $this->limpiarCadena($_POST['tela_descripcion'] ?? '');
			$precio = $this->limpiarCadena($_POST['tela_precio'] ?? '0');
			$stock = $this->limpiarCadena($_POST['tela_stock'] ?? '0');
			$texturaUrl = $this->limpiarCadena($_POST['tela_textura_url'] ?? '');
			$activo = isset($_POST['tela_activo']) ? (int)$_POST['tela_activo'] : 1;

			if($nombre==="" || $precio==="" || $stock===""){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos obligatorios",
					"icono"=>"error"
				]);
			}

			if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ \-]{2,80}",$nombre)){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE no coincide con el formato solicitado",
					"icono"=>"error"
				]);
			}

			if(!is_numeric($precio) || (float)$precio < 0){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO debe ser un número válido",
					"icono"=>"error"
				]);
			}

			if(!preg_match('/^\d+$/', (string)$stock)){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El STOCK debe ser un número entero",
					"icono"=>"error"
				]);
			}

			if(!$this->tablaTelasExiste()){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Falta tabla de telas",
					"texto"=>"Importa el script DB/tela.sql en tu base de datos",
					"icono"=>"error"
				]);
			}

			// Si viene archivo, lo guardamos y reemplaza la URL
			if(isset($_FILES['tela_textura_file'])){
				$up = $this->guardarTexturaSubida($_FILES['tela_textura_file']);
				if(!$up['ok']){
					$texto = 'No se pudo subir la textura.';
					if($up['error']==='file_too_large') $texto = 'La imagen supera el tamaño máximo (2MB).';
					if($up['error']==='invalid_type') $texto = 'Tipo de imagen no válido. Usa PNG, JPG o WebP.';
					return json_encode([
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>$texto,
						"icono"=>"error"
					]);
				}
				if($up['path']){
					$texturaUrl = $up['path'];
				}
			}

			$check_nombre = $this->ejecutarConsulta("SELECT tela_id FROM tela WHERE tela_nombre='$nombre'");
			if($check_nombre && $check_nombre->rowCount() > 0){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"La TELA ya está registrada",
					"icono"=>"error"
				]);
			}

			$tela_datos_reg=[
				[
					"campo_nombre"=>"tela_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"tela_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$descripcion
				],
				[
					"campo_nombre"=>"tela_precio",
					"campo_marcador"=>":Precio",
					"campo_valor"=>(float)$precio
				],
				[
					"campo_nombre"=>"tela_stock",
					"campo_marcador"=>":Stock",
					"campo_valor"=>(int)$stock
				],
				[
					"campo_nombre"=>"tela_textura_url",
					"campo_marcador"=>":TexturaUrl",
					"campo_valor"=>($texturaUrl!=='' ? $texturaUrl : null)
				],
				[
					"campo_nombre"=>"tela_activo",
					"campo_marcador"=>":Activo",
					"campo_valor"=>($activo===0 ? 0 : 1)
				]
			];

			$registrar = $this->guardarDatos("tela", $tela_datos_reg);

			if($registrar && $registrar->rowCount()==1){
				$this->registrarLogAccion("Alta de tela: ".$nombre);
				return json_encode([
					"tipo"=>"limpiar",
					"titulo"=>"Tela registrada",
					"texto"=>"La tela ".$nombre." se registró con éxito",
					"icono"=>"success"
				]);
			}

			return json_encode([
				"tipo"=>"simple",
				"titulo"=>"Ocurrió un error inesperado",
				"texto"=>"No se pudo registrar la tela, por favor intente nuevamente",
				"icono"=>"error"
			]);
		}


		/* ---------- Admin: obtener tela por id (para vista update) ---------- */
		public function obtenerTelaPorIdControlador($telaId){
			$telaId = (int)$this->limpiarCadena($telaId);
			if($telaId<=0 || !$this->tablaTelasExiste()){
				return null;
			}
			try{
				$sql = $this->ejecutarConsulta("SELECT * FROM tela WHERE tela_id=$telaId LIMIT 1");
				return $sql ? $sql->fetch(\PDO::FETCH_ASSOC) : null;
			}catch(\Exception $e){
				return null;
			}
		}


		/* ---------- Admin: actualizar tela ---------- */
		public function actualizarTelaControlador(){
			if(!$this->sessionEsAdmin()){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Acceso denegado",
					"texto"=>"Solo administradores pueden gestionar telas",
					"icono"=>"error"
				]);
			}

			$telaId = (int)($this->limpiarCadena($_POST['tela_id'] ?? '0'));
			$nombre = $this->limpiarCadena($_POST['tela_nombre'] ?? '');
			$descripcion = $this->limpiarCadena($_POST['tela_descripcion'] ?? '');
			$precio = $this->limpiarCadena($_POST['tela_precio'] ?? '0');
			$stock = $this->limpiarCadena($_POST['tela_stock'] ?? '0');
			$texturaUrl = $this->limpiarCadena($_POST['tela_textura_url'] ?? '');
			$activo = isset($_POST['tela_activo']) ? (int)$_POST['tela_activo'] : 1;

			if($telaId<=0 || $nombre===""){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"Datos inválidos",
					"icono"=>"error"
				]);
			}

			if($this->verificarDatos("[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ \-]{2,80}",$nombre)){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El NOMBRE no coincide con el formato solicitado",
					"icono"=>"error"
				]);
			}

			if(!$this->tablaTelasExiste()){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Falta tabla de telas",
					"texto"=>"Importa el script DB/tela.sql en tu base de datos",
					"icono"=>"error"
				]);
			}

			$actual = $this->obtenerTelaPorIdControlador($telaId);
			if(!$actual){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"No encontrado",
					"texto"=>"La tela no existe",
					"icono"=>"error"
				]);
			}

			// Si viene archivo, lo guardamos y reemplaza la URL
			if(isset($_FILES['tela_textura_file'])){
				$up = $this->guardarTexturaSubida($_FILES['tela_textura_file']);
				if(!$up['ok']){
					$texto = 'No se pudo subir la textura.';
					if($up['error']==='file_too_large') $texto = 'La imagen supera el tamaño máximo (2MB).';
					if($up['error']==='invalid_type') $texto = 'Tipo de imagen no válido. Usa PNG, JPG o WebP.';
					return json_encode([
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>$texto,
						"icono"=>"error"
					]);
				}
				if($up['path']){
					$texturaUrl = $up['path'];
				}
			}

			if(!is_numeric($precio) || (float)$precio < 0){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El PRECIO debe ser un número válido",
					"icono"=>"error"
				]);
			}

			if(!preg_match('/^\d+$/', (string)$stock)){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"El STOCK debe ser un número entero",
					"icono"=>"error"
				]);
			}

			// Si cambia el nombre, verificar duplicado
			if($actual['tela_nombre'] !== $nombre){
				$check_nombre = $this->ejecutarConsulta("SELECT tela_id FROM tela WHERE tela_nombre='$nombre' AND tela_id<>'$telaId'");
				if($check_nombre && $check_nombre->rowCount() > 0){
					return json_encode([
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"Ya existe otra tela con ese nombre",
						"icono"=>"error"
					]);
				}
			}

			$tela_datos_upd=[
				[
					"campo_nombre"=>"tela_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$nombre
				],
				[
					"campo_nombre"=>"tela_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$descripcion
				],
				[
					"campo_nombre"=>"tela_precio",
					"campo_marcador"=>":Precio",
					"campo_valor"=>(float)$precio
				],
				[
					"campo_nombre"=>"tela_stock",
					"campo_marcador"=>":Stock",
					"campo_valor"=>(int)$stock
				],
				[
					"campo_nombre"=>"tela_textura_url",
					"campo_marcador"=>":TexturaUrl",
					"campo_valor"=>($texturaUrl!=='' ? $texturaUrl : null)
				],
				[
					"campo_nombre"=>"tela_activo",
					"campo_marcador"=>":Activo",
					"campo_valor"=>($activo===0 ? 0 : 1)
				]
			];

			$condicion=[
				"condicion_campo"=>"tela_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$telaId
			];

			$actualizar = $this->actualizarDatos("tela", $tela_datos_upd, $condicion);
			if($actualizar && $actualizar->rowCount()>=0){
				$this->registrarLogAccion("Actualización de tela: ".$nombre);
				return json_encode([
					"tipo"=>"recargar",
					"titulo"=>"Tela actualizada",
					"texto"=>"Los datos se actualizaron correctamente",
					"icono"=>"success"
				]);
			}

			return json_encode([
				"tipo"=>"simple",
				"titulo"=>"Ocurrió un error inesperado",
				"texto"=>"No se pudo actualizar la tela",
				"icono"=>"error"
			]);
		}


		/* ---------- Admin: eliminar tela ---------- */
		public function eliminarTelaControlador(){
			if(!$this->sessionEsAdmin()){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Acceso denegado",
					"texto"=>"Solo administradores pueden gestionar telas",
					"icono"=>"error"
				]);
			}

			$telaId = (int)($this->limpiarCadena($_POST['tela_id'] ?? '0'));
			if($telaId<=0 || !$this->tablaTelasExiste()){
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No se pudo eliminar la tela",
					"icono"=>"error"
				]);
			}

			$actual = $this->obtenerTelaPorIdControlador($telaId);
			$eliminar = $this->eliminarRegistro("tela", "tela_id", $telaId);
			if($eliminar && $eliminar->rowCount()==1){
				$this->registrarLogAccion("Eliminación de tela: ".($actual['tela_nombre'] ?? (string)$telaId));
				return json_encode([
					"tipo"=>"recargar",
					"titulo"=>"Tela eliminada",
					"texto"=>"Registro eliminado correctamente",
					"icono"=>"success"
				]);
			}

			return json_encode([
				"tipo"=>"simple",
				"titulo"=>"Ocurrió un error inesperado",
				"texto"=>"No se pudo eliminar la tela",
				"icono"=>"error"
			]);
		}


		/* ---------- Admin: listar inventario de telas ---------- */
		public function listarTelasAdminControlador($pagina,$registros,$url,$busqueda){
			$pagina=$this->limpiarCadena($pagina);
			$registros=$this->limpiarCadena($registros);
			$url=$this->limpiarCadena($url);
			$url=APP_URL.$url."/";
			$busqueda=$this->limpiarCadena($busqueda);

			if(!$this->tablaTelasExiste()){
				return '<div class="notification is-warning">No existe la tabla <strong>tela</strong>. Importa <strong>DB/tela.sql</strong>.</div>';
			}

			$tabla="";
			$pagina = (isset($pagina) && $pagina>0) ? (int)$pagina : 1;
			$inicio = ($pagina>0) ? (($pagina*$registros)-$registros) : 0;

			if(isset($busqueda) && $busqueda!=""){
				$consulta_datos="SELECT * FROM tela WHERE tela_nombre LIKE '%$busqueda%' ORDER BY tela_nombre ASC LIMIT $inicio,$registros";
				$consulta_total="SELECT COUNT(tela_id) FROM tela WHERE tela_nombre LIKE '%$busqueda%'";
			}else{
				$consulta_datos="SELECT * FROM tela ORDER BY tela_nombre ASC LIMIT $inicio,$registros";
				$consulta_total="SELECT COUNT(tela_id) FROM tela";
			}

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos ? $datos->fetchAll() : [];
			$total = $this->ejecutarConsulta($consulta_total);
			$total = $total ? (int)$total->fetchColumn() : 0;

			$numeroPaginas = ($registros>0) ? (int)ceil($total/$registros) : 1;

			$tabla.='
				<div class="table-container">
				<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
					<thead>
						<tr>
							<th class="has-text-centered">#</th>
							<th class="has-text-centered">Nombre</th>
							<th class="has-text-centered">Precio</th>
							<th class="has-text-centered">Stock</th>
							<th class="has-text-centered">Activo</th>
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
						<tr class="has-text-centered">
							<td>'.$contador.'</td>
							<td>'.htmlspecialchars($rows['tela_nombre']).'</td>
							<td>'.MONEDA_SIMBOLO.number_format((float)$rows['tela_precio'],2).'</td>
							<td>'.(int)$rows['tela_stock'].'</td>
							<td>'.(((int)$rows['tela_activo']===1)?'Sí':'No').'</td>
							<td>
								<a href="'.APP_URL.'fabricUpdate/'.$rows['tela_id'].'/" class="button is-success is-rounded is-small">
									<i class="fas fa-sync fa-fw"></i>
								</a>
							</td>
							<td>
								<form class="FormularioAjax" action="'.APP_URL.'app/ajax/telaAjax.php" method="POST" autocomplete="off">
									<input type="hidden" name="modulo_tela" value="eliminar">
									<input type="hidden" name="tela_id" value="'.$rows['tela_id'].'">
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
						<tr class="has-text-centered">
							<td colspan="7">
								<a href="'.$url.'1/" class="button is-link is-rounded is-small mt-4 mb-4">Haga clic acá para recargar el listado</a>
							</td>
						</tr>
					';
				}else{
					$tabla.='
						<tr class="has-text-centered">
							<td colspan="7">No hay registros en el sistema</td>
						</tr>
					';
				}
			}

			$tabla.='</tbody></table></div>';

			if($total>0 && $pagina<=$numeroPaginas){
				$tabla.='<p class="has-text-right">Mostrando telas <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';
				$tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
			}

			return $tabla;
		}
	}
