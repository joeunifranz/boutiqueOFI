<?php

namespace app\controllers;

use app\models\mainModel;

class encajeController extends mainModel{

	private function sesionEsAdminLocal(): bool{
		if(!isset($_SESSION['id']) || $_SESSION['id']===""){
			return false;
		}
		if(isset($_SESSION['rol']) && $_SESSION['rol']==="Administrador"){
			return true;
		}
		if(isset($_SESSION['usuario']) && $_SESSION['usuario']==="Administrador"){
			return true;
		}
		if((int)$_SESSION['id']===1){
			return true;
		}
		return false;
	}

	private function tablaEncajeExiste(): bool{
		try{
			$check = $this->conectar()->query("SHOW TABLES LIKE 'encaje'");
			return ($check && $check->rowCount() >= 1);
		}catch(\Throwable $e){
			return false;
		}
	}

	private function ensureTablaEncaje(): bool{
		if($this->tablaEncajeExiste()){
			return true;
		}
		try{
			$this->ejecutarConsulta(
				"CREATE TABLE IF NOT EXISTS `encaje` (
					`encaje_id` INT NOT NULL AUTO_INCREMENT,
					`encaje_nombre` VARCHAR(140) NOT NULL,
					`encaje_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
					`encaje_imagen` VARCHAR(255) NULL,
					`encaje_activo` TINYINT(1) NOT NULL DEFAULT 1,
					`encaje_creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (`encaje_id`),
					UNIQUE KEY `uq_encaje_nombre` (`encaje_nombre`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8"
			);

			// Seed inicial (para no depender de importar SQL manualmente)
			try{
				$cnt = $this->conectar()->query('SELECT COUNT(*) FROM encaje');
				$n = $cnt ? (int)$cnt->fetchColumn() : 0;
				if($n === 0){
					$this->ejecutarConsulta(
						"INSERT INTO encaje (encaje_nombre, encaje_precio, encaje_imagen, encaje_activo) VALUES
						('Rosas con piedras del color del vestido',450.00,NULL,1),
						('Rosas con brillo pedrería plateada',570.00,NULL,1),
						('Ramas con pedrería plateada',570.00,NULL,1),
						('Bordado con pedrería',525.00,NULL,1),
						('Encaje en 3D',450.00,NULL,1),
						('Vipiur de hojas',525.00,NULL,1),
						('Vipiur hojas 3D',525.00,NULL,1),
						('Encaje sin pedrería',450.00,NULL,1),
						('Vipiur de rosas con poca pedrería',450.00,NULL,1),
						('Pedrería diseñada de rosas (pura piedra)',525.00,NULL,1)"
					);
				}
			}catch(\Throwable $e){
				// ignore
			}
			return true;
		}catch(\Throwable $e){
			return false;
		}
	}

	private function guardarImagenEncaje(array $file): ?string{
		try{
			if(!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])){
				return null;
			}
			if(!isset($file['name'])){
				return null;
			}

			$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
			$permitidas = ['jpg','jpeg','png','webp'];
			if(!in_array($ext, $permitidas, true)){
				return null;
			}

			$destDir = __DIR__ . '/../views/fotos/encajes/';
			if(!is_dir($destDir)){
				@mkdir($destDir, 0775, true);
			}
			if(!is_dir($destDir)){
				return null;
			}

			$name = 'encaje_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
			$destPath = $destDir . $name;
			if(!move_uploaded_file($file['tmp_name'], $destPath)){
				return null;
			}

			return 'app/views/fotos/encajes/' . $name;
		}catch(\Throwable $e){
			return null;
		}
	}

	/* ---------- Cliente: listado público de encajes activos (JSON) ---------- */
	public function listarEncajesPublicoControlador(){
		if(!$this->ensureTablaEncaje()){
			return json_encode(['ok'=>false,'message'=>'Tabla encaje no existe (importa DB/encaje.sql)']);
		}
		try{
			$datos = $this->ejecutarConsulta("SELECT encaje_id, encaje_nombre, encaje_precio, encaje_imagen FROM encaje WHERE encaje_activo=1 ORDER BY encaje_nombre ASC");
			$rows = $datos ? $datos->fetchAll() : [];
			return json_encode(['ok'=>true,'data'=>is_array($rows)?$rows:[]]);
		}catch(\Throwable $e){
			return json_encode(['ok'=>false,'message'=>'No se pudo listar encajes']);
		}
	}

	/* ---------- Admin: registrar encaje ---------- */
	public function registrarEncajeControlador(){
		if(!$this->sesionEsAdminLocal()){
			return json_encode([
				'tipo'=>'simple',
				'titulo'=>'Acceso restringido',
				'texto'=>'Solo administradores pueden gestionar encajes',
				'icono'=>'error'
			]);
		}

		if(!$this->ensureTablaEncaje()){
			return json_encode([
				'tipo'=>'simple',
				'titulo'=>'Error',
				'texto'=>'No se pudo crear la tabla encaje. Verifica la BD.',
				'icono'=>'error'
			]);
		}

		$nombre = $this->limpiarCadena($_POST['encaje_nombre'] ?? '');
		$precio = $this->limpiarCadena($_POST['encaje_precio'] ?? '0');
		$activo = isset($_POST['encaje_activo']) ? (int)$_POST['encaje_activo'] : 1;

		if(trim($nombre)===''){
			return json_encode(['tipo'=>'simple','titulo'=>'Campo requerido','texto'=>'El nombre es obligatorio','icono'=>'error']);
		}

		if(!is_numeric($precio)){
			return json_encode(['tipo'=>'simple','titulo'=>'Precio inválido','texto'=>'Ingresa un precio válido','icono'=>'error']);
		}

		$imgPath = null;
		if(isset($_FILES['encaje_imagen'])){
			$imgPath = $this->guardarImagenEncaje($_FILES['encaje_imagen']);
		}

		try{
			$stmt = $this->conectar()->prepare('INSERT INTO encaje (encaje_nombre, encaje_precio, encaje_imagen, encaje_activo) VALUES (:n,:p,:i,:a)');
			$stmt->bindValue(':n', $nombre);
			$stmt->bindValue(':p', (float)$precio);
			$stmt->bindValue(':i', $imgPath);
			$stmt->bindValue(':a', (int)$activo, \PDO::PARAM_INT);
			$stmt->execute();

			return json_encode(['tipo'=>'recargar','titulo'=>'Listo','texto'=>'Encaje registrado','icono'=>'success']);
		}catch(\Throwable $e){
			return json_encode(['tipo'=>'simple','titulo'=>'Error','texto'=>'No se pudo registrar el encaje (¿nombre duplicado?)','icono'=>'error']);
		}
	}

	public function obtenerEncajePorIdControlador(int $encajeId): ?array{
		if(!$this->sesionEsAdminLocal()){
			return null;
		}
		if($encajeId <= 0){
			return null;
		}
		if(!$this->ensureTablaEncaje()){
			return null;
		}
		try{
			$stmt = $this->conectar()->prepare('SELECT encaje_id, encaje_nombre, encaje_precio, encaje_imagen, encaje_activo FROM encaje WHERE encaje_id=:id LIMIT 1');
			$stmt->bindValue(':id', $encajeId, \PDO::PARAM_INT);
			$stmt->execute();
			$row = $stmt->fetch();
			return $row ? $row : null;
		}catch(\Throwable $e){
			return null;
		}
	}

	public function actualizarEncajeControlador(){
		if(!$this->sesionEsAdminLocal()){
			return json_encode([
				'tipo'=>'simple',
				'titulo'=>'Acceso restringido',
				'texto'=>'Solo administradores pueden gestionar encajes',
				'icono'=>'error'
			]);
		}

		if(!$this->ensureTablaEncaje()){
			return json_encode([
				'tipo'=>'simple',
				'titulo'=>'Error',
				'texto'=>'No se pudo preparar la tabla encaje. Verifica la BD.',
				'icono'=>'error'
			]);
		}

		$encajeId = (int)($this->limpiarCadena($_POST['encaje_id'] ?? '0'));
		$nombre = $this->limpiarCadena($_POST['encaje_nombre'] ?? '');
		$precio = $this->limpiarCadena($_POST['encaje_precio'] ?? '0');
		$activo = isset($_POST['encaje_activo']) ? (int)$_POST['encaje_activo'] : 1;

		if($encajeId <= 0){
			return json_encode(['tipo'=>'simple','titulo'=>'Error','texto'=>'ID de encaje inválido','icono'=>'error']);
		}
		if(trim($nombre)===''){
			return json_encode(['tipo'=>'simple','titulo'=>'Campo requerido','texto'=>'El nombre es obligatorio','icono'=>'error']);
		}
		if(!is_numeric($precio)){
			return json_encode(['tipo'=>'simple','titulo'=>'Precio inválido','texto'=>'Ingresa un precio válido','icono'=>'error']);
		}

		// Verificar que exista
		$actual = $this->obtenerEncajePorIdControlador($encajeId);
		if(!$actual){
			return json_encode(['tipo'=>'simple','titulo'=>'No encontrado','texto'=>'No existe el encaje','icono'=>'error']);
		}

		$imgPath = null;
		if(isset($_FILES['encaje_imagen'])){
			$imgPath = $this->guardarImagenEncaje($_FILES['encaje_imagen']);
		}

		try{
			if($imgPath !== null){
				$stmt = $this->conectar()->prepare('UPDATE encaje SET encaje_nombre=:n, encaje_precio=:p, encaje_imagen=:i, encaje_activo=:a WHERE encaje_id=:id');
				$stmt->bindValue(':i', $imgPath);
			}else{
				$stmt = $this->conectar()->prepare('UPDATE encaje SET encaje_nombre=:n, encaje_precio=:p, encaje_activo=:a WHERE encaje_id=:id');
			}
			$stmt->bindValue(':n', $nombre);
			$stmt->bindValue(':p', (float)$precio);
			$stmt->bindValue(':a', (int)$activo, \PDO::PARAM_INT);
			$stmt->bindValue(':id', $encajeId, \PDO::PARAM_INT);
			$stmt->execute();

			return json_encode(['tipo'=>'recargar','titulo'=>'Listo','texto'=>'Encaje actualizado','icono'=>'success']);
		}catch(\Throwable $e){
			return json_encode(['tipo'=>'simple','titulo'=>'Error','texto'=>'No se pudo actualizar (¿nombre duplicado?)','icono'=>'error']);
		}
	}

	public function listarEncajesAdminControlador(): string{
		if(!$this->sesionEsAdminLocal()){
			return "<article class='message is-danger'><div class='message-body'>Acceso restringido</div></article>";
		}
		if(!$this->ensureTablaEncaje()){
			return "<article class='message is-warning'><div class='message-body'>Tabla encaje no existe. Importa <strong>DB/encaje.sql</strong>.</div></article>";
		}

		try{
			$stmt = $this->conectar()->prepare('SELECT encaje_id, encaje_nombre, encaje_precio, encaje_imagen, encaje_activo FROM encaje ORDER BY encaje_nombre ASC');
			$stmt->execute();
			$rows = $stmt->fetchAll();
			if(!is_array($rows) || empty($rows)){
				return "<article class='message is-light'><div class='message-body'>No hay encajes registrados.</div></article>";
			}

			$html = "<div class='table-container'><table class='table is-fullwidth is-striped is-hoverable'>";
			$html .= "<thead><tr><th>Foto</th><th>Nombre</th><th>Precio (1.5 m)</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>";
			foreach($rows as $r){
				$encajeId = (int)($r['encaje_id'] ?? 0);
				$img = (string)($r['encaje_imagen'] ?? '');
				$imgTag = '';
				if($img !== '' && is_file('./'.$img)){
					$imgTag = '<figure class="image is-64x64"><img style="object-fit:cover; width:64px; height:64px; border-radius:8px;" src="'.APP_URL.htmlspecialchars($img,ENT_QUOTES,'UTF-8').'" alt=""></figure>';
				}else{
					$imgTag = '<span class="tag is-light">Sin foto</span>';
				}

				$activo = ((int)($r['encaje_activo'] ?? 1) === 1);
				$html .= '<tr>';
				$html .= '<td>'.$imgTag.'</td>';
				$html .= '<td>'.htmlspecialchars((string)($r['encaje_nombre'] ?? ''),ENT_QUOTES,'UTF-8').'</td>';
				$html .= '<td>'.htmlspecialchars(MONEDA_SIMBOLO.number_format((float)($r['encaje_precio'] ?? 0),2).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8').'</td>';
				$html .= '<td>'.($activo ? '<span class="tag is-success">Sí</span>' : '<span class="tag is-dark">No</span>').'</td>';
				$html .= '<td><a class="button is-small is-link is-light" href="'.APP_URL.'encajeUpdate/'.(int)$encajeId.'/">Editar</a></td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';
			return $html;
		}catch(\Throwable $e){
			return "<article class='message is-danger'><div class='message-body'>No se pudo cargar la lista.</div></article>";
		}
	}
}
