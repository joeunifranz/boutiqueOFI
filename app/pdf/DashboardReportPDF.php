<?php

require_once __DIR__ . '/TableReportPDF.php';

class DashboardReportPDF extends TableReportPDF{
	public function getLeftMargin(): float{ return (float)$this->lMargin; }
	public function getRightMargin(): float{ return (float)$this->rMargin; }
	public function getPageWidth(): float{ return (float)$this->w; }

	public function sectionTitle(string $title): void{
		$this->SetFont('Arial', 'B', 11);
		$this->Cell(0, 7, $this->encode($title), 0, 1, 'L');
		$this->SetFont('Arial', '', 9);
	}

	public function drawStatBoxes(array $stats, int $cols = 3): void{
		$cols = max(1, (int)$cols);
		$gap = 2;
		$boxH = 12;
		$usableW = ($this->w - $this->lMargin - $this->rMargin);
		$boxW = ($usableW - ($cols - 1) * $gap) / $cols;

		$this->SetDrawColor(210, 210, 210);
		$startX = $this->GetX();
		$startY = $this->GetY();

		$i = 0;
		foreach($stats as $stat){
			$label = (string)($stat['label'] ?? '');
			$value = (string)($stat['value'] ?? '');

			$row = (int)floor($i / $cols);
			$col = (int)($i % $cols);

			$x = $startX + $col * ($boxW + $gap);
			$y = $startY + $row * ($boxH + $gap);

			$this->SetXY($x, $y);
			$this->Rect($x, $y, $boxW, $boxH);

			$this->SetFont('Arial', '', 8);
			$this->SetTextColor(90, 90, 90);
			$this->SetXY($x + 2, $y + 2);
			$this->Cell($boxW - 4, 4, $this->encode($label), 0, 0, 'L');

			$this->SetFont('Arial', 'B', 11);
			$this->SetTextColor(0, 0, 0);
			$this->SetXY($x + 2, $y + 6);
			$this->Cell($boxW - 4, 5, $this->encode($value), 0, 0, 'L');

			$i++;
		}

		$rows = (int)ceil($i / $cols);
		$this->SetXY($startX, $startY + $rows * ($boxH + $gap));
		$this->Ln(2);
	}

	public function drawBarChart(
		float $x,
		float $y,
		float $w,
		float $h,
		array $labels,
		array $values,
		array $barRgb = [50, 115, 220]
	): void{
		$count = count($values);
		if($count <= 0){
			return;
		}

		$max = 0.0;
		foreach($values as $v){
			$max = max($max, (float)$v);
		}
		if($max <= 0){
			$max = 1.0;
		}

		$labelSpace = 8;
		$chartH = max(1.0, $h - $labelSpace);
		$gap = 1.0;
		$barW = ($w - ($count - 1) * $gap) / $count;

		$baseY = $y + $chartH;

		$this->SetDrawColor(180, 180, 180);
		$this->Line($x, $baseY, $x + $w, $baseY);

		$this->SetFillColor((int)$barRgb[0], (int)$barRgb[1], (int)$barRgb[2]);

		for($i = 0; $i < $count; $i++){
			$val = (float)($values[$i] ?? 0);
			$val = max(0.0, $val);
			$barH = ($val / $max) * ($chartH - 2);
			$bx = $x + $i * ($barW + $gap);
			$by = $baseY - $barH;
			$this->Rect($bx, $by, $barW, $barH, 'F');

			$label = (string)($labels[$i] ?? '');
			$this->SetFont('Arial', '', 6);
			$this->SetTextColor(70, 70, 70);
			$this->SetXY($bx, $baseY + 1);
			$this->Cell($barW, 3, $this->encode($label), 0, 0, 'C');
		}

		$this->SetTextColor(0, 0, 0);
	}

	public function drawHorizontalBars(
		float $x,
		float $y,
		float $w,
		array $labels,
		array $values,
		array $barRgb = [0, 209, 178]
	): float{
		$count = min(count($values), count($labels));
		if($count <= 0){
			return 0.0;
		}

		$max = 0.0;
		for($i = 0; $i < $count; $i++){
			$max = max($max, (float)($values[$i] ?? 0));
		}
		if($max <= 0){
			$max = 1.0;
		}

		$rowH = 6.0;
		$labelW = min(70.0, $w * 0.45);
		$barAreaW = $w - $labelW - 6.0;

		$this->SetFillColor((int)$barRgb[0], (int)$barRgb[1], (int)$barRgb[2]);
		$this->SetDrawColor(210, 210, 210);

		for($i = 0; $i < $count; $i++){
			$label = (string)($labels[$i] ?? '');
			$val = max(0.0, (float)($values[$i] ?? 0));
			$barW = ($val / $max) * $barAreaW;

			$ry = $y + $i * $rowH;

			$this->SetFont('Arial', '', 8);
			$this->SetTextColor(60, 60, 60);
			$this->SetXY($x, $ry);
			$this->Cell($labelW, $rowH, $this->encode($label), 0, 0, 'L');

			$this->SetXY($x + $labelW + 2, $ry + 1.5);
			$this->Rect($x + $labelW + 2, $ry + 1.5, $barW, $rowH - 3, 'F');

			$this->SetFont('Arial', '', 7);
			$this->SetTextColor(60, 60, 60);
			$this->SetXY($x + $labelW + 2 + $barAreaW + 2, $ry);
			$this->Cell(0, $rowH, (string)(int)round($val), 0, 0, 'L');
		}

		$this->SetTextColor(0, 0, 0);
		return $count * $rowH;
	}

