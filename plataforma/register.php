<?php
// register.php - Registro público para estudiantes

require_once 'includes/auth_helper.php';
require_once 'config/db.php';

// Si ya está logueado, redirigir
if (is_logged_in()) {
    header('Location: student/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            // Verificar si el correo ya existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este correo electrónico ya está registrado.';
            } else {
                // Crear el usuario estudiante
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt->execute([$name, $email, $hashed_password]);
                
                // Obtener ID del usuario recién creado
                $user_id = $pdo->lastInsertId();
                
                // Iniciar sesión automáticamente
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'student';
                
                // Redirigir al catálogo de cursos
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error al registrar el usuario: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Santy Copy LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="card auth-card">
            <div class="brand-logo" style="justify-content: center; margin-bottom: 20px;">
                Santy <span>Copy LMS</span>
            </div>
            
            <h2>Crea tu cuenta gratis</h2>
            <p class="subtitle">Regístrate para acceder al catálogo y empezar a estudiar.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <form action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="name">Nombre Completo</label>
                    <input class="form-control" type="text" id="name" name="name" required placeholder="Juan Pérez">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Correo Electrónico</label>
                    <input class="form-control" type="email" id="email" name="email" required placeholder="correo@ejemplo.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Contraseña (Mínimo 6 caracteres)</label>
                    <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirmar Contraseña</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">Crear mi Cuenta</button>
            </form>
            
            <div class="auth-footer">
                ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a>
            </div>
        </div>
    </div>
</body>
</html>
