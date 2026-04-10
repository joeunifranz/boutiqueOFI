<?php
require "config/server.php";
$code="Q9J9N9Q6M7D2-51";
$pdo=new PDO("mysql:host=".DB_SERVER.";dbname=".DB_NAME.(defined("DB_PORT")?";port=".DB_PORT:""), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET NAMES utf8");

function q($pdo,$sql,$params=[]){$st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);}
$out=[];
$out["reserva"]=q($pdo,"SELECT reserva_id,reserva_codigo,reserva_estado,reserva_total,reserva_abono,reserva_fecha,reserva_hora,cliente_id,producto_id,caja_id,usuario_id FROM reserva WHERE reserva_codigo=? LIMIT 1",[$code]);
$out["pagos"]=[]; try{$out["pagos"]=q($pdo,"SELECT reserva_pago_id,pago_proveedor,pago_status,pago_monto,pago_moneda,pago_aprobado_en,pago_actualizado_en FROM reserva_pago WHERE reserva_codigo=? ORDER BY reserva_pago_id DESC LIMIT 5",[$code]);}catch(Throwable $e){$out["pagos_error"]=$e->getMessage();}
if(!empty($out["reserva"])){$r=$out["reserva"][0]; $out["caja"]=q($pdo,"SELECT caja_id,caja_efectivo FROM caja WHERE caja_id=? LIMIT 1",[(int)($r["caja_id"]??0)]); $out["producto"]=q($pdo,"SELECT producto_id,producto_nombre,producto_stock_total,producto_precio_venta FROM producto WHERE producto_id=? LIMIT 1",[(int)($r["producto_id"]??0)]);}
$out["ventas_rel"]=[]; try{$out["ventas_rel"]=q($pdo,"SELECT vd.venta_codigo,vd.venta_detalle_descripcion,v.venta_fecha,v.venta_total FROM venta_detalle vd INNER JOIN venta v ON v.venta_codigo=vd.venta_codigo WHERE vd.venta_detalle_descripcion LIKE ? ORDER BY v.venta_id DESC LIMIT 3",["%Reserva ".$code."%"]);}catch(Throwable $e){$out["ventas_rel_error"]=$e->getMessage();}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
