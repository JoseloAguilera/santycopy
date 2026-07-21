<?php
// admin/course_edit.php - Panel de edición y gestión de contenido de un curso

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_admin();
$user = get_logged_user();

$course_id = intval($_GET['id'] ?? 0);
if ($course_id <= 0) {
    header('Location: index.php');
    exit;
}

$page_title = 'Editar Curso';
$active_tab = 'admin';

$error = '';
$success = '';

// ----------------------------------------------------
// PROCESAMIENTO DE ACCIONES POST
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    // 1. Modificar datos básicos del curso
    if ($action === 'update_course') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = intval($_POST['price'] ?? 0);
        $payment_type = trim($_POST['payment_type'] ?? 'one_time');
        $subscription_period = intval($_POST['subscription_period'] ?? 30);
        
        if (empty($title)) {
            $error = 'El título del curso no puede estar vacío.';
        } else {
            try {
                // Gestionar subida de miniatura si se proporcionó una nueva
                $thumbnail_clause = "";
                $params = [$title, $description, $price, $payment_type, ($payment_type === 'subscription' ? $subscription_period : null)];
                
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['thumbnail']['tmp_name'];
                    $file_name = $_FILES['thumbnail']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_ext, $allowed_exts)) {
                        // Eliminar miniatura anterior si existe
                        $stmt = $pdo->prepare("SELECT thumbnail FROM courses WHERE id = ?");
                        $stmt->execute([$course_id]);
                        $old_thumb = $stmt->fetchColumn();
                        if ($old_thumb && file_exists('../uploads/' . $old_thumb)) {
                            @unlink('../uploads/' . $old_thumb);
                        }
                        
                        $thumbnail_name = 'thumb_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                        if (move_uploaded_file($file_tmp, '../uploads/' . $thumbnail_name)) {
                            $thumbnail_clause = ", thumbnail = ?";
                            $params[] = $thumbnail_name;
                        }
                    } else {
                        $error = 'Miniatura inválida. Solo JPG, PNG y WEBP son aceptados.';
                    }
                }
                
                if (empty($error)) {
                    $params[] = $course_id;
                    $sql = "UPDATE courses SET title = ?, description = ?, price = ?, payment_type = ?, subscription_period = ? $thumbnail_clause WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = 'Curso actualizado correctamente.';
                }
            } catch (PDOException $e) {
                $error = 'Error al actualizar el curso: ' . $e->getMessage();
            }
        }
    }
    
    // 2. Agregar lección / video
    elseif ($action === 'add_lesson') {
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $order_number = intval($_POST['order_number'] ?? 0);
        
        if (empty($lesson_title)) {
            $error = 'El título de la clase es obligatorio.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, video_url, order_number) VALUES (?, ?, ?, ?)");
                $stmt->execute([$course_id, $lesson_title, $video_url, $order_number]);
                $success = 'Clase agregada correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al agregar la clase: ' . $e->getMessage();
            }
        }
    }
    
    // 3. Eliminar lección / video
    elseif ($action === 'delete_lesson') {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        if ($lesson_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
                $stmt->execute([$lesson_id, $course_id]);
                $success = 'Clase eliminada correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al eliminar la clase: ' . $e->getMessage();
            }
        }
    }
    
    // 4. Subir recurso (PDF)
    elseif ($action === 'add_resource') {
        $resource_title = trim($_POST['resource_title'] ?? '');
        
        if (empty($resource_title)) {
            $error = 'El título del recurso es obligatorio.';
        } elseif (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Debes seleccionar un archivo PDF válido para subir.';
        } else {
            $file_tmp = $_FILES['resource_file']['tmp_name'];
            $file_name = $_FILES['resource_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validar extensiones (Medida de Seguridad: solo PDFs)
            if ($file_ext !== 'pdf') {
                $error = 'Seguridad: Solo se permite la subida de archivos con extensión .pdf';
            } else {
                // Validar Tipo MIME real (Seguridad)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);
                
                if ($mime !== 'application/pdf') {
                    $error = 'Seguridad: El tipo MIME del archivo no corresponde a un archivo PDF legítimo.';
                } else {
                    // Sanitizar nombre de archivo (evitar directory traversal o inyección)
                    $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resource_title);
                    $new_file_name = 'res_' . $course_id . '_' . time() . '_' . $clean_title . '.pdf';
                    $upload_path = '../uploads/' . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO resources (course_id, title, file_path) VALUES (?, ?, ?)");
                            $stmt->execute([$course_id, $resource_title, $new_file_name]);
                            $success = 'Recurso PDF subido correctamente.';
                        } catch (PDOException $e) {
                            @unlink($upload_path); // revertir subida en disco
                            $error = 'Error de BD al registrar el recurso: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'No se pudo mover el archivo al directorio de subidas. Verifica permisos de escritura.';
                    }
                }
            }
        }
    }
    
    // 5. Eliminar recurso (PDF)
    elseif ($action === 'delete_resource') {
        $resource_id = intval($_POST['resource_id'] ?? 0);
        if ($resource_id > 0) {
            try {
                // Obtener ruta del archivo en disco
                $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = ? AND course_id = ?");
                $stmt->execute([$resource_id, $course_id]);
                $file_path = $stmt->fetchColumn();
                
                if ($file_path) {
                    // Eliminar de base de datos
                    $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
                    $stmt->execute([$resource_id]);
                    
                    // Eliminar archivo físico
                    @unlink('../uploads/' . $file_path);
                    $success = 'Recurso eliminado correctamente.';
                }
            } catch (PDOException $e) {
                $error = 'Error al eliminar el recurso: ' . $e->getMessage();
            }
        }
    }
    
    // 6. Inscribir alumno manualmente (Dar acceso)
    elseif ($action === 'enroll_student') {
        $student_id = intval($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            try {
                // Obtener datos del curso para expiración si es suscripción
                $stmt = $pdo->prepare("SELECT payment_type, subscription_period FROM courses WHERE id = ?");
                $stmt->execute([$course_id]);
                $c_info = $stmt->fetch();
                
                $expires_at = null;
                if ($c_info && $c_info['payment_type'] === 'subscription') {
                    $period = intval($c_info['subscription_period'] ?: 30);
                    $expires_at = date('Y-m-d H:i:s', strtotime("+$period days"));
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO enrollments (user_id, course_id, expires_at) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE expires_at = ?
                ");
                $stmt->execute([$student_id, $course_id, $expires_at, $expires_at]);
                $success = 'Acceso concedido al alumno correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al inscribir al alumno: ' . $e->getMessage();
            }
        }
    }
    
    // 7. Revocar acceso (Eliminar de inscriptos)
    elseif ($action === 'revoke_enrollment') {
        $enrollment_id = intval($_POST['enrollment_id'] ?? 0);
        if ($enrollment_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ? AND course_id = ?");
                $stmt->execute([$enrollment_id, $course_id]);
                $success = 'Acceso revocado correctamente (alumno eliminado del curso).';
            } catch (PDOException $e) {
                $error = 'Error al revocar el acceso: ' . $e->getMessage();
            }
        }
    }
}

