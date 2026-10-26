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
    <link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: 2; ?>">
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
                    <div class="password-input-wrapper" style="position: relative; display: flex; align-items: center; width: 100%;">
                        <input class="form-control" type="password" id="password" name="password" required placeholder="Ingresa tu contraseña" style="padding-right: 48px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Mostrar contraseña" aria-label="Mostrar contraseña" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; padding: 8px; cursor: pointer; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; z-index: 5; border-radius: 6px;">
                            <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block; pointer-events: none;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none; pointer-events: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>
                
                <div style="text-align: right; margin-bottom: 20px;">
                    <a href="forgot_password.php" style="font-size: 13px;">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Entrar a la Plataforma</button>
            </form>
            
            <div class="auth-footer">
                ¿No tienes cuenta? <a href="register.php">Regístrate gratis</a>
            </div>

            <?php if (is_dev_env()): ?>
            <!-- Caja de Credenciales de Prueba (Solo visible en entorno de desarrollo/local) -->
            <div style="margin-top: 25px; padding: 15px; background: rgba(255, 255, 255, 0.02); border: 1px dashed var(--border-color); border-radius: 8px; text-align: left; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">
                    <strong style="color: var(--heading-color);">🔑 Credenciales de Prueba:</strong>
                    <span style="font-size: 10px; background: rgba(245, 158, 11, 0.15); color: var(--warning); padding: 2px 6px; border-radius: 4px; font-weight: 600;">Modo Desarrollo</span>
                </div>
                <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                    <span><span style="color: #a78bfa; font-weight: 600;">Admin:</span> <code>admin@santycopy.com</code></span>
                    <span><code>admin123</code></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span><span style="color: var(--primary-color); font-weight: 600;">Alumno:</span> <code>alumno@santycopy.com</code></span>
                    <span><code>alumno123</code></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        
        btn.classList.toggle('is-visible', isPassword);
        const eyeOpen = btn.querySelector('.eye-open');
        const eyeClosed = btn.querySelector('.eye-closed');
        if (eyeOpen && eyeClosed) {
            eyeOpen.style.setProperty('display', isPassword ? 'none' : 'block', 'important');
            eyeClosed.style.setProperty('display', isPassword ? 'block' : 'none', 'important');
        }
        btn.setAttribute('title', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
        btn.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
    }
    </script>
</body>
</html>
