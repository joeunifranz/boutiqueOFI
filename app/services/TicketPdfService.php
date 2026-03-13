<?php

namespace app\services;

use app\controllers\reservationController;
use app\controllers\saleController;

class TicketPdfService{
	private ?string $lastError = null;

	public function getLastError(): ?string{
		return $this->lastError;
	}

	private function fail(string $message): ?string{
		$this->lastError = $message;
		return null;
	}

	private function codigoValido(string $code): bool{
		// Códigos del sistema suelen ser alfanuméricos con guiones.
		return preg_match('/^[A-Za-z0-9\-]{4,200}$/', $code) === 1;
	}

	/**
	 * Genera el PDF del ticket de venta (mismo formato que app/pdf/ticket.php) y lo devuelve como string.
	 */
	public function generarTicketVenta(string $ventaCodigo): ?string{
		$this->lastError = null;
		$ventaCodigo = trim($ventaCodigo);
		if(!$this->codigoValido($ventaCodigo)){
			return $this->fail('Código de venta inválido');
		}

		require_once __DIR__ . '/../pdf/code128.php';

		$insVenta = new saleController();
		$datosVenta = $insVenta->seleccionarDatos(
			"Normal",
			"venta INNER JOIN cliente ON venta.cliente_id=cliente.cliente_id INNER JOIN usuario ON venta.usuario_id=usuario.usuario_id INNER JOIN caja ON venta.caja_id=caja.caja_id WHERE (venta_codigo='{$ventaCodigo}')",
			"*",
			0
		);

		if(!$datosVenta || $datosVenta->rowCount() !== 1){
			return $this->fail('No se encontraron datos de la venta');
		}
		$datosVenta = $datosVenta->fetch();

		$datosEmpresa = $insVenta->seleccionarDatos('Normal','empresa LIMIT 1','*',0);
		$datosEmpresa = ($datosEmpresa && $datosEmpresa->rowCount()>=1) ? $datosEmpresa->fetch() : [
			'empresa_nombre' => defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE',
			'empresa_direccion' => '',
			'empresa_telefono' => '',
			'empresa_email' => '',
		];

		$pdf = new \PDF_Code128('P','mm',[80,258]);
		$pdf->SetMargins(4,10,4);
		$pdf->AddPage();

		$pdf->SetFont('Arial','B',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',strtoupper((string)$datosEmpresa['empresa_nombre'])),0,'C',false);
		$pdf->SetFont('Arial','',9);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',(string)$datosEmpresa['empresa_direccion']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Teléfono: '.(string)$datosEmpresa['empresa_telefono']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Email: '.(string)$datosEmpresa['empresa_email']),0,'C',false);

		$pdf->Ln(1);
		$pdf->Cell(0,5,iconv('UTF-8','ISO-8859-1','------------------------------------------------------'),0,0,'C');
		$pdf->Ln(5);

		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Fecha: '.date('d/m/Y', strtotime((string)$datosVenta['venta_fecha'])).' '.(string)$datosVenta['venta_hora']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Caja Nro: '.(string)$datosVenta['caja_numero']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Cajero: '.(string)$datosVenta['usuario_nombre'].' '.(string)$datosVenta['usuario_apellido']),0,'C',false);
		$pdf->SetFont('Arial','B',10);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',strtoupper('Ticket Nro: '.(string)$datosVenta['venta_id'])),0,'C',false);
		$pdf->SetFont('Arial','',9);

		$pdf->Ln(1);
		$pdf->Cell(0,5,iconv('UTF-8','ISO-8859-1','------------------------------------------------------'),0,0,'C');
		$pdf->Ln(5);

		if((int)$datosVenta['cliente_id'] === 1){
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Cliente: N/A'),0,'C',false);
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Documento: N/A'),0,'C',false);
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Teléfono: N/A'),0,'C',false);
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Dirección: N/A'),0,'C',false);
		}else{
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Cliente: '.(string)$datosVenta['cliente_nombre'].' '.(string)$datosVenta['cliente_apellido']),0,'C',false);
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Documento: '.(string)$datosVenta['cliente_tipo_documento'].' '.(string)$datosVenta['cliente_numero_documento']),0,'C',false);
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Teléfono: '.(string)$datosVenta['cliente_telefono']),0,'C',false);
			$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Dirección: '.(string)$datosVenta['cliente_ciudad'].', '.(string)$datosVenta['cliente_direccion']),0,'C',false);
		}

		$pdf->Ln(1);
		$pdf->Cell(0,5,iconv('UTF-8','ISO-8859-1','-------------------------------------------------------------------'),0,0,'C');
		$pdf->Ln(3);

		$pdf->Cell(18,5,iconv('UTF-8','ISO-8859-1','Cant.'),0,0,'C');
		$pdf->Cell(22,5,iconv('UTF-8','ISO-8859-1','Precio'),0,0,'C');
		$pdf->Cell(32,5,iconv('UTF-8','ISO-8859-1','Total'),0,0,'C');

		$pdf->Ln(3);
		$pdf->Cell(72,5,iconv('UTF-8','ISO-8859-1','-------------------------------------------------------------------'),0,0,'C');
		$pdf->Ln(3);

		$ventaDetalle = $insVenta->seleccionarDatos('Normal',"venta_detalle WHERE venta_codigo='".(string)$datosVenta['venta_codigo']."'",'*',0);
		$ventaDetalle = $ventaDetalle ? $ventaDetalle->fetchAll() : [];
		foreach($ventaDetalle as $detalle){
			$pdf->MultiCell(0,4,iconv('UTF-8','ISO-8859-1',(string)$detalle['venta_detalle_descripcion']),0,'C',false);
			$pdf->Cell(18,4,iconv('UTF-8','ISO-8859-1',(string)$detalle['venta_detalle_cantidad']),0,0,'C');
			$pdf->Cell(22,4,iconv('UTF-8','ISO-8859-1',MONEDA_SIMBOLO.number_format((float)$detalle['venta_detalle_precio_venta'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR)),0,0,'C');
			$pdf->Cell(32,4,iconv('UTF-8','ISO-8859-1',MONEDA_SIMBOLO.number_format((float)$detalle['venta_detalle_total'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR)),0,0,'C');
			$pdf->Ln(4);
			$pdf->Ln(3);
		}

		$pdf->Cell(72,5,iconv('UTF-8','ISO-8859-1','-------------------------------------------------------------------'),0,0,'C');
		$pdf->Ln(5);

		$pdf->Cell(18,5,iconv('UTF-8','ISO-8859-1',''),0,0,'C');
		$pdf->Cell(22,5,iconv('UTF-8','ISO-8859-1','TOTAL A PAGAR'),0,0,'C');
		$pdf->Cell(32,5,iconv('UTF-8','ISO-8859-1',MONEDA_SIMBOLO.number_format((float)$datosVenta['venta_total'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE),0,0,'C');

		$pdf->Ln(5);
		$pdf->Cell(18,5,iconv('UTF-8','ISO-8859-1',''),0,0,'C');
		$pdf->Cell(22,5,iconv('UTF-8','ISO-8859-1','TOTAL PAGADO'),0,0,'C');
		$pdf->Cell(32,5,iconv('UTF-8','ISO-8859-1',MONEDA_SIMBOLO.number_format((float)$datosVenta['venta_pagado'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE),0,0,'C');

		$pdf->Ln(5);
		$pdf->Cell(18,5,iconv('UTF-8','ISO-8859-1',''),0,0,'C');
		$pdf->Cell(22,5,iconv('UTF-8','ISO-8859-1','CAMBIO'),0,0,'C');
		$pdf->Cell(32,5,iconv('UTF-8','ISO-8859-1',MONEDA_SIMBOLO.number_format((float)$datosVenta['venta_cambio'],MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE),0,0,'C');

		$pdf->Ln(10);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','**Para poder realizar un reclamo debe de presentar este ticket ***'),0,'C',false);
		$pdf->SetFont('Arial','B',9);
		$pdf->Cell(0,7,iconv('UTF-8','ISO-8859-1','Gracias por su compra'),'',0,'C');

		$pdf->Ln(9);
		$pdf->Code128(5,$pdf->GetY(),(string)$datosVenta['venta_codigo'],70,20);
		$pdf->SetXY(0,$pdf->GetY()+21);
		$pdf->SetFont('Arial','',14);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',(string)$datosVenta['venta_codigo']),0,'C',false);

		return $pdf->Output('S');
	}

	/**
	 * Genera el PDF del ticket de reserva y lo devuelve como string.
	 */
	public function generarTicketReserva(string $reservaCodigo): ?string{
		$this->lastError = null;
		$reservaCodigo = trim($reservaCodigo);
		if(!$this->codigoValido($reservaCodigo)){
			return $this->fail('Código de reserva inválido');
		}

		require_once __DIR__ . '/../pdf/code128.php';

		$insReserva = new reservationController();
		$datos = $insReserva->seleccionarDatos(
			'Normal',
			"reserva r INNER JOIN cliente c ON c.cliente_id=r.cliente_id INNER JOIN producto p ON p.producto_id=r.producto_id WHERE (r.reserva_codigo='{$reservaCodigo}')",
			'r.*, c.cliente_nombre, c.cliente_apellido, c.cliente_email, p.producto_nombre',
			0
		);

		if(!$datos || $datos->rowCount() !== 1){
			return $this->fail('No se encontraron datos de la reserva');
		}
		$r = $datos->fetch();

		$datosEmpresa = $insReserva->seleccionarDatos('Normal','empresa LIMIT 1','*',0);
		$datosEmpresa = ($datosEmpresa && $datosEmpresa->rowCount()>=1) ? $datosEmpresa->fetch() : [
			'empresa_nombre' => defined('APP_NAME') ? (string)APP_NAME : 'BOUTIQUE',
			'empresa_direccion' => '',
			'empresa_telefono' => '',
			'empresa_email' => '',
		];

		$pdf = new \PDF_Code128('P','mm',[80,258]);
		$pdf->SetMargins(4,10,4);
		$pdf->AddPage();

		$pdf->SetFont('Arial','B',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',strtoupper((string)$datosEmpresa['empresa_nombre'])),0,'C',false);
		$pdf->SetFont('Arial','',9);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',(string)$datosEmpresa['empresa_direccion']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Teléfono: '.(string)$datosEmpresa['empresa_telefono']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Email: '.(string)$datosEmpresa['empresa_email']),0,'C',false);

		$pdf->Ln(1);
		$pdf->Cell(0,5,iconv('UTF-8','ISO-8859-1','------------------------------------------------------'),0,0,'C');
		$pdf->Ln(5);

		$fechaCita = '';
		try{
			$fechaCita = date('d/m/Y', strtotime((string)$r['reserva_fecha']));
		}catch(\Throwable $e){
			$fechaCita = (string)$r['reserva_fecha'];
		}

		$pdf->SetFont('Arial','B',10);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',strtoupper('Ticket Reserva Nro: '.(string)$r['reserva_id'])),0,'C',false);
		$pdf->SetFont('Arial','',9);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Emitido: '.date('d/m/Y H:i')),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Cita: '.$fechaCita.' '.(string)$r['reserva_hora']),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Estado: '.(string)$r['reserva_estado']),0,'C',false);

		$pdf->Ln(1);
		$pdf->Cell(0,5,iconv('UTF-8','ISO-8859-1','------------------------------------------------------'),0,0,'C');
		$pdf->Ln(5);

		$cliente = trim((string)$r['cliente_nombre'].' '.(string)$r['cliente_apellido']);
		if($cliente===''){
			$cliente = 'Cliente';
		}
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Cliente: '.$cliente),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Email: '.(string)$r['cliente_email']),0,'C',false);
		$pdf->Ln(2);

		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Producto: '.(string)$r['producto_nombre']),0,'C',false);

		$total = (float)($r['reserva_total'] ?? 0);
		$abono = (float)($r['reserva_abono'] ?? 0);
		$saldo = $total - $abono;
		if($saldo < 0){
			$saldo = 0;
		}
		$pdf->Ln(2);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Total: '.MONEDA_SIMBOLO.number_format($total,MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE),0,'C',false);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1','Abono: '.MONEDA_SIMBOLO.number_format($abono,MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR).' '.MONEDA_NOMBRE),0,'C',false);

		$pdf->Ln(2);
		$pdf->SetFont('Arial','B',11);
		$pdf->SetFillColor(33,33,33);
		$pdf->SetTextColor(255,255,255);
		$pdf->Cell(0,8,iconv('UTF-8','ISO-8859-1','TOTAL SALDO: '.MONEDA_SIMBOLO.' '.number_format($saldo,MONEDA_DECIMALES,MONEDA_SEPARADOR_DECIMAL,MONEDA_SEPARADOR_MILLAR)),0,1,'C',true);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('Arial','',9);

		$pdf->Ln(6);
		$pdf->Code128(5,$pdf->GetY(),(string)$r['reserva_codigo'],70,20);
		$pdf->SetXY(0,$pdf->GetY()+21);
		$pdf->SetFont('Arial','',14);
		$pdf->MultiCell(0,5,iconv('UTF-8','ISO-8859-1',(string)$r['reserva_codigo']),0,'C',false);

		return $pdf->Output('S');
	}
}
