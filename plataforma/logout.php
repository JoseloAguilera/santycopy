<?php
// logout.php - Cerrar sesión

require_once 'includes/auth_helper.php';

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: /santycopy/plataforma/login.php');
exit;
