<?php
// db.php - Conexión de base de datos usando PDO

// Definir constante para evitar acceso directo a archivos de configuración
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// Cargar variables de entorno desde .env (obligatorio)
$env_path = dirname(__DIR__, 2) . '/.env';

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
} catch (PDOException $e) {
    // Si la base de datos no existe todavía, permitimos que continúe si estamos en install.php
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/plataforma');
        $base_path = ($pos !== false) ? (substr($script, 0, $pos) . '/plataforma') : '/plataforma';
        die("Error de conexión a la base de datos: " . $e->getMessage() . "<br><br>¿Ya configuraste el archivo .env o ejecutaste <a href='" . $base_path . "/install.php'>install.php</a>?");
    }
}
