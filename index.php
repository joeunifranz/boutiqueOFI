<?php

    require_once "./config/app.php";
    require_once "./autoload.php";

    /*---------- Iniciando sesion ----------*/
    require_once "./app/views/inc/session_start.php";

    if(isset($_GET['views'])){
        $url=explode("/", $_GET['views']);
    }else{
        $url=["inicio"];
    }

    /*---------- Exportar logs CSV (sin renderizar layout) ----------*/
    if(isset($url[0]) && $url[0]=="exportarLogs"){
        $insLog = new \app\controllers\logController();
            $insLog->exportarLogsPDF();
        exit();
    }

    /*---------- Exportaciones PDF por módulo (sin renderizar layout) ----------*/
    if(isset($url[0]) && $url[0]=="exportarCajas"){
        $ins = new \app\controllers\cashierController();
        $ins->exportarCajasPDF($_GET['busqueda'] ?? "");
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarCategorias"){
        $ins = new \app\controllers\categoryController();
        $ins->exportarCategoriasPDF($_GET['busqueda'] ?? "");
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarClientes"){
        $ins = new \app\controllers\clientController();
        $ins->exportarClientesPDF($_GET['busqueda'] ?? "");
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarProductos"){
        $ins = new \app\controllers\productController();
        $ins->exportarProductosPDF($_GET['busqueda'] ?? "", $_GET['categoria'] ?? 0);
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarVentas"){
        $ins = new \app\controllers\saleController();
        $ins->exportarVentasPDF($_GET['busqueda'] ?? "");
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarReservas"){
        $ins = new \app\controllers\reservationController();
        $ins->exportarReservasPDF($_GET['busqueda'] ?? "");
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarReservasPendientes"){
        $ins = new \app\controllers\reservationController();
        $ins->exportarReservasPendientesPDF();
        exit();
    }

    if(isset($url[0]) && $url[0]=="exportarUsuarios"){
        $ins = new \app\controllers\userController();
        $ins->exportarUsuariosPDF($_GET['busqueda'] ?? "");
        exit();
    }

    /*---------- BISA QR: generar QR dinámico ----------*/
    if(isset($url[0]) && $url[0]=="pagoBisaQR"){
        $insReserva = new \app\controllers\reservationController();
        $insReserva->generarPagoBisaQrControlador();
        exit();
    }

    /*---------- BISA QR: webhook (confirmación de pago) ----------*/
    if(isset($url[0]) && $url[0]=="bisaWebhook"){
        header('Content-Type: application/json; charset=utf-8');
        $insReserva = new \app\controllers\reservationController();
        echo $insReserva->procesarWebhookBisaControlador();
        exit();
    }

    /*---------- Compatibilidad: rutas antiguas Mercado Pago (desactivadas) ----------*/
    if(isset($url[0]) && ($url[0]=="pagoMercadoPago" || $url[0]=="mercadopagoWebhook")){
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>false,
            'error'=>'mercadopago_disabled',
            'message'=>'Mercado Pago no está habilitado para este país. Usa BISA QR.'
        ]);
        exit();
    }

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require_once "./app/views/inc/head.php"; ?>
</head>
<body>
    <?php
        use app\controllers\viewsController;
        use app\controllers\loginController;

        $insLogin = new loginController();

        $viewsController= new viewsController();
        $vista=$viewsController->obtenerVistasControlador($url[0]);

        # Si ya está logueado y visita inicio, redirigir según rol #
        if($vista=="inicio" && isset($_SESSION['id']) && $_SESSION['id']!="" && isset($_SESSION['usuario']) && $_SESSION['usuario']!=""){
            $destino = $insLogin->sessionEsAdmin() ? "dashboard/" : "saleNew/";
            if(headers_sent()){
                echo "<script> window.location.href='".APP_URL.$destino."'; </script>";
            }else{
                header("Location: ".APP_URL.$destino);
            }
            exit();
        }

        if(
            $vista=="login" || 
            $vista=="adminLogin" || 
            $vista=="inicio" || 
            $vista=="404" || 
            $vista=="registroCliente" ||
            $vista=="clienteLogin" ||
            $vista=="clienteLogOut" ||
            $vista=="productosCliente" ||
            $vista=="productoDetalle" ||
            $vista=="reservaNueva" ||
            $vista=="reservaQR" ||
            $vista=="reservaPagar" ||
            $vista=="googleClienteAuth" ||
            $vista=="googleClienteCallback"
        ){
            require_once "./app/views/content/".$vista."-view.php";
        }else{
    ?>
    <main class="page-container">
    <?php
            // Bloqueo total de interfaz admin para clientes/no-admin (sin destruir sesión de cliente)
            $adminLogueado = (isset($_SESSION['id']) && $_SESSION['id']!="" && isset($_SESSION['usuario']) && $_SESSION['usuario']!="");
            $clienteLogueado = (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id']!="");

            if(!$adminLogueado){
                // Si es cliente, jamás mostramos el panel admin
                if($clienteLogueado){
                    if(headers_sent()){
                        echo "<script> window.location.href='".APP_URL."productosCliente/'; </script>";
                    }else{
                        header("Location: ".APP_URL."productosCliente/");
                    }
                    exit();
                }

                // No logueado: enviar al login de administrador y conservar redirect
                $redir = (isset($_GET['views']) && $_GET['views']!="") ? (string)$_GET['views'] : "dashboard/";
                $redir = preg_replace('/[^a-zA-Z0-9_\/-]/','', $redir);
                $target = APP_URL."adminLogin/?redirect=".urlencode($redir);
                if(headers_sent()){
                    echo "<script> window.location.href='".htmlspecialchars($target,ENT_QUOTES,'UTF-8')."'; </script>";
                }else{
                    header("Location: ".$target);
                }
                exit();
            }
            require_once "./app/views/inc/navlateral.php";
    ?>      
        <section class="full-width pageContent scroll" id="pageContent">
            <?php
                require_once "./app/views/inc/navbar.php";

                require_once $vista;
            ?>
        </section>
    </main>
    <?php
        }

        require_once "./app/views/inc/script.php"; 
    ?>
</body>
</html>