<?php

	const APP_URL="http://localhost/BOUTIQUE/";
	const APP_NAME="BOUTIQUE";
	const APP_SESSION_NAME="POS";

	/*----------  Tipos de documentos  ----------*/
	const DOCUMENTOS_USUARIOS=["Cedula","Licencia","Pasaporte","Otro"];


	/*----------  Tipos de unidades de productos  ----------*/
	const PRODUCTO_UNIDAD=["Unidad","Otro"];

	/*----------  Configuración de moneda  ----------*/
	const MONEDA_SIMBOLO="Bs";
	const MONEDA_NOMBRE="Bolivianos";
	const MONEDA_DECIMALES="2";
	const MONEDA_SEPARADOR_MILLAR=",";
	const MONEDA_SEPARADOR_DECIMAL=".";


	/*----------  Marcador de campos obligatorios (Font Awesome) ----------*/
	const CAMPO_OBLIGATORIO='&nbsp; <i class="fas fa-edit"></i> &nbsp;';

	/*----------  Zona horaria  ----------*/
	date_default_timezone_set("America/La_Paz");

