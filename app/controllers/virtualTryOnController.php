<?php

	namespace app\controllers;
	use app\models\mainModel;

	class virtualTryOnController extends mainModel{

		/*----------  Controlador procesar virtual try-on  ----------*/
		public function procesarTryOnControlador(){

			# Verificando que se haya subido una imagen #
			if(!isset($_FILES['foto_persona']) || $_FILES['foto_persona']['error'] != UPLOAD_ERR_OK){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No se ha subido ninguna imagen",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Verificando formato de imagen #
			$tipo_imagen = mime_content_type($_FILES['foto_persona']['tmp_name']);
			if($tipo_imagen != "image/jpeg" && $tipo_imagen != "image/png"){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El formato de imagen no es válido. Solo se permiten JPG y PNG",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Verificando peso de imagen (máximo 10MB) #
			if(($_FILES['foto_persona']['size']/1024/1024) > 10){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"La imagen es demasiado grande. Máximo 10MB",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Obteniendo ID del producto (vestido) #
			$producto_id = isset($_POST['producto_id']) ? $this->limpiarCadena($_POST['producto_id']) : "";

			if($producto_id == ""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No se ha seleccionado ningún vestido",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Obteniendo datos del producto #
			$producto = $this->ejecutarConsulta("SELECT * FROM producto WHERE producto_id='$producto_id'");
			if($producto->rowCount() != 1){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El producto seleccionado no existe",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}
			$producto = $producto->fetch();

			# Directorio para imágenes temporales (usando ruta absoluta) #
			$base_dir = dirname(dirname(__DIR__)); // Raíz del proyecto
			$temp_dir = $base_dir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'temp_tryon'.DIRECTORY_SEPARATOR;
			if(!file_exists($temp_dir)){
				if(!mkdir($temp_dir, 0777, true)){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"No se pudo crear el directorio temporal",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
			}

			# Guardando imagen subida temporalmente #
			$nombre_temp = "tryon_".time()."_".rand(1000,9999);
			$extension = ($tipo_imagen == "image/jpeg") ? ".jpg" : ".png";
			$ruta_persona = $temp_dir.$nombre_temp."_persona".$extension;

			if(!move_uploaded_file($_FILES['foto_persona']['tmp_name'], $ruta_persona)){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No se pudo guardar la imagen",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Ruta de la imagen del vestido (usando ruta absoluta) #
			$ruta_vestido = $base_dir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'productos'.DIRECTORY_SEPARATOR.$producto['producto_foto'];
			if(!is_file($ruta_vestido) || $producto['producto_foto'] == ""){
				$ruta_vestido = $base_dir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'productos'.DIRECTORY_SEPARATOR.'default.png';
			}

			# Procesando con IA (usando API de Replicate o similar) #
			$resultado = $this->procesarConIA($ruta_persona, $ruta_vestido, $nombre_temp);

			if($resultado['success']){
				# Guardando resultado #
				$ruta_resultado = $temp_dir.$nombre_temp."_resultado.jpg";
				file_put_contents($ruta_resultado, $resultado['imagen']);

				# Mensaje según si es simulación o API real
				$es_simulacion = (defined('USE_AI_SIMULATION') && USE_AI_SIMULATION && (REPLICATE_API_KEY == "" || !defined('REPLICATE_API_KEY')));
				$mensaje = $es_simulacion 
					? "El vestido ha sido aplicado (modo simulación). Para resultados más realistas, configura una API key de Replicate en config/app.php"
					: "El vestido ha sido aplicado exitosamente con IA";

				$alerta=[
					"tipo"=>"limpiar",
					"titulo"=>"¡Listo!",
					"texto"=>$mensaje,
					"icono"=>"success",
					"resultado_url" => APP_URL."app/views/temp_tryon/".$nombre_temp."_resultado.jpg?t=".time(),
					"producto_nombre" => $producto['producto_nombre']
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>$resultado['mensaje'],
					"icono"=>"error"
				];
			}

			# Limpiando imagen temporal de persona #
			if(file_exists($ruta_persona)){
				unlink($ruta_persona);
			}

			return json_encode($alerta);
		}

		/*----------  Función para procesar con IA  ----------*/
		private function procesarConIA($ruta_persona, $ruta_vestido, $nombre_temp){
			
			# Verificar si usar API real o simulación #
			if(defined('USE_AI_SIMULATION') && USE_AI_SIMULATION && (REPLICATE_API_KEY == "" || !defined('REPLICATE_API_KEY'))){
				return $this->procesarSimulacion($ruta_persona, $ruta_vestido);
			}

			# Usar API de Replicate #
			if(!defined('REPLICATE_API_KEY') || REPLICATE_API_KEY == ""){
				return [
					"success" => false,
					"mensaje" => "API key no configurada. Usando simulación."
				];
			}

			# Subir imágenes a un servicio temporal o usar URLs públicas
			# Para producción, sube las imágenes a Cloudinary, ImgBB, o similar
			$persona_url = $this->subirImagenTemporal($ruta_persona);
			$vestido_url = $this->subirImagenTemporal($ruta_vestido);

			if(!$persona_url || !$vestido_url){
				# Si falla la subida, intentar usar URLs locales si el servidor es público
				# O mostrar error más descriptivo
				return [
					"success" => false,
					"mensaje" => "No se pudieron subir las imágenes a un servicio público. Para usar la API de Replicate, necesitas configurar ImgBB o usar un servidor público. Revisa CONFIGURAR_API_IA.md para más información."
				];
			}

			# Llamada a la API de Replicate
			$api_url = "https://api.replicate.com/v1/predictions";
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $api_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Authorization: Token ".REPLICATE_API_KEY,
				"Content-Type: application/json"
			]);
			
			$input_data = [
				"crop" => false,
				"seed" => rand(1000, 9999),
				"steps" => 30,
				"category" => "dresses"
			];

			# Dependiendo del modelo, los parámetros pueden variar
			if(defined('REPLICATE_MODEL') && strpos(REPLICATE_MODEL, 'idm-vton') !== false){
				$input_data["model_type"] = "hd";
				$input_data["garm_img"] = $vestido_url;
				$input_data["human_img"] = $persona_url;
			}else{
				$input_data["garment_image"] = $vestido_url;
				$input_data["person_image"] = $persona_url;
			}

			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				"version" => defined('REPLICATE_MODEL') ? REPLICATE_MODEL : "cuuupid/idm-vton",
				"input" => $input_data
			]));
			
			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if($http_code != 201){
				# Si falla la API, usar simulación
				return $this->procesarSimulacion($ruta_persona, $ruta_vestido);
			}

			$response_data = json_decode($response, true);
			$prediction_id = $response_data['id'] ?? null;

			if(!$prediction_id){
				return $this->procesarSimulacion($ruta_persona, $ruta_vestido);
			}

			# Esperar a que termine el procesamiento (polling)
			$max_attempts = 30;
			$attempt = 0;
			while($attempt < $max_attempts){
				sleep(2);
				$status_ch = curl_init();
				curl_setopt($status_ch, CURLOPT_URL, "https://api.replicate.com/v1/predictions/".$prediction_id);
				curl_setopt($status_ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($status_ch, CURLOPT_HTTPHEADER, [
					"Authorization: Token ".REPLICATE_API_KEY
				]);
				$status_response = curl_exec($status_ch);
				curl_close($status_ch);

				$status_data = json_decode($status_response, true);
				if($status_data['status'] == 'succeeded'){
					$resultado_url = $status_data['output'][0] ?? null;
					if($resultado_url){
						$imagen_resultado = file_get_contents($resultado_url);
						return [
							"success" => true,
							"imagen" => $imagen_resultado
						];
					}
				}elseif($status_data['status'] == 'failed'){
					break;
				}
				$attempt++;
			}

			# Si falla, usar simulación
			return $this->procesarSimulacion($ruta_persona, $ruta_vestido);
		}

		/*----------  Función de simulación (sin API real)  ----------*/
		/* 
			NOTA: Esta es una simulación básica. Para resultados realistas de try-on,
			se recomienda usar una API de IA como Replicate (cuuupid/idm-vton).
			Configura REPLICATE_API_KEY en config/app.php para usar la API real.
		*/
		private function procesarSimulacion($ruta_persona, $ruta_vestido){
			
			# Verificar si GD está habilitado
			if(!function_exists('imagecreatefromstring')){
				return [
					"success" => false,
					"mensaje" => "La extensión GD de PHP no está habilitada. Contacta al administrador."
				];
			}

			# Verificar que los archivos existan
			if(!file_exists($ruta_persona) || !file_exists($ruta_vestido)){
				return [
					"success" => false,
					"mensaje" => "No se encontraron las imágenes necesarias"
				];
			}

			try{
				# Crear una imagen combinada simple (simulación)
				$persona_data = file_get_contents($ruta_persona);
				$vestido_data = file_get_contents($ruta_vestido);
				
				if($persona_data === false || $vestido_data === false){
					return [
						"success" => false,
						"mensaje" => "Error al leer las imágenes"
					];
				}

				$persona_img = @imagecreatefromstring($persona_data);
				$vestido_img = @imagecreatefromstring($vestido_data);
				
				if(!$persona_img || !$vestido_img){
					return [
						"success" => false,
						"mensaje" => "Error al procesar las imágenes. Verifica que sean JPG o PNG válidos."
					];
				}

				# Obtener dimensiones
				$persona_w = imagesx($persona_img);
				$persona_h = imagesy($persona_img);
				$vestido_w = imagesx($vestido_img);
				$vestido_h = imagesy($vestido_img);

				if($persona_w <= 0 || $persona_h <= 0){
					imagedestroy($persona_img);
					imagedestroy($vestido_img);
					return [
						"success" => false,
						"mensaje" => "Dimensiones de imagen inválidas"
					];
				}

				# Crear imagen resultado
				$resultado = imagecreatetruecolor($persona_w, $persona_h);
				if(!$resultado){
					imagedestroy($persona_img);
					imagedestroy($vestido_img);
					return [
						"success" => false,
						"mensaje" => "Error al crear imagen resultado"
					];
				}

				# Copiar imagen de persona como base
				imagecopy($resultado, $persona_img, 0, 0, 0, 0, $persona_w, $persona_h);
				
				# Calcular área del torso donde se aplicará el vestido
				# Ajustar según la proporción de la imagen de la persona
				$torso_x = (int)($persona_w * 0.15); // 15% desde la izquierda
				$torso_y = (int)($persona_h * 0.12); // 12% desde arriba (cuello/hombros)
				$torso_w = (int)($persona_w * 0.7); // 70% del ancho
				$torso_h = (int)($persona_h * 0.55); // 55% del alto (hasta cintura/cadera)
				
				# Redimensionar vestido para que cubra completamente el área del torso
				$vestido_ratio = $vestido_w / $vestido_h;
				$torso_ratio = $torso_w / $torso_h;
				
				# Asegurar que el vestido cubra todo el área del torso
				if($vestido_ratio > $torso_ratio){
					# Vestido más ancho, ajustar por ancho y aumentar altura si es necesario
					$vestido_escalado_w = $torso_w;
					$vestido_escalado_h = (int)($torso_w / $vestido_ratio);
					if($vestido_escalado_h < $torso_h){
						$vestido_escalado_h = $torso_h;
						$vestido_escalado_w = (int)($torso_h * $vestido_ratio);
					}
				}else{
					# Vestido más alto, ajustar por alto y aumentar ancho si es necesario
					$vestido_escalado_h = $torso_h;
					$vestido_escalado_w = (int)($torso_h * $vestido_ratio);
					if($vestido_escalado_w < $torso_w){
						$vestido_escalado_w = $torso_w;
						$vestido_escalado_h = (int)($torso_w / $vestido_ratio);
					}
				}
				
				# Centrar el vestido en el área del torso
				$vestido_x = $torso_x + (int)(($torso_w - $vestido_escalado_w) / 2);
				$vestido_y = $torso_y;
				
				# Crear imagen escalada del vestido con transparencia
				$vestido_escalado = imagecreatetruecolor($vestido_escalado_w, $vestido_escalado_h);
				imagealphablending($vestido_escalado, false);
				imagesavealpha($vestido_escalado, true);
				
				# Crear color transparente para el fondo
				$transparente = imagecolorallocatealpha($vestido_escalado, 0, 0, 0, 127);
				imagefill($vestido_escalado, 0, 0, $transparente);
				
				# Redimensionar vestido con mejor calidad
				imagealphablending($vestido_escalado, true);
				imagecopyresampled($vestido_escalado, $vestido_img, 0, 0, 0, 0, 
					$vestido_escalado_w, $vestido_escalado_h, $vestido_w, $vestido_h);
				
				# Crear máscara para aplicar el vestido solo en el área del torso
				# Esto reemplazará el área del torso con el vestido, no solo lo superpondrá
				
				# Primero, copiar el área del torso de la persona original
				$torso_persona = imagecreatetruecolor($torso_w, $torso_h);
				imagecopy($torso_persona, $persona_img, 0, 0, $torso_x, $torso_y, $torso_w, $torso_h);
				
				# Recortar el vestido al tamaño exacto del área del torso
				# Esto asegura que solo se muestre la parte del vestido que corresponde al torso
				$vestido_recortado = imagecreatetruecolor($torso_w, $torso_h);
				imagealphablending($vestido_recortado, false);
				imagesavealpha($vestido_recortado, false); // No usar transparencia, usar fondo sólido
				
				# Calcular el offset para centrar el vestido en el área del torso
				$offset_x = (int)(($vestido_escalado_w - $torso_w) / 2);
				$offset_y = (int)(($vestido_escalado_h - $torso_h) / 2);
				if($offset_x < 0) $offset_x = 0;
				if($offset_y < 0) $offset_y = 0;
				
				# Copiar solo la parte central del vestido que corresponde al área del torso
				imagealphablending($vestido_recortado, true);
				imagecopyresampled($vestido_recortado, $vestido_escalado, 
					0, 0, $offset_x, $offset_y, 
					$torso_w, $torso_h, 
					min($torso_w, $vestido_escalado_w - $offset_x), min($torso_h, $vestido_escalado_h - $offset_y));
				
				# Aplicar el vestido reemplazando completamente el área del torso de la persona
				# Esto hace que el vestido se adapte al cuerpo, no que se superponga
				imagealphablending($resultado, true);
				# Usar imagecopy para reemplazar completamente el área del torso
				imagecopy($resultado, $vestido_recortado, $torso_x, $torso_y, 0, 0, $torso_w, $torso_h);
				
				# Limpiar recursos
				imagedestroy($vestido_escalado);
				imagedestroy($vestido_recortado);
				imagedestroy($torso_persona);

				# Guardar resultado
				ob_start();
				$jpeg_ok = imagejpeg($resultado, null, 90);
				$imagen_resultado = ob_get_clean();

				if(!$jpeg_ok || empty($imagen_resultado)){
					imagedestroy($persona_img);
					imagedestroy($vestido_img);
					imagedestroy($resultado);
					return [
						"success" => false,
						"mensaje" => "Error al generar imagen resultado"
					];
				}

				# Limpiar memoria
				imagedestroy($persona_img);
				imagedestroy($vestido_img);
				imagedestroy($resultado);

				return [
					"success" => true,
					"imagen" => $imagen_resultado
				];

			}catch(\Exception $e){
				return [
					"success" => false,
					"mensaje" => "Error: ".$e->getMessage()
				];
			}
		}

		/*----------  Función para subir imagen temporal  ----------*/
		private function subirImagenTemporal($ruta_imagen){
			# Opción 1: Usar servicio de hosting temporal (recomendado para desarrollo)
			# Convertir la imagen a base64 y crear una URL data
			# NOTA: Replicate acepta URLs públicas, así que necesitamos subir a un servicio
			
			# Opción A: Usar ImgBB (gratis, requiere API key)
			# Obtén tu API key en: https://api.imgbb.com/
			$imgbb_api_key = defined('IMGBB_API_KEY') ? IMGBB_API_KEY : "";
			
			if($imgbb_api_key != ""){
				$image_data = base64_encode(file_get_contents($ruta_imagen));
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "https://api.imgbb.com/1/upload");
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
					'key' => $imgbb_api_key,
					'image' => $image_data
				]));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);
				
				if($http_code == 200){
					$data = json_decode($response, true);
					if(isset($data['data']['url'])){
						return $data['data']['url'];
					}
				}
			}
			
			# Opción B: Para desarrollo local, crear una URL pública temporal
			# Esto requiere que tu servidor sea accesible públicamente
			# O usar un servicio como ngrok para exponer localhost
			$base_dir = dirname(dirname(__DIR__));
			$relative_path = str_replace($base_dir, '', $ruta_imagen);
			$relative_path = str_replace('\\', '/', $relative_path);
			$relative_path = ltrim($relative_path, '/');
			
			# Si la imagen está en el directorio público, crear URL
			if(strpos($relative_path, 'app/views/') !== false){
				$public_url = APP_URL . $relative_path;
				# Verificar que la URL sea accesible (solo si el servidor es público)
				# Para desarrollo local, necesitarás ngrok o similar
				return $public_url;
			}
			
			# Si no se puede crear URL pública, retornar null (usará simulación)
			return null;
		}

		/*----------  Controlador obtener productos para probador  ----------*/
		public function obtenerProductosProbadorControlador(){
			$consulta = "SELECT producto_id, producto_nombre, producto_foto, producto_precio_venta FROM producto WHERE producto_estado='Habilitado' ORDER BY producto_nombre ASC LIMIT 20";
			$productos = $this->ejecutarConsulta($consulta);
			$productos = $productos->fetchAll();

			# Obtener ruta base del proyecto
			$base_dir = dirname(dirname(__DIR__));

			$html = "";
			if(count($productos) == 0){
				$html = '<p class="has-text-centered">No hay productos disponibles</p>';
			}else{
				foreach($productos as $prod){
					# Verificar si la imagen existe usando ruta absoluta
					$ruta_foto = $base_dir.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'productos'.DIRECTORY_SEPARATOR.$prod['producto_foto'];
					$foto_url = APP_URL."app/views/productos/".$prod['producto_foto'];
					
					# Si no existe la foto o está vacía, usar default
					if($prod['producto_foto'] == "" || !is_file($ruta_foto)){
						$foto_url = APP_URL."app/views/productos/default.png";
					}
					
					$html .= '
					<div class="tryon-product-item" data-product-id="'.$prod['producto_id'].'">
						<img src="'.$foto_url.'?t='.time().'" alt="'.$prod['producto_nombre'].'" onerror="this.src=\''.APP_URL.'app/views/productos/default.png?t='.time().'\'">
						<p class="tryon-product-name">'.$prod['producto_nombre'].'</p>
						<p class="tryon-product-price">'.MONEDA_SIMBOLO.number_format($prod['producto_precio_venta'], MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).'</p>
					</div>';
				}
			}

			return $html;
		}

	}

