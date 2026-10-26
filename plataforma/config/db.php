<?php
// db.php - Conexión de base de datos usando PDO

// Definir constante para evitar acceso directo a archivos de configuración
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// Cargar variables de entorno desde .env (obligatorio)
$env_path = dirname(__DIR__, 2) . '/.env';
if (!file_exists($env_path)) {
    $alt_path = dirname(__DIR__) . '/.env';
    if (file_exists($alt_path)) {
        $env_path = $alt_path;
    }
}

if (!file_exists($env_path)) {
    header('HTTP/1.1 500 Internal Server Error');
    die("<h3>Error de Configuración</h3><p>No se encontró el archivo <strong>.env</strong> en la raíz del proyecto. El archivo <strong>.env</strong> es obligatorio para conectar a la base de datos.</p><p>Por favor, crea el archivo <code>.env</code> a partir de <code>.env.example</code> con la configuración de tu base de datos.</p>");
}

$lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    // Ignorar comentarios
    if (strpos(trim($line), '#') === 0) {
        continue;
    }
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, '"\''); // Remover comillas
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// Definir constante de entorno de la aplicación ('development' o 'production')
if (!defined('APP_ENV')) {
    $detected_env = strtolower(trim($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));
    define('APP_ENV', $detected_env);
}

// Configurar reporte de errores según el entorno
if (in_array(APP_ENV, ['dev', 'development', 'local', 'test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Validar que las credenciales obligatorias de base de datos estén presentes en el .env
if (!isset($_ENV['DB_HOST']) || !isset($_ENV['DB_NAME']) || !isset($_ENV['DB_USER'])) {
    header('HTTP/1.1 500 Internal Server Error');
    die("<h3>Error de Configuración</h3><p>El archivo <strong>.env</strong> debe definir las variables <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> y <code>DB_PASS</code>.</p>");
}

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Auto-migración silenciosa de columnas y tablas de la plataforma
    try {
        // Columna audio_file en lessons
        $checkCol = $pdo->query("SHOW COLUMNS FROM `lessons` LIKE 'audio_file'");
        if ($checkCol && $checkCol->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `audio_file` VARCHAR(255) DEFAULT NULL AFTER `video_url`");
        }

        // Columnas promo_price y promo_duration_minutes en courses
        $checkPromoPrice = $pdo->query("SHOW COLUMNS FROM `courses` LIKE 'promo_price'");
        if ($checkPromoPrice && $checkPromoPrice->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `courses` ADD COLUMN `promo_price` INT DEFAULT NULL AFTER `price`");
            $pdo->exec("ALTER TABLE `courses` ADD COLUMN `promo_duration_minutes` INT DEFAULT 15 AFTER `promo_price`");
            // Asignar precio en oferta de prueba al curso 1 (ej: 65.000 ₲)
            $pdo->exec("UPDATE `courses` SET `promo_price` = 65000, `promo_duration_minutes` = 15 WHERE `id` = 1");
        }

        // Columna is_bonus en lessons
        $checkLessonBonus = $pdo->query("SHOW COLUMNS FROM `lessons` LIKE 'is_bonus'");
        if ($checkLessonBonus && $checkLessonBonus->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `is_bonus` TINYINT(1) DEFAULT 0 AFTER `order_number`");
        }

        // Columna is_bonus en resources
        $checkResourceBonus = $pdo->query("SHOW COLUMNS FROM `resources` LIKE 'is_bonus'");
        if ($checkResourceBonus && $checkResourceBonus->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `resources` ADD COLUMN `is_bonus` TINYINT(1) DEFAULT 0 AFTER `file_path`");
        }

        // Tabla promo_sessions para control de tiempo de ofertas por usuario/visitante
        $pdo->exec("CREATE TABLE IF NOT EXISTS `promo_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT NOT NULL,
            `visitor_key` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `visitor_course` (`visitor_key`, `course_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    } catch (Exception $e) {
        // Ignorar errores si la base de datos o tablas aún no existen
    }
} catch (PDOException $e) {
    // Si la base de datos no existe todavía, permitimos que continúe si estamos en install.php
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/plataforma');
        $base_path = ($pos !== false) ? (substr($script, 0, $pos) . '/plataforma') : '/plataforma';
        die("Error de conexión a la base de datos: " . $e->getMessage() . "<br><br>¿Ya configuraste el archivo .env o ejecutaste <a href='" . $base_path . "/install.php'>install.php</a>?");
    }
}
