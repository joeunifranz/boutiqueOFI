<?php

	namespace app\controllers;
	use app\models\mainModel;

	class logController extends mainModel{
		/*----------  Exportar logs a PDF  ----------*/
		public function exportarLogsPDF(){
			// Asegurar que no haya salida previa (headers)
			if(ob_get_length()){
				@ob_end_clean();
			}

			require_once __DIR__ . '/../pdf/LogsPDF.php';

			$datos = $this->ejecutarConsulta("SELECT * FROM log_acceso ORDER BY log_id DESC");
			$logs = $datos->fetchAll();

			$pdf = new \LogsPDF('L', 'mm', 'A4');
			$pdf->AliasNbPages();
			$pdf->SetMargins(10, 12, 10);
			$pdf->SetAutoPageBreak(true, 15);
			$pdf->titulo = iconv('UTF-8','ISO-8859-1//TRANSLIT', APP_NAME.' - Reporte de Logs');
			$pdf->subtitulo = iconv('UTF-8','ISO-8859-1//TRANSLIT', 'Generado: '.date('d/m/Y H:i:s').'  |  Total registros: '.count($logs));
			$pdf->AddPage();

			$toIso = function($txt){
				$txt = (string)$txt;
				$converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $txt);
				return ($converted!==false) ? $converted : $txt;
			};

			$widths = [12, 58, 32, 24, 18, 32, 101];
			$aligns = ['C','L','L','C','C','C','L'];

			$checkPageBreak = function($h) use ($pdf, $widths, $toIso){
				if($pdf->GetY()+$h > $pdf->getPageBreakTrigger()){
					$pdf->AddPage($pdf->getCurOrientation());
					// Encabezado de la tabla en la nueva página
					$pdf->SetFont('Arial','B',9);
					$pdf->SetFillColor(240,240,240);
					$pdf->Cell($widths[0],7,$toIso('ID'),1,0,'C',true);
					$pdf->Cell($widths[1],7,$toIso('Nombre'),1,0,'C',true);
					$pdf->Cell($widths[2],7,$toIso('Usuario'),1,0,'C',true);
					$pdf->Cell($widths[3],7,$toIso('Fecha'),1,0,'C',true);
					$pdf->Cell($widths[4],7,$toIso('Hora'),1,0,'C',true);
					$pdf->Cell($widths[5],7,$toIso('IP'),1,0,'C',true);
					$pdf->Cell($widths[6],7,$toIso('Acción'),1,1,'C',true);
					$pdf->SetFont('Arial','',8);
				}
			};

			$nbLines = function($w, $txt) use ($pdf){
				$txt = (string)$txt;
				$cw = $pdf->getCurrentFontCw();
				if($w==0){
					$w = $pdf->getDocW() - $pdf->getRMarginVal() - $pdf->GetX();
				}
				$wmax = ($w - 2*$pdf->getCMarginVal()) * 1000 / $pdf->getFontSizeVal();
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
			};

			$row = function($data, $fill) use ($pdf, $widths, $aligns, $nbLines, $checkPageBreak){
				$maxLines = 1;
				for($i=0; $i<count($data); $i++){
					$maxLines = max($maxLines, $nbLines($widths[$i], $data[$i]));
				}
				$lineHeight = 5;
				$h = $lineHeight * $maxLines;
				$checkPageBreak($h);
				for($i=0; $i<count($data); $i++){
					$w = $widths[$i];
					$a = $aligns[$i] ?? 'L';
					$x = $pdf->GetX();
					$y = $pdf->GetY();
					$pdf->Rect($x, $y, $w, $h);
					$pdf->MultiCell($w, $lineHeight, $data[$i], 0, $a, $fill);
					$pdf->SetXY($x + $w, $y);
				}
				$pdf->Ln($h);
			};

			// Encabezado de tabla
			$pdf->SetFont('Arial','B',9);
			$pdf->SetFillColor(240,240,240);
			$pdf->Cell($widths[0],7,$toIso('ID'),1,0,'C',true);
			$pdf->Cell($widths[1],7,$toIso('Nombre'),1,0,'C',true);
			$pdf->Cell($widths[2],7,$toIso('Usuario'),1,0,'C',true);
			$pdf->Cell($widths[3],7,$toIso('Fecha'),1,0,'C',true);
			$pdf->Cell($widths[4],7,$toIso('Hora'),1,0,'C',true);
			$pdf->Cell($widths[5],7,$toIso('IP'),1,0,'C',true);
			$pdf->Cell($widths[6],7,$toIso('Acción'),1,1,'C',true);

			$pdf->SetFont('Arial','',8);
			$fill = false;
			foreach($logs as $rowData){
				$accion = (isset($rowData['log_accion']) && $rowData['log_accion']!=='') ? $rowData['log_accion'] : 'Acceso';
				$accion = trim($accion);

				$pdf->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255);
				$row([
					$toIso($rowData['log_id'] ?? ''),
					$toIso($rowData['usuario_nombre'] ?? 'N/A'),
					$toIso($rowData['usuario_usuario'] ?? 'N/A'),
					$toIso($rowData['log_fecha'] ?? ''),
					$toIso($rowData['log_hora'] ?? ''),
					$toIso($rowData['log_ip'] ?? ''),
					$toIso($accion)
				], $fill);
				$fill = !$fill;
			}

			// Forzar descarga
			$pdf->Output('D', 'reporte_logs_acceso_'.date('Ymd').'.pdf');
			exit();
		}

		/* Compatibilidad: si algún lugar aún llama exportarLogsCSV, exporta PDF */
		public function exportarLogsCSV(){
			return $this->exportarLogsPDF();
		}

		/*----------  Controlador listar logs de acceso  ----------*/
		public function listarLogControlador($pagina,$registros,$url,$busqueda){

			$pagina=$this->limpiarCadena($pagina);
			$registros=$this->limpiarCadena($registros);
			$url=$this->limpiarCadena($url);
			$url=APP_URL.$url."/";
			$busqueda=$this->limpiarCadena($busqueda);
			$tabla="";

			$pagina = (isset($pagina) && $pagina>0) ? (int) $pagina : 1;
			$inicio = ($pagina>0) ? (($pagina * $registros)-$registros) : 0;

			if(isset($busqueda) && $busqueda!=""){

				$consulta_datos="SELECT * FROM log_acceso WHERE usuario_nombre LIKE '%$busqueda%' OR usuario_usuario LIKE '%$busqueda%' OR log_ip LIKE '%$busqueda%' ORDER BY log_id DESC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(log_id) FROM log_acceso WHERE usuario_nombre LIKE '%$busqueda%' OR usuario_usuario LIKE '%$busqueda%' OR log_ip LIKE '%$busqueda%'";

			}else{

				$consulta_datos="SELECT * FROM log_acceso ORDER BY log_id DESC LIMIT $inicio,$registros";

				$consulta_total="SELECT COUNT(log_id) FROM log_acceso";

			}

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();

			$total = $this->ejecutarConsulta($consulta_total);
			$total = (int) $total->fetchColumn();

			$numeroPaginas =ceil($total/$registros);

		    if($total>=1 && $pagina<=$numeroPaginas){
				$contador=$inicio+1;
				$pag_inicio=$inicio+1;
				$tabla.="\n<div class=\"table-container\">\n<table class=\"table is-fullwidth is-striped is-hoverable\">\n\t<thead>\n\t\t<tr>\n\t\t\t<th class=\"has-text-centered\" style=\"width:60px;\">#</th>\n\t\t\t<th class=\"has-text-centered\" style=\"width:80px;\">ID</th>\n\t\t\t<th>Nombre</th>\n\t\t\t<th>Usuario</th>\n\t\t\t<th class=\"has-text-centered\">Fecha</th>\n\t\t\t<th class=\"has-text-centered\">Hora</th>\n\t\t\t<th class=\"has-text-centered\">IP</th>\n\t\t\t<th>Acción</th>\n\t\t</tr>\n\t</thead>\n\t<tbody>\n";
				foreach($datos as $rows){
					$accionRaw = (isset($rows['log_accion']) && $rows['log_accion']!=='') ? $rows['log_accion'] : 'Acceso';
					$accion = htmlspecialchars($accionRaw, ENT_QUOTES, 'UTF-8');
					$usuarioNombre = htmlspecialchars($rows['usuario_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
					$usuarioUser = htmlspecialchars($rows['usuario_usuario'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
					$fecha = htmlspecialchars($rows['log_fecha'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
					$hora = htmlspecialchars($rows['log_hora'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
					$ip = htmlspecialchars($rows['log_ip'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
					$logId = htmlspecialchars($rows['log_id'] ?? '', ENT_QUOTES, 'UTF-8');

					$tagClass = 'is-link';
					$tagTexto = 'ACCESO';
					if(stripos($accionRaw,'Elimin')!==false){
						$tagClass = 'is-danger';
						$tagTexto = 'ELIMINÓ';
					}elseif(stripos($accionRaw,'Alta')!==false){
						$tagClass = 'is-success';
						$tagTexto = 'ALTA';
					}elseif(stripos($accionRaw,'Modific')!==false || stripos($accionRaw,'Actualiz')!==false){
						$tagClass = 'is-warning';
						$tagTexto = 'MODIFICÓ';
					}elseif(stripos($accionRaw,'Inicio')!==false || stripos($accionRaw,'Acceso')!==false){
						$tagClass = 'is-info';
						$tagTexto = (stripos($accionRaw,'Inicio')!==false) ? 'LOGIN' : 'ACCESO';
					}
					$tagTexto = htmlspecialchars($tagTexto, ENT_QUOTES, 'UTF-8');

					$tabla.="\n\t\t<tr>\n\t\t\t<td class=\"has-text-centered\">".$contador."</td>\n\t\t\t<td class=\"has-text-centered\">".$logId."</td>\n\t\t\t<td>".$usuarioNombre."</td>\n\t\t\t<td>".$usuarioUser."</td>\n\t\t\t<td class=\"has-text-centered\">".$fecha."</td>\n\t\t\t<td class=\"has-text-centered\">".$hora."</td>\n\t\t\t<td class=\"has-text-centered\">".$ip."</td>\n\t\t\t<td><span class=\"tag ".$tagClass." is-light\">".$tagTexto."</span> &nbsp; <span class=\"is-size-7\">".$accion."</span></td>\n\t\t</tr>\n";
					$contador++;
				}
				$tabla.="\t</tbody>\n</table>\n</div>\n";
				$pag_final=$contador-1;
			}else{
				if($total>=1){
					$tabla.='
						<p class="has-text-centered pb-6"><i class="far fa-hand-point-down fa-5x"></i></p>
			            <p class="has-text-centered">
			                <a href="'.$url.'1/" class="button is-link is-rounded is-small mt-4 mb-4">
			                    Haga clic acá para recargar el listado
			                </a>
			            </p>
					';
				}else{
					$tabla.='
						<p class="has-text-centered pb-6"><i class="far fa-grin-beam-sweat fa-5x"></i></p>
						<p class="has-text-centered">No hay logs de acceso registrados</p>
					';
				}
			}

			### Paginacion ###
			if($total>0 && $pagina<=$numeroPaginas){
				$tabla.='<p class="has-text-right">Mostrando logs <strong>'.$pag_inicio.'</strong> al <strong>'.$pag_final.'</strong> de un <strong>total de '.$total.'</strong></p>';

				$tabla.=$this->paginadorTablas($pagina,$numeroPaginas,$url,7);
			}

			return $tabla;
		}

	}

