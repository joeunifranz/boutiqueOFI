<?php

/*
 * Configuración de OAuth de Google para clientes.
 *
 * IMPORTANTE:
 * - Reemplaza los valores de GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET
 *   con las credenciales reales de tu proyecto en Google Cloud Console.
 * - Configura en Google la URL de redirección EXACTAMENTE igual a
 *   la constante GOOGLE_REDIRECT_URI_CLIENT.
 *
 * Ejemplo típico en local:
 *   http://localhost/BOUTIQUE/googleClienteCallback/
 */

const GOOGLE_CLIENT_ID = '242560302516-nqec2boiooect2gg4jla2oadvfuq5v9b.apps.googleusercontent.com';
const GOOGLE_CLIENT_SECRET = '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';

// URL pública completa donde Google devolverá al usuario después del login
// Debe apuntar a la vista: APP_URL . 'googleClienteCallback/'
const GOOGLE_REDIRECT_URI_CLIENT = 'http://localhost/BOUTIQUE/googleClienteCallback/';

