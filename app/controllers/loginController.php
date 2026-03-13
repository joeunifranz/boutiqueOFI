<?php

	namespace app\controllers;
	use app\models\mainModel;
	use app\services\GoogleClientAuth;

	class loginController extends mainModel{

		/*----------  Controlador iniciar sesion  ----------*/
		public function iniciarSesionControlador(){

			$usuario=$this->limpiarCadena($_POST['login_usuario']);
		    $clave=$this->limpiarCadena($_POST['login_clave']);

		    # Verificando campos obligatorios #
		    if($usuario=="" || $clave==""){
				echo '<article class="message is-danger">
				  <div class="message-body">
				    <strong>Ocurrió un error inesperado</strong><br>
				    No has llenado todos los campos que son obligatorios
				  </div>
				</article>';
		    }else{

			    # Verificando integridad de los datos #
			    if($this->verificarDatos("[a-zA-Z0-9]{4,20}",$usuario)){
					echo '<article class="message is-danger">
					  <div class="message-body">
					    <strong>Ocurrió un error inesperado</strong><br>
					    El USUARIO no coincide con el formato solicitado
					  </div>
					</article>';
			    }else{

			    	# Verificando integridad de los datos #
				    if($this->verificarDatos("[a-zA-Z0-9$@.-]{7,100}",$clave)){
						echo '<article class="message is-danger">
						  <div class="message-body">
						    <strong>Ocurrió un error inesperado</strong><br>
						    La CLAVE no coincide con el formato solicitado
						  </div>
						</article>';
				    }else{

					    # Verificando usuario #
					    $check_usuario=$this->ejecutarConsulta("SELECT * FROM usuario WHERE usuario_usuario='$usuario'");

					    if($check_usuario->rowCount()==1){

					    	$check_usuario=$check_usuario->fetch();

					    	if($check_usuario['usuario_usuario']==$usuario && password_verify($clave,$check_usuario['usuario_clave'])){

				    			$_SESSION['id']=$check_usuario['usuario_id'];
					            $_SESSION['nombre']=$check_usuario['usuario_nombre'];
					            $_SESSION['apellido']=$check_usuario['usuario_apellido'];
					            $_SESSION['usuario']=$check_usuario['usuario_usuario'];
			            $_SESSION['rol']=$check_usuario['usuario_rol'] ?? 'Usuario';
					            $_SESSION['foto']=$check_usuario['usuario_foto'];
					            $_SESSION['caja']=$check_usuario['caja_id'];

					            $this->registrarLogAccion("Inicio de sesión");

		            	$redirect_to = $this->limpiarCadena($_POST['redirect_to'] ?? "");
			            if($redirect_to!=""){
			            	// Solo permitir rutas internas simples
			            	if(preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/',$redirect_to)){
		            			// Bloquear redirect a dashboard si no es admin
		            			if($redirect_to=="dashboard/" && !$this->sessionEsAdmin()){
		            				$redirect_to = "saleNew/";
		            			}
			            		if(headers_sent()){
				                	echo "<script> window.location.href='".APP_URL.$redirect_to."'; </script>";
				            	}else{
				            		header("Location: ".APP_URL.$redirect_to);
				            	}
				            	return;
				            }
			            }

		            			$destino = $this->sessionEsAdmin() ? "dashboard/" : "saleNew/";
		            			if(headers_sent()){
		                			echo "<script> window.location.href='".APP_URL.$destino."'; </script>";
		            			}else{
		            				header("Location: ".APP_URL.$destino);
		            			}

					    	}else{
					    		echo '<article class="message is-danger">
								  <div class="message-body">
								    <strong>Ocurrió un error inesperado</strong><br>
								    Usuario o clave incorrectos
								  </div>
								</article>';
					    	}

					    }else{
							echo '<article class="message is-danger">
							  <div class="message-body">
							    <strong>Ocurrió un error inesperado</strong><br>
							    Usuario o clave incorrectos
							  </div>
							</article>';
					    }
				    }
			    }
		    }
		}


		/*----------  Controlador redirigir a Google (cliente)  ----------*/
		public function redirigirGoogleClienteControlador(){

			$googleAuth = new GoogleClientAuth();

			if(!$googleAuth->configuracionValida()){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>Configuraci?n incompleta</strong><br>
						Debes configurar GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en <code>config/google_oauth.php</code>.
					</div>
				</article>';
				return;
			}

			$url = $googleAuth->obtenerUrlAutorizacion();

			if(headers_sent()){
				echo "<script> window.location.href='".htmlspecialchars($url,ENT_QUOTES,'UTF-8')."'; </script>";
			}else{
				header("Location: ".$url);
			}
		}


		/*----------  Controlador callback de Google para clientes  ----------*/
		public function procesarGoogleClienteCallbackControlador(){

			$googleAuth = new GoogleClientAuth();

			if(!$googleAuth->configuracionValida()){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>Configuraci?n incompleta</strong><br>
						Debes configurar GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en <code>config/google_oauth.php</code>.
					</div>
				</article>';
				return;
			}

			if(!isset($_GET['code']) || empty($_GET['code'])){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>No se pudo iniciar sesi?n</strong><br>
						No recibimos el c?digo de autorizaci?n de Google.
					</div>
				</article>';
				return;
			}

			$code = $this->limpiarCadena($_GET['code']);

			$userInfo = $googleAuth->obtenerUsuarioDesdeCode($code);
			if(!$userInfo || empty($userInfo['email'])){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>No se pudo obtener tu correo</strong><br>
						No recibimos un correo v?lido desde Google.
					</div>
				</article>';
				return;
			}

			$email = $this->limpiarCadena($userInfo['email']);
			$nombre = $this->limpiarCadena($userInfo['given_name'] ?? "");
			$apellido = $this->limpiarCadena($userInfo['family_name'] ?? "");

			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>Correo inv?lido</strong><br>
						El correo recibido de Google no es v?lido.
					</div>
				</article>';
				return;
			}

			# Buscar cliente por correo #
			$check_cliente = $this->ejecutarConsulta("SELECT * FROM cliente WHERE cliente_email='$email' LIMIT 1");

			if($check_cliente->rowCount()==1){
				$check_cliente = $check_cliente->fetch();

				$_SESSION['cliente_id']       = $check_cliente['cliente_id'];
				$_SESSION['cliente_nombre']   = $check_cliente['cliente_nombre'];
				$_SESSION['cliente_apellido'] = $check_cliente['cliente_apellido'];
				$_SESSION['cliente_email']    = $check_cliente['cliente_email'];

				if(headers_sent()){
					echo "<script> window.location.href='".APP_URL."productosCliente/'; </script>";
				}else{
					header("Location: ".APP_URL."productosCliente/");
				}
				return;
			}

			# Si no existe, guardar datos en sesi?n y enviar al formulario de registro #
			$_SESSION['google_cliente_email']    = $email;
			$_SESSION['google_cliente_nombre']   = $nombre;
			$_SESSION['google_cliente_apellido'] = $apellido;

			if(headers_sent()){
				echo "<script> window.location.href='".APP_URL."registroCliente/'; </script>";
			}else{
				header("Location: ".APP_URL."registroCliente/");
			}
		}


		/*----------  Controlador iniciar sesión de cliente por correo  ----------*/
		public function iniciarSesionClientePorCorreoControlador(){

			$email = $this->limpiarCadena($_POST['cliente_email'] ?? "");
			$redirect_to = $this->limpiarCadena($_POST['redirect_to'] ?? "");
			if($redirect_to!="" && !preg_match('/^[a-zA-Z0-9_\/-]{1,200}$/', $redirect_to)){
				$redirect_to = "";
			}

			if($email==""){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>Ocurrió un error inesperado</strong><br>
						Debes ingresar tu correo electrónico
					</div>
				</article>';
				return;
			}

			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>Ocurrió un error inesperado</strong><br>
						El correo electrónico no es válido
					</div>
				</article>';
				return;
			}

			$check_cliente = $this->ejecutarConsulta("SELECT * FROM cliente WHERE cliente_email='$email' LIMIT 1");

			if($check_cliente->rowCount()!=1){
				echo '<article class="message is-danger">
					<div class="message-body">
						<strong>No encontramos tu correo</strong><br>
						El correo ingresado no está registrado. Por favor regístrate primero.
					</div>
				</article>';
				return;
			}

			$check_cliente = $check_cliente->fetch();

			$_SESSION['cliente_id']      = $check_cliente['cliente_id'];
			$_SESSION['cliente_nombre']  = $check_cliente['cliente_nombre'];
			$_SESSION['cliente_apellido']= $check_cliente['cliente_apellido'];
			$_SESSION['cliente_email']   = $check_cliente['cliente_email'];

			$destino = ($redirect_to!="") ? (APP_URL.$redirect_to) : (APP_URL."productosCliente/");
			if(headers_sent()){
				echo "<script> window.location.href='".$destino."'; </script>";
			}else{
				header("Location: ".$destino);
			}
		}


		/*----------  Controlador cerrar sesion  ----------*/
		public function cerrarSesionControlador(){

			session_destroy();

		    if(headers_sent()){
                echo "<script> window.location.href='".APP_URL."inicio/'; </script>";
            }else{
                header("Location: ".APP_URL."inicio/");
            }
		}


		/*----------  Controlador cerrar sesion (solo cliente)  ----------*/
		public function cerrarSesionClienteControlador(){

			unset(
				$_SESSION['cliente_id'],
				$_SESSION['cliente_nombre'],
				$_SESSION['cliente_apellido'],
				$_SESSION['cliente_email'],
				$_SESSION['google_cliente_email'],
				$_SESSION['google_cliente_nombre'],
				$_SESSION['google_cliente_apellido']
			);

			if(headers_sent()){
				echo "<script> window.location.href='".APP_URL."inicio/'; </script>";
			}else{
				header("Location: ".APP_URL."inicio/");
			}
		}

	}