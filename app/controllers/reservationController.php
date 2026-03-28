<?php

namespace app\controllers;

use app\models\mainModel;
use app\services\MercadoPagoService;
use app\services\BisaQrService;

class reservationController extends mainModel{

    private static $reservaTallaColDisponible = null;

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
        try{
            $stmt = $this->conectar()->prepare(
                "SELECT r.reserva_id, r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado, r.reserva_observacion{$colsTalla},
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
        try{
            $stmt = $this->conectar()->prepare(
                "SELECT r.reserva_id, r.reserva_codigo, r.reserva_fecha, r.reserva_hora, r.reserva_total, r.reserva_abono, r.reserva_estado, r.reserva_observacion{$colsTalla},
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
            $saldo = $total - $abono;
            if($saldo < 0){
                $saldo = 0;
            }

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
                        <li><strong>Abono:</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($abono, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE,ENT_QUOTES,'UTF-8')."</li>
                        <li><strong>TOTAL SALDO (debe pagar):</strong> ".htmlspecialchars(MONEDA_SIMBOLO.number_format($saldo, MONEDA_DECIMALES, MONEDA_SEPARADOR_DECIMAL, MONEDA_SEPARADOR_MILLAR).' Bs',ENT_QUOTES,'UTF-8')."</li>
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
            return [];
        }
        try{
            $stmt = $this->conectar()->prepare("SELECT reserva_hora FROM reserva WHERE reserva_fecha=:f AND reserva_estado<>'rechazada'");
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

    private function cargarConfigBisaQr(){
        if(!defined('BISA_API_BASE_URL')){
            $ruta = __DIR__."/../../config/bisa_qr.php";
            if(file_exists($ruta)){
                require_once $ruta;
            }
        }
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

    private function obtenerUltimoQrGeneradoPorCodigo($codigo){
        $codigo = $this->limpiarCadena($codigo);
        if($codigo==="" || !$this->tablaReservaPagoExiste()){
            return null;
        }

        try{
            // Preferimos proveedor bisa, estado created/pending
            $stmt = $this->conectar()->prepare("SELECT * FROM reserva_pago WHERE reserva_codigo=:c AND pago_proveedor='bisa' ORDER BY reserva_pago_id DESC LIMIT 1");
            $stmt->bindParam(':c', $codigo);
            $stmt->execute();
            $row = $stmt->fetch();

            if(!$row){
                return null;
            }

            // Si no existen columnas BISA en tabla, intentar recuperar del JSON guardado en pago_raw
            if((empty($row['pago_qr_string']) || $row['pago_qr_string']===null) && !empty($row['pago_raw'])){
                $raw = json_decode((string)$row['pago_raw'], true);
                if(is_array($raw)){
                    $qrString = $raw['qr_string'] ?? ($raw['qr'] ?? ($raw['data']['qr_string'] ?? null));
                    if(is_string($qrString) && $qrString!==''){
                        $row['pago_qr_string'] = $qrString;
                    }
                    $qrId = $raw['qr_id'] ?? ($raw['data']['qr_id'] ?? null);
                    if(is_string($qrId) && $qrId!==''){
                        $row['pago_qr_id'] = $qrId;
                    }
                }
            }

            return $row;
        }catch(\Throwable $e){
            return null;
        }
    }

    public function obtenerUltimoQrGeneradoPorCodigoControlador($codigo){
        return $this->obtenerUltimoQrGeneradoPorCodigo($codigo);
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

    private function registrarReservaPagoCreadoBisa($codigo,$qrId,$qrString,$monto,$moneda,$raw=null){
        if(!$this->tablaReservaPagoExiste()){
            return false;
        }
        try{
            $now = date('Y-m-d H:i:s');
            $stmt = $this->conectar()->prepare("INSERT INTO reserva_pago (reserva_codigo,pago_proveedor,pago_payment_id,pago_status,pago_monto,pago_moneda,pago_creado_en,pago_actualizado_en,pago_qr_id,pago_qr_string,pago_raw)
                VALUES (:c,'bisa',:pid,'created',:m,:mon,:cre,:act,:qid,:qstr,:raw)");
            $stmt->bindParam(':c', $codigo);
            $pid = (string)$qrId;
            $stmt->bindParam(':pid', $pid);
            $montoFmt = number_format((float)$monto, MONEDA_DECIMALES, '.', '');
            $stmt->bindParam(':m', $montoFmt);
            $stmt->bindParam(':mon', $moneda);
            $stmt->bindParam(':cre', $now);
            $stmt->bindParam(':act', $now);
            $qid = (string)$qrId;
            $stmt->bindParam(':qid', $qid);
            $qstr = (string)$qrString;
            $stmt->bindParam(':qstr', $qstr);
            $rawStr = $raw!==null ? (is_string($raw) ? $raw : json_encode($raw)) : null;
            $stmt->bindParam(':raw', $rawStr);
            return $stmt->execute();
        }catch(\Throwable $e){
            // Si la tabla no tiene columnas bisa (pago_qr_id/pago_qr_string), intentamos guardar en campos existentes
            try{
                $now = date('Y-m-d H:i:s');
                $stmt = $this->conectar()->prepare("INSERT INTO reserva_pago (reserva_codigo,pago_proveedor,pago_payment_id,pago_status,pago_monto,pago_moneda,pago_creado_en,pago_actualizado_en,pago_raw)
                    VALUES (:c,'bisa',:pid,'created',:m,:mon,:cre,:act,:raw)");
                $stmt->bindParam(':c', $codigo);
                $pid = (string)$qrId;
                $stmt->bindParam(':pid', $pid);
                $montoFmt = number_format((float)$monto, MONEDA_DECIMALES, '.', '');
                $stmt->bindParam(':m', $montoFmt);
                $stmt->bindParam(':mon', $moneda);
                $stmt->bindParam(':cre', $now);
                $stmt->bindParam(':act', $now);
                $rawMerged = [
                    'qr_id' => (string)$qrId,
                    'qr_string' => (string)$qrString,
                    'raw' => $raw
                ];
                $rawStr = json_encode($rawMerged);
                $stmt->bindParam(':raw', $rawStr);
                return $stmt->execute();
            }catch(\Throwable $e2){
                return false;
            }
        }
    }

    private function upsertPagoDesdeBisa($codigo,$paymentId,$status,$monto,$moneda,$raw=null){
        if(!$this->tablaReservaPagoExiste()){
            return false;
        }
        try{
            $now = date('Y-m-d H:i:s');
            $aprobadoEn = null;
            $this->cargarConfigBisaQr();
            $approved = false;
            if(defined('BISA_APPROVED_STATUSES') && is_array(BISA_APPROVED_STATUSES)){
                $approved = in_array(strtolower((string)$status), array_map('strtolower', BISA_APPROVED_STATUSES), true);
            }else{
                $approved = (strtolower((string)$status)==='paid');
            }
            $normalizedStatus = (string)$status;
            if($approved){
                $aprobadoEn = $now;
                $normalizedStatus = 'approved';
            }

            $sql = "INSERT INTO reserva_pago
                    (reserva_codigo,pago_proveedor,pago_payment_id,pago_status,pago_monto,pago_moneda,pago_creado_en,pago_actualizado_en,pago_aprobado_en,pago_raw)
                    VALUES
                    (:c,'bisa',:pid,:st,:m,:mon,:cre,:act,:apr,:raw)
                    ON DUPLICATE KEY UPDATE
                        reserva_codigo=VALUES(reserva_codigo),
                        pago_status=VALUES(pago_status),
                        pago_monto=VALUES(pago_monto),
                        pago_moneda=VALUES(pago_moneda),
                        pago_actualizado_en=VALUES(pago_actualizado_en),
                        pago_aprobado_en=IF(VALUES(pago_aprobado_en) IS NOT NULL, VALUES(pago_aprobado_en), pago_aprobado_en),
                        pago_raw=VALUES(pago_raw)";

            $stmt = $this->conectar()->prepare($sql);
            $stmt->bindParam(':c', $codigo);
            $pid = (string)$paymentId;
            $stmt->bindParam(':pid', $pid);
            $stmt->bindParam(':st', $normalizedStatus);
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

    public function generarPagoBisaQrControlador(){

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
        }

        $this->cargarConfigBisaQr();
        $bisa = new BisaQrService();
        if(!$bisa->configuracionValida()){
            echo "BISA QR no está configurado. Completa config/bisa_qr.php con tu API Key y base URL.";
            return;
        }

        $moneda = defined('BISA_CURRENCY_ID') ? (string)BISA_CURRENCY_ID : 'BOB';
        $webhookUrl = APP_URL.'bisaWebhook/?token='.(defined('BISA_WEBHOOK_TOKEN') ? urlencode((string)BISA_WEBHOOK_TOKEN) : '');

        $descripcion = 'Reserva '.$reserva['reserva_codigo'].' - '.$reserva['producto_nombre'];
        $resp = $bisa->crearQrDinamico((string)$reserva['reserva_codigo'], (float)$monto, $moneda, $descripcion, $webhookUrl);

        if(!is_array($resp) || (($resp['_http_code'] ?? 0) < 200) || (($resp['_http_code'] ?? 0) >= 300)){
            echo "No se pudo generar el QR en BISA (falta endpoint/payload correcto).";
            return;
        }

        // Estos keys dependen del API real. Ajustar cuando BISA entregue la documentación.
        $qrId = (string)($resp['qr_id'] ?? ($resp['id'] ?? ($resp['charge_id'] ?? '')));
        $qrString = (string)($resp['qr_string'] ?? ($resp['qr'] ?? ($resp['data'] ?? '')));
        if($qrId==='' && isset($resp['data']['qr_id'])){
            $qrId = (string)$resp['data']['qr_id'];
        }
        if($qrString==='' && isset($resp['data']['qr_string'])){
            $qrString = (string)$resp['data']['qr_string'];
        }

        if($qrString===''){
            echo "BISA respondió pero no encontramos el string del QR en la respuesta. Ajustar mapeo de campos.";
            return;
        }
        if($qrId===''){
            // Si no hay ID, usamos uno interno (no ideal, pero permite flujo visual)
            $qrId = 'qr_'.date('YmdHis').'_'.substr(md5($reserva['reserva_codigo'].$monto),0,8);
        }

        $this->registrarReservaPagoCreadoBisa(
            (string)$reserva['reserva_codigo'],
            $qrId,
            $qrString,
            (float)$monto,
            $moneda,
            $resp
        );

        if(headers_sent()){
            echo "<script>window.location.href='".APP_URL."reservaPagar/".urlencode($reserva['reserva_codigo'])."/?qr_result=generated';</script>";
        }else{
            header('Location: '.APP_URL.'reservaPagar/'.urlencode($reserva['reserva_codigo']).'/?qr_result=generated');
        }
    }


    public function procesarWebhookBisaControlador(){

        $this->cargarConfigBisaQr();

        if(defined('BISA_WEBHOOK_TOKEN') && BISA_WEBHOOK_TOKEN!=='' && BISA_WEBHOOK_TOKEN!=='CAMBIAME_POR_UN_TOKEN_LARGO'){
            $token = $_GET['token'] ?? '';
            if(!hash_equals((string)BISA_WEBHOOK_TOKEN, (string)$token)){
                http_response_code(401);
                return json_encode(['ok'=>false,'error'=>'unauthorized']);
            }
        }

        $rawBody = file_get_contents('php://input');
        $json = null;
        if($rawBody){
            $json = json_decode($rawBody,true);
        }

        if(!is_array($json)){
            return json_encode(['ok'=>true,'ignored'=>true]);
        }

        // Campos típicos (ajustar cuando llegue doc real)
        $codigo = (string)($json['reference'] ?? ($json['external_reference'] ?? ($json['reserva_codigo'] ?? '')));
        if($codigo==='' && isset($json['data']['reference'])){
            $codigo = (string)$json['data']['reference'];
        }

        $paymentId = (string)($json['transaction_id'] ?? ($json['payment_id'] ?? ($json['qr_id'] ?? ($json['id'] ?? ''))));
        if($paymentId==='' && isset($json['data']['transaction_id'])){
            $paymentId = (string)$json['data']['transaction_id'];
        }

        $status = (string)($json['status'] ?? ($json['payment_status'] ?? ''));
        if($status==='' && isset($json['data']['status'])){
            $status = (string)$json['data']['status'];
        }

        $monto = (float)($json['amount'] ?? ($json['transaction_amount'] ?? 0));
        if($monto<=0 && isset($json['data']['amount'])){
            $monto = (float)$json['data']['amount'];
        }

        $moneda = (string)($json['currency'] ?? ($json['currency_id'] ?? (defined('BISA_CURRENCY_ID') ? BISA_CURRENCY_ID : 'BOB')));
        if($codigo===''){
            return json_encode(['ok'=>true,'no_reference'=>true]);
        }

        if($paymentId===''){
            $paymentId = 'bisa_'.date('YmdHis').'_'.substr(md5($codigo),0,8);
        }

        $this->upsertPagoDesdeBisa($codigo, $paymentId, $status, $monto, $moneda, $json);

        $approved = false;
        if(defined('BISA_APPROVED_STATUSES') && is_array(BISA_APPROVED_STATUSES)){
            $approved = in_array(strtolower((string)$status), array_map('strtolower', BISA_APPROVED_STATUSES), true);
        }

        if(defined('BISA_AUTO_CONFIRM_RESERVA') && BISA_AUTO_CONFIRM_RESERVA===true && $approved){
            $this->cargarConfigBisaQr();
            $usuarioAuto = defined('BISA_AUTO_CONFIRM_USUARIO_ID') ? (int)BISA_AUTO_CONFIRM_USUARIO_ID : 1;
            $this->confirmarReservaDesdePagoOnline($codigo, $monto, $usuarioAuto);
        }

        return json_encode(['ok'=>true]);
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

            $stmtUpRes = $pdo->prepare("UPDATE reserva
                    SET reserva_abono=:a,
                        reserva_estado='confirmada',
                        usuario_id=:uid,
                        caja_id=NULL
                    WHERE reserva_codigo=:c AND reserva_estado='pendiente'");
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
            $stmtUpRes = $pdo->prepare("UPDATE reserva
                                        SET reserva_estado='completada',
                                            usuario_id=:uid,
                                            caja_id=:cid
                                        WHERE reserva_codigo=:c AND reserva_estado IN ('confirmada','reprogramada')");
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

        $alerta=[
            "tipo"=>"redireccionar",
            "url"=>APP_URL."reservaQR/".$codigo."/"
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
            $stmtUpRes = $pdo->prepare("UPDATE reserva 
                                         SET reserva_abono=:a,
                                             reserva_estado='confirmada',
                                             usuario_id=:uid,
                                             caja_id=:cid
                                         WHERE reserva_codigo=:c AND reserva_estado='pendiente'");
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
            $stmtUpRes = $pdo->prepare("UPDATE reserva
                                         SET reserva_abono=:a,
                                             reserva_estado='confirmada',
                                             usuario_id=:uid,
                                             caja_id=:cid
                                         WHERE reserva_codigo=:c AND reserva_estado='pendiente'");
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
