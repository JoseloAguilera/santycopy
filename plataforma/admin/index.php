<?php
// admin/index.php - Dashboard de Administración

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_admin();
$user = get_logged_user();

$page_title = 'Panel de Administración';
$active_tab = 'admin';

$error = '';
$success = '';

// Procesar Creación de Curso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = intval($_POST['price'] ?? 0);
    $payment_type = trim($_POST['payment_type'] ?? 'one_time');
    $subscription_period = intval($_POST['subscription_period'] ?? 30);
    
    // Procesar carga de imagen miniatura (thumbnail)
    $thumbnail_name = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['thumbnail']['tmp_name'];
        $file_name = $_FILES['thumbnail']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validar extensiones de imagen permitidas
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_exts)) {
            // Generar nombre de archivo seguro
            $thumbnail_name = 'thumb_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $upload_path = '../uploads/' . $thumbnail_name;
            
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $error = 'Error al subir la miniatura del curso.';
                $thumbnail_name = null;
            }
        } else {
            $error = 'Formato de imagen inválido. Solo JPG, PNG, WEBP.';
        }
    }
    
    if (empty($title)) {
        $error = 'El título del curso es obligatorio.';
    }
    
    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO courses (title, description, thumbnail, price, payment_type, subscription_period) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, 
                $description, 
                $thumbnail_name, 
                $price, 
                $payment_type, 
                ($payment_type === 'subscription' ? $subscription_period : null)
            ]);
            $success = 'Curso creado correctamente.';
        } catch (PDOException $e) {
            $error = 'Error al crear el curso: ' . $e->getMessage();
        }
    }
}

// Procesar Eliminación de Curso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_course') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $course_id = intval($_POST['course_id'] ?? 0);
    if ($course_id > 0) {
        try {
            // Eliminar imagen física
            $stmt = $pdo->prepare("SELECT thumbnail FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $c = $stmt->fetch();
            if ($c && !empty($c['thumbnail'])) {
                @unlink('../uploads/' . $c['thumbnail']);
            }
            
            // Eliminar de BD (las relaciones se borran por CASCADE)
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $success = 'Curso eliminado correctamente.';
        } catch (PDOException $e) {
            $error = 'Error al eliminar el curso: ' . $e->getMessage();
        }
    }
}

// Consultar Estadísticas
try {
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM orders WHERE status = 'paid'")->fetchColumn() ?: 0;
    
    // Obtener cursos con métricas asociadas
    $stmt = $pdo->query("
        SELECT c.*, 
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as total_enrolled
        FROM courses c 
        ORDER BY c.created_at DESC
    ");
    $courses = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error al consultar datos de administración: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="admin-grid">
    <div class="admin-sidebar">
        <h3 style="font-size: 15px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 15px; letter-spacing: 0.05em;">Menu Admin</h3>
        <nav class="admin-nav">
            <a href="index.php" class="active">📚 Cursos</a>
            <a href="students.php">👥 Alumnos</a>
            <a href="../index.php">🌐 Ver Catálogo</a>
        </nav>
    </div>
    
    <div class="admin-content">
        <h1 style="font-size: 26px; margin-bottom: 5px;">Panel de Control</h1>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Gestiona el contenido y accesos de Santy Copy Academy.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <!-- Tarjetas de Estadísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <span class="stat-label">Cursos Creados</span>
                <div class="stat-val"><?php echo $total_courses; ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Alumnos Registrados</span>
                <div class="stat-val"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Ingresos Totales</span>
                <div class="stat-val" style="color: var(--success);"><?php echo number_format($total_revenue, 0, ',', '.'); ?> ₲</div>
            </div>
        </div>
        
        <!-- Listado de Cursos y Formulario de Creación -->
        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 30px; margin-top: 30px;">
            <div>
                <h2 style="font-size: 18px; margin-bottom: 15px;">📚 Cursos en la Plataforma</h2>
                
                <div class="card table-responsive" style="padding: 10px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Videos</th>
                                <th>Alumnos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">No hay cursos creados todavía.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($courses as $c): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--heading-color);"><?php echo h($c['title']); ?></td>
                                        <td>
                                            <span style="font-size: 12px; font-weight: 500;">
                                                <?php echo ($c['payment_type'] === 'subscription') ? 'Suscripción' : 'Compra única'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($c['price'], 0, ',', '.'); ?> ₲</td>
                                        <td style="text-align: center;"><?php echo $c['total_lessons']; ?></td>
                                        <td style="text-align: center; font-weight: 600; color: var(--primary-color);"><?php echo $c['total_enrolled']; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <a href="course_edit.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm">Editar</a>
                                                <form action="index.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este curso? Se borrarán todos los accesos, videos y recursos asociados de forma permanente.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete_course">
                                                    <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Borrar</button>
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
            
            <div>
                <h2 style="font-size: 18px; margin-bottom: 15px;">➕ Nuevo Curso</h2>
                <div class="card">
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="create_course">
                        
                        <div class="form-group">
                            <label class="form-label" for="title">Título del Curso</label>
                            <input class="form-control" type="text" id="title" name="title" required placeholder="Ej: Copywriting de Cero a Pro">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="description">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Resumen del contenido..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="price">Precio (en Guaraníes)</label>
                            <input class="form-control" type="number" id="price" name="price" required min="0" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="payment_type">Tipo de Cobro</label>
                            <select class="form-control" id="payment_type" name="payment_type" onchange="toggleSubscriptionField(this.value)">
                                <option value="one_time">Compra Única</option>
                                <option value="subscription">Suscripción Recurrente</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="sub-period-group" style="display: none;">
                            <label class="form-label" for="subscription_period">Periodo de Suscripción (en días)</label>
                            <input class="form-control" type="number" id="subscription_period" name="subscription_period" min="1" value="30">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="thumbnail">Miniatura (Opcional)</label>
                            <input class="form-control" type="file" id="thumbnail" name="thumbnail" accept="image/*" style="padding: 6px;">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">Crear Curso</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSubscriptionField(val) {
    const group = document.getElementById('sub-period-group');
    if (val === 'subscription') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
    }
}
</script>

<?php
require_once '../includes/footer.php';
?>
