<?php

require_once __DIR__ . '/fpdf.php';

class LogsPDF extends FPDF{
	public $titulo = '';
	public $subtitulo = '';

	public function getPageBreakTrigger(){ return $this->PageBreakTrigger; }
	public function getCurOrientation(){ return $this->CurOrientation; }
	public function getCurrentFontCw(){ return $this->CurrentFont['cw'] ?? []; }
	public function getDocW(){ return $this->w; }
	public function getRMarginVal(){ return $this->rMargin; }
	public function getCMarginVal(){ return $this->cMargin; }
	public function getFontSizeVal(){ return $this->FontSize; }

	public function Header(){
		$this->SetFont('Arial','B',12);
		$this->Cell(0,7,$this->titulo,0,1,'C');
		$this->SetFont('Arial','',9);
		$this->Cell(0,5,$this->subtitulo,0,1,'C');
		$this->Ln(2);
		$this->SetDrawColor(200,200,200);
		$this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
		$this->Ln(4);
	}

	public function Footer(){
		$this->SetY(-12);
		$this->SetFont('Arial','',8);
		$this->SetTextColor(120,120,120);
		$this->Cell(0,6,iconv('UTF-8','ISO-8859-1//TRANSLIT','Página '.$this->PageNo().'/{nb}'),0,0,'C');
		$this->SetTextColor(0,0,0);
	}
}
