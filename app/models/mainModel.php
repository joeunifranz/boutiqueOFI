<?php
	
	namespace app\models;
	use \PDO;

	if(file_exists(__DIR__."/../../config/server.php")){
		require_once __DIR__."/../../config/server.php";
	}

	class mainModel{

		protected static $logTablaDisponible = null;
		protected static $logAccionDisponible = null;

		private $server=DB_SERVER;
		private $db=DB_NAME;
		private $user=DB_USER;
		private $pass=DB_PASS;


		/*----------  Funcion conectar a BD  ----------*/
		protected function conectar(){
			$dsn = "mysql:host=".$this->server.";dbname=".$this->db;
			if(defined('DB_PORT') && (string)DB_PORT !== ''){
				$dsn .= ";port=".DB_PORT;
			}
			$conexion = new PDO($dsn,$this->user,$this->pass);
			$conexion->exec("SET CHARACTER SET utf8");
			return $conexion;
		}


		/*----------  Funcion ejecutar consultas  ----------*/
		protected function ejecutarConsulta($consulta){
			$sql=$this->conectar()->prepare($consulta);
			$sql->execute();
			return $sql;
		}


		/*----------  Helper: validar si la sesión es de administrador  ----------*/
		public function sessionEsAdmin(){
			if(isset($_SESSION['rol']) && $_SESSION['rol']=="Administrador"){
				return true;
			}
			if(isset($_SESSION['usuario']) && $_SESSION['usuario']=="Administrador"){
				return true;
			}
			if(isset($_SESSION['id']) && (int)$_SESSION['id']===1){
				return true;
			}
			return false;
		}


		/*---------- Registrar acción en log_acceso (si existe) ----------*/
		protected function registrarLogAccion($accion){
			try{
				// Cache rápido: si ya sabemos que no hay tabla, no insistir
				if(self::$logTablaDisponible === false){
					return false;
				}

				$usuarioId = $_SESSION['id'] ?? 0;
				$usuarioNombre = trim(($_SESSION['nombre'] ?? '').' '.($_SESSION['apellido'] ?? ''));
				$usuarioUser = $_SESSION['usuario'] ?? 'N/A';
				$logIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
				$logFecha = date('Y-m-d');
				$logHora = date('H:i:s');

				// Detectar si la columna log_accion existe (solo una vez)
				if(self::$logAccionDisponible === null){
					try{
						$check = $this->conectar()->prepare("SHOW COLUMNS FROM log_acceso LIKE 'log_accion'");
						$check->execute();
						self::$logAccionDisponible = ($check->rowCount() >= 1);
					}catch(\Exception $e){
						self::$logAccionDisponible = false;
					}
				}

				$logDatos=[
					[
						"campo_nombre"=>"usuario_id",
						"campo_marcador"=>":UsuarioID",
						"campo_valor"=>$usuarioId
					],
					[
						"campo_nombre"=>"usuario_nombre",
						"campo_marcador"=>":UsuarioNombre",
						"campo_valor"=>($usuarioNombre!=='' ? $usuarioNombre : 'N/A')
					],
					[
						"campo_nombre"=>"usuario_usuario",
						"campo_marcador"=>":UsuarioUsuario",
						"campo_valor"=>$usuarioUser
					],
					[
						"campo_nombre"=>"log_fecha",
						"campo_marcador"=>":Fecha",
						"campo_valor"=>$logFecha
					],
					[
						"campo_nombre"=>"log_hora",
						"campo_marcador"=>":Hora",
						"campo_valor"=>$logHora
					],
					[
						"campo_nombre"=>"log_ip",
						"campo_marcador"=>":IP",
						"campo_valor"=>$logIp
					]
				];

				if(self::$logAccionDisponible){
					$logDatos[] = [
						"campo_nombre"=>"log_accion",
						"campo_marcador"=>":Accion",
						"campo_valor"=>$accion
					];
				}

				$this->guardarDatos("log_acceso", $logDatos);
				self::$logTablaDisponible = true;
				return true;
			}catch(\Exception $e){
				// Si no existe la tabla (o falla), no bloquear el flujo principal
				self::$logTablaDisponible = false;
				return false;
			}
		}


		/*----------  Funcion limpiar cadenas  ----------*/
		public function limpiarCadena($cadena){

			$palabras=["<script>","</script>","<script src","<script type=","SELECT * FROM","SELECT "," SELECT ","DELETE FROM","INSERT INTO","DROP TABLE","DROP DATABASE","TRUNCATE TABLE","SHOW TABLES","SHOW DATABASES","<?php","?>","--","^","<",">","==",";","::"];

			$cadena=trim($cadena);
			$cadena=stripslashes($cadena);

			foreach($palabras as $palabra){
				$cadena=str_ireplace($palabra, "", $cadena);
			}

			$cadena=trim($cadena);
			$cadena=stripslashes($cadena);

			return $cadena;
		}


		/*---------- Funcion verificar datos (expresion regular) ----------*/
		protected function verificarDatos($filtro,$cadena){
			if(preg_match("/^".$filtro."$/", $cadena)){
				return false;
            }else{
                return true;
            }
		}


		/*----------  Funcion para ejecutar una consulta INSERT preparada  ----------*/
		protected function guardarDatos($tabla,$datos){

			$query="INSERT INTO $tabla (";

			$C=0;
			foreach ($datos as $clave){
				if($C>=1){ $query.=","; }
				$query.=$clave["campo_nombre"];
				$C++;
			}
			
			$query.=") VALUES(";

			$C=0;
			foreach ($datos as $clave){
				if($C>=1){ $query.=","; }
				$query.=$clave["campo_marcador"];
				$C++;
			}

			$query.=")";
			$sql=$this->conectar()->prepare($query);

			foreach ($datos as $clave){
				$sql->bindParam($clave["campo_marcador"],$clave["campo_valor"]);
			}

			$sql->execute();

			return $sql;
		}


		/*---------- Funcion seleccionar datos ----------*/
        public function seleccionarDatos($tipo,$tabla,$campo,$id){
			$tipo=$this->limpiarCadena($tipo);
			$tabla=$this->limpiarCadena($tabla);
			$campo=$this->limpiarCadena($campo);
			$id=$this->limpiarCadena($id);

            if($tipo=="Unico"){
                $sql=$this->conectar()->prepare("SELECT * FROM $tabla WHERE $campo=:ID");
                $sql->bindParam(":ID",$id);
            }elseif($tipo=="Normal"){
                $sql=$this->conectar()->prepare("SELECT $campo FROM $tabla");
            }
            $sql->execute();

            return $sql;
		}


		/*----------  Funcion para ejecutar una consulta UPDATE preparada  ----------*/
		protected function actualizarDatos($tabla,$datos,$condicion){

			$query="UPDATE $tabla SET ";

			$C=0;
			foreach ($datos as $clave){
				if($C>=1){ $query.=","; }
				$query.=$clave["campo_nombre"]."=".$clave["campo_marcador"];
				$C++;
			}

			$query.=" WHERE ".$condicion["condicion_campo"]."=".$condicion["condicion_marcador"];

			$sql=$this->conectar()->prepare($query);

			foreach ($datos as $clave){
				$sql->bindParam($clave["campo_marcador"],$clave["campo_valor"]);
			}

			$sql->bindParam($condicion["condicion_marcador"],$condicion["condicion_valor"]);

			$sql->execute();

			return $sql;
		}


		/*---------- Funcion eliminar registro ----------*/
        protected function eliminarRegistro($tabla,$campo,$id){
            $sql=$this->conectar()->prepare("DELETE FROM $tabla WHERE $campo=:id");
            $sql->bindParam(":id",$id);
            $sql->execute();
            
            return $sql;
        }


		/*---------- Paginador de tablas ----------*/
		protected function paginadorTablas($pagina,$numeroPaginas,$url,$botones){
	        $tabla='<nav class="pagination is-centered is-rounded" role="navigation" aria-label="pagination">';

	        if($pagina<=1){
	            $tabla.='
	            <a class="pagination-previous is-disabled" disabled ><i class="fas fa-arrow-alt-circle-left"></i> &nbsp; Anterior</a>
	            <ul class="pagination-list">
	            ';
	        }else{
	            $tabla.='
	            <a class="pagination-previous" href="'.$url.($pagina-1).'/"><i class="fas fa-arrow-alt-circle-left"></i> &nbsp; Anterior</a>
	            <ul class="pagination-list">
	                <li><a class="pagination-link" href="'.$url.'1/">1</a></li>
	                <li><span class="pagination-ellipsis">&hellip;</span></li>
	            ';
	        }


	        $ci=0;
	        for($i=$pagina; $i<=$numeroPaginas; $i++){

	            if($ci>=$botones){
	                break;
	            }

	            if($pagina==$i){
	                $tabla.='<li><a class="pagination-link is-current" href="'.$url.$i.'/">'.$i.'</a></li>';
	            }else{
	                $tabla.='<li><a class="pagination-link" href="'.$url.$i.'/">'.$i.'</a></li>';
	            }

	            $ci++;
	        }


	        if($pagina==$numeroPaginas){
	            $tabla.='
	            </ul>
	            <a class="pagination-next is-disabled" disabled ><i class="fas fa-arrow-alt-circle-right"></i> &nbsp; Siguiente</a>
	            ';
	        }else{
	            $tabla.='
	                <li><span class="pagination-ellipsis">&hellip;</span></li>
	                <li><a class="pagination-link" href="'.$url.$numeroPaginas.'/">'.$numeroPaginas.'</a></li>
	            </ul>
	            <a class="pagination-next" href="'.$url.($pagina+1).'/"><i class="fas fa-arrow-alt-circle-right"></i> &nbsp; Siguiente</a>
	            ';
	        }

	        $tabla.='</nav>';
	        return $tabla;
	    }


	    /*----------  Funcion generar select ----------*/
		public function generarSelect($datos,$campo_db){
			$check_select='';
			$text_select='';
			$count_select=1;
			$select='';
			foreach($datos as $row){

				if($campo_db==$row){
					$check_select='selected=""';
					$text_select=' (Actual)';
				}

				$select.='<option value="'.$row.'" '.$check_select.'>'.$count_select.' - '.$row.$text_select.'</option>';

				$check_select='';
				$text_select='';
				$count_select++;
			}
			return $select;
		}

		/*----------  Funcion generar codigos aleatorios  ----------*/
		protected function generarCodigoAleatorio($longitud,$correlativo){
			$codigo="";
			$caracter="Letra";
			for($i=1; $i<=$longitud; $i++){
				if($caracter=="Letra"){
					$letra_aleatoria=chr(rand(ord("a"),ord("z")));
					$letra_aleatoria=strtoupper($letra_aleatoria);
					$codigo.=$letra_aleatoria;
					$caracter="Numero";
				}else{
					$numero_aleatorio=rand(0,9);
					$codigo.=$numero_aleatorio;
					$caracter="Letra";
				}
			}
			return $codigo."-".$correlativo;
		}


		/*----------  Limitar cadenas de texto  ----------*/
		public function limitarCadena($cadena,$limite,$sufijo){
			if(strlen($cadena)>$limite){
				return substr($cadena,0,$limite).$sufijo;
			}else{
				return $cadena;
			}
		}
	    
	}