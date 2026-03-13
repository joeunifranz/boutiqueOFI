
<?php
use app\controllers\productController;

$insProducto = new productController();

$id = 0;
if(isset($url[1]) && $url[1]!==""){
    $id = (int)$url[1];
}elseif(isset($_GET['id'])){
    $id = (int)$_GET['id'];
}

$producto = $insProducto->obtenerProductoPorIdControlador($id);

if(!$producto){
    echo "<div class='has-text-centered mt-6'>Producto no encontrado</div>";
    exit();
}
?>

<?php require_once "./app/views/inc/navbar_cliente.php"; ?>

<div class="container py-6">
    <div class="columns is-vcentered">
        <!-- Imagen -->
        <div class="column is-6">
            <figure class="image">
                <?php
                if(is_file("./app/views/productos/".$producto['producto_foto'])){
                    echo '<img class="detalle-img" src="'.APP_URL.'app/views/productos/'.$producto['producto_foto'].'" alt="">';
                }else{
                    echo '<img class="detalle-img" src="'.APP_URL.'app/views/productos/default.png" alt="">';
                }
                ?>
            </figure>
        </div>
        <!-- Información -->
        <div class="column is-6">
            <h1 class="title is-2 has-text-weight-light">
                <?php echo htmlspecialchars($producto['producto_nombre']); ?>
            </h1>
            <p class="is-size-4 has-text-grey mb-4">
                <?php echo htmlspecialchars($producto['producto_descripcion'] ?? 'Vestido exclusivo de alta calidad.'); ?>
            </p>
            <p class="title is-3 has-text-danger mb-5">
                <?php echo MONEDA_SIMBOLO.number_format($producto['producto_precio_venta'],2); ?>
            </p>

            <?php
                $clienteLogueado = (isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']));
            ?>
            <?php if($clienteLogueado){ ?>
                <a class="button is-danger is-medium is-fullwidth mb-3" href="<?php echo APP_URL; ?>reservaNueva/<?php echo (int)$producto['producto_id']; ?>/">
                    <i class="fas fa-qrcode"></i> &nbsp; Reservar con 50%
                </a>
            <?php }else{ ?>
                <a class="button is-danger is-medium is-fullwidth mb-3" href="<?php echo APP_URL; ?>reservaNueva/<?php echo (int)$producto['producto_id']; ?>/">
                    <i class="fas fa-qrcode"></i> &nbsp; Reservar con 50%
                </a>
            <?php } ?>
            <a href="<?php echo APP_URL; ?>productosCliente/" 
               class="button is-light is-fullwidth">
               Volver a la tienda
            </a>
        </div>
    </div>
</div>

<style>
.detalle-img{
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}
.detalle-img:hover{
    transform: scale(1.03);
}
</style>
