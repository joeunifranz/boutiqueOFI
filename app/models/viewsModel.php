<?php
	
	namespace app\models;

	class viewsModel{

		/*---------- Modelo obtener vista ----------*/
		protected function obtenerVistasModelo($vista){

			$listaBlanca=["dashboard","cashierNew","cashierList","cashierSearch","cashierUpdate","userNew","userList","userUpdate","userSearch","userPhoto","clientNew","clientList","clientSearch","clientUpdate","categoryNew","categoryList","categorySearch","categoryUpdate","productNew","productList","productSearch","productUpdate","productPhoto","productCategory","companyNew","saleNew","saleList","saleSearch","saleDetail","logList","logOut","exportarLogs","reservaConfirmar","reservaAprobar","reservaDetalle","reservaList","reservaHorarios","reservaHoy","fabricNew","fabricList","fabricUpdate"];

			if(in_array($vista, $listaBlanca)){
				if(is_file("./app/views/content/".$vista."-view.php")){
					$contenido="./app/views/content/".$vista."-view.php";
				}else{
					$contenido="404";
				}
			}elseif($vista=="login"){
				$contenido="login";
			}elseif($vista=="inicio" || $vista=="index" || $vista==""){
				$contenido="inicio";
			}elseif($vista=="registroCliente"){
				$contenido="registroCliente";
			}elseif($vista=="clienteLogin"){
				$contenido="clienteLogin";
			}elseif($vista=="adminLogin"){
				$contenido="login";
			}elseif($vista=="productosCliente"){
				$contenido="productosCliente";
			}elseif($vista=="telasCliente"){
				$contenido="telasCliente";
			}elseif($vista=="productoDetalle"){
				$contenido="productoDetalle";
			}elseif($vista=="reservaNueva"){
				$contenido="reservaNueva";
			}elseif($vista=="reservaQR"){
				$contenido="reservaQR";
			}elseif($vista=="reservaPagar"){
				$contenido="reservaPagar";
			}elseif($vista=="googleClienteAuth"){
				$contenido="googleClienteAuth";
			}elseif($vista=="googleClienteCallback"){
				$contenido="googleClienteCallback";
			}elseif($vista=="reservasComprasCliente"){
				$contenido="reservasComprasCliente";
			}elseif($vista=="seguimientoReservaCliente"){
				$contenido="seguimientoReservaCliente";
			}elseif($vista=="seguimientoCompraCliente"){
				$contenido="seguimientoCompraCliente";
			}elseif($vista=="clienteLogOut"){
				$contenido="clienteLogOut";
			}else{
				$contenido="404";
			}
			return $contenido;
		}

	}