<?php
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../autoload.php';
use app\controllers\logController;

$log = new logController();
$log->exportarLogsPDF();