	public function drawGroupedBarChart(
		float $x,
		float $y,
		float $w,
		float $h,
		array $labels,
		array $valuesA,
		array $valuesB,
		array $rgbA = [255, 221, 87],
		array $rgbB = [35, 209, 96],
		array $rgbNegB = [255, 56, 96]
	): void{
		$count = min(count($labels), count($valuesA), count($valuesB));
		if($count <= 0){
			return;
		}

		$maxVal = 0.0;
		$minVal = 0.0;
		for($i = 0; $i < $count; $i++){
			$a = (float)($valuesA[$i] ?? 0);
			$b = (float)($valuesB[$i] ?? 0);
			$maxVal = max($maxVal, $a, $b, 0.0);
			$minVal = min($minVal, $a, $b, 0.0);
		}
		$range = $maxVal - $minVal;
		if($range == 0.0){
			$range = 1.0;
		}

		$labelSpace = 10.0;
		$chartH = max(1.0, $h - $labelSpace);
		$baseY = $y + ($maxVal / $range) * $chartH;

		$this->SetDrawColor(180, 180, 180);
		$this->Line($x, $baseY, $x + $w, $baseY);

		$groupGap = 1.5;
		$groupW = ($w - ($count - 1) * $groupGap) / $count;
		$barGap = 1.0;
		$barW = ($groupW - $barGap) / 2.0;

		for($i = 0; $i < $count; $i++){
			$gx = $x + $i * ($groupW + $groupGap);

			$a = (float)($valuesA[$i] ?? 0);
			$b = (float)($valuesB[$i] ?? 0);

			$ha = ($a / $range) * $chartH;
			$hb = ($b / $range) * $chartH;

			// A (costo)
			$this->SetFillColor((int)$rgbA[0], (int)$rgbA[1], (int)$rgbA[2]);
			$ya = ($a >= 0) ? ($baseY - $ha) : $baseY;
			$this->Rect($gx, $ya, $barW, abs($ha), 'F');

			// B (ganancia) con manejo negativo
			if($b >= 0){
				$this->SetFillColor((int)$rgbB[0], (int)$rgbB[1], (int)$rgbB[2]);
				$yb = $baseY - $hb;
				$this->Rect($gx + $barW + $barGap, $yb, $barW, abs($hb), 'F');
			}else{
				$this->SetFillColor((int)$rgbNegB[0], (int)$rgbNegB[1], (int)$rgbNegB[2]);
				$yb = $baseY;
				$this->Rect($gx + $barW + $barGap, $yb, $barW, abs($hb), 'F');
			}

			$label = (string)($labels[$i] ?? '');
			$this->SetFont('Arial', '', 6);
			$this->SetTextColor(70, 70, 70);
			$this->SetXY($gx, $y + $chartH + 1);
			$this->Cell($groupW, 3, $this->encode($label), 0, 0, 'C');
		}

		$this->SetTextColor(0, 0, 0);
	}

	public function drawSimpleTable(float $x, float $y, array $headers, array $rows, array $widths): float{
		$this->SetXY($x, $y);
		$this->SetFont('Arial', 'B', 8);
		$this->SetFillColor(240, 240, 240);
		$this->SetDrawColor(210, 210, 210);

		$h = 6;
		for($i = 0; $i < count($headers); $i++){
			$w = (float)($widths[$i] ?? 20);
			$this->Cell($w, $h, $this->encode((string)($headers[$i] ?? '')), 1, 0, 'C', true);
		}
		$this->Ln($h);

		$this->SetFont('Arial', '', 8);
		$fill = false;
		foreach($rows as $row){
			$this->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255);
			for($i = 0; $i < count($headers); $i++){
				$w = (float)($widths[$i] ?? 20);
				$txt = (string)($row[$i] ?? '');
				$this->Cell($w, $h, $this->encode($txt), 1, 0, 'L', true);
			}
			$this->Ln($h);
			$fill = !$fill;
		}

		return ($h * (1 + count($rows)));
	}
}
