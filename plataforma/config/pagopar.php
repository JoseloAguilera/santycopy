<?php
// pagopar.php - Configuración de entorno y claves de Pagopar

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// Entornos disponibles: 'development' o 'production'
// En 'development', la plataforma usará un simulador local sin conectar a la API real.
define('PAGOPAR_ENV', 'development');

// Credenciales de Pagopar (Se rellenan para producción)
define('PAGOPAR_PUBLIC_KEY', '');
define('PAGOPAR_PRIVATE_KEY', '');

// URLs base de Pagopar
define('PAGOPAR_API_URL', 'https://api.pagopar.com/api/comercios/2.0/iniciar-transaccion');
define('PAGOPAR_CHECKOUT_URL', 'https://checkout.pagopar.com/pagos/');
