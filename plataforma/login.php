<?php
// login.php - Pantalla de inicio de sesión premium

require_once 'includes/auth_helper.php';
require_once 'config/db.php';

// Si ya está logueado, redirigir según su rol
if (is_logged_in()) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: student/index.php');
    }
    exit;
}

$error = '';
$success_msg = '';

if (isset($_GET['registered'])) {
    $success_msg = 'Registro completado con éxito. Inicia sesión.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Regenerar id de sesión para seguridad (Session Fixation Prevention)
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: admin/index.php');
                } else {
                    header('Location: student/index.php');
                }
                exit;
            } else {
                $error = 'Credenciales incorrectas.';
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Santy Copy LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="brand-logo" style="justify-content: center; margin-bottom: 20px;">
                Santy <span>Copy LMS</span>
            </div>
            
            <h2>¡Hola de nuevo!</h2>
            <p class="subtitle">Ingresa tus credenciales para acceder a tus cursos.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo h($success_msg); ?></div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Correo Electrónico</label>
                    <input class="form-control" type="email" id="email" name="email" required placeholder="correo@ejemplo.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                
                <div style="text-align: right; margin-bottom: 20px;">
                    <a href="forgot_password.php" style="font-size: 13px;">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Entrar a la Plataforma</button>
            </form>
            
            <div class="auth-footer">
                ¿No tienes cuenta? <a href="register.php">Regístrate gratis</a>
            </div>
        </div>
    </div>
</body>
</html>