// ----------------------------------------------------
// CARGA DE DATOS PARA RENDERIZADO
// ----------------------------------------------------
try {
    // 1. Obtener datos del curso
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) {
        header('Location: index.php');
        exit;
    }
    
    // 2. Obtener lecciones/videos (ordenados)
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_number ASC, id ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
    
    // 3. Obtener recursos PDFs
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE course_id = ? ORDER BY created_at DESC");
    $stmt->execute([$course_id]);
    $resources = $stmt->fetchAll();
    
    // 4. Obtener alumnos inscriptos
    $stmt = $pdo->prepare("
        SELECT e.id as enrollment_id, e.expires_at, e.created_at as enrolled_at, u.id as user_id, u.name, u.email 
        FROM enrollments e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.course_id = ? AND u.role = 'student'
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$course_id]);
    $enrolled_students = $stmt->fetchAll();
    
    // 5. Obtener alumnos NO inscriptos para el dropdown de agregar manual
    $stmt = $pdo->prepare("
        SELECT id, name, email 
        FROM users 
        WHERE role = 'student' 
          AND id NOT IN (SELECT user_id FROM enrollments WHERE course_id = ?)
        ORDER BY name ASC
    ");
    $stmt->execute([$course_id]);
    $available_students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error cargando el curso para edición: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="admin-grid">
    <div class="admin-sidebar">
        <h3 style="font-size: 15px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 15px;">Menu Admin</h3>
        <nav class="admin-nav">
            <a href="index.php" class="active">📚 Cursos</a>
            <a href="students.php">👥 Alumnos</a>
            <a href="../index.php">🌐 Ver Catálogo</a>
        </nav>
        
        <div style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px; text-align: center;">
            <a href="index.php" class="btn btn-secondary btn-sm" style="width: 100%;">⬅️ Volver al Panel</a>
        </div>
    </div>
    
    <div class="admin-content">
        <h1 style="font-size: 26px; margin-bottom: 5px;">Editar: <?php echo h($course['title']); ?></h1>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Agrega videos en orden, sube recursos PDFs y gestiona el acceso de los alumnos.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <!-- Pestañas o secciones de edición -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
            <!-- 1. Datos del curso -->
            <div class="card">
                <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">✏️ Datos del Curso</h3>
                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_course">
                    
                    <div class="form-group">
                        <label class="form-label" for="title">Título del Curso</label>
                        <input class="form-control" type="text" id="title" name="title" required value="<?php echo h($course['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="description">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo h($course['description']); ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label" for="price">Precio (Guaraníes)</label>
                            <input class="form-control" type="number" id="price" name="price" required value="<?php echo $course['price']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="payment_type">Tipo de Cobro</label>
                            <select class="form-control" id="payment_type" name="payment_type" onchange="toggleSubscriptionFieldEdit(this.value)">
                                <option value="one_time" <?php echo ($course['payment_type'] === 'one_time') ? 'selected' : ''; ?>>Compra única</option>
                                <option value="subscription" <?php echo ($course['payment_type'] === 'subscription') ? 'selected' : ''; ?>>Suscripción</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" id="sub-period-group-edit" style="display: <?php echo ($course['payment_type'] === 'subscription') ? 'block' : 'none'; ?>;">
                        <label class="form-label" for="subscription_period">Periodo de Suscripción (en días)</label>
                        <input class="form-control" type="number" id="subscription_period" name="subscription_period" min="1" value="<?php echo $course['subscription_period'] ?: 30; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="thumbnail">Miniatura del Curso</label>
                        <?php if (!empty($course['thumbnail']) && file_exists('../uploads/' . $course['thumbnail'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../uploads/<?php echo h($course['thumbnail']); ?>" style="width: 120px; border-radius: 4px; border: 1px solid var(--border-color);" alt="Vista previa">
                            </div>
                        <?php endif; ?>
                        <input class="form-control" type="file" id="thumbnail" name="thumbnail" accept="image/*" style="padding: 6px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Guardar Cambios</button>
                </form>
            </div>
            
            <!-- 2. Recursos del curso -->
            <div class="card">
                <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">📂 Sección de Recursos (PDFs)</h3>
                
                <!-- Subir Recurso -->
                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" style="margin-bottom: 25px;">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_resource">
                    
                    <div class="form-group">
                        <label class="form-label" for="resource_title">Nombre del Recurso</label>
                        <input class="form-control" type="text" id="resource_title" name="resource_title" required placeholder="Ej: Guía de Fórmulas de Persuasión">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="resource_file">Archivo PDF</label>
                        <input class="form-control" type="file" id="resource_file" name="resource_file" accept=".pdf" required style="padding: 6px;">
                    </div>
                    
                    <button type="submit" class="btn btn-secondary btn-full">📤 Subir Recurso PDF</button>
                </form>
                
                <!-- Listado de Recursos -->
                <h4 style="font-size: 14px; margin-bottom: 10px;">Archivos Disponibles</h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if (empty($resources)): ?>
                        <p style="font-size: 13px; color: var(--text-muted); text-align: center;">No hay archivos PDFs subidos para este curso.</p>
                    <?php else: ?>
                        <?php foreach ($resources as $res): ?>
                            <div class="resource-file" style="justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; width: 70%;">
                                    <span class="resource-icon">📕</span>
                                    <div class="resource-meta">
                                        <span style="font-weight: 500; color: var(--heading-color);"><?php echo h($res['title']); ?></span>
                                        <a href="../uploads/<?php echo h($res['file_path']); ?>" target="_blank" style="font-size: 11px;">Abrir PDF</a>
                                    </div>
                                </div>
                                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" onsubmit="return confirm('¿Eliminar este PDF?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete_resource">
                                    <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 11px;">Borrar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sección de Videos (Clases) y Orden -->
        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 30px; margin-bottom: 30px;">
            <div class="card">
                <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">🎥 Clases / Videos del Curso</h3>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">Orden</th>
                                <th>Título de la Clase</th>
                                <th>Enlace de Video</th>
                                <th style="width: 80px; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lessons)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No hay clases agregadas. Agrega tu primer video a la derecha.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lessons as $index => $les): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600; color: var(--primary-color);">
                                            #<?php echo $les['order_number']; ?>
                                        </td>
                                        <td style="color: var(--heading-color); font-weight: 500;"><?php echo h($les['title']); ?></td>
                                        <td style="font-size: 12px; color: var(--text-muted); max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo h($les['video_url']); ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" onsubmit="return confirm('¿Seguro que quieres borrar esta clase?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete_lesson">
                                                <input type="hidden" name="lesson_id" value="<?php echo $les['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 11px;">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">➕ Agregar Clase (Video)</h3>
                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_lesson">
                    
                    <div class="form-group">
                        <label class="form-label" for="lesson_title">Título de la Clase</label>
                        <input class="form-control" type="text" id="lesson_title" name="lesson_title" required placeholder="Ej: Clase 1: El por qué de esta estrategia">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="video_url">Enlace del Video (YouTube / Vimeo / MP4)</label>
                        <input class="form-control" type="url" id="video_url" name="video_url" placeholder="https://www.youtube.com/embed/...">
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                            Pega un iframe embed link o enlace directo. Ejemplo: YouTube Embed o Vimeo Embed.
                        </span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="order_number">Número de Orden</label>
                        <input class="form-control" type="number" id="order_number" name="order_number" min="1" value="<?php echo empty($lessons) ? 1 : (max(array_column($lessons, 'order_number')) + 1); ?>">
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                            Define la posición en el orden del curso.
                        </span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Añadir Clase</button>
                </form>
            </div>
        </div>
        
        <!-- Gestión de Accesos (Alumnos Inscriptos) -->
        <div class="card">
            <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">👥 Alumnos Inscritos y Control de Acceso</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 340px; gap: 30px;">
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 10px;">Inscriptos Activos</h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Email</th>
                                    <th>Fecha de Inscripción</th>
                                    <th>Vencimiento de Acceso</th>
                                    <th style="width: 90px; text-align: center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($enrolled_students)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 15px;">Ningún alumno tiene acceso a este curso actualmente.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($enrolled_students as $est): ?>
                                        <tr>
                                            <td style="font-weight: 500; color: var(--heading-color);"><?php echo h($est['name']); ?></td>
                                            <td><?php echo h($est['email']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($est['enrolled_at'])); ?></td>
                                            <td>
                                                <?php if (empty($est['expires_at'])): ?>
                                                    <span style="color: var(--success); font-weight: 600;">Permanente</span>
                                                <?php else: ?>
                                                    <?php 
                                                    $exp = strtotime($est['expires_at']);
                                                    $is_expired = ($exp < time());
                                                    ?>
                                                    <span style="color: <?php echo $is_expired ? 'var(--danger)' : 'var(--warning)'; ?>;">
                                                        <?php echo date('d/m/Y H:i', $exp); ?> <?php echo $is_expired ? '(Expirado)' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" onsubmit="return confirm('¿Revocar acceso a este alumno? Ya no podrá ver las clases.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="revoke_enrollment">
                                                    <input type="hidden" name="enrollment_id" value="<?php echo $est['enrollment_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 11px;">Dar de Baja</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div>
                    <h4 style="font-size: 14px; margin-bottom: 10px;">🔑 Conceder Acceso Manual</h4>
                    <div class="card" style="background: rgba(255,255,255,0.01);">
                        <?php if (empty($available_students)): ?>
                            <p style="font-size: 13px; color: var(--text-muted); text-align: center; padding: 10px;">Todos los alumnos registrados ya tienen acceso a este curso.</p>
                        <?php else: ?>
                            <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="enroll_student">
                                
                                <div class="form-group">
                                    <label class="form-label" for="student_id">Seleccionar Alumno</label>
                                    <select class="form-control" name="student_id" id="student_id" required>
                                        <option value="">-- Elige un Alumno --</option>
                                        <?php foreach ($available_students as $as): ?>
                                            <option value="<?php echo $as['id']; ?>"><?php echo h($as['name']); ?> (<?php echo h($as['email']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-full">Dar Acceso</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSubscriptionFieldEdit(val) {
    const group = document.getElementById('sub-period-group-edit');
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
