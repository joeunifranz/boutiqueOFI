<?php

require_once __DIR__ . '/fpdf.php';

class TableReportPDF extends FPDF{
	public string $titulo = '';
	public string $subtitulo = '';

	private array $headers = [];
	private array $widths = [];
	private array $aligns = [];
	private int $headerFontSize = 9;
	private int $bodyFontSize = 8;
	private int $lineHeight = 5;

	public function setTable(array $headers, array $widths, array $aligns = []): void{
		$this->headers = $headers;
		$this->widths = $widths;
		$this->aligns = $aligns;
	}

	public function encode($txt): string{
		$txt = (string)$txt;
		$converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $txt);
		return ($converted !== false) ? $converted : $txt;
	}

	public function Header(){
		if($this->titulo !== ''){
			$this->SetFont('Arial','B',12);
			$this->Cell(0,7,$this->encode($this->titulo),0,1,'C');
		}
		if($this->subtitulo !== ''){
			$this->SetFont('Arial','',9);
			$this->Cell(0,5,$this->encode($this->subtitulo),0,1,'C');
		}
		$this->Ln(2);
		$this->SetDrawColor(200,200,200);
		$this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
		$this->Ln(4);

		if(!empty($this->headers) && !empty($this->widths)){
			$this->renderTableHeader();
		}
	}

	public function Footer(){
		$this->SetY(-12);
		$this->SetFont('Arial','',8);
		$this->SetTextColor(120,120,120);
		$this->Cell(0,6,$this->encode('Página '.$this->PageNo().'/{nb}'),0,0,'C');
		$this->SetTextColor(0,0,0);
	}

	private function renderTableHeader(): void{
		$this->SetFont('Arial','B',$this->headerFontSize);
		$this->SetFillColor(240,240,240);
		for($i=0; $i<count($this->headers); $i++){
			$w = $this->widths[$i] ?? 20;
			$h = 7;
			$label = $this->headers[$i] ?? '';
			$this->Cell($w,$h,$this->encode($label),1,0,'C',true);
		}
		$this->Ln(7);
		$this->SetFont('Arial','',$this->bodyFontSize);
	}

	private function nbLines($w, $txt): int{
		$txt = (string)$txt;
		$cw = $this->CurrentFont['cw'] ?? [];
		if($w==0){
			$w = $this->w - $this->rMargin - $this->x;
		}
		$wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
		$s = str_replace("\r", '', $txt);
		$nb = strlen($s);
		if($nb>0 && $s[$nb-1]=="\n"){
			$nb--;
		}
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$nl = 1;
		while($i<$nb){
			$c = $s[$i];
			if($c=="\n"){
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				$nl++;
				continue;
			}
			if($c==' '){
				$sep = $i;
			}
			$l += $cw[$c] ?? 0;
			if($l>$wmax){
				if($sep==-1){
					if($i==$j){
						$i++;
					}
				}else{
					$i = $sep + 1;
				}
				$sep = -1;
				$j = $i;
				$l = 0;
				$nl++;
			}else{
				$i++;
			}
		}
		return $nl;
	}

	private function checkPageBreak($h): void{
		if($this->GetY()+$h > $this->PageBreakTrigger){
			$this->AddPage($this->CurOrientation);
		}
	}

	public function addRow(array $data, bool $fill = false): void{
		$maxLines = 1;
		for($i=0; $i<count($data); $i++){
			$w = $this->widths[$i] ?? 20;
			$maxLines = max($maxLines, $this->nbLines($w, (string)$data[$i]));
		}

		$h = $this->lineHeight * $maxLines;
		$this->checkPageBreak($h);

		$this->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255);

		for($i=0; $i<count($data); $i++){
			$w = $this->widths[$i] ?? 20;
			$a = $this->aligns[$i] ?? 'L';
			$x = $this->GetX();
			$y = $this->GetY();

			$this->Rect($x, $y, $w, $h);
			$this->MultiCell($w, $this->lineHeight, $this->encode((string)$data[$i]), 0, $a, $fill);
			$this->SetXY($x + $w, $y);
		}
		$this->Ln($h);
	}
}
