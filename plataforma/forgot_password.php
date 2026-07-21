<?php
// forgot_password.php - Recuperación de contraseñas

require_once 'includes/auth_helper.php';
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor ingresa tu correo electrónico.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } else {
        try {
            // Verificar si el correo existe
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Crear un token único
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar en la base de datos
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires_at]);
                
                // Enlace de restablecimiento
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/santycopy/plataforma/reset_password.php?token=" . $token;
                
                // Enviar "email" simulado guardándolo en un archivo log local
                $log_content = "[" . date('Y-m-d H:i:s') . "] Enlace de recuperación para " . $email . " (" . $user['name'] . "): \n" . $reset_link . "\n----------------------------------------\n";
                file_put_contents('password_resets_log.txt', $log_content, FILE_APPEND);
                
                // Enviar email real (esto puede fallar si localmente no hay SMTP configurado, pero lo ejecutamos silenciosamente con @)
                $subject = "Restablece tu contraseña - Santy Copy LMS";
                $headers = "From: no-reply@santycopy.com\r\nReply-To: no-reply@santycopy.com\r\nContent-Type: text/plain; charset=UTF-8";
                $body = "Hola " . $user['name'] . ",\n\nHemos recibido una solicitud para restablecer tu contraseña. Haz clic en el siguiente enlace para continuar:\n\n" . $reset_link . "\n\nSi no fuiste tú, puedes ignorar este correo.\nEste enlace expirará en 1 hora.";
                @mail($email, $subject, $body, $headers);
                
                $success = 'Se han enviado las instrucciones de recuperación a tu correo electrónico.';
            } else {
                // Por seguridad, no revelamos si el email no existe, mostramos el mismo mensaje
                $success = 'Se han enviado las instrucciones de recuperación a tu correo electrónico.';
            }
        } catch (PDOException $e) {
            $error = 'Error en el servidor: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Santy Copy LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="brand-logo" style="justify-content: center; margin-bottom: 20px;">
                Santy <span>Copy LMS</span>
            </div>
            
            <h2>¿Olvidaste tu contraseña?</h2>
            <p class="subtitle">Ingresa tu correo electrónico registrado y te enviaremos instrucciones de recuperación.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo h($success); ?>
                </div>
                <div class="info-box" style="margin-top: 15px; font-size: 13px; text-align: center; border-color: var(--primary-color);">
                    💡 <strong>Tip de Desarrollo:</strong> Abre el archivo <code>plataforma/password_resets_log.txt</code> para acceder al enlace de recuperación generado al instante.
                </div>
                <div style="margin-top: 20px;">
                    <a href="login.php" class="btn btn-secondary">Regresar al Login</a>
                </div>
            <?php else: ?>
                <form action="forgot_password.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Correo Electrónico</label>
                        <input class="form-control" type="email" id="email" name="email" required placeholder="correo@ejemplo.com">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">Enviar Enlace de Recuperación</button>
                </form>
                
                <div class="auth-footer">
                    <a href="login.php">Volver al inicio de sesión</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
