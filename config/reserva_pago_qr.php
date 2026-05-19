<?php

// Pago de reserva con QR ESTÁTICO + comprobante
// - RESERVA_PAGO_QR_DATA: contenido que codifica el QR (texto/URL/código del banco).
// - Si prefieres una imagen ya lista (URL o data URI), puedes usar RESERVA_PAGO_QR_IMAGE.

// Ejemplo: tu texto/código QR del banco, o un enlace.
const RESERVA_PAGO_QR_DATA = 'CAMBIAME_POR_TU_QR_ESTATICO';

// Opcional: URL o data URI (data:image/png;base64,...) de un QR ya generado.
// Si se define y no está vacío, se usará en vez de generar con QRserver.
// También puedes usar una ruta relativa pública, por ejemplo: 'app/views/img/qr_reserva.png'
const RESERVA_PAGO_QR_IMAGE = 'app/views/img/qr_reserva.jpg';

// Límite de tamaño para comprobante (MB)
const RESERVA_COMPROBANTE_MAX_MB = 10;
