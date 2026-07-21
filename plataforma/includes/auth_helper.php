<?php
// auth_helper.php - Funciones compartidas de autenticación, seguridad y sesión

// Iniciar sesión con cookies seguras
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Solo si está bajo HTTPS (en localhost usualmente es http)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Evitar acceso directo a archivos si es necesario
define('ACCESS_ALLOWED', true);

// Verificar si el usuario está autenticado
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Obtener datos del usuario logueado
function get_logged_user() {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}

// Forzar inicio de sesión
function require_login() {
    if (!is_logged_in()) {
        header('Location: /santycopy/plataforma/login.php');
        exit;
    }
}

// Forzar rol de Administrador
function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: /santycopy/plataforma/student/index.php');
        exit;
    }
}

// Forzar rol de Estudiante
function require_student() {
    require_login();
    if ($_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'admin') {
        header('Location: /santycopy/plataforma/login.php');
        exit;
    }
}

// Sanitizar salidas para evitar XSS
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Generar un token CSRF
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validar token CSRF
function validate_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Validación de seguridad CSRF fallida.");
    }
    return true;
}
