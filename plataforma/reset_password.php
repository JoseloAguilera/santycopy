<?php
// reset_password.php - Restablecer la contraseña con token de validación

require_once 'includes/auth_helper.php';
require_once 'config/db.php';

$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$valid_token = false;
$email = '';

if (empty($token)) {
    $error = 'Token de recuperación no válido o inexistente.';
} else {
    try {
        // Validar token en la base de datos
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch();
        
        if ($reset_request) {
            $valid_token = true;
            $email = $reset_request['email'];
        } else {
            $error = 'El enlace de recuperación es inválido o ha expirado.';
        }
    } catch (PDOException $e) {
        $error = 'Error en el servidor: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Por favor ingresa la nueva contraseña.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            // Actualizar contraseña del usuario
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);
            
            // Eliminar token usado
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);
            
            $success = 'Tu contraseña ha sido actualizada con éxito.';
        } catch (PDOException $e) {
            $error = 'Error al actualizar contraseña: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Santy Copy LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="brand-logo" style="justify-content: center; margin-bottom: 20px;">
                Santy <span>Copy LMS</span>
            </div>
            
            <h2>Crear nueva contraseña</h2>
            <p class="subtitle">Ingresa y confirma tu nueva contraseña de acceso.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
                <div style="margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary btn-full">Iniciar Sesión</a>
                </div>
            <?php else: ?>
                <?php if ($valid_token): ?>
                    <form action="reset_password.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="token" value="<?php echo h($token); ?>">
                        
                        <div class="form-group">
                            <label class="form-label" for="password">Nueva Contraseña</label>
                            <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirmar Contraseña</label>
                            <input class="form-control" type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">Guardar Contraseña</button>
                    </form>
                <?php else: ?>
                    <div style="margin-top: 20px;">
                        <a href="forgot_password.php" class="btn btn-secondary">Solicitar nuevo enlace</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
