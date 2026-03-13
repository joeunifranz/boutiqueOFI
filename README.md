# SISTEMA
SISTEMA BOUTIQUE desarrollado en PHP, MySQL, MVC, AJAX &amp; BULMA

# INSTALACIÓN
<p>1 - Copie o mueva la carpeta BOUTIQUE a su servidor local o remoto</p>
<p>2 - Cree una base de datos en MYSQL con el nombre de su preferencia, 
selecciónela e importe la base de datos del sistema con phpmyadmin u otro gestor grafico de MYSQL que utilice, la base de datos se encuentra en la carpeta DB</p>
<p>3 - Abra el archivo server.php con su editor de código favorito y configure solamente los datos del servidor. El archivo se encuentra en la carpeta “config”</p>
<p>4 - Abra el archivo app.php con su editor de código favorito, a continuación configúrelo según su empresa y servidor. El archivo se encuentra en la carpeta “config”</p>
<p>APP_NAME -> El nombre de su empresa/(BOUTIQUE)
</p>
<p>APP_URL -> La dirección URL de su servidor local (http://localhost/BOUTIQUE/) o remoto (https://midominio/BOUTIQUE/). 
Importante no olvide colocar en la URL si es http o https</p>

# CUENTA POR DEFECTO
<p>Usuario: Administrador</p>
<p>Clave: Administrador</p>

# RESERVAS CON QR (ANTICIPO 50%)

<p>Este proyecto incluye un flujo de <strong>reserva de producto con QR</strong> para clientes.</p>

<p><strong>1)</strong> Cree la tabla <code>reserva</code> ejecutando en el navegador:</p>
<p><code>http://localhost/BOUTIQUE/install_reserva_table.php</code></p>

<p><strong>2)</strong> Como cliente: tienda &rarr; detalle del producto &rarr; <strong>Reservar con 50%</strong>. Se genera un QR.</p>

<p><strong>3)</strong> En caja/personal: escanee el QR (abre <code>reservaConfirmar/&lt;codigo&gt;/</code>) y registre el abono (m&iacute;nimo 50%).</p>
