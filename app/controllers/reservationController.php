<?php

namespace app\controllers;

use app\models\mainModel;
use app\services\MercadoPagoService;

class reservationController extends mainModel{

    private static $reservaTallaColDisponible = null;
	private static $tablaSolicitudPersonalizadaDisponible = null;
    private static $reservaClienteNotifColDisponible = null;

    private function columnaReservaTallaDisponible(): bool{
        if(self::$reservaTallaColDisponible !== null){
            return (bool)self::$reservaTallaColDisponible;
        }
        try{
            $check = $this->conectar()->prepare("SHOW COLUMNS FROM `reserva` LIKE 'reserva_talla'");
            $check->execute();
            self::$reservaTallaColDisponible = ($check->rowCount() >= 1);
        }catch(\Throwable $e){
            self::$reservaTallaColDisponible = false;
        }
        return (bool)self::$reservaTallaColDisponible;
    }

    private function columnaReservaClienteNotificacionDisponible(): bool{
        if(self::$reservaClienteNotifColDisponible !== null){
            return (bool)self::$reservaClienteNotifColDisponible;
        }
        try{
            $check = $this->conectar()->prepare("SHOW COLUMNS FROM `reserva` LIKE 'reserva_cliente_notificacion'");
            $check->execute();
            self::$reservaClienteNotifColDisponible = ($check->rowCount() >= 1);
        }catch(\Throwable $e){
            self::$reservaClienteNotifColDisponible = false;
        }
        return (bool)self::$reservaClienteNotifColDisponible;
    }

    private function parseTallasProducto(?string $raw): array{
        $raw = trim((string)$raw);
        if($raw===''){
            return [];
        }
        $parts = preg_split('/[,;]+/', $raw);
        $out = [];
        if(is_array($parts)){
            foreach($parts as $p){
                $p = trim((string)$p);
                if($p !== ''){ $out[] = $p; }
            }
        }
        if(empty($out)){
            return [];
        }
        return array_values(array_unique($out));
    }

    private function normalizarHoraCita(string $hora): ?string{
        $hora = trim(strtolower($hora));
        if($hora===''){
            return null;
        }
        if(preg_match('/^\d{2}:\d{2}$/', $hora)){
            $dt = \DateTime::createFromFormat('H:i', $hora);
            if($dt instanceof \DateTime){
                return $dt->format('h:i a');
            }
            return null;
        }
        if(preg_match('/^\d{1,2}:\d{2}\s*(am|pm)$/', $hora)){
            $hora = preg_replace('/\s+/', ' ', $hora);
            $dt = \DateTime::createFromFormat('g:i a', $hora);
            if($dt instanceof \DateTime){
                return $dt->format('h:i a');
            }
            return null;
        }
        return null;
    }

    /*----------  Reservas del cliente (cliente)  ----------*/
    public function obtenerReservasPorClienteControlador(int $clienteId): array{
        $clienteId = (int)$clienteId;
        if($clienteId <= 0){
            return [];
        }

        $colsTalla = $this->columnaReservaTallaDisponible() ? ', r.reserva_talla' : '';
        $colsNotif = $this->columnaReservaClienteNotificacionDisponible() ? ', r.reserva_cliente_notificacion' : '';
        try{
            $stmt = $this->conectar()->prepare(
                "SELECT r.reserva_id, r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado, r.reserva_observacion{$colsTalla}{$colsNotif},
                    p.producto_id, p.producto_nombre, p.producto_foto
                 FROM reserva r
                 INNER JOIN producto p ON p.producto_id=r.producto_id
                 WHERE r.cliente_id=:cid
                 ORDER BY r.reserva_fecha DESC, r.reserva_id DESC"
            );
            $stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        }catch(\Throwable $e){
            return [];
        }
    }

    public function obtenerReservaPorCodigoParaClienteControlador(string $codigo, int $clienteId): ?array{
        $codigo = trim($this->limpiarCadena($codigo));
        $clienteId = (int)$clienteId;
        if($codigo==='' || $clienteId <= 0){
            return null;
        }

        $colsTalla = $this->columnaReservaTallaDisponible() ? ', r.reserva_talla' : '';
        $colsNotif = $this->columnaReservaClienteNotificacionDisponible() ? ', r.reserva_cliente_notificacion' : '';
        try{
            $stmt = $this->conectar()->prepare(
                "SELECT r.reserva_id, r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado, r.reserva_observacion{$colsTalla}{$colsNotif},
                    c.cliente_id, c.cliente_nombre, c.cliente_apellido, c.cliente_email,
                    p.producto_id, p.producto_nombre, p.producto_foto
                 FROM reserva r
                 INNER JOIN cliente c ON c.cliente_id=r.cliente_id
                 INNER JOIN producto p ON p.producto_id=r.producto_id
                 WHERE r.reserva_codigo=:cod AND r.cliente_id=:cid
                 LIMIT 1"
            );
            $stmt->bindValue(':cod', $codigo);
            $stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            return $row ? $row : null;
        }catch(\Throwable $e){
            return null;
        }
    }

    public function contarNotificacionesReservaClienteControlador(int $clienteId): int{
        $clienteId = (int)$clienteId;
        if($clienteId <= 0){
            return 0;
        }
        if(!$this->tablaReservaExiste()){
            return 0;
        }
        if(!$this->columnaReservaClienteNotificacionDisponible()){
            return 0;
        }
        try{
            $stmt = $this->conectar()->prepare(
                "SELECT COALESCE(SUM(reserva_cliente_notificacion),0) as c
                 FROM reserva
                 WHERE cliente_id=:cid
                   AND reserva_cliente_notificacion>0
                   AND reserva_estado NOT IN ('rechazada','completada')"
            );
            $stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return (int)$val;
        }catch(\Throwable $e){
            return 0;
        }
    }

    public function marcarNotificacionesReservaClienteVistasControlador(int $clienteId): void{
        $clienteId = (int)$clienteId;
        if($clienteId <= 0){
            return;
        }
        if(!$this->tablaReservaExiste()){
            return;
        }
        if(!$this->columnaReservaClienteNotificacionDisponible()){
            return;
        }
        try{
            $stmt = $this->conectar()->prepare(
                "UPDATE reserva
                 SET reserva_cliente_notificacion=0
                 WHERE cliente_id=:cid
                   AND reserva_cliente_notificacion>0"
            );
            $stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
        }catch(\Throwable $e){
            // silencio
        }
    }

    public function marcarNotificacionReservaClienteVistaPorCodigoControlador(string $codigo, int $clienteId): void{
        $codigo = trim($this->limpiarCadena($codigo));
        $clienteId = (int)$clienteId;
        if($codigo === '' || $clienteId <= 0){
            return;
        }
        if(!$this->tablaReservaExiste()){
            return;
        }
        if(!$this->columnaReservaClienteNotificacionDisponible()){
            return;
        }
        try{
            $stmt = $this->conectar()->prepare(
                "UPDATE reserva
                 SET reserva_cliente_notificacion=0
                 WHERE reserva_codigo=:c AND cliente_id=:cid
                 LIMIT 1"
            );
            $stmt->bindValue(':c', $codigo);
            $stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
        }catch(\Throwable $e){
            // silencio
        }
    }

