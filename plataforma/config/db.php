<?php
// db.php - Conexión de base de datos usando PDO

// Definir constante para evitar acceso directo a archivos de configuración
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

$host = '127.0.0.1';
$dbname = 'santycopy_cursos';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la base de datos no existe todavía, permitimos que continúe si estamos en install.php
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        die("Error de conexión a la base de datos: " . $e->getMessage() . "<br><br>¿Ya ejecutaste <a href='/santycopy/plataforma/install.php'>install.php</a>?");
    }
}
