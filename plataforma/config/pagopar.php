<?php
// pagopar.php - Configuración de entorno y claves de Pagopar

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// Cargar credenciales desde variables de entorno (.env) con fallbacks
$pagopar_env = $_ENV['PAGOPAR_ENV'] ?? getenv('PAGOPAR_ENV') ?: 'development';
$pagopar_public = $_ENV['PAGOPAR_PUBLIC_KEY'] ?? getenv('PAGOPAR_PUBLIC_KEY') ?: '';
$pagopar_private = $_ENV['PAGOPAR_PRIVATE_KEY'] ?? getenv('PAGOPAR_PRIVATE_KEY') ?: '';

// Entornos disponibles: 'development' (simulador local) o 'production' (API real Pagopar)
define('PAGOPAR_ENV', $pagopar_env);

// Credenciales del comercio
define('PAGOPAR_PUBLIC_KEY', $pagopar_public);
define('PAGOPAR_PRIVATE_KEY', $pagopar_private);

// URLs base de la API Pagopar v2.0
define('PAGOPAR_API_URL', 'https://api.pagopar.com/api/comercios/2.0/iniciar-transaccion');
define('PAGOPAR_CHECKOUT_URL', 'https://checkout.pagopar.com/pagos/');
