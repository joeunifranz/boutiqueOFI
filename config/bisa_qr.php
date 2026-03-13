<?php

// Configuración Banco BISA - Cobranza en línea (QR dinámico)
// IMPORTANTE: BISA normalmente entrega endpoints + formato de payload cuando activan el servicio para tu cuenta comercial.
// Completa estas constantes con los datos oficiales que te entregue BISA.

// Base URL del API (ej: https://api.bisa.com.bo/...) - placeholder
const BISA_API_BASE_URL = 'https://API_BASE_URL_DE_BISA';

// API Key del comercio (NO la publiques)
const BISA_API_KEY = 'TU_BISA_API_KEY';

// Endpoints (paths relativos a BISA_API_BASE_URL) - placeholders
const BISA_ENDPOINT_CREATE_QR = '/qr/dinamico';
const BISA_ENDPOINT_GET_STATUS = '/qr/estado';

// Token simple para proteger tu webhook
const BISA_WEBHOOK_TOKEN = 'CAMBIAME_POR_UN_TOKEN_LARGO';

// Moneda (por defecto BOB)
const BISA_CURRENCY_ID = 'BOB';

// Si es true: cuando el banco notifique un pago en estado "aprobado", la reserva se CONFIRMA automáticamente
const BISA_AUTO_CONFIRM_RESERVA = true;

// Usuario que quedará registrado como aprobador cuando se auto-confirme (por defecto 1)
const BISA_AUTO_CONFIRM_USUARIO_ID = 1;

// Lista de estados que consideramos "pago aprobado" (ajustar según documentación real)
const BISA_APPROVED_STATUSES = ['paid','approved','completed','success'];
