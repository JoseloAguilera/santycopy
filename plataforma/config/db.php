<?php
// db.php - Conexión de base de datos usando PDO

// Definir constante para evitar acceso directo a archivos de configuración
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// Cargar variables de entorno desde .env si existe
$env_path = dirname(__DIR__, 2) . '/.env';
if (file_exists($env_path)) {
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
}

// Configuración de base de datos con variables de entorno o valores por defecto
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_NAME'] ?? 'santycopy_cursos';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la base de datos no existe todavía, permitimos que continúe si estamos en install.php
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/plataforma');
        $base_path = ($pos !== false) ? (substr($script, 0, $pos) . '/plataforma') : '/plataforma';
        die("Error de conexión a la base de datos: " . $e->getMessage() . "<br><br>¿Ya configuraste el archivo .env o ejecutaste <a href='" . $base_path . "/install.php'>install.php</a>?");
    }
}