    private function tablaReservaRecordatorioColsDisponibles(): bool{
        try{
            $check = $this->conectar()->prepare("SHOW COLUMNS FROM `reserva` LIKE 'reserva_recordatorio_1d_enviado'");
            $check->execute();
            return ($check->rowCount() >= 1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function enviarReprogramacionCitaPorCorreo(array $reserva, string $nuevaFecha, string $nuevaHora, string $motivo): void{
        try{
            $email = trim((string)($reserva['cliente_email'] ?? ''));
            if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
                return;
            }

            $cliente = trim((string)($reserva['cliente_nombre'] ?? '').' '.(string)($reserva['cliente_apellido'] ?? ''));
            if($cliente===''){
                $cliente = 'Cliente';
            }

            $fechaPretty = $nuevaFecha;
            try{
                $dt = new \DateTime($nuevaFecha);
                $fechaPretty = $dt->format('d/m/Y');
            }catch(\Throwable $e){
                // keep
            }

            $codigo = (string)($reserva['reserva_codigo'] ?? '');
            $producto = (string)($reserva['producto_nombre'] ?? '');
            $subject = 'Reasignación de cita - '.(defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE');

            $total = (float)($reserva['reserva_total'] ?? 0);
            $abono = (float)($reserva['reserva_abono'] ?? 0);
            $saldo = $total - $abono;
            if($saldo < 0){ $saldo = 0; }

            $motivoTxt = trim($motivo);
            if($motivoTxt===''){
                $motivoTxt = 'No asistencia a la cita.';
            }

            $html = "
                <div style=\"font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#111;\">
                    <p>Hola <strong>".htmlspecialchars($cliente,ENT_QUOTES,'UTF-8')."</strong>,</p>
                    <p>Tu cita fue <strong>reasignada</strong>. Por favor toma nota de la nueva fecha:</p>
                    <ul>
                        <li><strong>Código:</strong> ".htmlspecialchars($codigo,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Producto:</strong> ".htmlspecialchars($producto,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Nueva fecha:</strong> ".htmlspecialchars($fechaPretty,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Nueva hora:</strong> ".htmlspecialchars($nuevaHora,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Saldo pendiente:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($saldo, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Motivo:</strong> ".htmlspecialchars($motivoTxt,ENT_QUOTES,'UTF-8')."</li>
                    </ul>
                    <p><strong>Importante:</strong> si no asistes a la cita reasignada, se entiende que <strong>no hay devolución</strong>.</p>
                    <p>Gracias,<br>".htmlspecialchars((defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE'),ENT_QUOTES,'UTF-8')."</p>
                </div>
            ";

            $mailer = new \app\services\MailService();
            $ok = $mailer->sendHtml($email, $subject, $html);
            if(!$ok){
                $err = $mailer->getLastError() ?: 'Falló envío (sin detalle)';
                error_log('[BOUTIQUE][MAIL] Fallo reprogramacion reserva codigo='.$codigo.' to='.$email.' :: '.$err);
            }
        }catch(\Throwable $e){
            error_log('[BOUTIQUE][MAIL] Excepción reprogramacion reserva :: '.$e->getMessage());
        }
    }

    /*----------  Reasignar cita por no asistencia (admin)  ----------*/
    public function reasignarCitaNoAsistioControlador(){
        if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
            $alerta=[
                'tipo'=>'redireccionar',
                'url'=>APP_URL.'login/'
            ];
            return json_encode($alerta);
        }

        if(!$this->sesionEsAdmin()){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Acceso restringido',
                'texto'=>'Solo el administrador puede reasignar citas.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? '');
        $nuevaFecha = $this->limpiarCadena($_POST['nueva_fecha'] ?? '');
        $nuevaHoraIn = (string)($_POST['nueva_hora'] ?? '');
        $motivo = $this->limpiarCadena($_POST['motivo'] ?? '');

        if($codigo==='' || $nuevaFecha===''){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Datos incompletos',
                'texto'=>'Debes indicar el código y la nueva fecha.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha)){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Fecha inválida',
                'texto'=>'La fecha no tiene el formato correcto (YYYY-MM-DD).',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        try{
            new \DateTime($nuevaFecha);
        }catch(\Throwable $e){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Fecha inválida',
                'texto'=>'La fecha indicada no es válida.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $nuevaHora = $this->normalizarHoraCita((string)$nuevaHoraIn);
        if($nuevaHora === null){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Hora inválida',
                'texto'=>'La hora no tiene un formato válido.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Reserva no encontrada',
                'texto'=>'No encontramos la reserva indicada.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $estadoActual = (string)($reserva['reserva_estado'] ?? '');
        if(!in_array($estadoActual, ['confirmada','reprogramada'], true)){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'No se puede reasignar',
                'texto'=>'Solo se pueden reasignar reservas en estado confirmada o reprogramada (actual: '.$estadoActual.').',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $obs = trim((string)($reserva['reserva_observacion'] ?? ''));
        $nota = 'Reprogramada por no asistencia. ';
        if(trim($motivo) !== ''){
            $nota .= 'Motivo: '.trim($motivo).'. ';
        }
        $nota .= 'Política: si no asiste a la cita reasignada, no hay devolución.';
        $nuevaObs = trim(($obs !== '' ? ($obs.' | ') : '').$nota);
        if(function_exists('mb_substr')){
            $nuevaObs = mb_substr($nuevaObs, 0, 255, 'UTF-8');
        }else{
            $nuevaObs = substr($nuevaObs, 0, 255);
        }

        try{
            $pdo = $this->conectar();
            $pdo->beginTransaction();

            $setReminderCols = $this->tablaReservaRecordatorioColsDisponibles();
            $sql = "UPDATE reserva SET reserva_fecha=:f, reserva_hora=:h, reserva_estado='reprogramada', reserva_observacion=:o";
            if($setReminderCols){
                $sql .= ", reserva_recordatorio_1d_enviado=0, reserva_recordatorio_1d_enviado_en=NULL, reserva_recordatorio_1d_ultimo_intento=NULL, reserva_recordatorio_1d_error=NULL";
            }
            if($this->columnaReservaClienteNotificacionDisponible()){
                $sql .= ", reserva_cliente_notificacion=(reserva_cliente_notificacion+1)";
            }
            $sql .= " WHERE reserva_codigo=:c AND reserva_estado IN ('confirmada','reprogramada') LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':f', $nuevaFecha);
            $stmt->bindValue(':h', $nuevaHora);
            $stmt->bindValue(':o', $nuevaObs);
            $stmt->bindValue(':c', $codigo);
            $stmt->execute();
            if($stmt->rowCount() !== 1){
                throw new \Exception('No se pudo actualizar la reserva.');
            }

            $pdo->commit();
        }catch(\Throwable $e){
            try{ if(isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()){ $pdo->rollBack(); } }catch(\Throwable $e2){}
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Ocurrió un error inesperado',
                'texto'=>'No pudimos reasignar la cita: '.$e->getMessage(),
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $this->registrarLogAccion('Reasignó cita (no asistió) reserva '.$codigo.' -> '.$nuevaFecha.' '.$nuevaHora);
        $this->enviarReprogramacionCitaPorCorreo($reserva, $nuevaFecha, $nuevaHora, $motivo);

        $alerta=[
            'tipo'=>'recargar',
            'titulo'=>'Cita reasignada',
            'texto'=>'La cita fue reasignada y se notificó al cliente (si tiene email válido).',
            'icono'=>'success'
        ];
        return json_encode($alerta);
    }

    private function enviarTicketReservaPorCorreo(string $codigo): void{
        try{
            $reserva = $this->obtenerReservaPorCodigo($codigo);
            if(!$reserva){
                return;
            }

            $estado = (string)($reserva['reserva_estado'] ?? '');
            $mensajeEstado = ($estado === 'confirmada')
                ? 'Tu reserva fue confirmada con éxito. Detalles:'
                : 'Tu reserva fue registrada con éxito. Detalles:';

            $email = trim((string)($reserva['cliente_email'] ?? ''));
            if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
                return;
            }

            $cliente = trim((string)($reserva['cliente_nombre'] ?? '').' '.(string)($reserva['cliente_apellido'] ?? ''));
            if($cliente===''){
                $cliente = 'Cliente';
            }

            $fechaPretty = (string)($reserva['reserva_fecha'] ?? '');
            try{
                $dt = new \DateTime((string)$reserva['reserva_fecha']);
                $fechaPretty = $dt->format('d/m/Y');
            }catch(\Throwable $e){
                // keep
            }

            $subject = 'Tu reserva - '.(defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE');
            $linkPdf = (defined('APP_URL') ? (string)APP_URL : '').'app/pdf/reserva_ticket.php?code='.urlencode($codigo);

            $total = (float)($reserva['reserva_total'] ?? 0);
            $abono = (float)($reserva['reserva_abono'] ?? 0);
            $saldo = (float)number_format(($total - $abono), (int)MONEDA_DECIMALES, '.', '');
            if($saldo < 0){ $saldo = 0; }
            $pagoCompleto = (strtolower(trim((string)$estado)) === 'completada') || ($saldo <= 0);

            $liPago = $pagoCompleto
                ? ("<li><strong>Pago:</strong> Completado</li>\n<li><strong>Total pagado:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($total, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>")
                : ("<li><strong>Abono:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($abono, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>\n<li><strong>Debe pagar:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($saldo, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>");

            $html = "
                <div style=\"font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#111;\">
                    <p>Hola <strong>".htmlspecialchars($cliente,ENT_QUOTES,'UTF-8')."</strong>,</p>
                    <p>".htmlspecialchars($mensajeEstado,ENT_QUOTES,'UTF-8')."</p>
                    <ul>
                        <li><strong>Código:</strong> ".htmlspecialchars((string)$codigo,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Fecha:</strong> ".htmlspecialchars($fechaPretty,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Hora:</strong> ".htmlspecialchars((string)($reserva['reserva_hora'] ?? ''),ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Producto:</strong> ".htmlspecialchars((string)($reserva['producto_nombre'] ?? ''),ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>Total:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($total, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>
                        ".$liPago."
                    </ul>
                    <p>Adjuntamos tu ticket en PDF. También puedes abrirlo aquí: <a href=\"".htmlspecialchars($linkPdf,ENT_QUOTES,'UTF-8')."\" target=\"_blank\" rel=\"noopener\">Ticket de reserva</a></p>
                    <p>Gracias,<br>".htmlspecialchars((defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE'),ENT_QUOTES,'UTF-8')."</p>
                </div>
            ";

            $pdfSvc = new \app\services\TicketPdfService();
            $pdf = $pdfSvc->generarTicketReserva($codigo);
            $mailer = new \app\services\MailService();
            $ok = false;
            if(is_string($pdf) && $pdf !== ''){
                $ok = $mailer->sendHtmlWithAttachments($email, $subject, $html, [[
                    'filename' => 'Ticket_Reserva_'.$codigo.'.pdf',
                    'contentType' => 'application/pdf',
                    'data' => $pdf,
                ]]);
            }else{
                $ok = $mailer->sendHtml($email, $subject, $html);
            }

            if(!$ok){
                $err = $mailer->getLastError() ?: 'Falló envío (sin detalle)';
                error_log('[BOUTIQUE][MAIL] Fallo ticket reserva codigo='.$codigo.' to='.$email.' :: '.$err);
            }
        }catch(\Throwable $e){
            // No interrumpir el flujo por error de correo
            error_log('[BOUTIQUE][MAIL] Excepción ticket reserva codigo='.$codigo.' :: '.$e->getMessage());
        }
    }

    private function enviarTicketVentaPorCorreo(string $ventaCodigo, array $clienteData): void{
        try{
            $email = trim((string)($clienteData['cliente_email'] ?? ''));
            if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
                return;
            }

            $cliente = trim((string)($clienteData['cliente_nombre'] ?? '').' '.(string)($clienteData['cliente_apellido'] ?? ''));
            if($cliente===''){
                $cliente = 'Cliente';
            }

            $subject = 'Comprobante de compra - '.(defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE');
            $linkPdf = (defined('APP_URL') ? (string)APP_URL : '').'app/pdf/ticket.php?code='.urlencode($ventaCodigo);

            $html = "
                <div style=\"font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#111;\">
                    <p>Hola <strong>".htmlspecialchars($cliente,ENT_QUOTES,'UTF-8')."</strong>,</p>
                    <p>Te enviamos tu ticket de compra en PDF.</p>
                    <p>Puedes abrirlo aquí: <a href=\"".htmlspecialchars($linkPdf,ENT_QUOTES,'UTF-8')."\" target=\"_blank\" rel=\"noopener\">Ticket</a></p>
                    <p>Gracias,<br>".htmlspecialchars((defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE'),ENT_QUOTES,'UTF-8')."</p>
                </div>
            ";

            $pdfSvc = new \app\services\TicketPdfService();
            $pdf = $pdfSvc->generarTicketVenta($ventaCodigo);
            $mailer = new \app\services\MailService();
            $ok = false;
            if(is_string($pdf) && $pdf !== ''){
                $ok = $mailer->sendHtmlWithAttachments($email, $subject, $html, [[
                    'filename' => 'Ticket_'.$ventaCodigo.'.pdf',
                    'contentType' => 'application/pdf',
                    'data' => $pdf,
                ]]);
            }else{
                $ok = $mailer->sendHtml($email, $subject, $html);
            }

            if(!$ok){
                $err = $mailer->getLastError() ?: 'Falló envío (sin detalle)';
                error_log('[BOUTIQUE][MAIL] Fallo ticket venta codigo='.$ventaCodigo.' to='.$email.' :: '.$err);
            }
        }catch(\Throwable $e){
            // No interrumpir el flujo por error de correo
            error_log('[BOUTIQUE][MAIL] Excepción ticket venta codigo='.$ventaCodigo.' :: '.$e->getMessage());
        }
    }

    private function tablaVentaExiste(){
        try{
            $check = $this->conectar()->query("SHOW TABLES LIKE 'venta'");
            return ($check && $check->rowCount()>=1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function tablaVentaDetalleExiste(){
        try{
            $check = $this->conectar()->query("SHOW TABLES LIKE 'venta_detalle'");
            return ($check && $check->rowCount()>=1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function obtenerConfigCitas(){
        $ruta = __DIR__."/../../config/citas.php";
        if(is_file($ruta)){
            $data = include $ruta;
            if(is_array($data)){
                return $data;
            }
        }
        return [
            'start' => '10:00',
            'end' => '19:00',
            'interval_minutes' => 60,
        ];
    }

    private function tablaReservaHorarioBloqueoExiste(){
        try{
            $check = $this->conectar()->query("SHOW TABLES LIKE 'reserva_horario_bloqueo'");
            return ($check && $check->rowCount()>=1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function crearTablaReservaHorarioBloqueoSiNoExiste(){
        if($this->tablaReservaHorarioBloqueoExiste()){
            return true;
        }
        try{
            $sql = "CREATE TABLE IF NOT EXISTS `reserva_horario_bloqueo` (
                `bloqueo_id` int(30) NOT NULL AUTO_INCREMENT,
                `bloqueo_fecha` date NOT NULL,
                `bloqueo_hora` varchar(17) COLLATE utf8_spanish2_ci NOT NULL,
                `usuario_id` int(7) DEFAULT NULL,
                `creado_en` datetime NOT NULL,
                PRIMARY KEY (`bloqueo_id`),
                UNIQUE KEY `fecha_hora_unique` (`bloqueo_fecha`,`bloqueo_hora`),
                KEY `bloqueo_fecha_idx` (`bloqueo_fecha`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;";

            $this->conectar()->exec($sql);
            return $this->tablaReservaHorarioBloqueoExiste();
        }catch(\Throwable $e){
            return false;
        }
    }

    private function obtenerFeriadosConfigurados(){
        $ruta = __DIR__."/../../config/feriados.php";
        if(is_file($ruta)){
            $data = include $ruta;
            if(is_array($data)){
                return $data;
            }
        }
        return [];
    }

    private function fechaYmdValida($fecha){
        $fecha = (string)$fecha;
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
        return ($dt && $dt->format('Y-m-d') === $fecha);
    }

    private function esDomingo($fechaYmd){
        $ts = strtotime((string)$fechaYmd);
        if($ts===false){
            return false;
        }
        return (int)date('w', $ts) === 0;
    }

    private function esFeriado($fechaYmd){
        $feriados = $this->obtenerFeriadosConfigurados();
        return in_array((string)$fechaYmd, $feriados, true);
    }

    private function generarHorariosPermitidos(){
        $cfg = $this->obtenerConfigCitas();
        $start = isset($cfg['start']) ? (string)$cfg['start'] : '10:00';
        $end = isset($cfg['end']) ? (string)$cfg['end'] : '19:00';
        $stepMinutes = isset($cfg['interval_minutes']) ? (int)$cfg['interval_minutes'] : 60;
        if($stepMinutes<=0){
            $stepMinutes = 60;
        }

        $base = new \DateTime('2000-01-01 00:00:00');
        $dtStart = \DateTime::createFromFormat('Y-m-d H:i', '2000-01-01 '.$start);
        $dtEnd = \DateTime::createFromFormat('Y-m-d H:i', '2000-01-01 '.$end);
        if(!$dtStart || !$dtEnd){
            $dtStart = \DateTime::createFromFormat('Y-m-d H:i', '2000-01-01 10:00');
            $dtEnd = \DateTime::createFromFormat('Y-m-d H:i', '2000-01-01 19:00');
        }
        $minStart = ((int)$dtStart->format('H'))*60 + (int)$dtStart->format('i');
        $minEnd = ((int)$dtEnd->format('H'))*60 + (int)$dtEnd->format('i');
        if($minEnd < $minStart){
            $tmp = $minStart;
            $minStart = $minEnd;
            $minEnd = $tmp;
        }

        $horarios = [];
        for($m = $minStart; $m <= $minEnd; $m += $stepMinutes){
            $dt = clone $base;
            $dt->modify('+'.$m.' minutes');
            $horarios[] = $dt->format('h:i a');
        }
        return $horarios;
    }

    private function obtenerHorasBloqueadas($fechaYmd){
        if(!$this->tablaReservaHorarioBloqueoExiste()){
            return [];
        }
        try{
            $stmt = $this->conectar()->prepare("SELECT bloqueo_hora FROM reserva_horario_bloqueo WHERE bloqueo_fecha=:f");
            $stmt->bindParam(':f', $fechaYmd);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if(!is_array($rows)){
                return [];
            }
            $out = [];
            foreach($rows as $h){
                $nh = $this->normalizarHora12($h);
                if($nh!==''){
                    $out[] = $nh;
                }
            }
            return array_values(array_unique($out));
        }catch(\Throwable $e){
            return [];
        }
    }

    private function normalizarHora12($hora){
        $hora = strtolower(trim((string)$hora));
        $hora = preg_replace('/\s+/', ' ', $hora);
        if($hora===''){
            return '';
        }

        $dt = \DateTime::createFromFormat('g:i a', $hora);
        if(!$dt){
            $dt = \DateTime::createFromFormat('h:i a', $hora);
        }
        if(!$dt){
            return '';
        }
        return $dt->format('h:i a');
    }

    private function minutosDeHora12($hora){
        $norm = $this->normalizarHora12($hora);
        if($norm===''){
            return null;
        }
        $dt = \DateTime::createFromFormat('h:i a', $norm);
        if(!$dt){
            return null;
        }
        return ((int)$dt->format('H'))*60 + (int)$dt->format('i');
    }

    private function obtenerHorasOcupadas($fechaYmd){
        if(!$this->tablaReservaExiste()){
            // Aún si no existe la tabla reserva, puede existir la tabla de solicitudes personalizadas.
            return $this->obtenerHorasOcupadasSolicitudesPersonalizadas($fechaYmd);
        }
        try{
            $stmt = $this->conectar()->prepare("SELECT reserva_hora FROM reserva WHERE reserva_fecha=:f AND reserva_estado<>'rechazada'");
            $stmt->bindParam(':f', $fechaYmd);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if(!is_array($rows)){
                $rows = [];
            }
            $out = [];
            foreach($rows as $h){
                $nh = $this->normalizarHora12($h);
                if($nh!==''){
                    $out[] = $nh;
                }
            }

            // Agregar citas de solicitudes personalizadas
            $extra = $this->obtenerHorasOcupadasSolicitudesPersonalizadas($fechaYmd);
            if(!empty($extra)){
                $out = array_merge($out, $extra);
            }

            return array_values(array_unique($out));
        }catch(\Throwable $e){
            return $this->obtenerHorasOcupadasSolicitudesPersonalizadas($fechaYmd);
        }
    }

    private function tablaSolicitudPersonalizadaExiste(): bool{
        if(self::$tablaSolicitudPersonalizadaDisponible !== null){
            return (bool)self::$tablaSolicitudPersonalizadaDisponible;
        }
        try{
            $check = $this->ejecutarConsulta("SHOW TABLES LIKE 'solicitud_personalizada'");
            self::$tablaSolicitudPersonalizadaDisponible = ($check && $check->rowCount() >= 1);
        }catch(\Throwable $e){
            self::$tablaSolicitudPersonalizadaDisponible = false;
        }
        return (bool)self::$tablaSolicitudPersonalizadaDisponible;
    }

    private function crearTablaSolicitudPersonalizadaSiNoExiste(): bool{
        try{
            $ok = $this->ejecutarConsulta(
                "CREATE TABLE IF NOT EXISTS `solicitud_personalizada` (
                    `solicitud_id` INT NOT NULL AUTO_INCREMENT,
                    `cliente_id` INT NOT NULL,
                    `cita_fecha` DATE NOT NULL,
                    `cita_hora` VARCHAR(12) NOT NULL,
                    `talla` VARCHAR(10) NULL,
                    `tela_id` INT NOT NULL,
                    `tela_nombre` VARCHAR(80) NOT NULL,
                    `tela_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `metros_estimados` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
                    `encaje_id` INT NULL,
                    `encaje_key` VARCHAR(80) NOT NULL,
                    `encaje_nombre` VARCHAR(140) NOT NULL,
                    `encaje_precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `vestido_detalle` VARCHAR(500) NULL,
                    `estado` VARCHAR(20) NOT NULL DEFAULT 'pendiente',
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`solicitud_id`),
                    KEY `idx_fecha_hora` (`cita_fecha`, `cita_hora`),
                    KEY `idx_cliente` (`cliente_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
            );
            self::$tablaSolicitudPersonalizadaDisponible = true;
            return ($ok !== false);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function columnaExiste(string $tabla, string $columna): bool{
        try{
            $stmt = $this->conectar()->prepare("SHOW COLUMNS FROM {$tabla} LIKE :c");
            $stmt->bindValue(':c', $columna);
            $stmt->execute();
            return ($stmt->rowCount() >= 1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function asegurarColumnaEncajeIdSolicitudPersonalizada(): void{
        if(!$this->tablaSolicitudPersonalizadaExiste()){
            return;
        }
        if($this->columnaExiste('solicitud_personalizada', 'encaje_id')){
            return;
        }
        try{
            $this->ejecutarConsulta("ALTER TABLE solicitud_personalizada ADD COLUMN encaje_id INT NULL AFTER metros_estimados");
        }catch(\Throwable $e){
            // Sin permisos / no soportado: ignorar
        }
    }

    private function tablaEncajeExiste(): bool{
        try{
            $check = $this->ejecutarConsulta("SHOW TABLES LIKE 'encaje'");
            return ($check && $check->rowCount() >= 1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function obtenerEncajeActivoPorId(int $encajeId): ?array{
        if($encajeId <= 0){
            return null;
        }
        if(!$this->tablaEncajeExiste()){
            return null;
        }
        try{
            $stmt = $this->conectar()->prepare('SELECT encaje_id, encaje_nombre, encaje_precio FROM encaje WHERE encaje_id=:id AND encaje_activo=1 LIMIT 1');
            $stmt->bindValue(':id', $encajeId, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            return $row ? $row : null;
        }catch(\Throwable $e){
            return null;
        }
    }

    private function obtenerHorasOcupadasSolicitudesPersonalizadas(string $fechaYmd): array{
        if(!$this->tablaSolicitudPersonalizadaExiste()){
            return [];
        }
        try{
            $stmt = $this->conectar()->prepare("SELECT cita_hora FROM solicitud_personalizada WHERE cita_fecha=:f AND estado<>'cancelada'");
            $stmt->bindParam(':f', $fechaYmd);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if(!is_array($rows)){
                return [];
            }
            $out = [];
            foreach($rows as $h){
                $nh = $this->normalizarHora12((string)$h);
                if($nh !== ''){
                    $out[] = $nh;
                }
            }
            return array_values(array_unique($out));
        }catch(\Throwable $e){
            return [];
        }
    }

    private function estimarMetrosPorTalla(string $talla): float{
        $talla = strtoupper(trim($talla));
        $baseBySize = [
            'XS' => 2.4,
            'S' => 2.6,
            'M' => 2.8,
            'L' => 3.0,
            'XL' => 3.2,
            'XXL' => 3.4,
        ];
        $base = $baseBySize[$talla] ?? $baseBySize['M'];
        $mult = 1.15;
        $metros = $base * $mult;
        return round($metros, 1);
    }

    private function encajesPermitidos(): array{
        return [
            'rosas_piedras_color' => ['nombre' => 'Rosas con piedras del color del vestido', 'precio' => 450.00],
            'rosas_brillo_plateado' => ['nombre' => 'Rosas con brillo pedrería plateada', 'precio' => 570.00],
            'ramas_pedreria_plateada' => ['nombre' => 'Ramas con pedrería plateada', 'precio' => 570.00],
            'bordado_pedreria' => ['nombre' => 'Bordado con pedrería', 'precio' => 525.00],
            'encaje_3d' => ['nombre' => 'Encaje en 3D', 'precio' => 450.00],
            'vipiur_hojas' => ['nombre' => 'Vipiur de hojas', 'precio' => 525.00],
            'vipiur_hojas_3d' => ['nombre' => 'Vipiur hojas 3D', 'precio' => 525.00],
            'encaje_sin_pedreria' => ['nombre' => 'Encaje sin pedrería', 'precio' => 450.00],
            'vipiur_rosas_poca_pedreria' => ['nombre' => 'Vipiur de rosas con poca pedrería', 'precio' => 450.00],
            'pedreria_rosas_pura_piedra' => ['nombre' => 'Pedrería diseñada de rosas (pura piedra)', 'precio' => 525.00],
        ];
    }

    private function obtenerEmailAdminDestino(): string{
        // 1) Empresa (configurable desde el sistema)
        try{
            $empresaStmt = $this->ejecutarConsulta('SELECT empresa_email FROM empresa LIMIT 1');
            if($empresaStmt && $empresaStmt->rowCount() >= 1){
                $row = $empresaStmt->fetch();
                $email = trim((string)($row['empresa_email'] ?? ''));
                if($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)){
                    return $email;
                }
            }
        }catch(\Throwable $e){
            // ignore
        }

        // 2) Fallback a config/mail.php (from email)
        try{
            $path = __DIR__ . '/../../config/mail.php';
            if(is_file($path)){
                $cfg = require $path;
                if(is_array($cfg)){
                    $from = $cfg['from'] ?? [];
                    if(is_array($from)){
                        $email = trim((string)($from['email'] ?? ''));
                        if($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)){
                            return $email;
                        }
                    }
                }
            }
        }catch(\Throwable $e){
            // ignore
        }
        return '';
    }

    private function tablaTelaExiste(): bool{
        try{
            $check = $this->ejecutarConsulta("SHOW TABLES LIKE 'tela'");
            return ($check && $check->rowCount() >= 1);
        }catch(\Throwable $e){
            return false;
        }
    }

    public function crearSolicitudPersonalizadaControlador(){
        if(!isset($_SESSION['cliente_id']) || (int)$_SESSION['cliente_id'] <= 0){
            return json_encode(['ok' => false, 'mensaje' => 'Debes iniciar sesión para enviar la solicitud']);
        }

        $clienteId = (int)$_SESSION['cliente_id'];
        $fecha = $this->limpiarCadena($_POST['cita_fecha'] ?? '');
        $horaIn = (string)($_POST['cita_hora'] ?? '');
        $talla = $this->limpiarCadena($_POST['talla'] ?? 'M');
        $telaId = (int)($this->limpiarCadena($_POST['tela_id'] ?? '0'));
        $encajeId = (int)($this->limpiarCadena($_POST['encaje_id'] ?? '0'));
        $encajeKey = $this->limpiarCadena($_POST['encaje_key'] ?? '');
        $detalle = $this->limpiarCadena($_POST['vestido_detalle'] ?? '');

        if($fecha==='' || !$this->fechaYmdValida($fecha)){
            return json_encode(['ok'=>false,'mensaje'=>'Fecha inválida']);
        }

        $hoy = date('Y-m-d');
        if($fecha < $hoy){
            return json_encode(['ok'=>false,'mensaje'=>'No puedes elegir una fecha pasada']);
        }
        if($this->esDomingo($fecha)){
            return json_encode(['ok'=>false,'mensaje'=>'Los domingos no atendemos']);
        }
        if($this->esFeriado($fecha)){
            return json_encode(['ok'=>false,'mensaje'=>'Este día es feriado y no está disponible']);
        }

        $hora = $this->normalizarHoraCita($horaIn);
        if($hora === null){
            return json_encode(['ok'=>false,'mensaje'=>'Hora inválida']);
        }

        if($telaId <= 0){
            return json_encode(['ok'=>false,'mensaje'=>'Debes seleccionar una tela']);
        }

        $encajeNombre = '';
        $encajePrecio = 0.0;
        $encajeRow = $this->obtenerEncajeActivoPorId($encajeId);
        if($encajeRow){
            $encajeNombre = (string)($encajeRow['encaje_nombre'] ?? '');
            $encajePrecio = (float)($encajeRow['encaje_precio'] ?? 0);
            // Para compatibilidad: guardamos un "key" basado en ID
            $encajeKey = (string)$encajeId;
        }else{
            // Fallback por si aún se envía encaje_key hardcode
            $encajes = $this->encajesPermitidos();
            if($encajeKey==='' || !isset($encajes[$encajeKey])){
                return json_encode(['ok'=>false,'mensaje'=>'Debes seleccionar un encaje válido']);
            }
            $encajeNombre = (string)$encajes[$encajeKey]['nombre'];
            $encajePrecio = (float)$encajes[$encajeKey]['precio'];
            $encajeId = 0;
        }

        // Validar hora contra reglas actuales
        $permitidos = $this->generarHorariosPermitidos();
        if(!in_array($hora, $permitidos, true)){
            return json_encode(['ok'=>false,'mensaje'=>'La hora seleccionada no está permitida']);
        }
        $ocupados = $this->obtenerHorasOcupadas($fecha);
        if(in_array($hora, $ocupados, true)){
            return json_encode(['ok'=>false,'mensaje'=>'Ese horario ya no está disponible']);
        }
        $bloqueados = $this->obtenerHorasBloqueadas($fecha);
        if(in_array($hora, $bloqueados, true)){
            return json_encode(['ok'=>false,'mensaje'=>'Ese horario no está disponible']);
        }
        if($fecha === $hoy){
            $nowMinutes = ((int)date('H'))*60 + (int)date('i');
            $hm = $this->minutosDeHora12($hora);
            if($hm !== null && $hm < $nowMinutes){
                return json_encode(['ok'=>false,'mensaje'=>'Ese horario ya pasó']);
            }
        }

        // Obtener datos de tela
        if(!$this->tablaTelaExiste()){
            return json_encode(['ok'=>false,'mensaje'=>'No está configurado el inventario de telas']);
        }
        try{
            $stmtTela = $this->conectar()->prepare('SELECT tela_id, tela_nombre, tela_precio FROM tela WHERE tela_id=:id AND tela_activo=1 LIMIT 1');
            $stmtTela->bindValue(':id', $telaId, \PDO::PARAM_INT);
            $stmtTela->execute();
            $telaRow = $stmtTela->fetch();
            if(!$telaRow){
                return json_encode(['ok'=>false,'mensaje'=>'La tela seleccionada no existe o está inactiva']);
            }
        }catch(\Throwable $e){
            return json_encode(['ok'=>false,'mensaje'=>'No se pudo validar la tela']);
        }

        $telaNombre = (string)($telaRow['tela_nombre'] ?? '');
        $telaPrecio = (float)($telaRow['tela_precio'] ?? 0);
        $metros = $this->estimarMetrosPorTalla($talla);


        if(!$this->crearTablaSolicitudPersonalizadaSiNoExiste()){
            return json_encode(['ok'=>false,'mensaje'=>'No se pudo crear la tabla de solicitudes. Verifica tu BD.']);
        }

        $this->asegurarColumnaEncajeIdSolicitudPersonalizada();
        $tieneEncajeId = $this->columnaExiste('solicitud_personalizada', 'encaje_id');

        $pdo = null;
        try{
            $pdo = $this->conectar();
            $pdo->beginTransaction();

            $sql = 'INSERT INTO solicitud_personalizada (cliente_id, cita_fecha, cita_hora, talla, tela_id, tela_nombre, tela_precio, metros_estimados';
            if($tieneEncajeId){
                $sql .= ', encaje_id';
            }
            $sql .= ', encaje_key, encaje_nombre, encaje_precio, vestido_detalle, estado) VALUES (:cid, :f, :h, :talla, :tela_id, :tela_nombre, :tela_precio, :metros';
            if($tieneEncajeId){
                $sql .= ', :encaje_id';
            }
            $sql .= ', :ek, :en, :ep, :det, \'pendiente\')';

            $ins = $pdo->prepare($sql);
            $ins->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $ins->bindValue(':f', $fecha);
            $ins->bindValue(':h', $hora);
            $ins->bindValue(':talla', $talla);
            $ins->bindValue(':tela_id', $telaId, \PDO::PARAM_INT);
            $ins->bindValue(':tela_nombre', $telaNombre);
            $ins->bindValue(':tela_precio', $telaPrecio);
            $ins->bindValue(':metros', $metros);
            if($tieneEncajeId){
                $ins->bindValue(':encaje_id', $encajeId > 0 ? $encajeId : null, $encajeId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
            }
            $ins->bindValue(':ek', $encajeKey);
            $ins->bindValue(':en', $encajeNombre);
            $ins->bindValue(':ep', $encajePrecio);
            $ins->bindValue(':det', $detalle);
            $ins->execute();
            $solId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Correo al administrador (best-effort)
            try{
                $adminEmail = $this->obtenerEmailAdminDestino();
                if($adminEmail !== ''){
                    $cliStmt = $this->conectar()->prepare('SELECT cliente_nombre, cliente_apellido, cliente_email FROM cliente WHERE cliente_id=:id LIMIT 1');
                    $cliStmt->bindValue(':id', $clienteId, \PDO::PARAM_INT);
                    $cliStmt->execute();
                    $cli = $cliStmt->fetch();
                    $cliNombre = trim((string)($cli['cliente_nombre'] ?? '').' '.(string)($cli['cliente_apellido'] ?? ''));
                    $cliEmail = trim((string)($cli['cliente_email'] ?? ''));
                    if($cliNombre === ''){ $cliNombre = 'Cliente'; }

                    $subject = 'Nueva solicitud personalizada - '.(defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE');
                    $telaCosto = $metros * $telaPrecio;
                    $totalEstimado = $telaCosto + $encajePrecio;

                    $html = "
                        <div style=\"font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#111;\">
                            <p>Se registró una <strong>solicitud de vestido personalizado</strong>.</p>
                            <ul>
                                <li><strong>ID:</strong> ".htmlspecialchars((string)$solId,ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Cliente:</strong> ".htmlspecialchars($cliNombre,ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Email cliente:</strong> ".htmlspecialchars($cliEmail,ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Cita:</strong> ".htmlspecialchars($fecha.' '.$hora,ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Talla:</strong> ".htmlspecialchars($talla,ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Tela:</strong> ".htmlspecialchars($telaNombre,ENT_QUOTES,'UTF-8')." — ".htmlspecialchars(MONEDA_SIMBOLO.number_format($telaPrecio,2).' '.MONEDA_NOMBRE.' / m',ENT_QUOTES,'UTF-8')." — ".htmlspecialchars((string)$metros.' m aprox.',ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Encaje:</strong> ".htmlspecialchars($encajeNombre,ENT_QUOTES,'UTF-8')." — ".htmlspecialchars(MONEDA_SIMBOLO.number_format($encajePrecio,2).' '.MONEDA_NOMBRE.' / 1.5 m',ENT_QUOTES,'UTF-8')."</li>
                                <li><strong>Total estimado:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($totalEstimado,2).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>
                            </ul>
                            <p><strong>Detalle del vestido:</strong><br>
                                ".nl2br(htmlspecialchars($detalle !== '' ? $detalle : '—',ENT_QUOTES,'UTF-8'))."
                            </p>
                        </div>
                    ";

                    $mailer = new \app\services\MailService();
                    $okMail = $mailer->sendHtml($adminEmail, $subject, $html);
                    if(!$okMail){
                        $err = $mailer->getLastError() ?: 'Falló envío (sin detalle)';
                        error_log('[BOUTIQUE][MAIL] Fallo solicitud personalizada id='.$solId.' to='.$adminEmail.' :: '.$err);
                    }
                }
            }catch(\Throwable $e){
                error_log('[BOUTIQUE][MAIL] Excepción solicitud personalizada id='.$solId.' :: '.$e->getMessage());
            }

            return json_encode(['ok'=>true,'mensaje'=>'Solicitud enviada. Te contactaremos pronto.']);
        }catch(\Throwable $e){
            if($pdo instanceof \PDO){
                try{ $pdo->rollBack(); }catch(\Throwable $x){ /* ignore */ }
            }
            return json_encode(['ok'=>false,'mensaje'=>'No se pudo registrar la solicitud']);
        }
    }

    public function listarSolicitudesPersonalizadasAdminControlador(string $busqueda = '', string $estado = ''): string{
        if(!$this->sesionEsAdmin()){
            return "<article class='message is-danger'><div class='message-body'>Acceso restringido</div></article>";
        }

        $busqueda = trim($this->limpiarCadena($busqueda));
        $estado = trim($this->limpiarCadena($estado));

        if(!$this->tablaSolicitudPersonalizadaExiste()){
            return "<article class='message is-warning'><div class='message-body'>Aún no hay solicitudes personalizadas registradas (o falta crear la tabla). Puedes crear la tabla ejecutando <strong>DB/solicitud_personalizada.sql</strong> o enviar una solicitud desde <strong>telasCliente</strong>.</div></article>";
        }

        $where = [];
        $params = [];
        if($estado !== ''){
            $where[] = 'sp.estado = :estado';
            $params[':estado'] = $estado;
        }
        if($busqueda !== ''){
            $where[] = '(sp.solicitud_id = :idExact OR c.cliente_nombre LIKE :q OR c.cliente_apellido LIKE :q OR c.cliente_email LIKE :q)';
            $params[':q'] = '%'.$busqueda.'%';
            $params[':idExact'] = ctype_digit($busqueda) ? (int)$busqueda : -1;
        }

        $whereSql = '';
        if(!empty($where)){
            $whereSql = 'WHERE '.implode(' AND ', $where);
        }

        try{
            $sql = "SELECT sp.solicitud_id, sp.cita_fecha, sp.cita_hora, sp.talla, sp.tela_nombre, sp.tela_precio, sp.metros_estimados,
                sp.encaje_nombre, sp.encaje_precio, sp.vestido_detalle, sp.estado, sp.creado_en,
                c.cliente_nombre, c.cliente_apellido, c.cliente_email
                FROM solicitud_personalizada sp
                INNER JOIN cliente c ON c.cliente_id = sp.cliente_id
                {$whereSql}
                ORDER BY sp.solicitud_id DESC
                LIMIT 200";

            $stmt = $this->conectar()->prepare($sql);
            foreach($params as $k => $v){
                if($k === ':idExact'){
                    $stmt->bindValue($k, (int)$v, \PDO::PARAM_INT);
                }else{
                    $stmt->bindValue($k, $v);
                }
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();
            if(!is_array($rows) || empty($rows)){
                return "<article class='message is-light'><div class='message-body'>Sin resultados.</div></article>";
            }

            $html = "";
            $html .= "<div class='table-container'>";
            $html .= "<table class='table is-fullwidth is-striped is-hoverable'>";
            $html .= "<thead><tr>";
            $html .= "<th>ID</th><th>Cliente</th><th>Contacto</th><th>Cita</th><th>Tela</th><th>Encaje</th><th>Estado</th><th>Detalle</th><th>Creado</th>";
            $html .= "</tr></thead><tbody>";

            foreach($rows as $r){
                $id = (int)($r['solicitud_id'] ?? 0);
                $cliente = trim((string)($r['cliente_nombre'] ?? '').' '.(string)($r['cliente_apellido'] ?? ''));
                $email = (string)($r['cliente_email'] ?? '');
                $cita = trim((string)($r['cita_fecha'] ?? '').' '.(string)($r['cita_hora'] ?? ''));
                $talla = (string)($r['talla'] ?? '');
                $tela = (string)($r['tela_nombre'] ?? '');
                $telaPrecio = (float)($r['tela_precio'] ?? 0);
                $metros = (float)($r['metros_estimados'] ?? 0);
                $encaje = (string)($r['encaje_nombre'] ?? '');
                $encajePrecio = (float)($r['encaje_precio'] ?? 0);
                $est = (string)($r['estado'] ?? '');
                $detalle = (string)($r['vestido_detalle'] ?? '');
                $creado = (string)($r['creado_en'] ?? '');

                $detalleSafe = htmlspecialchars($detalle !== '' ? $detalle : '—', ENT_QUOTES, 'UTF-8');
                $clienteSafe = htmlspecialchars($cliente !== '' ? $cliente : '—', ENT_QUOTES, 'UTF-8');
                $emailSafe = htmlspecialchars($email !== '' ? $email : '—', ENT_QUOTES, 'UTF-8');
                $citaSafe = htmlspecialchars($cita !== '' ? $cita : '—', ENT_QUOTES, 'UTF-8');
                $tallaSafe = htmlspecialchars($talla !== '' ? $talla : '—', ENT_QUOTES, 'UTF-8');
                $telaSafe = htmlspecialchars($tela, ENT_QUOTES, 'UTF-8');
                $encajeSafe = htmlspecialchars($encaje, ENT_QUOTES, 'UTF-8');
                $estSafe = htmlspecialchars($est !== '' ? $est : '—', ENT_QUOTES, 'UTF-8');
                $creadoSafe = htmlspecialchars($creado !== '' ? $creado : '—', ENT_QUOTES, 'UTF-8');

                $telaTxt = $telaSafe;
                if($tela !== ''){
                    $telaTxt .= "<br><small>".htmlspecialchars(MONEDA_SIMBOLO.number_format($telaPrecio,2)." ".MONEDA_NOMBRE." / m",ENT_QUOTES,'UTF-8')."</small>";
                    $telaTxt .= "<br><small>".htmlspecialchars(number_format($metros,1)." m aprox.",ENT_QUOTES,'UTF-8')."</small>";
                }
                $encajeTxt = $encajeSafe;
                if($encaje !== ''){
                    $encajeTxt .= "<br><small>".htmlspecialchars(MONEDA_SIMBOLO.number_format($encajePrecio,2)." ".MONEDA_NOMBRE." / 1.5 m",ENT_QUOTES,'UTF-8')."</small>";
                }

                $html .= "<tr>";
                $html .= "<td>".htmlspecialchars((string)$id,ENT_QUOTES,'UTF-8')."</td>";
                $html .= "<td>{$clienteSafe}<br><small>Talla: {$tallaSafe}</small></td>";
                $html .= "<td>{$emailSafe}</td>";
                $html .= "<td>{$citaSafe}</td>";
                $html .= "<td>{$telaTxt}</td>";
                $html .= "<td>{$encajeTxt}</td>";
                $html .= "<td><span class='tag is-light'>${estSafe}</span></td>";
                $html .= "<td style='max-width: 420px; white-space: normal;'>".nl2br($detalleSafe)."</td>";
                $html .= "<td>{$creadoSafe}</td>";
                $html .= "</tr>";
            }

            $html .= "</tbody></table></div>";
            $html .= "<p class='help'>Mostrando hasta 200 registros (los más recientes).</p>";
            return $html;
        }catch(\Throwable $e){
            return "<article class='message is-danger'><div class='message-body'>No se pudo cargar la lista.</div></article>";
        }
    }

    public function listarSolicitudesPersonalizadasClienteControlador(): string{
        if(!isset($_SESSION['cliente_id']) || (int)$_SESSION['cliente_id'] <= 0){
            return json_encode(['ok' => false, 'mensaje' => 'Debes iniciar sesión']);
        }
        $clienteId = (int)$_SESSION['cliente_id'];

        if(!$this->tablaSolicitudPersonalizadaExiste()){
            return json_encode(['ok' => true, 'data' => [], 'mensaje' => 'Sin solicitudes registradas']);
        }

        try{
            $sql = "SELECT solicitud_id, cita_fecha, cita_hora, talla, tela_nombre, tela_precio, metros_estimados,
                encaje_nombre, encaje_precio, vestido_detalle, estado, creado_en
                FROM solicitud_personalizada
                WHERE cliente_id = :cid
                ORDER BY solicitud_id DESC
                LIMIT 50";
            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindValue(':cid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            if(!is_array($rows)){
                $rows = [];
            }
            return json_encode(['ok' => true, 'data' => $rows]);
        }catch(\Throwable $e){
            return json_encode(['ok' => false, 'mensaje' => 'No se pudo cargar el historial']);
        }
    }

    /*---------- Horarios disponibles (cliente) ----------*/
    public function horariosDisponiblesControlador(){
        $fecha = $this->limpiarCadena($_POST['cita_fecha'] ?? '');
        if($fecha==='' || !$this->fechaYmdValida($fecha)){
            return json_encode([
                'ok'=>false,
                'mensaje'=>'Fecha inválida'
            ]);
        }

        $hoy = date('Y-m-d');
        if($fecha < $hoy){
            return json_encode([
                'ok'=>false,
                'mensaje'=>'No puedes elegir una fecha pasada'
            ]);
        }

        if($this->esDomingo($fecha)){
            return json_encode([
                'ok'=>false,
                'mensaje'=>'Los domingos no atendemos'
            ]);
        }

        if($this->esFeriado($fecha)){
            return json_encode([
                'ok'=>false,
                'mensaje'=>'Este día es feriado y no está disponible'
            ]);
        }

        $permitidos = $this->generarHorariosPermitidos();
        $ocupados = $this->obtenerHorasOcupadas($fecha);
        $bloqueados = $this->obtenerHorasBloqueadas($fecha);

        $nowMinutes = null;
        if($fecha === $hoy){
            $nowMinutes = ((int)date('H'))*60 + (int)date('i');
        }

        $available = [];
        foreach($permitidos as $h){
            if(in_array($h, $ocupados, true)){
                continue;
            }
            if(in_array($h, $bloqueados, true)){
                continue;
            }
            if($nowMinutes !== null){
                $hm = $this->minutosDeHora12($h);
                if($hm !== null && $hm < $nowMinutes){
                    continue;
                }
            }
            $available[] = $h;
        }

        return json_encode([
            'ok'=>true,
            'available'=>$available,
            'taken'=>$ocupados
        ]);
    }


    /*---------- Horarios info (admin) ----------*/
    public function horariosInfoAdminControlador(){
        if(!$this->sesionEsAdmin()){
            return json_encode(['ok'=>false,'mensaje'=>'Acceso restringido']);
        }

        $fecha = $this->limpiarCadena($_POST['cita_fecha'] ?? '');
        if($fecha==='' || !$this->fechaYmdValida($fecha)){
            return json_encode(['ok'=>false,'mensaje'=>'Fecha inválida']);
        }

        $hoy = date('Y-m-d');
        if($fecha < $hoy){
            return json_encode(['ok'=>false,'mensaje'=>'No puedes editar una fecha pasada']);
        }

        if($this->esDomingo($fecha)){
            return json_encode(['ok'=>false,'mensaje'=>'Los domingos no están disponibles']);
        }

        if($this->esFeriado($fecha)){
            return json_encode(['ok'=>false,'mensaje'=>'Feriado: no disponible']);
        }

        $permitidos = $this->generarHorariosPermitidos();
        $ocupados = $this->obtenerHorasOcupadas($fecha);
        $bloqueados = $this->obtenerHorasBloqueadas($fecha);

        return json_encode([
            'ok'=>true,
            'permitidos'=>$permitidos,
            'taken'=>$ocupados,
            'blocked'=>$bloqueados
        ]);
    }

    public function guardarHorariosAdminControlador(){
        if(!$this->sesionEsAdmin()){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Acceso restringido',
                'texto'=>'Solo el administrador puede configurar horarios',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $fecha = $this->limpiarCadena($_POST['cita_fecha'] ?? '');
        if($fecha==='' || !$this->fechaYmdValida($fecha)){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Fecha inválida',
                'texto'=>'Selecciona una fecha válida',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $hoy = date('Y-m-d');
        if($fecha < $hoy){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Fecha no válida',
                'texto'=>'No puedes configurar una fecha pasada',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        if($this->esDomingo($fecha) || $this->esFeriado($fecha)){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Fecha no disponible',
                'texto'=>'No se permite configurar domingos ni feriados',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        if(!$this->crearTablaReservaHorarioBloqueoSiNoExiste()){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Error de configuración',
                'texto'=>'No se pudo crear la tabla de horarios. Verifica permisos de BD.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $permitidos = $this->generarHorariosPermitidos();
        $permitidosSet = array_flip($permitidos);

        $bloqueadas = $_POST['bloqueadas'] ?? [];
        if(!is_array($bloqueadas)){
            $bloqueadas = [];
        }

        $filtradas = [];
        foreach($bloqueadas as $h){
            $nh = $this->normalizarHora12($this->limpiarCadena($h));
            if($nh!=='' && isset($permitidosSet[$nh])){
                $filtradas[] = $nh;
            }
        }
        $filtradas = array_values(array_unique($filtradas));

        try{
            $pdo = $this->conectar();
            $pdo->beginTransaction();

            $del = $pdo->prepare('DELETE FROM reserva_horario_bloqueo WHERE bloqueo_fecha=:f');
            $del->bindParam(':f', $fecha);
            $del->execute();

            if(count($filtradas)>0){
                $uid = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;
                $now = date('Y-m-d H:i:s');
                $ins = $pdo->prepare('INSERT INTO reserva_horario_bloqueo (bloqueo_fecha,bloqueo_hora,usuario_id,creado_en) VALUES (:f,:h,:u,:c)');
                foreach($filtradas as $h){
                    $ins->bindParam(':f', $fecha);
                    $ins->bindParam(':h', $h);
                    $ins->bindParam(':u', $uid);
                    $ins->bindParam(':c', $now);
                    $ins->execute();
                }
            }

            $pdo->commit();
        }catch(\Throwable $e){
            if(isset($pdo) && $pdo->inTransaction()){
                $pdo->rollBack();
            }
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Error',
                'texto'=>'No se pudieron guardar los horarios',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $alerta=[
            'tipo'=>'simple',
            'titulo'=>'Listo',
            'texto'=>'Horarios guardados correctamente',
            'icono'=>'success'
        ];
        return json_encode($alerta);
    }

    private function cargarConfigMercadoPago(){
        if(!defined('MP_ACCESS_TOKEN')){
            $ruta = __DIR__."/../../config/mercadopago.php";
            if(file_exists($ruta)){
                require_once $ruta;
            }
        }
    }

    private function sesionEsAdmin(){
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

    private function tablaReservaExiste(){
        try{
            $check = $this->conectar()->query("SHOW TABLES LIKE 'reserva'");
            return ($check && $check->rowCount()>=1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function tablaReservaPagoExiste(){
        try{
            $check = $this->conectar()->query("SHOW TABLES LIKE 'reserva_pago'");
            return ($check && $check->rowCount()>=1);
        }catch(\Throwable $e){
            return false;
        }
    }

    private function obtenerUltimoComprobanteSubidoPorCodigo($codigo){
        $codigo = $this->limpiarCadena($codigo);
        if($codigo==="" || !$this->tablaReservaPagoExiste()){
            return null;
        }

        try{
            $stmt = $this->conectar()->prepare("SELECT * FROM reserva_pago WHERE reserva_codigo=:c AND pago_proveedor='manual' AND pago_status IN ('uploaded','pending','created') ORDER BY reserva_pago_id DESC LIMIT 1");
            $stmt->bindParam(':c', $codigo);
            $stmt->execute();
            $row = $stmt->fetch();
            return $row ? $row : null;
        }catch(\Throwable $e){
            return null;
        }
    }

    public function obtenerUltimoComprobanteSubidoPorCodigoControlador($codigo){
        return $this->obtenerUltimoComprobanteSubidoPorCodigo($codigo);
    }

    private function obtenerPagoAprobadoPorCodigo($codigo){
        $codigo = $this->limpiarCadena($codigo);
        if($codigo==="" || !$this->tablaReservaPagoExiste()){
            return null;
        }

        try{
            $stmt = $this->conectar()->prepare("SELECT * FROM reserva_pago WHERE reserva_codigo=:c AND pago_status='approved' ORDER BY reserva_pago_id DESC LIMIT 1");
            $stmt->bindParam(':c', $codigo);
            $stmt->execute();
            $row = $stmt->fetch();
            return $row ? $row : null;
        }catch(\Throwable $e){
            return null;
        }
    }

    public function obtenerUltimoPagoAprobadoPorCodigoControlador($codigo){
        return $this->obtenerPagoAprobadoPorCodigo($codigo);
    }

    public function subirComprobanteReservaClienteControlador(){
        // Solo cliente logueado
        if(!isset($_SESSION['cliente_id']) || (int)$_SESSION['cliente_id']<=0){
            return json_encode([
                'ok'=>false,
                'error'=>'unauthorized',
                'message'=>'Debes iniciar sesión para subir tu comprobante.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? '');
        if($codigo===''){
            return json_encode([
                'ok'=>false,
                'error'=>'invalid_codigo',
                'message'=>'Código de reserva inválido.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            return json_encode([
                'ok'=>false,
                'error'=>'not_found',
                'message'=>'Reserva no encontrada.'
            ], JSON_UNESCAPED_UNICODE);
        }

        if((int)$reserva['cliente_id'] !== (int)$_SESSION['cliente_id']){
            return json_encode([
                'ok'=>false,
                'error'=>'forbidden',
                'message'=>'No tienes permiso para subir comprobante de esta reserva.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $file = $_FILES['comprobante'] ?? null;
        if(!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
            return json_encode([
                'ok'=>false,
                'error'=>'no_file',
                'message'=>'Selecciona un archivo de comprobante.'
            ], JSON_UNESCAPED_UNICODE);
        }
        if(($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK){
            return json_encode([
                'ok'=>false,
                'error'=>'upload_error',
                'message'=>'Error al subir el archivo.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if($tmp==='' || !is_uploaded_file($tmp)){
            return json_encode([
                'ok'=>false,
                'error'=>'invalid_upload',
                'message'=>'Subida inválida.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $maxMb = 10;
        $cfg = __DIR__ . '/../../config/reserva_pago_qr.php';
        if(file_exists($cfg)){
            require_once $cfg;
            if(defined('RESERVA_COMPROBANTE_MAX_MB')){
                $maxMb = (int)RESERVA_COMPROBANTE_MAX_MB;
            }
        }
        if($maxMb<=0){ $maxMb = 10; }
        $maxBytes = $maxMb * 1024 * 1024;
        $size = (int)($file['size'] ?? 0);
        if($size<=0 || $size>$maxBytes){
            return json_encode([
                'ok'=>false,
                'error'=>'file_too_large',
                'message'=>'El comprobante supera el tamaño permitido (máx. '.$maxMb.'MB).'
            ], JSON_UNESCAPED_UNICODE);
        }

        $mime = @mime_content_type($tmp);
        $allowed = ['image/jpeg','image/png','application/pdf'];
        if(!in_array((string)$mime, $allowed, true)){
            return json_encode([
                'ok'=>false,
                'error'=>'invalid_type',
                'message'=>'Formato no permitido. Sube JPG, PNG o PDF.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $ext = '.bin';
        switch((string)$mime){
            case 'image/jpeg': $ext = '.jpg'; break;
            case 'image/png': $ext = '.png'; break;
            case 'application/pdf': $ext = '.pdf'; break;
        }

        $dir = __DIR__ . '/../views/comprobantes_reserva/';
        if(!is_dir($dir)){
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);

        $base = 'reserva_'.$codigo.'_'.date('YmdHis').'_'.rand(100,999);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/','_', $base).$ext;
        $destPath = $dir.$filename;
        if(!@move_uploaded_file($tmp, $destPath)){
            return json_encode([
                'ok'=>false,
                'error'=>'move_failed',
                'message'=>'No se pudo guardar el comprobante.'
            ], JSON_UNESCAPED_UNICODE);
        }

        // Registrar en reserva_pago como pago manual
        if($this->tablaReservaPagoExiste()){
            try{
                $now = date('Y-m-d H:i:s');
                $paymentId = 'manual_'.date('YmdHis').'_'.substr(md5($codigo.$filename),0,8);
                $raw = json_encode([
                    'tipo'=>'comprobante',
                    'archivo'=>'app/views/comprobantes_reserva/'.$filename,
                    'mime'=>(string)$mime,
                    'size'=>$size,
                    'subido_en'=>$now
                ], JSON_UNESCAPED_UNICODE);

                $moneda = defined('MONEDA_NOMBRE') ? (string)MONEDA_NOMBRE : 'BOB';
                $monto = number_format(0, MONEDA_DECIMALES, '.', '');
                $stmt = $this->conectar()->prepare("INSERT INTO reserva_pago (reserva_codigo,pago_proveedor,pago_payment_id,pago_status,pago_monto,pago_moneda,pago_creado_en,pago_actualizado_en,pago_raw)
                    VALUES (:c,'manual',:pid,'uploaded',:m,:mon,:cre,:act,:raw)");
                $stmt->bindParam(':c', $codigo);
                $stmt->bindParam(':pid', $paymentId);
                $stmt->bindParam(':m', $monto);
                $stmt->bindParam(':mon', $moneda);
                $stmt->bindParam(':cre', $now);
                $stmt->bindParam(':act', $now);
                $stmt->bindParam(':raw', $raw);
                $stmt->execute();
            }catch(\Throwable $e){
                // No bloqueamos al cliente si el registro falla; el archivo ya está guardado.
            }
        }

        return json_encode([
            'ok'=>true,
            'message'=>'Comprobante subido correctamente.',
            'file'=>'app/views/comprobantes_reserva/'.$filename
        ], JSON_UNESCAPED_UNICODE);
    }

    public function subirQrEstaticoReservaAdminControlador(){
        if(!$this->sesionEsAdmin()){
            return json_encode([
                'ok'=>false,
                'error'=>'unauthorized',
                'message'=>'Acceso restringido.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $file = $_FILES['qr_image'] ?? null;
        if(!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
            return json_encode([
                'ok'=>false,
                'error'=>'no_file',
                'message'=>'Selecciona una imagen (PNG/JPG) del QR.'
            ], JSON_UNESCAPED_UNICODE);
        }
        if(($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK){
            return json_encode([
                'ok'=>false,
                'error'=>'upload_error',
                'message'=>'Error al subir la imagen.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if($tmp==='' || !is_uploaded_file($tmp)){
            return json_encode([
                'ok'=>false,
                'error'=>'invalid_upload',
                'message'=>'Subida inválida.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $size = (int)($file['size'] ?? 0);
        $maxBytes = 5 * 1024 * 1024;
        if($size<=0 || $size>$maxBytes){
            return json_encode([
                'ok'=>false,
                'error'=>'file_too_large',
                'message'=>'La imagen supera el tamaño permitido (máx. 5MB).'
            ], JSON_UNESCAPED_UNICODE);
        }

        $mime = @mime_content_type($tmp);
        if($mime!=='image/png' && $mime!=='image/jpeg'){
            return json_encode([
                'ok'=>false,
                'error'=>'invalid_type',
                'message'=>'Formato no permitido. Sube PNG o JPG.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $ext = ($mime==='image/png') ? '.png' : '.jpg';
        $dir = __DIR__ . '/../views/img/';
        if(!is_dir($dir)){
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);

        $filename = 'qr_reserva'. $ext;
        $destPath = $dir.$filename;
        if(!@move_uploaded_file($tmp, $destPath)){
            return json_encode([
                'ok'=>false,
                'error'=>'move_failed',
                'message'=>'No se pudo guardar la imagen del QR.'
            ], JSON_UNESCAPED_UNICODE);
        }

        // Actualizar config
        $configPath = __DIR__ . '/../../config/reserva_pago_qr.php';
        if(!file_exists($configPath)){
            return json_encode([
                'ok'=>false,
                'error'=>'config_missing',
                'message'=>'No existe config/reserva_pago_qr.php'
            ], JSON_UNESCAPED_UNICODE);
        }

        $cfg = @file_get_contents($configPath);
        if(!is_string($cfg) || $cfg===''){
            return json_encode([
                'ok'=>false,
                'error'=>'config_read_failed',
                'message'=>'No se pudo leer la configuración del QR.'
            ], JSON_UNESCAPED_UNICODE);
        }

        $newRel = 'app/views/img/'.$filename;
        $pattern = "/const\s+RESERVA_PAGO_QR_IMAGE\s*=\s*'[^']*'\s*;/";
        $replacement = "const RESERVA_PAGO_QR_IMAGE = '".$newRel."';";
        if(preg_match($pattern, $cfg)){
            $cfgNew = preg_replace($pattern, $replacement, $cfg, 1);
        }else{
            $cfgNew = $cfg."\n\n".$replacement."\n";
        }

        if(!is_string($cfgNew) || $cfgNew===''){
            return json_encode([
                'ok'=>false,
                'error'=>'config_update_failed',
                'message'=>'No se pudo actualizar la configuración.'
            ], JSON_UNESCAPED_UNICODE);
        }

        if(@file_put_contents($configPath, $cfgNew)===false){
            return json_encode([
                'ok'=>false,
                'error'=>'config_write_failed',
                'message'=>'No se pudo guardar la configuración. Revisa permisos del archivo.'
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'ok'=>true,
            'message'=>'QR actualizado correctamente.',
            'file'=>$newRel
        ], JSON_UNESCAPED_UNICODE);
    }

    private function registrarReservaPagoCreado($codigo,$preferenceId,$initPoint,$monto,$moneda,$raw=null){
        if(!$this->tablaReservaPagoExiste()){
            return false;
        }
        try{
            $now = date('Y-m-d H:i:s');
            $stmt = $this->conectar()->prepare("INSERT INTO reserva_pago (reserva_codigo,pago_proveedor,pago_preference_id,pago_init_point,pago_status,pago_monto,pago_moneda,pago_creado_en,pago_actualizado_en,pago_raw)
                VALUES (:c,'mercadopago',:pref,:init,'created',:m,:mon,:cre,:act,:raw)");
            $stmt->bindParam(':c', $codigo);
            $stmt->bindParam(':pref', $preferenceId);
            $stmt->bindParam(':init', $initPoint);
            $montoFmt = number_format((float)$monto, MONEDA_DECIMALES, '.', '');
            $stmt->bindParam(':m', $montoFmt);
            $stmt->bindParam(':mon', $moneda);
            $stmt->bindParam(':cre', $now);
            $stmt->bindParam(':act', $now);
            $rawStr = $raw!==null ? (is_string($raw) ? $raw : json_encode($raw)) : null;
            $stmt->bindParam(':raw', $rawStr);
            return $stmt->execute();
        }catch(\Throwable $e){
            return false;
        }
    }

    private function upsertPagoDesdeMercadoPago($codigo,$paymentId,$status,$monto,$moneda,$raw=null){
        if(!$this->tablaReservaPagoExiste()){
            return false;
        }
        try{
            $now = date('Y-m-d H:i:s');
            $aprobadoEn = null;
            if($status==='approved'){
                $aprobadoEn = $now;
            }

            $sql = "INSERT INTO reserva_pago
                    (reserva_codigo,pago_proveedor,pago_payment_id,pago_status,pago_monto,pago_moneda,pago_creado_en,pago_actualizado_en,pago_aprobado_en,pago_raw)
                    VALUES
                    (:c,'mercadopago',:pid,:st,:m,:mon,:cre,:act,:apr,:raw)
                    ON DUPLICATE KEY UPDATE
                        reserva_codigo=VALUES(reserva_codigo),
                        pago_status=VALUES(pago_status),
                        pago_monto=VALUES(pago_monto),
                        pago_moneda=VALUES(pago_moneda),
                        pago_actualizado_en=VALUES(pago_actualizado_en),
                        pago_aprobado_en=IF(VALUES(pago_status)='approved', VALUES(pago_actualizado_en), pago_aprobado_en),
                        pago_raw=VALUES(pago_raw)";

            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindParam(':c', $codigo);
            $pid = (string)$paymentId;
            $stmt->bindParam(':pid', $pid);
            $stmt->bindParam(':st', $status);
            $montoFmt = number_format((float)$monto, MONEDA_DECIMALES, '.', '');
            $stmt->bindParam(':m', $montoFmt);
            $stmt->bindParam(':mon', $moneda);
            $stmt->bindParam(':cre', $now);
            $stmt->bindParam(':act', $now);
            $stmt->bindParam(':apr', $aprobadoEn);
            $rawStr = $raw!==null ? (is_string($raw) ? $raw : json_encode($raw)) : null;
            $stmt->bindParam(':raw', $rawStr);
            return $stmt->execute();
        }catch(\Throwable $e){
            return false;
        }
    }

    private function confirmarReservaDesdePagoOnline($codigo,$abono,$usuarioAuto=1){
        $codigo = $this->limpiarCadena($codigo);
        if($codigo===''){
            return false;
        }

        if(!$this->tablaReservaExiste()){
            return false;
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            return false;
        }

        if(($reserva['reserva_estado'] ?? '')==='confirmada'){
            return true;
        }

        $total = (float)$reserva['reserva_total'];
        $abono = (float)$abono;
        $minimo = (float)number_format($total * 0.50, MONEDA_DECIMALES, '.', '');

        if($abono < $minimo){
            return false;
        }
        if($abono > $total){
            $abono = $total;
        }

        $abonoFmt = number_format($abono, MONEDA_DECIMALES, '.', '');

        $usuarioAuto = (int)$usuarioAuto;
        if($usuarioAuto<=0){
            $usuarioAuto = 1;
        }

        $pdo = $this->conectar();

        $confirmada = false;

        try{
            $pdo->beginTransaction();

            $stmtProd = $pdo->prepare("SELECT producto_stock_total FROM producto WHERE producto_id=:pid FOR UPDATE");
            $pid = (int)$reserva['producto_id'];
            $stmtProd->bindParam(':pid', $pid, \PDO::PARAM_INT);
            $stmtProd->execute();
            $prod = $stmtProd->fetch();
            if(!$prod || (int)$prod['producto_stock_total']<=0){
                $pdo->rollBack();
                return false;
            }

            $nuevo_stock = ((int)$prod['producto_stock_total']) - 1;
            $stmtUpProd = $pdo->prepare("UPDATE producto SET producto_stock_total=:s WHERE producto_id=:pid");
            $stmtUpProd->bindParam(':s', $nuevo_stock, \PDO::PARAM_INT);
            $stmtUpProd->bindParam(':pid', $pid, \PDO::PARAM_INT);
            if(!$stmtUpProd->execute()){
                throw new \Exception('No se pudo actualizar stock');
            }

            $sqlUpRes = "UPDATE reserva
                    SET reserva_abono=:a,
                        reserva_estado='confirmada',
                        usuario_id=:uid,
                        caja_id=NULL";
            if($this->columnaReservaClienteNotificacionDisponible()){
                $sqlUpRes .= ", reserva_cliente_notificacion=(reserva_cliente_notificacion+1)";
            }
            $sqlUpRes .= " WHERE reserva_codigo=:c AND reserva_estado='pendiente'";
            $stmtUpRes = $pdo->prepare($sqlUpRes);
            $stmtUpRes->bindParam(':a', $abonoFmt);
            $stmtUpRes->bindParam(':uid', $usuarioAuto, \PDO::PARAM_INT);
            $stmtUpRes->bindParam(':c', $codigo);
            $stmtUpRes->execute();

            if($stmtUpRes->rowCount()!=1){
                throw new \Exception('La reserva no pudo confirmarse');
            }

            $pdo->commit();
            $confirmada = true;

        }catch(\Throwable $e){
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            return false;
        }

        if($confirmada){
            // Enviar ticket de reserva al cliente (best-effort)
            $this->enviarTicketReservaPorCorreo($codigo);
        }

        return true;
    }

    public function redirigirAPagoMercadoPagoControlador(){

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? '');
        $montoTipo = $this->limpiarCadena($_POST['monto_tipo'] ?? 'minimo');

        if($codigo===''){
            echo "Código de reserva inválido";
            return;
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            echo "Reserva no encontrada";
            return;
        }

        if(($reserva['reserva_estado'] ?? '')==='confirmada'){
            if(headers_sent()){
                echo "<script>window.location.href='".APP_URL."reservaPagar/".urlencode($codigo)."/';</script>";
            }else{
                header('Location: '.APP_URL.'reservaPagar/'.urlencode($codigo).'/');
            }
            return;
        }

        $total = (float)$reserva['reserva_total'];
        $minimo = (float)number_format($total * 0.50, MONEDA_DECIMALES, '.', '');
        $monto = $minimo;

        if($montoTipo==='total'){
            $monto = $total;
        }elseif($montoTipo==='custom'){
            $custom = (float)$this->limpiarCadena($_POST['monto_custom'] ?? '0');
            if($custom >= $minimo && $custom <= $total){
                $monto = $custom;
            }
        }

        $this->cargarConfigMercadoPago();
        $mp = new MercadoPagoService();
        if(!$mp->configuracionValida()){
            echo "Mercado Pago no está configurado. Revisa config/mercadopago.php";
            return;
        }

        $currency = defined('MP_CURRENCY_ID') ? MP_CURRENCY_ID : 'BOB';

        $params = [
            'items' => [
                [
                    'title' => 'Reserva '.$reserva['reserva_codigo'].' - '.$reserva['producto_nombre'],
                    'quantity' => 1,
                    'unit_price' => (float)$monto,
                    'currency_id' => $currency
                ]
            ],
            'external_reference' => (string)$reserva['reserva_codigo'],
            'back_urls' => [
                'success' => APP_URL.'reservaPagar/'.urlencode($reserva['reserva_codigo']).'/?mp_result=success',
                'pending' => APP_URL.'reservaPagar/'.urlencode($reserva['reserva_codigo']).'/?mp_result=pending',
                'failure' => APP_URL.'reservaPagar/'.urlencode($reserva['reserva_codigo']).'/?mp_result=failure'
            ],
            'auto_return' => 'approved'
        ];

        if(defined('MP_WEBHOOK_TOKEN') && MP_WEBHOOK_TOKEN!=='' && MP_WEBHOOK_TOKEN!=='CAMBIAME_POR_UN_TOKEN_LARGO'){
            $params['notification_url'] = APP_URL.'mercadopagoWebhook/?token='.urlencode(MP_WEBHOOK_TOKEN);
        }

        // Opcional: enviar email del cliente a MP
        if(!empty($reserva['cliente_email'])){
            $params['payer'] = [
                'email' => (string)$reserva['cliente_email']
            ];
        }

        $pref = $mp->crearPreferencia($params);

        if(!is_array($pref) || empty($pref['init_point'])){
            echo "No se pudo crear el link de pago en Mercado Pago";
            return;
        }

        $this->registrarReservaPagoCreado(
            (string)$reserva['reserva_codigo'],
            (string)($pref['id'] ?? ''),
            (string)$pref['init_point'],
            (float)$monto,
            (string)$currency,
            $pref
        );

        if(headers_sent()){
            echo "<script>window.location.href='".htmlspecialchars($pref['init_point'],ENT_QUOTES,'UTF-8')."';</script>";
        }else{
            header('Location: '.$pref['init_point']);
        }
    }


    public function procesarWebhookMercadoPagoControlador(){

        $this->cargarConfigMercadoPago();

        if(defined('MP_WEBHOOK_TOKEN') && MP_WEBHOOK_TOKEN!=='' && MP_WEBHOOK_TOKEN!=='CAMBIAME_POR_UN_TOKEN_LARGO'){
            $token = $_GET['token'] ?? '';
            if(!hash_equals(MP_WEBHOOK_TOKEN, (string)$token)){
                http_response_code(401);
                return json_encode(['ok'=>false,'error'=>'unauthorized']);
            }
        }

        $paymentId = '';

        if(isset($_GET['id']) && (isset($_GET['topic']) && $_GET['topic']==='payment')){
            $paymentId = (string)$_GET['id'];
        }
        if($paymentId==='' && isset($_GET['data_id'])){
            $paymentId = (string)$_GET['data_id'];
        }

        $rawBody = file_get_contents('php://input');
        $json = null;
        if($rawBody){
            $json = json_decode($rawBody,true);
            if(is_array($json)){
                if(isset($json['data']['id'])){
                    $paymentId = (string)$json['data']['id'];
                }elseif(isset($json['id']) && (isset($json['type']) && $json['type']==='payment')){
                    $paymentId = (string)$json['id'];
                }
            }
        }

        if($paymentId===''){
            return json_encode(['ok'=>true,'ignored'=>true]);
        }

        $mp = new MercadoPagoService();
        if(!$mp->configuracionValida()){
            http_response_code(500);
            return json_encode(['ok'=>false,'error'=>'mp_not_configured']);
        }

        $pago = $mp->obtenerPago($paymentId);
        if(!is_array($pago) || (($pago['_http_code'] ?? 0) < 200) || (($pago['_http_code'] ?? 0) >= 300)){
            return json_encode(['ok'=>true,'fetched'=>false]);
        }

        $codigo = (string)($pago['external_reference'] ?? '');
        $status = (string)($pago['status'] ?? '');
        $monto = (float)($pago['transaction_amount'] ?? 0);
        $moneda = (string)($pago['currency_id'] ?? (defined('MP_CURRENCY_ID') ? MP_CURRENCY_ID : 'BOB'));

        if($codigo===''){
            return json_encode(['ok'=>true,'no_reference'=>true]);
        }

        $this->upsertPagoDesdeMercadoPago($codigo, $paymentId, $status, $monto, $moneda, $pago);

        if(defined('MP_AUTO_CONFIRM_RESERVA') && MP_AUTO_CONFIRM_RESERVA===true && $status==='approved'){
            $usuarioAuto = defined('MP_AUTO_CONFIRM_USUARIO_ID') ? (int)MP_AUTO_CONFIRM_USUARIO_ID : 1;
            $this->confirmarReservaDesdePagoOnline($codigo, $monto, $usuarioAuto);
        }

        return json_encode(['ok'=>true]);
    }

    private function obtenerReservaPorCodigo($codigo){
        $codigo = $this->limpiarCadena($codigo);

        if(!$this->tablaReservaExiste()){
            return false;
        }

        $sql = "SELECT r.*, 
                       c.cliente_nombre, c.cliente_apellido, c.cliente_email,
                       p.producto_nombre, p.producto_precio_venta, p.producto_stock_total, p.producto_foto
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id = r.cliente_id
                INNER JOIN producto p ON p.producto_id = r.producto_id
                WHERE r.reserva_codigo = :codigo
                LIMIT 1";

        try{
            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindParam(":codigo", $codigo);
            $stmt->execute();
            return $stmt->fetch();
        }catch(\Throwable $e){
            return false;
        }
    }

    public function obtenerReservaPorCodigoControlador($codigo){
        return $this->obtenerReservaPorCodigo($codigo);
    }


    /*---------- Listar reservas pendientes (solo admin) ----------*/
    public function listarReservasPendientesControlador($limite=50){

        if(!$this->tablaReservaExiste()){
            return [];
        }

        $limite = (int)$limite;
        if($limite<=0){
            $limite = 50;
        }
        if($limite>200){
            $limite = 200;
        }

        $sql = "SELECT r.reserva_id, r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado,
                       c.cliente_nombre, c.cliente_apellido, c.cliente_email,
                       p.producto_nombre
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id = r.cliente_id
                INNER JOIN producto p ON p.producto_id = r.producto_id
                WHERE r.reserva_estado='pendiente'
                ORDER BY r.reserva_id DESC
                LIMIT :lim";

        try{
            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindValue(":lim", $limite, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }catch(\Throwable $e){
            return [];
        }
    }


    /*---------- Listar citas de hoy (solo admin) ----------*/
    public function listarCitasDeHoyControlador($limite=50){

        if(!$this->tablaReservaExiste()){
            return '<article class="message is-danger"><div class="message-body">No existe la tabla <strong>reserva</strong> en la base de datos.</div></article>';
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
            return '<article class="message is-danger"><div class="message-body">Debes iniciar sesión.</div></article>';
        }

        if(!$this->sesionEsAdmin()){
            return '';
        }

        $limite = (int)$limite;
        if($limite<=0){
            $limite = 50;
        }
        if($limite>200){
            $limite = 200;
        }

        $hoy = date('Y-m-d');

        $sql = "SELECT r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado,
                       c.cliente_nombre, c.cliente_apellido, c.cliente_email,
                       p.producto_nombre
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id = r.cliente_id
                INNER JOIN producto p ON p.producto_id = r.producto_id
            WHERE r.reserva_fecha = :hoy AND r.reserva_estado NOT IN ('rechazada','completada')
                ORDER BY STR_TO_DATE(r.reserva_hora, '%h:%i %p') ASC, r.reserva_id ASC
                LIMIT :lim";

        try{
            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindParam(':hoy', $hoy);
            $stmt->bindValue(':lim', $limite, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }catch(\Throwable $e){
            $rows = [];
        }

        if(empty($rows)){
            return '<article class="message is-info"><div class="message-body">No hay citas para hoy.</div></article>';
        }

        $tabla = '<div class="table-container">';
        $tabla .= '<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">';
        $tabla .= '<thead><tr>';
        $tabla .= '<th class="has-text-centered">Hora</th>';
        $tabla .= '<th class="has-text-centered">Código</th>';
        $tabla .= '<th class="has-text-centered">Cliente</th>';
        $tabla .= '<th class="has-text-centered">Producto</th>';
        $tabla .= '<th class="has-text-centered">Total</th>';
        $tabla .= '<th class="has-text-centered">Abono</th>';
        $tabla .= '<th class="has-text-centered">Estado</th>';
        $tabla .= '</tr></thead><tbody>';

        foreach($rows as $r){
            $estado = (string)($r['reserva_estado'] ?? '');
            $tagColor = 'is-info';
            if($estado==='pendiente'){
                $tagColor = 'is-warning';
            }elseif($estado==='confirmada'){
                $tagColor = 'is-success';
            }elseif($estado==='reprogramada'){
                $tagColor = 'is-link';
            }elseif($estado==='completada'){
                $tagColor = 'is-link';
            }

            $cliente = $this->limitarCadena(trim((string)$r['cliente_nombre'].' '.(string)$r['cliente_apellido']), 30, '...');
            $producto = $this->limitarCadena((string)$r['producto_nombre'], 30, '...');
            $codigo = (string)($r['reserva_codigo'] ?? '');
            $hora = (string)($r['reserva_hora'] ?? '');
            $email = (string)($r['cliente_email'] ?? '');

            $tabla .= '<tr class="has-text-centered">';
            $tabla .= '<td>'.htmlspecialchars($hora, ENT_QUOTES, 'UTF-8').'</td>';
            $tabla .= '<td>'.htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8').'</td>';
            $tabla .= '<td>'.htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8').'<br><span class="is-size-7 has-text-grey">'.htmlspecialchars($this->limitarCadena($email, 35, '...'), ENT_QUOTES, 'UTF-8').'</span></td>';
            $tabla .= '<td>'.htmlspecialchars($producto, ENT_QUOTES, 'UTF-8').'</td>';
            $tabla .= '<td>'.MONEDA_SIMBOLO.number_format((float)$r['reserva_total'], MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE.'</td>';
            $tabla .= '<td>'.MONEDA_SIMBOLO.number_format((float)$r['reserva_abono'], MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).'</td>';
            $tabla .= '<td><span class="tag '.$tagColor.' is-light">'.htmlspecialchars($estado, ENT_QUOTES, 'UTF-8').'</span></td>';
            $tabla .= '</tr>';
        }

        $tabla .= '</tbody></table></div>';
        return $tabla;
    }


    /*---------- Calendario de reservas (solo admin) ----------*/
    public function mostrarCalendarioReservasControlador($limite=200){

        if(!$this->tablaReservaExiste()){
            return '<article class="message is-danger"><div class="message-body">No existe la tabla <strong>reserva</strong> en la base de datos.</div></article>';
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
            return '<article class="message is-danger"><div class="message-body">Debes iniciar sesión.</div></article>';
        }

        if(!$this->sesionEsAdmin()){
            return '';
        }

        $limite = (int)$limite;
        if($limite<=0){
            $limite = 200;
        }
        if($limite>500){
            $limite = 500;
        }

        $hoy = date('Y-m-d');
        $fechaSel = isset($_GET['fecha']) ? (string)$this->limpiarCadena($_GET['fecha']) : $hoy;

        // Permitir también YYYY-MM (desde el selector <input type="month">)
        if(preg_match('/^\d{4}-\d{2}$/', $fechaSel)){
            $fechaSel .= '-01';
        }

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSel)){
            $fechaSel = $hoy;
        }else{
            $yy = (int)substr($fechaSel, 0, 4);
            $mm = (int)substr($fechaSel, 5, 2);
            $dd = (int)substr($fechaSel, 8, 2);
            if(!checkdate($mm, $dd, $yy)){
                $fechaSel = $hoy;
            }
        }

        $anio = (int)substr($fechaSel, 0, 4);
        $mes = (int)substr($fechaSel, 5, 2);

        if($anio<2000 || $anio>2100){
            $anio = (int)date('Y');
        }
        if($mes<1 || $mes>12){
            $mes = (int)date('m');
        }

        $inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
        $finMes = date('Y-m-t', strtotime($inicioMes));
        $diasEnMes = (int)date('t', strtotime($inicioMes));
        $offsetInicio = (int)date('N', strtotime($inicioMes));

        $prevMes = date('Y-m-d', strtotime($inicioMes.' -1 month'));
        $nextMes = date('Y-m-d', strtotime($inicioMes.' +1 month'));

        // Cargar todas las citas del mes (para poder mostrarlas dentro del calendario)
        $citasPorFecha = [];
        $conteo = [];
        $limiteMes = 2000;
        $sqlMes = "SELECT r.reserva_fecha, r.reserva_hora, r.reserva_codigo, r.reserva_estado,
                          c.cliente_nombre, c.cliente_apellido,
                          p.producto_nombre
                   FROM reserva r
                   INNER JOIN cliente c ON c.cliente_id = r.cliente_id
                   INNER JOIN producto p ON p.producto_id = r.producto_id
                   WHERE r.reserva_fecha BETWEEN :ini AND :fin
                                         AND r.reserva_estado <> 'rechazada'
                   ORDER BY r.reserva_fecha ASC, STR_TO_DATE(r.reserva_hora, '%h:%i %p') ASC, r.reserva_id ASC
                   LIMIT :lim";
        try{
            $stmt = $this->conectar()->prepare($sqlMes);
            $stmt->bindParam(':ini', $inicioMes);
            $stmt->bindParam(':fin', $finMes);
            $stmt->bindValue(':lim', $limiteMes, \PDO::PARAM_INT);
            $stmt->execute();
            $rowsMes = $stmt->fetchAll();
            foreach($rowsMes as $rm){
                $f = (string)($rm['reserva_fecha'] ?? '');
                if($f===''){
                    continue;
                }
                if(!isset($citasPorFecha[$f])){
                    $citasPorFecha[$f] = [];
                }
                $citasPorFecha[$f][] = $rm;
            }
            foreach($citasPorFecha as $f=>$items){
                $conteo[$f] = count($items);
            }
        }catch(\Throwable $e){
            $citasPorFecha = [];
            $conteo = [];
        }

        // Config para el calendario semanal
        $cfgCitas = $this->obtenerConfigCitas();
        $minTime = isset($cfgCitas['start']) ? (string)$cfgCitas['start'] : '10:00';
        $maxTime = isset($cfgCitas['end']) ? (string)$cfgCitas['end'] : '19:00';
        $slotMinutes = isset($cfgCitas['interval_minutes']) ? (int)$cfgCitas['interval_minutes'] : 30;
        if($slotMinutes <= 0){
            $slotMinutes = 30;
        }

        $meses = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];
        $nombreMes = $meses[$mes] ?? '';
        // Usar URLs root-relative basadas en APP_URL para:
        // - evitar problemas de sesión por host distinto (same-origin)
        // - evitar rutas relativas rotas dentro de /reservaHoy/
        $appPath = (string)parse_url(APP_URL, PHP_URL_PATH);
        if($appPath === ''){ $appPath = '/'; }
        if(substr($appPath, -1) !== '/'){ $appPath .= '/'; }
        $baseUrl = $appPath.'reservaHoy/';

        $html = '';
        $html .= '<div class="columns is-variable is-6">';

        $html .= '<div class="column is-3">';
        $html .= '<div class="card bq-mini-card">';
        $html .= '<header class="card-header">';
        $html .= '<p class="card-header-title">';
        $html .= '<span class="icon"><i class="fas fa-calendar-alt"></i></span>';
        $html .= '<span>'.htmlspecialchars($nombreMes.' '.$anio, ENT_QUOTES, 'UTF-8').'</span>';
        $html .= '</p>';
        $html .= '<div class="card-header-icon">';
        $html .= '<div class="buttons has-addons">';
        $html .= '<a class="button is-small is-light" href="'.$baseUrl.'?fecha='.urlencode($prevMes).'" aria-label="Mes anterior"><span class="icon is-small"><i class="fas fa-chevron-left"></i></span></a>';
        $html .= '<a class="button is-small is-light" href="'.$baseUrl.'?fecha='.urlencode($nextMes).'" aria-label="Mes siguiente"><span class="icon is-small"><i class="fas fa-chevron-right"></i></span></a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</header>';

        $html .= '<div class="card-content">';

        // Botón/selector de mes sin depender de JS
        $html .= '<details class="bq-mini-month-details">';
        $html .= '  <summary class="button is-small is-light" aria-label="Seleccionar mes y año" title="Seleccionar mes y año"><span class="icon is-small"><i class="fas fa-calendar"></i></span><span>Seleccionar mes</span></summary>';
        $html .= '  <form method="GET" action="'.$baseUrl.'" class="mt-2">';
        $html .= '    <div class="field has-addons">';
        $html .= '      <div class="control is-expanded">';
        $html .= '        <input class="input is-small" type="month" name="fecha" min="2000-01" max="2100-12" value="'.htmlspecialchars(sprintf('%04d-%02d', $anio, $mes), ENT_QUOTES, 'UTF-8').'">';
        $html .= '      </div>';
        $html .= '      <div class="control">';
        $html .= '        <button class="button is-small is-link" type="submit">Ir</button>';
        $html .= '      </div>';
        $html .= '    </div>';
        $html .= '  </form>';
        $html .= '</details>';

        // Mini calendario mensual ULTRA compacto
        $html .= '<style>';
        $html .= '  .bq-mini-card .card-header-title{padding: .30rem .40rem; font-size: .85rem;}';
        $html .= '  .bq-mini-card .card-header-icon{padding: .15rem .25rem;}';
        $html .= '  .bq-mini-card .card-content{padding: .25rem;}';
        $html .= '  .bq-mini-month-details{margin: 0 0 .25rem 0;}';
        $html .= '  .bq-mini-month-details > summary{list-style: none;}';
        $html .= '  .bq-mini-month-details > summary::-webkit-details-marker{display:none;}';
        $html .= '  .bq-mini-month-details .input{height: 1.95em; padding: 0 .5em; font-size: .80rem;}';
        $html .= '  .bq-mini-card .table-container{margin: 0;}';
        $html .= '  .bq-mini-card table{margin-bottom: 0 !important;}';
        $html .= '  .bq-mini-cal{table-layout: fixed;}';
        $html .= '  .bq-mini-cal thead th{padding: .06rem !important; font-size: .70rem;}';
        $html .= '  .bq-mini-cal th, .bq-mini-cal td{padding: .06rem !important;}';
        $html .= '  .bq-mini-cal .button{height: 1.55em; padding: 0 .22em; line-height: 1;}';
        $html .= '  .bq-mini-cal .tag{height: 1.12em; font-size: .60rem; padding: 0 .28em; line-height: 1;}';
        $html .= '  .bq-mini-card .buttons.has-addons .button{height: 1.85em; padding: 0 .45em;}';
        $html .= '</style>';

        $html .= '<div class="table-container">';
        $html .= '<table class="table is-bordered is-fullwidth is-narrow is-size-7 bq-mini-cal">';
        $html .= '<thead><tr class="has-text-centered is-size-7">';
        $html .= '<th>Lun</th><th>Mar</th><th>Mié</th><th>Jue</th><th>Vie</th><th>Sáb</th><th>Dom</th>';
        $html .= '</tr></thead><tbody>';

        $dia = 1;
        $col = 1;
        $html .= '<tr>';

        while($col < $offsetInicio){
            $html .= '<td>&nbsp;</td>';
            $col++;
        }

        while($dia <= $diasEnMes){
            $fechaDia = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            $esHoy = ($fechaDia === $hoy);
            $esSeleccion = ($fechaDia === $fechaSel);
            $claseTd = 'has-text-centered';
            if($esHoy){
                $claseTd .= ' has-background-warning-light';
            }elseif($esSeleccion){
                $claseTd .= ' has-background-link-light';
            }

            $count = (int)($conteo[$fechaDia] ?? 0);
            $badge = '';
            if($count>0){
                $badge = '<div style="margin-top:2px;"><span class="tag is-info is-light is-rounded is-small">'.(int)$count.'</span></div>';
            }

            $html .= '<td class="'.$claseTd.'">';
            $html .= '<a href="'.$baseUrl.'?fecha='.urlencode($fechaDia).'" class="button is-white is-small is-fullwidth">';
            $html .= '<span class="is-size-7 has-text-weight-semibold">'.$dia.'</span>';
            $html .= '</a>';
            $html .= $badge;
            $html .= '</td>';

            if($col==7){
                $html .= '</tr>';
                if($dia < $diasEnMes){
                    $html .= '<tr>';
                }
                $col = 1;
            }else{
                $col++;
            }

            $dia++;
        }

        if($col!==1){
            while($col<=7){
                $html .= '<td>&nbsp;</td>';
                $col++;
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="column is-9">';
        $html .= '<div class="card">';
        $html .= '<header class="card-header">';
        $html .= '<p class="card-header-title">';
        $html .= '<span class="icon"><i class="fas fa-calendar-week"></i></span>';
        $html .= '<span>Agenda semanal</span>';
        $html .= '</p>';
        $html .= '</header>';
        $html .= '<div class="card-content">';
        $html .= '<p class="is-size-7 has-text-grey mb-3">Haz clic en una cita para ver el detalle. Usa la barra superior para cambiar entre <strong>Semana</strong>, <strong>Día</strong> o <strong>Mes</strong>.</p>';

        $html .= '<style>';
        $html .= '  .admin-calendar-wrap{overflow-x: auto;}';
        $html .= '  #adminCitasCalendar{max-width: 100%; min-width: 0;}';
        $html .= '  .fc{font-size: 0.95rem;}';
        $html .= '  .fc .fc-toolbar{flex-wrap: wrap; gap: 8px;}';
        $html .= '  .fc .fc-toolbar-chunk{display: flex; flex-wrap: wrap; gap: 6px; align-items: center;}';
        $html .= '  .fc .fc-button-group{display: flex; flex-wrap: wrap; gap: 6px;}';
        $html .= '  .fc .fc-toolbar-title{font-size: 1.25rem; font-weight: 700;}';
        $html .= '  .fc .fc-button{font-size: .8rem; height: 2.25em; white-space: nowrap;}';
        $html .= '  .fc .fc-timegrid-slot-label{font-size: .85rem;}';
        $html .= '  .fc .fc-col-header-cell-cushion{font-weight: 700; white-space: normal; line-height: 1.1;}';

        // Horarios (eje izquierdo) más visibles
        $html .= '  .fc .fc-timegrid-axis{width: 78px !important;}';
        $html .= '  .fc .fc-timegrid-axis-cushion{font-size: .98rem; font-weight: 700; padding: 0 6px;}';
        $html .= '  .fc .fc-timegrid-slot-label-cushion{font-size: .95rem; font-weight: 700;}';
        $html .= '  .fc .fc-timegrid-slot{border-top-style: solid;}';

        // Cuadros (slots) más grandes en la agenda
        $html .= '  .fc{--fc-timegrid-slot-min-height: 3.4em;}';
        $html .= '  .fc .fc-timegrid-slot{height: 3.4em;}';

        // En vista "Día" un poco más alto para ver mejor el contenido
        $html .= '  .fc-timeGridDay-view{--fc-timegrid-slot-min-height: 3.9em;}';
        $html .= '  .fc-timeGridDay-view .fc-timegrid-slot{height: 3.9em;}';

        // Eventos: permitir 2 líneas (cliente + producto) sin recortes
        $html .= '  .fc .fc-event{padding: 1px 2px;}';
        $html .= '  .fc .fc-event .bq-event-wrap{display:block;}';
        $html .= '  .fc .fc-event .bq-top{display:flex; gap:4px; flex-wrap:wrap; align-items:center; margin-bottom:2px;}';
        $html .= '  .fc .fc-event .bq-time{font-size:.78rem; padding:0 .4em; height:1.55em; line-height:1.55em; font-weight:700;}';
        $html .= '  .fc .fc-event .bq-title{display:block; font-weight: 800; white-space: normal; line-height: 1.05;}';
        $html .= '  .fc .fc-event .bq-sub{display:block; font-size: .78rem; opacity: .95; white-space: normal; line-height: 1.05;}';

        // En vista semanal/diaria, evitar que el texto se salga y "tape" otros eventos
        $html .= '  .fc .fc-timegrid-event .fc-event-main{overflow:hidden;}';
        $html .= '  .fc .fc-timegrid-event .bq-title, .fc .fc-timegrid-event .bq-sub{white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}';

        // Vista mensual: más compacta para que entre todo el mes
        $html .= '  .fc .fc-daygrid-day-number{padding: 2px 4px; font-size: .75rem;}';
        $html .= '  .fc .fc-daygrid-event{font-size: .72rem; padding: 0 2px;}';
        $html .= '  .fc .fc-daygrid-day-top{flex-direction: row; justify-content: flex-start;}';

        // En pantallas pequeñas, reducir un poco para evitar recortes
        $html .= '  @media (max-width: 1023px){';
        $html .= '    .fc{font-size: 0.9rem;}';
        $html .= '    .fc .fc-toolbar-title{font-size: 1.1rem;}';
        $html .= '    .fc .fc-button{font-size: .75rem;}';
        $html .= '  }';
        $html .= '</style>';

        $html .= '<div class="admin-calendar-wrap">';
        $html .= '<div id="adminCitasCalendar"></div>';
        $html .= '</div>';

        $html .= '<hr class="my-4">';
        $html .= '<div class="content">';
        $html .= '<p class="mb-2"><strong>Leyenda de colores</strong></p>';
        $html .= '<div class="tags are-medium">';
        $html .= '<span class="tag is-warning is-light is-rounded">PENDIENTE</span>';
        $html .= '<span class="tag is-success is-light is-rounded">CONFIRMADA</span>';
        $html .= '<span class="tag is-link is-light is-rounded">REPROGRAMADA</span>';
        $html .= '<span class="tag is-dark is-light is-rounded">COMPLETADA</span>';
        $html .= '<span class="tag is-danger is-light is-rounded">RECHAZADA</span>';
        $html .= '<span class="tag is-info is-light is-rounded">OTRA</span>';
        $html .= '</div>';
        $html .= '<p class="is-size-7 has-text-grey">La vista de calendario no muestra citas <strong>rechazadas</strong> por defecto.</p>';
        $html .= '</div>';

        // FullCalendar assets (CDN)
        $html .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">';
        $html .= '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>';
        $html .= '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>';

        // URL root-relative para asegurar same-origin + ruta correcta
        $ajaxUrl = $appPath.'app/ajax/reservaAjax.php';
        $initialDate = htmlspecialchars($fechaSel, ENT_QUOTES, 'UTF-8');
        $slotMinTime = htmlspecialchars($minTime.':00', ENT_QUOTES, 'UTF-8');
        $slotMaxTime = htmlspecialchars($maxTime.':00', ENT_QUOTES, 'UTF-8');
        $slotDuration = sprintf('00:%02d:00', $slotMinutes);
        $slotDuration = htmlspecialchars($slotDuration, ENT_QUOTES, 'UTF-8');

        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function(){';
        $html .= '  var el = document.getElementById("adminCitasCalendar");';
        $html .= '  if(!el || !window.FullCalendar){ return; }';
        $html .= '  var colorCache = {};';
        $html .= '  function estadoToBulma(estado){';
        $html .= '    estado = (estado||"").toLowerCase().trim();';
        $html .= '    if(estado==="pendiente") return "is-warning";';
        $html .= '    if(estado==="confirmada") return "is-success";';
        $html .= '    if(estado==="reprogramada") return "is-link";';
        $html .= '    if(estado==="completada") return "is-dark";';
        $html .= '    if(estado==="rechazada") return "is-danger";';
        $html .= '    return "is-info";';
        $html .= '  }';
        $html .= '  function resolveBulmaBg(bulmaClass){';
        $html .= '    if(colorCache[bulmaClass]) return colorCache[bulmaClass];';
        $html .= '    var probe = document.createElement("span");';
        $html .= '    probe.className = "tag "+bulmaClass;';
        $html .= '    probe.style.position = "absolute";';
        $html .= '    probe.style.left = "-9999px";';
        $html .= '    probe.style.top = "-9999px";';
        $html .= '    document.body.appendChild(probe);';
        $html .= '    var bg = window.getComputedStyle(probe).backgroundColor;';
        $html .= '    document.body.removeChild(probe);';
        $html .= '    colorCache[bulmaClass] = bg;';
        $html .= '    return bg;';
        $html .= '  }';
        $html .= '  var calendar = new FullCalendar.Calendar(el, {';
        $html .= '    initialView: "timeGridWeek",';
        $html .= '    initialDate: "'.$initialDate.'",';
        $html .= '    locale: "es",';
        $html .= '    firstDay: 1,';
        $html .= '    nowIndicator: true,';
        $html .= '    allDaySlot: false,';
        $html .= '    expandRows: true,';
        $html .= '    height: 860,';
        $html .= '    slotMinTime: "'.$slotMinTime.'",';
        $html .= '    slotMaxTime: "'.$slotMaxTime.'",';
        $html .= '    slotDuration: "'.$slotDuration.'",';
        $html .= '    headerToolbar: { left: "prev,next today", center: "title", right: "timeGridWeek,timeGridDay,dayGridMonth" },';
        $html .= '    buttonText: { today: "Hoy", month: "Mes", week: "Semana", day: "Día" },';
        $html .= '    eventTimeFormat: { hour: "2-digit", minute: "2-digit", hour12: true },';
        $html .= '    slotLabelFormat: { hour: "2-digit", minute: "2-digit", hour12: true },';
        $html .= '    slotLabelInterval: { hours: 1 },';
        $html .= '    dayHeaderFormat: { weekday: "short", day: "2-digit", month: "2-digit" },';
        $html .= '    slotEventOverlap: false,';
        $html .= '    eventDisplay: "block",';
        $html .= '    eventMinHeight: 40,';
        $html .= '    views: {';
        $html .= '      timeGridWeek: { height: 880 },';
        $html .= '      timeGridDay: { height: 900 },';
        $html .= '      dayGridMonth: { height: 640, dayMaxEventRows: 2 }';
        $html .= '    },';
        $html .= '    moreLinkText: function(n){ return "+ "+n+" más"; },';
        $html .= '    events: function(info, successCallback, failureCallback){';
        $html .= '      fetch("'.htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8').'", {';
        $html .= '        method: "POST",';
        $html .= '        credentials: "same-origin",';
        $html .= '        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },';
        $html .= '        body: new URLSearchParams({ modulo_reserva: "calendario_eventos_admin", start: info.startStr, end: info.endStr }).toString()';
        $html .= '      }).then(function(r){ return r.json(); })';
        $html .= '      .then(function(data){ successCallback(Array.isArray(data)?data:[]); })';
        $html .= '      .catch(function(){ failureCallback(); });';
        $html .= '    },';
        $html .= '    eventClick: function(arg){';
        $html .= '      if(arg.event && arg.event.url){ arg.jsEvent.preventDefault(); window.location.href = arg.event.url; }';
        $html .= '    },';
        $html .= '    eventDidMount: function(info){';
        $html .= '      var estado = info.event.extendedProps ? info.event.extendedProps.estado : "";';
        $html .= '      var bulma = estadoToBulma(estado);';
        $html .= '      var bg = resolveBulmaBg(bulma);';
        $html .= '      if(bg){ info.el.style.backgroundColor = bg; info.el.style.borderColor = bg; }';
        $html .= '    },';
        $html .= '    eventContent: function(arg){';
        $html .= '      var estado = arg.event.extendedProps ? arg.event.extendedProps.estado : "";';
        $html .= '      var producto = arg.event.extendedProps ? (arg.event.extendedProps.producto || "") : "";';
        $html .= '      var bulma = estadoToBulma(estado);';
        $html .= '      var wrap = document.createElement("div");';
        $html .= '      wrap.className = "bq-event-wrap";';
        $html .= '      var isMonth = (arg.view && arg.view.type === "dayGridMonth");';
        $html .= '      var top = document.createElement("div");';
        $html .= '      top.className = "bq-top";';
        $html .= '      var tag = document.createElement("span");';
        $html .= '      tag.className = "tag is-light is-rounded "+bulma;';
        $html .= '      tag.textContent = estado ? estado.toUpperCase() : "CITA";';
        $html .= '      top.appendChild(tag);';
        $html .= '      if(!isMonth){';
        $html .= '        var t = document.createElement("span");';
        $html .= '        t.className = "tag is-light is-rounded bq-time";';
        $html .= '        var d = arg.event && arg.event.start ? arg.event.start : null;';
        $html .= '        if(d){';
        $html .= '          try{';
        $html .= '            t.textContent = new Intl.DateTimeFormat("es-ES", { hour: "2-digit", minute: "2-digit", hour12: true }).format(d);';
        $html .= '          }catch(e){ t.textContent = arg.timeText || ""; }';
        $html .= '        }else{ t.textContent = arg.timeText || ""; }';
        $html .= '        if(t.textContent){ top.appendChild(t); }';
        $html .= '      }';
        $html .= '      var title = document.createElement("div");';
        $html .= '      title.className = "bq-title";';
        $html .= '      title.textContent = arg.event.title || "";';
        $html .= '      wrap.appendChild(top);';
        $html .= '      wrap.appendChild(title);';
        $html .= '      if(!isMonth && producto){';
        $html .= '        var sub = document.createElement("div");';
        $html .= '        sub.className = "bq-sub";';
        $html .= '        sub.textContent = producto;';
        $html .= '        wrap.appendChild(sub);';
        $html .= '      }';
        $html .= '      return { domNodes: [wrap] };';
        $html .= '    }';
        $html .= '  });';
        $html .= '  calendar.render();';
        $html .= '});';
        $html .= '</script>';

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }


    /*---------- Eventos para calendario (solo admin, AJAX) ----------*/
    public function calendarioEventosAdminControlador(): string{
        if(!$this->tablaReservaExiste()){
            return json_encode([]);
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
            return json_encode([]);
        }

        if(!$this->sesionEsAdmin()){
            return json_encode([]);
        }

        $startRaw = isset($_POST['start']) ? (string)$this->limpiarCadena($_POST['start']) : '';
        $endRaw = isset($_POST['end']) ? (string)$this->limpiarCadena($_POST['end']) : '';

        // FullCalendar suele enviar ISO (YYYY-MM-DD o YYYY-MM-DDTHH:mm:ssZ).
        // Como la BD guarda la fecha en DATE, tomamos solo YYYY-MM-DD para evitar desfases por timezone.
        $startDate = substr($startRaw, 0, 10);
        $endExclusive = substr($endRaw, 0, 10);
        if(!$this->fechaYmdValida($startDate) || !$this->fechaYmdValida($endExclusive)){
            $startDate = date('Y-m-d');
            $endExclusive = date('Y-m-d', strtotime($startDate.' +30 days'));
        }

        // end es exclusivo; consultamos hasta el día anterior
        $endInclusive = (new \DateTime($endExclusive))->modify('-1 day')->format('Y-m-d');

        $cfg = $this->obtenerConfigCitas();
        $durMin = isset($cfg['interval_minutes']) ? (int)$cfg['interval_minutes'] : 30;
        if($durMin <= 0){
            $durMin = 30;
        }

        $sql = "SELECT r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_estado,
                       c.cliente_nombre, c.cliente_apellido,
                       p.producto_nombre
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id = r.cliente_id
                INNER JOIN producto p ON p.producto_id = r.producto_id
                WHERE r.reserva_fecha BETWEEN :ini AND :fin
                  AND r.reserva_estado <> 'rechazada'
                ORDER BY r.reserva_fecha ASC, STR_TO_DATE(r.reserva_hora, '%h:%i %p') ASC, r.reserva_id ASC
                LIMIT 5000";

        try{
            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindParam(':ini', $startDate);
            $stmt->bindParam(':fin', $endInclusive);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }catch(\Throwable $e){
            $rows = [];
        }

        $events = [];
        foreach($rows as $r){
            $fecha = (string)($r['reserva_fecha'] ?? '');
            $hora = $this->normalizarHora12((string)($r['reserva_hora'] ?? ''));
            $codigo = (string)($r['reserva_codigo'] ?? '');
            $estado = (string)($r['reserva_estado'] ?? '');
            if($fecha==='' || $hora==='' || $codigo===''){
                continue;
            }

            $dtStart = \DateTime::createFromFormat('Y-m-d h:i a', $fecha.' '.$hora);
            if(!$dtStart){
                continue;
            }
            $dtEnd = (clone $dtStart);
            $dtEnd->modify('+'.$durMin.' minutes');

            $cliente = trim((string)($r['cliente_nombre'] ?? '').' '.(string)($r['cliente_apellido'] ?? ''));
            $producto = (string)($r['producto_nombre'] ?? '');
            $titulo = $cliente !== '' ? $cliente : $codigo;
            $titulo = $this->limitarCadena($titulo, 55, '...');
            $productoShort = $this->limitarCadena($producto, 70, '...');

            $events[] = [
                'id' => $codigo,
                'title' => $titulo,
                'start' => $dtStart->format('c'),
                'end' => $dtEnd->format('c'),
                'url' => APP_URL.'reservaDetalle/'.urlencode($codigo).'/',
                'extendedProps' => [
                    'estado' => $estado,
                    'codigo' => $codigo,
                    'cliente' => $cliente,
                    'producto' => $productoShort,
                ],
            ];
        }

        return json_encode($events);
    }


    /*----------  Controlador listar reservas (solo admin)  ----------*/
    public function listarReservaControlador($pagina,$registros,$url,$busqueda,$estado=""){

        if(!$this->tablaReservaExiste()){
            return '<article class="message is-danger"><div class="message-body">No existe la tabla <strong>reserva</strong> en la base de datos. Ejecuta el instalador: <a href="'.APP_URL.'install_reserva_table.php" target="_blank" rel="noopener">install_reserva_table.php</a></div></article>';
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']=="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']=="")){
            return '<article class="message is-danger"><div class="message-body">Debes iniciar sesión.</div></article>';
        }

        if(!$this->sesionEsAdmin()){
            return '<article class="message is-danger"><div class="message-body">Acceso restringido: solo el administrador puede ver el listado de reservas.</div></article>';
        }

        $pagina=$this->limpiarCadena($pagina);
        $registros=$this->limpiarCadena($registros);

        $url=$this->limpiarCadena($url);
        $url=APP_URL.$url."/";

        $busqueda=$this->limpiarCadena($busqueda);
        $estado=$this->limpiarCadena($estado);
        $estado=strtolower((string)$estado);
        $estadosPermitidos=['pendiente','confirmada','reprogramada','completada','rechazada'];
        if($estado!=="" && !in_array($estado,$estadosPermitidos,true)){
            $estado="";
        }
        $tabla="";

        $pagina = (isset($pagina) && $pagina>0) ? (int) $pagina : 1;
        $inicio = ($pagina>0) ? (($pagina * $registros)-$registros) : 0;

        $campos_tablas="r.reserva_id,r.reserva_codigo,r.reserva_fecha,r.reserva_hora,r.reserva_total,r.reserva_abono,r.reserva_estado,r.reserva_observacion,r.usuario_id,r.caja_id,
                         c.cliente_nombre,c.cliente_apellido,c.cliente_email,
                         p.producto_nombre,
                         u.usuario_nombre,u.usuario_apellido,
                         ca.caja_nombre";

        $condiciones = [];
        if(isset($busqueda) && $busqueda!=""){
			$condiciones[] = "(r.reserva_codigo='$busqueda' OR c.cliente_nombre LIKE '%$busqueda%' OR c.cliente_apellido LIKE '%$busqueda%' OR c.cliente_email LIKE '%$busqueda%')";
		}
		if(isset($estado) && $estado!=""){
			$condiciones[] = "r.reserva_estado='$estado'";
		}
		$whereSql = "";
		if(!empty($condiciones)){
			$whereSql = "WHERE ".implode(" AND ", $condiciones);
		}

		if(!empty($condiciones)){
            $consulta_datos="SELECT $campos_tablas
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id=r.cliente_id
                INNER JOIN producto p ON p.producto_id=r.producto_id
                LEFT JOIN usuario u ON u.usuario_id=r.usuario_id
                LEFT JOIN caja ca ON ca.caja_id=r.caja_id
                $whereSql
                ORDER BY r.reserva_id DESC
                LIMIT $inicio,$registros";

            $consulta_total="SELECT COUNT(r.reserva_id)
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id=r.cliente_id
                $whereSql";
        }else{
            $consulta_datos="SELECT $campos_tablas
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id=r.cliente_id
                INNER JOIN producto p ON p.producto_id=r.producto_id
                LEFT JOIN usuario u ON u.usuario_id=r.usuario_id
                LEFT JOIN caja ca ON ca.caja_id=r.caja_id
                ORDER BY r.reserva_id DESC
                LIMIT $inicio,$registros";

            $consulta_total="SELECT COUNT(reserva_id) FROM reserva";
        }

        $datos = $this->ejecutarConsulta($consulta_datos);
        $datos = $datos->fetchAll();

        $total = $this->ejecutarConsulta($consulta_total);
        $total = (int) $total->fetchColumn();

        $numeroPaginas = ceil($total/$registros);

        $tabla.='
            <div class="table-container">
            <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
                <thead>
                    <tr>
                        <th class="has-text-centered">NRO.</th>
                        <th class="has-text-centered">Código</th>
                        <th class="has-text-centered">Fecha</th>
                        <th class="has-text-centered">Cliente</th>
                        <th class="has-text-centered">Producto</th>
                        <th class="has-text-centered">Total</th>
                        <th class="has-text-centered">Abono</th>
                        <th class="has-text-centered">Estado</th>
                        <th class="has-text-centered">Usuario</th>
                        <th class="has-text-centered">Caja</th>
                        <th class="has-text-centered">Observación</th>
                        <th class="has-text-centered">Opciones</th>
                    </tr>
                </thead>
                <tbody>
        ';

        if($total>=1 && $pagina<=$numeroPaginas){
            $contador=$inicio+1;
            $pag_inicio=$inicio+1;
            foreach($datos as $rows){
                $detalle = APP_URL.'reservaDetalle/'.urlencode($rows['reserva_codigo']).'/';
                $confirmar = APP_URL.'reservaConfirmar/'.urlencode($rows['reserva_codigo']).'/';

                $estado = (string)($rows['reserva_estado'] ?? '');
                $tagColor = 'is-info';
                if($estado==='pendiente'){
                    $tagColor = 'is-warning';
                }elseif($estado==='confirmada'){
                    $tagColor = 'is-success';
                }elseif($estado==='reprogramada'){
                    $tagColor = 'is-link';
                }elseif($estado==='completada'){
                    $tagColor = 'is-link';
                }elseif($estado==='rechazada'){
                    $tagColor = 'is-danger';
                }

                $cliente = $this->limitarCadena(trim($rows['cliente_nombre'].' '.$rows['cliente_apellido']),30,'...');
                $producto = $this->limitarCadena((string)$rows['producto_nombre'],30,'...');
                $usuario = '';
                if(!empty($rows['usuario_nombre'])){
                    $usuario = $this->limitarCadena(trim($rows['usuario_nombre'].' '.$rows['usuario_apellido']),25,'...');
                }
                $caja = (string)($rows['caja_nombre'] ?? '');
                $obs = (string)($rows['reserva_observacion'] ?? '');
                $obs = ($obs!=="") ? $this->limitarCadena($obs,25,'...') : '';

                $tabla.='
                    <tr class="has-text-centered">
                        <td>'.$contador.'</td>
                        <td>'.$rows['reserva_codigo'].'</td>
                        <td>'.date("d-m-Y", strtotime($rows['reserva_fecha'])).' '.$rows['reserva_hora'].'</td>
                        <td>'.$cliente.'<br><span class="is-size-7 has-text-grey">'.$this->limitarCadena((string)$rows['cliente_email'],30,'...').'</span></td>
                        <td>'.$producto.'</td>
                        <td>'.MONEDA_SIMBOLO.number_format($rows['reserva_total'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE.'</td>
                        <td>'.MONEDA_SIMBOLO.number_format($rows['reserva_abono'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).'</td>
                        <td><span class="tag '.$tagColor.' is-light">'.$estado.'</span></td>
                        <td>'.$usuario.'</td>
                        <td>'.$caja.'</td>
                        <td>'.$obs.'</td>
                        <td>
                            <a href="'.$detalle.'" class="button is-info is-rounded is-small" title="Detalle" >
                                <i class="fas fa-eye fa-fw"></i>
                            </a>
                            <button type="button" class="button is-link is-outlined is-rounded is-small btn-sale-options" onclick="print_ticket(\''.APP_URL.'app/pdf/reserva_ticket.php?code='.$rows['reserva_codigo'].'\')" title="Imprimir ticket de reserva" >
                                <i class="fas fa-receipt fa-fw"></i>
                            </button>
                    ';

                if($estado==='pendiente'){
                    $tabla.='
                            <a href="'.$confirmar.'" class="button is-success is-rounded is-small" title="Aprobar" >
                                <i class="fas fa-check fa-fw"></i>
                            </a>
                    ';
                }

                if($estado==='confirmada'){
                    $tabla.='
                            <form class="FormularioAjax" action="'.APP_URL.'app/ajax/reservaAjax.php" method="POST" autocomplete="off" style="display:inline-block;">
                                <input type="hidden" name="modulo_reserva" value="completar">
                                <input type="hidden" name="reserva_codigo" value="'.$rows['reserva_codigo'].'">
                                <button type="submit" class="button is-link is-rounded is-small" title="Completar venta">
                                    <i class="fas fa-check-double fa-fw"></i> &nbsp; Completar
                                </button>
                            </form>
                    ';
                }

                $tabla.='
                        </td>
                    </tr>
                ';

                $contador++;
            }
            $pag_final=$contador-1;
        }else{
            if($total>=1){
                $tabla.='
                    <tr class="has-text-centered" >
                        <td colspan="12">
                            <a href="'.$url.'1/" class="button is-link is-rounded is-small mt-4 mb-4">
                                Haga clic acá para recargar el listado
                            </a>
                        </td>
                    </tr>
                ';
            }else{
                $tabla.='
                    <tr class="has-text-centered" >
                        <td colspan="12">No hay registros en el sistema</td>
                    </tr>
                ';
            }
        }

        $tabla.='</tbody></table></div>';

        ### Paginacion ###
        if($total>0 && $pagina<=$numeroPaginas){
            $tabla.='<p class="has-text-right">Mostrando reservas <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';
            $tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
        }

        return $tabla;
    }


    /*----------  Exportar reservas a PDF  ----------*/
    public function exportarReservasPDF($busqueda=""){
        // Restringido a admin (misma lógica que la vista)
        $esAdmin = false;
        if(isset($_SESSION['rol']) && $_SESSION['rol']==="Administrador"){
            $esAdmin = true;
        }elseif(isset($_SESSION['usuario']) && $_SESSION['usuario']==="Administrador"){
            $esAdmin = true;
        }elseif(isset($_SESSION['id']) && (int)$_SESSION['id']===1){
            $esAdmin = true;
        }

        if(!$esAdmin){
            if(!headers_sent()){
                header('HTTP/1.1 403 Forbidden');
            }
            exit();
        }

        if(ob_get_length()){
            @ob_end_clean();
        }

        require_once __DIR__ . '/../pdf/TableReportPDF.php';

        $busqueda = $this->limpiarCadena($busqueda);

        $campos = "r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado, c.cliente_nombre, c.cliente_apellido, p.producto_nombre, u.usuario_nombre, u.usuario_apellido";

        if(isset($busqueda) && $busqueda!=""){
            $consulta = "SELECT $campos
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id=r.cliente_id
                INNER JOIN producto p ON p.producto_id=r.producto_id
                LEFT JOIN usuario u ON u.usuario_id=r.usuario_id
                WHERE (r.reserva_codigo='$busqueda' OR c.cliente_nombre LIKE '%$busqueda%' OR c.cliente_apellido LIKE '%$busqueda%' OR c.cliente_email LIKE '%$busqueda%')
                ORDER BY r.reserva_id DESC";
        }else{
            $consulta = "SELECT $campos
                FROM reserva r
                INNER JOIN cliente c ON c.cliente_id=r.cliente_id
                INNER JOIN producto p ON p.producto_id=r.producto_id
                LEFT JOIN usuario u ON u.usuario_id=r.usuario_id
                ORDER BY r.reserva_id DESC";
        }

        $datos = $this->ejecutarConsulta($consulta);
        $rows = $datos ? $datos->fetchAll() : [];

        $pdf = new \TableReportPDF('L','mm','A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->titulo = APP_NAME.' - Reporte de Reservas';
        $pdf->subtitulo = 'Generado: '.date('d/m/Y H:i:s').'  |  Total registros: '.count($rows);
        $pdf->setTable(
            ['Código','Fecha/Hora','Cliente','Producto','Total','Abono','Estado','Usuario'],
            [30,35,55,55,25,25,22,30],
            ['L','L','L','L','R','R','C','L']
        );
        $pdf->AddPage();
        $pdf->SetFont('Arial','',8);

        $fill = false;
        foreach($rows as $r){
            $cliente = trim((string)($r['cliente_nombre'] ?? '').' '.(string)($r['cliente_apellido'] ?? ''));
            $usuario = trim((string)($r['usuario_nombre'] ?? '').' '.(string)($r['usuario_apellido'] ?? ''));
            $fecha = '';
            try{
                $fecha = date('d-m-Y', strtotime((string)($r['reserva_fecha'] ?? ''))).' '.(string)($r['reserva_hora'] ?? '');
            }catch(\Throwable $e){
                $fecha = (string)($r['reserva_fecha'] ?? '').' '.(string)($r['reserva_hora'] ?? '');
            }
            $total = $r['reserva_total'] ?? '';
            $total = is_numeric($total) ? (MONEDA_SIMBOLO.number_format((float)$total, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)) : (string)$total;
            $abono = $r['reserva_abono'] ?? '';
            $abono = is_numeric($abono) ? (MONEDA_SIMBOLO.number_format((float)$abono, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)) : (string)$abono;

            $pdf->addRow([
                (string)($r['reserva_codigo'] ?? ''),
                $fecha,
                $cliente,
                (string)($r['producto_nombre'] ?? ''),
                $total,
                $abono,
                (string)($r['reserva_estado'] ?? ''),
                $usuario,
            ], $fill);
            $fill = !$fill;
        }

        $pdf->Output('D', 'reporte_reservas_'.date('Ymd').'.pdf');
        exit();
    }


    /*----------  Exportar reservas pendientes (Pendientes / Aprobar) a PDF  ----------*/
    public function exportarReservasPendientesPDF(){
        // Restringido a admin
        $esAdmin = false;
        if(isset($_SESSION['rol']) && $_SESSION['rol']==="Administrador"){
            $esAdmin = true;
        }elseif(isset($_SESSION['usuario']) && $_SESSION['usuario']==="Administrador"){
            $esAdmin = true;
        }elseif(isset($_SESSION['id']) && (int)$_SESSION['id']===1){
            $esAdmin = true;
        }

        if(!$esAdmin){
            if(!headers_sent()){
                header('HTTP/1.1 403 Forbidden');
            }
            exit();
        }

        if(!$this->tablaReservaExiste()){
            if(!headers_sent()){
                header('HTTP/1.1 400 Bad Request');
            }
            exit();
        }

        if(ob_get_length()){
            @ob_end_clean();
        }

        require_once __DIR__ . '/../pdf/TableReportPDF.php';

        $campos = "r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado, c.cliente_nombre, c.cliente_apellido, c.cliente_email, p.producto_nombre";
        $consulta = "SELECT $campos
            FROM reserva r
            INNER JOIN cliente c ON c.cliente_id=r.cliente_id
            INNER JOIN producto p ON p.producto_id=r.producto_id
            WHERE r.reserva_estado='pendiente'
            ORDER BY r.reserva_id DESC";

        $datos = $this->ejecutarConsulta($consulta);
        $rows = $datos ? $datos->fetchAll() : [];

        $totalMonto = 0.0;
        foreach($rows as $r){
            $totalMonto += (float)($r['reserva_total'] ?? 0);
        }

        $pdf = new \TableReportPDF('L','mm','A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(10, 12, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->titulo = APP_NAME.' - Reservas Pendientes / Aprobar';
        $pdf->subtitulo = 'Generado: '.date('d/m/Y H:i:s').'  |  Pendientes: '.count($rows).'  |  Total: '.MONEDA_SIMBOLO.number_format($totalMonto, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE;
        $pdf->setTable(
            ['Código','Fecha/Hora','Cliente','Email','Producto','Total','Estado'],
            [25,32,50,50,65,25,30],
            ['L','L','L','L','L','R','C']
        );
        $pdf->AddPage();
        $pdf->SetFont('Arial','',8);

        $fill = false;
        foreach($rows as $r){
            $cliente = trim((string)($r['cliente_nombre'] ?? '').' '.(string)($r['cliente_apellido'] ?? ''));
            $fecha = '';
            try{
                $fecha = date('d-m-Y', strtotime((string)($r['reserva_fecha'] ?? ''))).' '.(string)($r['reserva_hora'] ?? '');
            }catch(\Throwable $e){
                $fecha = (string)($r['reserva_fecha'] ?? '').' '.(string)($r['reserva_hora'] ?? '');
            }
            $total = $r['reserva_total'] ?? '';
            $total = is_numeric($total) ? (MONEDA_SIMBOLO.number_format((float)$total, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)) : (string)$total;

            $pdf->addRow([
                (string)($r['reserva_codigo'] ?? ''),
                $fecha,
                $cliente,
                (string)($r['cliente_email'] ?? ''),
                (string)($r['producto_nombre'] ?? ''),
                $total,
                (string)($r['reserva_estado'] ?? ''),
            ], $fill);
            $fill = !$fill;
        }

        $pdf->Output('D', 'reporte_reservas_pendientes_'.date('Ymd').'.pdf');
        exit();
    }


    /*---------- Completar reserva (convertir a venta) ----------*/
    public function completarReservaVentaControlador(){

        if(!$this->tablaReservaExiste()){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Falta configuración',
                'texto'=>'No existe la tabla reserva en la base de datos.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        if(!$this->tablaVentaExiste() || !$this->tablaVentaDetalleExiste()){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Falta configuración',
                'texto'=>'No existe la tabla venta/venta_detalle en la base de datos.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']==="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']==="")){
            $alerta=[
                'tipo'=>'redireccionar',
                'url'=>APP_URL.'login/'
            ];
            return json_encode($alerta);
        }

        if(!$this->sesionEsAdmin()){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Acceso restringido',
                'texto'=>'Solo el administrador puede completar reservas.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? '');
        if($codigo===''){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Ocurrió un error inesperado',
                'texto'=>'Falta el código de la reserva.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Reserva no encontrada',
                'texto'=>'No encontramos la reserva indicada.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $estado = (string)($reserva['reserva_estado'] ?? '');
        if($estado==='completada'){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Reserva ya completada',
                'texto'=>'Esta reserva ya fue completada anteriormente.',
                'icono'=>'info'
            ];
            return json_encode($alerta);
        }

        if(!in_array($estado, ['confirmada','reprogramada'], true)){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'No se puede completar',
                'texto'=>'Solo se pueden completar reservas en estado confirmada o reprogramada.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $caja_id = isset($_SESSION['caja']) ? (int)$_SESSION['caja'] : 0;
        if($caja_id<=0){
            $caja_id = isset($reserva['caja_id']) ? (int)$reserva['caja_id'] : 0;
        }
        if($caja_id<=0){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Caja no configurada',
                'texto'=>'No se encontró una caja para registrar la venta.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $total = (float)($reserva['reserva_total'] ?? 0);
        $abono = (float)($reserva['reserva_abono'] ?? 0);
        $total_fmt = (float)number_format($total, MONEDA_DECIMALES, '.', '');
        $abono_fmt = (float)number_format($abono, MONEDA_DECIMALES, '.', '');

        if($total_fmt<=0){
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Monto inválido',
                'texto'=>'El total de la reserva es inválido.',
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $restante = $total_fmt - $abono_fmt;
        if($restante < 0){
            $restante = 0;
        }
        $restante_fmt = number_format($restante, MONEDA_DECIMALES, '.', '');

        $pdo = $this->conectar();

        try{
            $pdo->beginTransaction();

            // Verificar caja
            $stmtCaja = $pdo->prepare('SELECT caja_efectivo FROM caja WHERE caja_id=:cid FOR UPDATE');
            $stmtCaja->bindParam(':cid', $caja_id, \PDO::PARAM_INT);
            $stmtCaja->execute();
            $caja = $stmtCaja->fetch();
            if(!$caja){
                throw new \Exception('Caja no encontrada');
            }

            // Generar codigo de venta
            $correlativo = $this->ejecutarConsulta('SELECT venta_id FROM venta');
            $correlativo = ($correlativo->rowCount()) + 1;
            $codigo_venta = $this->generarCodigoAleatorio(10, $correlativo);

            $venta_fecha = date('Y-m-d');
            $venta_hora = date('h:i a');

            // Insertar venta
            $stmtVenta = $pdo->prepare('INSERT INTO venta (venta_codigo, venta_fecha, venta_hora, venta_total, venta_pagado, venta_cambio, usuario_id, cliente_id, caja_id)
                                        VALUES (:cod, :f, :h, :t, :p, :c, :uid, :clid, :cid)');
            $venta_pagado = number_format($total_fmt, MONEDA_DECIMALES, '.', '');
            $venta_cambio = number_format(0, MONEDA_DECIMALES, '.', '');
            $stmtVenta->bindParam(':cod', $codigo_venta);
            $stmtVenta->bindParam(':f', $venta_fecha);
            $stmtVenta->bindParam(':h', $venta_hora);
            $stmtVenta->bindParam(':t', $venta_pagado);
            $stmtVenta->bindParam(':p', $venta_pagado);
            $stmtVenta->bindParam(':c', $venta_cambio);
            $stmtVenta->bindValue(':uid', (int)$_SESSION['id'], \PDO::PARAM_INT);
            $stmtVenta->bindValue(':clid', (int)$reserva['cliente_id'], \PDO::PARAM_INT);
            $stmtVenta->bindParam(':cid', $caja_id, \PDO::PARAM_INT);
            if(!$stmtVenta->execute()){
                throw new \Exception('No se pudo registrar la venta');
            }

            // Datos del producto para detalle
            $stmtProd = $pdo->prepare('SELECT producto_id, producto_nombre, producto_precio_compra, producto_precio_venta FROM producto WHERE producto_id=:pid LIMIT 1');
            $stmtProd->bindValue(':pid', (int)$reserva['producto_id'], \PDO::PARAM_INT);
            $stmtProd->execute();
            $prod = $stmtProd->fetch();
            if(!$prod){
                throw new \Exception('Producto no encontrado');
            }

            $detalle_cant = 1;
            $detalle_precio_compra = number_format((float)$prod['producto_precio_compra'], MONEDA_DECIMALES, '.', '');
            $detalle_precio_venta = number_format($total_fmt, MONEDA_DECIMALES, '.', '');
            $detalle_total = number_format($total_fmt, MONEDA_DECIMALES, '.', '');
            $detalle_desc = (string)$prod['producto_nombre'].' (Reserva '.$codigo.')';

            $stmtDet = $pdo->prepare('INSERT INTO venta_detalle (venta_detalle_cantidad, venta_detalle_precio_compra, venta_detalle_precio_venta, venta_detalle_total, venta_detalle_descripcion, venta_codigo, producto_id)
                                      VALUES (:cant, :pc, :pv, :tot, :desc, :vcod, :pid)');
            $stmtDet->bindValue(':cant', $detalle_cant, \PDO::PARAM_INT);
            $stmtDet->bindParam(':pc', $detalle_precio_compra);
            $stmtDet->bindParam(':pv', $detalle_precio_venta);
            $stmtDet->bindParam(':tot', $detalle_total);
            $stmtDet->bindParam(':desc', $detalle_desc);
            $stmtDet->bindParam(':vcod', $codigo_venta);
            $stmtDet->bindValue(':pid', (int)$prod['producto_id'], \PDO::PARAM_INT);
            if(!$stmtDet->execute()){
                throw new \Exception('No se pudo registrar el detalle de la venta');
            }

            // Actualizar efectivo en caja SOLO por el restante
            if((float)$restante_fmt > 0){
                $nuevo_efectivo = (float)$caja['caja_efectivo'] + (float)$restante_fmt;
                $nuevo_efectivo = number_format($nuevo_efectivo, MONEDA_DECIMALES, '.', '');
                $stmtUpCaja = $pdo->prepare('UPDATE caja SET caja_efectivo=:e WHERE caja_id=:cid');
                $stmtUpCaja->bindParam(':e', $nuevo_efectivo);
                $stmtUpCaja->bindParam(':cid', $caja_id, \PDO::PARAM_INT);
                if(!$stmtUpCaja->execute()){
                    throw new \Exception('No se pudo actualizar caja');
                }
            }

            // Marcar reserva como completada (evita duplicados)
            // Importante: al completar el pago, el abono debe quedar igual al total.
            $stmtUpRes = $pdo->prepare("UPDATE reserva
                                    SET reserva_abono=:a,
                                        reserva_estado='completada',
                                        usuario_id=:uid,
                                        caja_id=:cid
                                    WHERE reserva_codigo=:c AND reserva_estado IN ('confirmada','reprogramada')");
            $stmtUpRes->bindParam(':a', $venta_pagado);
            $stmtUpRes->bindValue(':uid', (int)$_SESSION['id'], \PDO::PARAM_INT);
            $stmtUpRes->bindParam(':cid', $caja_id, \PDO::PARAM_INT);
            $stmtUpRes->bindParam(':c', $codigo);
            $stmtUpRes->execute();
            if($stmtUpRes->rowCount()!=1){
                throw new \Exception('La reserva no pudo completarse (¿ya fue procesada?)');
            }

            $pdo->commit();

        }catch(\Throwable $e){
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            $alerta=[
                'tipo'=>'simple',
                'titulo'=>'Ocurrió un error inesperado',
                'texto'=>'No pudimos completar la reserva: '.$e->getMessage(),
                'icono'=>'error'
            ];
            return json_encode($alerta);
        }

        $this->registrarLogAccion('Completó reserva '.$codigo.' -> Venta '.$codigo_venta.' (Restante: '.$restante_fmt.')');

        // Enviar ticket de compra al cliente (best-effort)
        $this->enviarTicketVentaPorCorreo($codigo_venta, $reserva);

        $alerta=[
            'tipo'=>'redireccionar',
            'url'=>APP_URL.'saleList/1/'
        ];
        return json_encode($alerta);
    }


    /*---------- Crear reserva desde cliente (genera QR) ----------*/
    public function crearReservaClienteControlador(){

        if(!$this->tablaReservaExiste()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Falta configuración",
                "texto"=>"No existe la tabla 'reserva' en la base de datos. Debes crearla abriendo: ".APP_URL."install_reserva_table.php",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if(!isset($_SESSION['cliente_id']) || $_SESSION['cliente_id']==""){
            $producto_id_tmp = (int)($this->limpiarCadena($_POST['producto_id'] ?? "0"));
            $redirect_to = ($producto_id_tmp>0) ? ("reservaNueva/".$producto_id_tmp."/") : "productosCliente/";
            $alerta=[
                "tipo"=>"redireccionar",
                "url"=>APP_URL."registroCliente/?redirect_to=".urlencode($redirect_to)
            ];
            return json_encode($alerta);
        }

        $producto_id = (int)($this->limpiarCadena($_POST['producto_id'] ?? "0"));

        if($producto_id<=0){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"Producto inválido para reservar",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $check_producto = $this->conectar()->prepare("SELECT producto_id, producto_nombre, producto_precio_venta, producto_stock_total, producto_estado, producto_talla FROM producto WHERE producto_id=:id LIMIT 1");
        $check_producto->bindParam(":id", $producto_id, \PDO::PARAM_INT);
        $check_producto->execute();

        if($check_producto->rowCount()!=1){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Producto no encontrado",
                "texto"=>"No encontramos el producto que intentas reservar",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $producto = $check_producto->fetch();

        $tallasDisponibles = $this->parseTallasProducto(isset($producto['producto_talla']) ? (string)$producto['producto_talla'] : '');
        $reserva_talla = $this->limpiarCadena($_POST['reserva_talla'] ?? '');
        $reserva_talla = trim((string)$reserva_talla);

        if(!empty($tallasDisponibles)){
            if($reserva_talla===''){
                if(count($tallasDisponibles)===1){
                    $reserva_talla = (string)$tallasDisponibles[0];
                }else{
                    $alerta=[
                        "tipo"=>"simple",
                        "titulo"=>"Talla requerida",
                        "texto"=>"Debes seleccionar una talla para continuar",
                        "icono"=>"error"
                    ];
                    return json_encode($alerta);
                }
            }

            if(!in_array($reserva_talla, $tallasDisponibles, true)){
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Talla no válida",
                    "texto"=>"La talla seleccionada no está disponible para este producto",
                    "icono"=>"error"
                ];
                return json_encode($alerta);
            }
        }

        if(($producto['producto_estado'] ?? '')!="Habilitado"){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Producto no disponible",
                "texto"=>"Este producto no está habilitado para reservar",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if((int)$producto['producto_stock_total']<=0){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Sin stock",
                "texto"=>"Lo sentimos, ya no hay stock disponible de este producto",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $reserva_total = number_format((float)$producto['producto_precio_venta'], MONEDA_DECIMALES, '.', '');

        $reserva_fecha = $this->limpiarCadena($_POST['cita_fecha'] ?? '');
        $reserva_hora = $this->normalizarHora12($this->limpiarCadena($_POST['cita_hora'] ?? ''));

        if($reserva_fecha==='' || !$this->fechaYmdValida($reserva_fecha)){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"Debes seleccionar una fecha válida para la cita",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if($reserva_hora===''){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"Debes seleccionar una hora válida para la cita",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $hoy = date('Y-m-d');
        if($reserva_fecha < $hoy){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Fecha no válida",
                "texto"=>"No puedes reservar para una fecha pasada",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if($this->esDomingo($reserva_fecha)){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Fecha no disponible",
                "texto"=>"Los domingos no atendemos",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if($this->esFeriado($reserva_fecha)){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Fecha no disponible",
                "texto"=>"Este día es feriado y no está disponible",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $horariosPermitidos = $this->generarHorariosPermitidos();
        if(!in_array($reserva_hora, $horariosPermitidos, true)){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Hora no disponible",
                "texto"=>"La hora seleccionada debe estar entre 10:00 am y 07:00 pm",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if($reserva_fecha === $hoy){
            $nowMinutes = ((int)date('H'))*60 + (int)date('i');
            $hm = $this->minutosDeHora12($reserva_hora);
            if($hm !== null && $hm < $nowMinutes){
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Hora no disponible",
                    "texto"=>"La hora seleccionada ya pasó. Elige otro horario",
                    "icono"=>"error"
                ];
                return json_encode($alerta);
            }
        }

        // Evitar doble reserva para el mismo horario
        try{
            // Bloqueo admin
            $bloqueados = $this->obtenerHorasBloqueadas($reserva_fecha);
            if(in_array($reserva_hora, $bloqueados, true)){
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Horario no disponible",
                    "texto"=>"Ese horario no está habilitado. Selecciona otro",
                    "icono"=>"error"
                ];
                return json_encode($alerta);
            }

            $stmtOcupado = $this->conectar()->prepare("SELECT reserva_id FROM reserva WHERE reserva_fecha=:f AND reserva_hora=:h AND reserva_estado<>'rechazada' LIMIT 1");
            $stmtOcupado->bindParam(':f', $reserva_fecha);
            $stmtOcupado->bindParam(':h', $reserva_hora);
            $stmtOcupado->execute();
            if($stmtOcupado->rowCount()>=1){
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Horario no disponible",
                    "texto"=>"Ese horario ya no está disponible. Selecciona otro",
                    "icono"=>"error"
                ];
                return json_encode($alerta);
            }
        }catch(\Throwable $e){
            // Si falla la verificación, seguimos y confiará en la inserción/listado.
        }

        try{
            $correlativo = $this->ejecutarConsulta("SELECT reserva_id FROM reserva");
            $correlativo = ($correlativo->rowCount()) + 1;
        }catch(\Throwable $e){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"No pudimos iniciar la reserva. Verifica que exista la tabla 'reserva' (instalador: ".APP_URL."install_reserva_table.php)",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }
        $codigo = $this->generarCodigoAleatorio(12, $correlativo);

        // Asegurar unicidad del código (reintentos)
        $intentos = 0;
        while($intentos < 5){
            $check_codigo = $this->conectar()->prepare("SELECT reserva_id FROM reserva WHERE reserva_codigo=:c LIMIT 1");
            $check_codigo->bindParam(":c", $codigo);
            $check_codigo->execute();
            if($check_codigo->rowCount()==0){
                break;
            }
            $correlativo++;
            $codigo = $this->generarCodigoAleatorio(12, $correlativo);
            $intentos++;
        }

        if($intentos>=5){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"No pudimos generar el código de reserva, intenta nuevamente",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $datos_reserva=[
            [
                "campo_nombre"=>"reserva_codigo",
                "campo_marcador"=>":Codigo",
                "campo_valor"=>$codigo
            ],
            [
                "campo_nombre"=>"reserva_fecha",
                "campo_marcador"=>":Fecha",
                "campo_valor"=>$reserva_fecha
            ],
            [
                "campo_nombre"=>"reserva_hora",
                "campo_marcador"=>":Hora",
                "campo_valor"=>$reserva_hora
            ],
            [
                "campo_nombre"=>"reserva_total",
                "campo_marcador"=>":Total",
                "campo_valor"=>$reserva_total
            ],
            [
                "campo_nombre"=>"reserva_abono",
                "campo_marcador"=>":Abono",
                "campo_valor"=>"0.00"
            ],
            [
                "campo_nombre"=>"reserva_estado",
                "campo_marcador"=>":Estado",
                "campo_valor"=>"pendiente"
            ],
            [
                "campo_nombre"=>"cliente_id",
                "campo_marcador"=>":Cliente",
                "campo_valor"=>$_SESSION['cliente_id']
            ],
            [
                "campo_nombre"=>"producto_id",
                "campo_marcador"=>":Producto",
                "campo_valor"=>$producto_id
            ]
        ];

        if($this->columnaReservaClienteNotificacionDisponible()){
            $datos_reserva[] = [
                "campo_nombre"=>"reserva_cliente_notificacion",
                "campo_marcador"=>":Notif",
                "campo_valor"=>"1"
            ];
        }

        if($this->columnaReservaTallaDisponible() && $reserva_talla !== ''){
            $datos_reserva[] = [
                "campo_nombre"=>"reserva_talla",
                "campo_marcador"=>":Talla",
                "campo_valor"=>$reserva_talla
            ];
        }

        $guardar = $this->guardarDatos("reserva", $datos_reserva);

        if($guardar->rowCount()!=1){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"No pudimos registrar la reserva, intenta nuevamente",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        // Enviar ticket de reserva al cliente (best-effort)
        $this->enviarTicketReservaPorCorreo($codigo);

        $alerta=[
            "tipo"=>"redireccionar",
            "url"=>APP_URL."reservaPagar/".urlencode($codigo)."/?qr_result=generated"
        ];
        return json_encode($alerta);
    }


    /*---------- Confirmar reserva (admin/caja) ----------*/
    public function confirmarReservaControlador(){

        if(!$this->tablaReservaExiste()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Falta configuración",
                "texto"=>"No existe la tabla 'reserva' en la base de datos. Debes crearla abriendo: ".APP_URL."install_reserva_table.php",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']=="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']=="")){
            $alerta=[
                "tipo"=>"redireccionar",
                "url"=>APP_URL."login/"
            ];
            return json_encode($alerta);
        }

        if(!$this->sesionEsAdmin()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Acceso restringido",
                "texto"=>"Solo el administrador puede aprobar/confirmar reservas.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? "");
        $abono  = $this->limpiarCadena($_POST['reserva_abono'] ?? "");

        if($codigo=="" || $abono==""){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"Faltan datos para confirmar la reserva",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if($this->verificarDatos("[0-9.]{1,25}", $abono)){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Monto inválido",
                "texto"=>"El abono no coincide con el formato solicitado",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Reserva no encontrada",
                "texto"=>"No encontramos la reserva indicada",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $estadoActual = (string)($reserva['reserva_estado'] ?? '');
        if($estadoActual!=="pendiente"){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Reserva ya procesada",
                "texto"=>"Esta reserva ya fue procesada (estado actual: ".$estadoActual.")",
                "icono"=>"info"
            ];
            return json_encode($alerta);
        }

        if((int)$reserva['producto_stock_total']<=0){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Sin stock",
                "texto"=>"No hay stock disponible para confirmar esta reserva",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $total = (float)$reserva['reserva_total'];
        $abono = (float)$abono;

        $minimo = $total * 0.50;
        $minimo = (float)number_format($minimo, MONEDA_DECIMALES, '.', '');

        if($abono < $minimo){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Abono insuficiente",
                "texto"=>"Para reservar debes abonar al menos el 50% (mínimo: ".MONEDA_SIMBOLO.number_format($minimo, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR)." ".MONEDA_NOMBRE.")",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if($abono > $total){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Abono inválido",
                "texto"=>"El abono no puede ser mayor al total",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $abono_fmt = number_format($abono, MONEDA_DECIMALES, '.', '');

        $caja_id = isset($_SESSION['caja']) ? (int)$_SESSION['caja'] : 0;
        if($caja_id<=0){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Caja no configurada",
                "texto"=>"No se encontró una caja asociada al usuario actual",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $pdo = $this->conectar();

        try{
            $pdo->beginTransaction();

            // Revalidar stock dentro de la transacción
            $stmtProd = $pdo->prepare("SELECT producto_stock_total FROM producto WHERE producto_id=:pid FOR UPDATE");
            $stmtProd->bindParam(":pid", $reserva['producto_id'], \PDO::PARAM_INT);
            $stmtProd->execute();
            $prod = $stmtProd->fetch();
            if(!$prod || (int)$prod['producto_stock_total']<=0){
                $pdo->rollBack();
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Sin stock",
                    "texto"=>"No hay stock disponible para confirmar esta reserva",
                    "icono"=>"error"
                ];
                return json_encode($alerta);
            }

            $nuevo_stock = ((int)$prod['producto_stock_total']) - 1;
            $stmtUpProd = $pdo->prepare("UPDATE producto SET producto_stock_total=:s WHERE producto_id=:pid");
            $stmtUpProd->bindParam(":s", $nuevo_stock, \PDO::PARAM_INT);
            $stmtUpProd->bindParam(":pid", $reserva['producto_id'], \PDO::PARAM_INT);
            if(!$stmtUpProd->execute()){
                throw new \Exception("No se pudo actualizar stock");
            }

            // Actualizar caja
            $stmtCaja = $pdo->prepare("SELECT caja_efectivo FROM caja WHERE caja_id=:cid FOR UPDATE");
            $stmtCaja->bindParam(":cid", $caja_id, \PDO::PARAM_INT);
            $stmtCaja->execute();
            $caja = $stmtCaja->fetch();
            if(!$caja){
                throw new \Exception("Caja no encontrada");
            }

            $nuevo_efectivo = (float)$caja['caja_efectivo'] + (float)$abono_fmt;
            $nuevo_efectivo = number_format($nuevo_efectivo, MONEDA_DECIMALES, '.', '');

            $stmtUpCaja = $pdo->prepare("UPDATE caja SET caja_efectivo=:e WHERE caja_id=:cid");
            $stmtUpCaja->bindParam(":e", $nuevo_efectivo);
            $stmtUpCaja->bindParam(":cid", $caja_id, \PDO::PARAM_INT);
            if(!$stmtUpCaja->execute()){
                throw new \Exception("No se pudo actualizar caja");
            }

            // Confirmar reserva
            $sqlUpRes = "UPDATE reserva 
                                         SET reserva_abono=:a,
                                             reserva_estado='confirmada',
                                             usuario_id=:uid,
                                             caja_id=:cid";
            if($this->columnaReservaClienteNotificacionDisponible()){
                $sqlUpRes .= ", reserva_cliente_notificacion=(reserva_cliente_notificacion+1)";
            }
            $sqlUpRes .= " WHERE reserva_codigo=:c AND reserva_estado='pendiente'";
            $stmtUpRes = $pdo->prepare($sqlUpRes);
            $stmtUpRes->bindParam(":a", $abono_fmt);
            $stmtUpRes->bindParam(":uid", $_SESSION['id'], \PDO::PARAM_INT);
            $stmtUpRes->bindParam(":cid", $caja_id, \PDO::PARAM_INT);
            $stmtUpRes->bindParam(":c", $codigo);
            $stmtUpRes->execute();

            if($stmtUpRes->rowCount()!=1){
                throw new \Exception("La reserva no pudo confirmarse (¿ya fue procesada?)");
            }

            $pdo->commit();

        }catch(\Throwable $e){
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"No pudimos confirmar la reserva: ".$e->getMessage(),
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $this->registrarLogAccion("Confirmó reserva: ".$codigo." (Abono: ".$abono_fmt.")");

        // Enviar ticket de reserva al cliente (best-effort)
        $this->enviarTicketReservaPorCorreo($codigo);

        $alerta=[
            "tipo"=>"redireccionar",
            "url"=>APP_URL."reservaDetalle/".urlencode($codigo)."/"
        ];
        return json_encode($alerta);
    }


    /*---------- Confirmar reserva usando pago online (solo admin, sin caja_efectivo) ----------*/
    public function confirmarReservaOnlineControlador(){

        if(!$this->tablaReservaExiste()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Falta configuración",
                "texto"=>"No existe la tabla 'reserva' en la base de datos.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']=="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']=="")){
            $alerta=[
                "tipo"=>"redireccionar",
                "url"=>APP_URL."login/"
            ];
            return json_encode($alerta);
        }

        if(!$this->sesionEsAdmin()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Acceso restringido",
                "texto"=>"Solo el administrador puede aprobar/confirmar reservas.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? "");
        if($codigo===""){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"Faltan datos para confirmar la reserva",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Reserva no encontrada",
                "texto"=>"No encontramos la reserva indicada",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $estadoActual = (string)($reserva['reserva_estado'] ?? '');
        if($estadoActual!=="pendiente"){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Reserva ya procesada",
                "texto"=>"Esta reserva ya fue procesada (estado actual: ".$estadoActual.")",
                "icono"=>"info"
            ];
            return json_encode($alerta);
        }

        $pago = $this->obtenerPagoAprobadoPorCodigo($codigo);
        if(!$pago){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Pago no encontrado",
                "texto"=>"No hay un pago online aprobado asociado a esta reserva.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $total = (float)$reserva['reserva_total'];
        $abono = (float)$pago['pago_monto'];

        $minimo = (float)number_format($total * 0.50, MONEDA_DECIMALES, '.', '');
        if($abono < $minimo){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Pago insuficiente",
                "texto"=>"El pago online aprobado no alcanza el mínimo del 50%.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }
        if($abono > $total){
            $abono = $total;
        }

        if((int)$reserva['producto_stock_total']<=0){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Sin stock",
                "texto"=>"No hay stock disponible para confirmar esta reserva",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $abono_fmt = number_format($abono, MONEDA_DECIMALES, '.', '');
        $caja_id = isset($_SESSION['caja']) ? (int)$_SESSION['caja'] : 0;

        $pdo = $this->conectar();

        try{
            $pdo->beginTransaction();

            // Revalidar stock dentro de la transacción
            $stmtProd = $pdo->prepare("SELECT producto_stock_total FROM producto WHERE producto_id=:pid FOR UPDATE");
            $stmtProd->bindParam(":pid", $reserva['producto_id'], \PDO::PARAM_INT);
            $stmtProd->execute();
            $prod = $stmtProd->fetch();
            if(!$prod || (int)$prod['producto_stock_total']<=0){
                $pdo->rollBack();
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Sin stock",
                    "texto"=>"No hay stock disponible para confirmar esta reserva",
                    "icono"=>"error"
                ];
                return json_encode($alerta);
            }

            $nuevo_stock = ((int)$prod['producto_stock_total']) - 1;
            $stmtUpProd = $pdo->prepare("UPDATE producto SET producto_stock_total=:s WHERE producto_id=:pid");
            $stmtUpProd->bindParam(":s", $nuevo_stock, \PDO::PARAM_INT);
            $stmtUpProd->bindParam(":pid", $reserva['producto_id'], \PDO::PARAM_INT);
            if(!$stmtUpProd->execute()){
                throw new \Exception("No se pudo actualizar stock");
            }

            // Confirmar reserva (sin sumar a caja_efectivo)
            $sqlUpRes = "UPDATE reserva
                                         SET reserva_abono=:a,
                                             reserva_estado='confirmada',
                                             usuario_id=:uid,
                                             caja_id=:cid";
            if($this->columnaReservaClienteNotificacionDisponible()){
                $sqlUpRes .= ", reserva_cliente_notificacion=(reserva_cliente_notificacion+1)";
            }
            $sqlUpRes .= " WHERE reserva_codigo=:c AND reserva_estado='pendiente'";
            $stmtUpRes = $pdo->prepare($sqlUpRes);
            $stmtUpRes->bindParam(":a", $abono_fmt);
            $stmtUpRes->bindParam(":uid", $_SESSION['id'], \PDO::PARAM_INT);
            $stmtUpRes->bindParam(":cid", $caja_id, \PDO::PARAM_INT);
            $stmtUpRes->bindParam(":c", $codigo);
            $stmtUpRes->execute();

            if($stmtUpRes->rowCount()!=1){
                throw new \Exception("La reserva no pudo confirmarse (¿ya fue procesada?)");
            }

            $pdo->commit();

        }catch(\Throwable $e){
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"No pudimos confirmar la reserva: ".$e->getMessage(),
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $this->registrarLogAccion("Confirmó reserva (pago online): ".$codigo." (Abono: ".$abono_fmt.")");

        // Enviar ticket de reserva al cliente (best-effort)
        $this->enviarTicketReservaPorCorreo($codigo);

        $alerta=[
            "tipo"=>"redireccionar",
            "url"=>APP_URL."reservaDetalle/".urlencode($codigo)."/"
        ];
        return json_encode($alerta);
    }


    /*---------- Rechazar reserva (solo admin) ----------*/
    public function rechazarReservaControlador(){

        if(!$this->tablaReservaExiste()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Falta configuración",
                "texto"=>"No existe la tabla 'reserva' en la base de datos.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if((!isset($_SESSION['id']) || $_SESSION['id']=="") || (!isset($_SESSION['usuario']) || $_SESSION['usuario']=="")){
            $alerta=[
                "tipo"=>"redireccionar",
                "url"=>APP_URL."login/"
            ];
            return json_encode($alerta);
        }

        if(!$this->sesionEsAdmin()){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Acceso restringido",
                "texto"=>"Solo el administrador puede rechazar reservas.",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $codigo = $this->limpiarCadena($_POST['reserva_codigo'] ?? "");
        $observacion = $this->limpiarCadena($_POST['reserva_observacion'] ?? "");

        if($codigo==""){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"Falta el código de la reserva",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $reserva = $this->obtenerReservaPorCodigo($codigo);
        if(!$reserva){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Reserva no encontrada",
                "texto"=>"No encontramos la reserva indicada",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        if(($reserva['reserva_estado'] ?? '')!="pendiente"){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"No se puede rechazar",
                "texto"=>"Solo se pueden rechazar reservas en estado pendiente",
                "icono"=>"info"
            ];
            return json_encode($alerta);
        }

        if($observacion!="" && strlen($observacion)>255){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Observación muy larga",
                "texto"=>"La observación no puede superar 255 caracteres",
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        try{
            $pdo = $this->conectar();
            $stmt = $pdo->prepare("UPDATE reserva
                SET reserva_estado='rechazada',
                    reserva_observacion=:obs,
                    usuario_id=:uid,
                    caja_id=NULL
                WHERE reserva_codigo=:c AND reserva_estado='pendiente'");
            $stmt->bindValue(":obs", ($observacion==="" ? null : $observacion));
            $stmt->bindValue(":uid", (int)$_SESSION['id'], \PDO::PARAM_INT);
            $stmt->bindValue(":c", $codigo);
            $stmt->execute();

            if($stmt->rowCount()!=1){
                throw new \Exception("La reserva no pudo rechazarse (¿ya fue procesada?)");
            }
        }catch(\Throwable $e){
            $alerta=[
                "tipo"=>"simple",
                "titulo"=>"Ocurrió un error inesperado",
                "texto"=>"No pudimos rechazar la reserva: ".$e->getMessage(),
                "icono"=>"error"
            ];
            return json_encode($alerta);
        }

        $this->registrarLogAccion("Rechazó reserva: ".$codigo.(($observacion!=="") ? " (Obs: ".$observacion.")" : ""));

        $alerta=[
            "tipo"=>"recargar",
            "titulo"=>"Reserva rechazada",
            "texto"=>"La reserva fue marcada como rechazada.",
            "icono"=>"success"
        ];
        return json_encode($alerta);
    }
}
