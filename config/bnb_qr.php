<?php

// Configuración Banco BNB - API QR (Sandbox/Producción)
// Completa estas constantes con los datos oficiales que te entregue BNB.

// Base URL del API / gateway que consumirá tu servidor
// En sandbox del portal suele funcionar como: https://www.bnb.com.bo/PortalBNB/Api
const BNB_API_BASE_URL = 'https://API_BASE_URL_DE_BNB';

// Endpoints (paths relativos a BNB_API_BASE_URL)
// Nota: en el portal Sandbox se usan estos endpoints intermediarios.
const BNB_ENDPOINT_TOKEN = '/ConsumirServicioQRToken';
const BNB_ENDPOINT_CONSUMIR_QR = '/ConsumirServicioQR';

// Credenciales del comercio (BNB)
// En sandbox la obtención del token usa accountId + authorizationId.
const BNB_ACCOUNT_ID = 'TU_ACCOUNT_ID';
const BNB_AUTHORIZATION_ID = 'TU_AUTHORIZATION_ID';

// Moneda
const BNB_CURRENCY_ID = 'BOB';

// Si es true: cuando el estado del QR sea aprobado, la reserva se CONFIRMA automáticamente
const BNB_AUTO_CONFIRM_RESERVA = true;

// Usuario que quedará registrado como aprobador cuando se auto-confirme
const BNB_AUTO_CONFIRM_USUARIO_ID = 1;

// Estados que consideramos "pago aprobado" (ajustar según documentación real)
const BNB_APPROVED_STATUSES = ['paid','approved','completed','success'];

// Intervalo sugerido (en segundos) para verificación por cron/polling
const BNB_POLL_SECONDS = 60;
