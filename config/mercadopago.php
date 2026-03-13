<?php

// Configuración Mercado Pago (Checkout Pro)
// 1) Crea una APP en Mercado Pago y copia tu Access Token (producción o test)
// 2) Configura el webhook apuntando a: APP_URL."mercadopagoWebhook/?token=".MP_WEBHOOK_TOKEN

// Access Token (NO lo publiques)
const MP_ACCESS_TOKEN = 'TU_MP_ACCESS_TOKEN';

// Token simple para proteger tu endpoint de webhook (usa un valor largo/aleatorio)
const MP_WEBHOOK_TOKEN = 'CAMBIAME_POR_UN_TOKEN_LARGO';

// Moneda. Para Bolivia normalmente: BOB
const MP_CURRENCY_ID = 'BOB';

// Si es true: cuando Mercado Pago confirme (approved), la reserva se CONFIRMA automáticamente
// Si es false: solo se registra el pago en `reserva_pago` y el admin confirma después.
const MP_AUTO_CONFIRM_RESERVA = true;

// Usuario que quedará registrado como aprobador cuando se auto-confirme (por defecto 1)
const MP_AUTO_CONFIRM_USUARIO_ID = 1;
