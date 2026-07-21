<?php
// admin/students.php - Gestión global de Alumnos

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_admin();
$user = get_logged_user();

$page_title = 'Gestionar Alumnos';
$active_tab = 'admin';

$error = '';
$success = '';

// ----------------------------------------------------
// PROCESAMIENTO DE ACCIONES POST
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    // 1. Crear nuevo alumno
    if ($action === 'create_student') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Por favor completa todos los campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            try {
                // Verificar si existe el email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'Este correo electrónico ya está registrado.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
                    $stmt->execute([$name, $email, $hashed_password]);
                    $success = 'Alumno creado con éxito.';
                }
            } catch (PDOException $e) {
                $error = 'Error al registrar al alumno: ' . $e->getMessage();
            }
        }
    }
    
    // 2. Cambiar contraseña de un alumno
    elseif ($action === 'change_password') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        
        if ($student_id > 0 && !empty($new_password)) {
            if (strlen($new_password) < 6) {
                $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
                    $stmt->execute([$hashed_password, $student_id]);
                    $success = 'Contraseña actualizada correctamente.';
                } catch (PDOException $e) {
                    $error = 'Error de base de datos al cambiar la contraseña: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Datos incompletos para restablecer contraseña.';
        }
    }
    
    // 3. Eliminar alumno
    elseif ($action === 'delete_student') {
        $student_id = intval($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
                $stmt->execute([$student_id]);
                $success = 'Alumno eliminado de la plataforma correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al eliminar al alumno: ' . $e->getMessage();
            }
        }
    }
}

// ----------------------------------------------------
// CARGA DE DATOS PARA RENDERIZADO
// ----------------------------------------------------
try {
    // Obtener todos los alumnos inscriptos y sus cantidades de cursos
    $stmt = $pdo->query("
        SELECT u.*, 
            (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id) as total_courses_enrolled
        FROM users u 
        WHERE u.role = 'student' 
        ORDER BY u.name ASC
    ");
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error cargando alumnos: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="admin-grid">
    <div class="admin-sidebar">
        <h3 style="font-size: 15px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 15px;">Menu Admin</h3>
        <nav class="admin-nav">
            <a href="index.php">📚 Cursos</a>
            <a href="students.php" class="active">👥 Alumnos</a>
            <a href="../index.php">🌐 Ver Catálogo</a>
        </nav>
        
        <div style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px; text-align: center;">
            <a href="index.php" class="btn btn-secondary btn-sm" style="width: 100%;">⬅️ Volver al Panel</a>
        </div>
    </div>
    
    <div class="admin-content">
        <h1 style="font-size: 26px; margin-bottom: 5px;">Gestión de Alumnos</h1>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Crea cuentas de alumnos, restablece contraseñas y visualiza las inscripciones activas.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 30px;">
            <!-- Listado General de Alumnos -->
            <div>
                <h2 style="font-size: 18px; margin-bottom: 15px;">👥 Alumnos Registrados</h2>
                
                <div class="card table-responsive" style="padding: 10px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Registro</th>
                                <th style="text-align: center;">Cursos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No hay alumnos registrados en la plataforma.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $st): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--heading-color);"><?php echo h($st['name']); ?></td>
                                        <td><?php echo h($st['email']); ?></td>
                                        <td style="font-size: 12px;"><?php echo date('d/m/Y', strtotime($st['created_at'])); ?></td>
                                        <td style="text-align: center; font-weight: bold; color: var(--primary-color);">
                                            <?php echo $st['total_courses_enrolled']; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <!-- Botón para modal/formulario rápido de contraseña -->
                                                <button onclick="promptPassword(<?php echo $st['id']; ?>, '<?php echo h(addslashes($st['name'])); ?>')" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;">Contraseña</button>
                                                
                                                <form action="students.php" method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar a este estudiante? Se borrarán sus accesos y su progreso de clases de forma permanente.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete_student">
                                                    <input type="hidden" name="student_id" value="<?php echo $st['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 11px;">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Crear Alumno Form -->
            <div>
                <h2 style="font-size: 18px; margin-bottom: 15px;">➕ Registrar Alumno</h2>
                <div class="card">
                    <form action="students.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="create_student">
                        
                        <div class="form-group">
                            <label class="form-label" for="name">Nombre Completo</label>
                            <input class="form-control" type="text" id="name" name="name" required placeholder="Ej: Pedro González">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Correo Electrónico</label>
                            <input class="form-control" type="email" id="email" name="email" required placeholder="correo@ejemplo.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="password">Contraseña Temporal</label>
                            <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••" minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">Crear Cuenta de Alumno</button>
                    </form>
                </div>
                
                <!-- Formulario invisible para actualización de contraseña mediante JavaScript Prompt -->
                <form id="pw-form" action="students.php" method="POST" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="student_id" id="pw-student-id">
                    <input type="hidden" name="new_password" id="pw-new-password">
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function promptPassword(id, name) {
    const newPassword = prompt("Ingresa la nueva contraseña para el estudiante: " + name + " (Mínimo 6 caracteres)");
    if (newPassword === null) return; // cancelado
    
    if (newPassword.trim().length < 6) {
        alert("La contraseña debe tener al menos 6 caracteres.");
        return;
    }
    
    document.getElementById('pw-student-id').value = id;
    document.getElementById('pw-new-password').value = newPassword.trim();
    document.getElementById('pw-form').submit();
}
</script>

<?php
require_once '../includes/footer.php';
?>
