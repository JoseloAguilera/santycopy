<?php
// student/course_view.php - Reproductor de Cursos (LMS Player)

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_login();
$user = get_logged_user();

$course_id = intval($_GET['course_id'] ?? 0);
$active_lesson_id = intval($_GET['lesson_id'] ?? 0);

if ($course_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // 1. Validar que el alumno esté inscrito
    $stmt = $pdo->prepare("SELECT id, expires_at FROM enrollments WHERE user_id = ? AND course_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$user['id'], $course_id]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        die("No tienes acceso a este curso. Si acabas de realizar el pago, espera unos momentos o contacta a soporte.");
    }
    
    // 2. Obtener detalles del curso
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    // 3. Obtener todas las lecciones del curso ordenadas
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_number ASC, id ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
    
    if (empty($lessons)) {
        die("Este curso aún no tiene clases cargadas por el administrador.");
    }
    
    // 4. Seleccionar la lección activa
    $active_lesson = null;
    if ($active_lesson_id > 0) {
        foreach ($lessons as $l) {
            if ($l['id'] === $active_lesson_id) {
                $active_lesson = $l;
                break;
            }
        }
    }
    // Si no se especificó o no se encontró la lección activa, elegir la primera del curso
    if (!$active_lesson) {
        $active_lesson = $lessons[0];
        $active_lesson_id = $active_lesson['id'];
    }
    
    // 5. Cargar progreso del usuario en este curso
    $stmt = $pdo->prepare("
        SELECT lesson_id 
        FROM progress p
        JOIN lessons l ON p.lesson_id = l.id
        WHERE l.course_id = ? AND p.user_id = ? AND p.completed = 1
    ");
    $stmt->execute([$course_id, $user['id']]);
    $completed_lessons_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 6. Cargar los recursos (PDFs) del curso
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE course_id = ? ORDER BY created_at DESC");
    $stmt->execute([$course_id]);
    $resources = $stmt->fetchAll();
    
    // 7. Calcular progreso
    $total_lessons = count($lessons);
    $completed_count = count($completed_lessons_ids);
    $progress_pct = ($total_lessons > 0) ? round(($completed_count / $total_lessons) * 100) : 0;
    
    // 8. Determinar lección anterior y siguiente para navegación
    $prev_lesson = null;
    $next_lesson = null;
    foreach ($lessons as $index => $l) {
        if ($l['id'] === $active_lesson_id) {
            if (isset($lessons[$index - 1])) {
                $prev_lesson = $lessons[$index - 1];
            }
            if (isset($lessons[$index + 1])) {
                $next_lesson = $lessons[$index + 1];
            }
            break;
        }
    }

} catch (PDOException $e) {
    die("Error cargando el reproductor de clases: " . $e->getMessage());
}

// Función auxiliar para parsear URLs de video y retornar el HTML adecuado
function render_video_player($url) {
    if (empty($url)) {
        return '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">🎥 No hay video disponible para esta clase.</div>';
    }
    
    // Si ya es un enlace de inserción de YouTube o Vimeo
    if (strpos($url, '/embed/') !== false || strpos($url, 'player.vimeo.com') !== false) {
        return '<iframe src="' . h($url) . '" allowfullscreen allow="autoplay; fullscreen; picture-in-picture"></iframe>';
    }
    
    // YouTube Estándar
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
        $video_id = $match[1];
        return '<iframe src="https://www.youtube.com/embed/' . $video_id . '" allowfullscreen allow="autoplay; fullscreen"></iframe>';
    }
    
    // Vimeo Estándar
    if (preg_match('%vimeo\.com/(?:channels/(?:\w+/)?|groups/([^/]*)/videos/|album/(\d+)/video/|video/|)(\d+)(?:$|[?&])%i', $url, $match)) {
        $video_id = $match[3];
        return '<iframe src="https://player.vimeo.com/video/' . $video_id . '" allowfullscreen allow="autoplay; fullscreen"></iframe>';
    }
    
    // Video Directo (MP4/WebM)
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if ($ext === 'mp4' || $ext === 'webm' || $ext === 'ogg' || strpos($url, '.mp4') !== false) {
        return '<video src="' . h($url) . '" controls controlsList="nodownload"></video>';
    }
    
    // Fallback: tratar como enlace externo
    return '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:20px;text-align:center;gap:15px;">
        <span style="font-size:30px;">🔗</span>
        <p>Esta clase utiliza un video externo. Haz clic en el botón para visualizarlo:</p>
        <a href="' . h($url) . '" target="_blank" class="btn btn-primary btn-sm">Abrir Video Externo</a>
    </div>';
}

$page_title = $course['title'];
$active_tab = 'student';

// Agregar script de confeti en la cabecera
$extra_head = '
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
';

require_once '../includes/header.php';
?>

<div class="lms-layout">
    <!-- Panel Principal (Video e Información) -->
    <div class="lms-main">
        <!-- Navegación jerárquica de migas de pan -->
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">
            <a href="index.php">Mis Cursos</a> &gt; <span><?php echo h($course['title']); ?></span>
        </div>
        
        <!-- Contenedor del Reproductor -->
        <div class="video-container">
            <?php echo render_video_player($active_lesson['video_url']); ?>
        </div>
        
        <!-- Acciones Rápidas debajo del Video -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; margin-bottom: 25px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Checkbox interactiva de completado -->
                <label class="custom-checkbox" style="width: 24px; height: 24px;">
                    <input type="checkbox" id="class-checkbox" onchange="toggleLessonCompletion(<?php echo $active_lesson['id']; ?>, this.checked)" <?php echo in_array($active_lesson['id'], $completed_lessons_ids) ? 'checked' : ''; ?>>
                    <span class="checkbox-mark" style="width: 24px; height: 24px; border-radius: 6px;"></span>
                </label>
                <span id="checkbox-label" style="font-weight: 600; font-size: 15px; color: <?php echo in_array($active_lesson['id'], $completed_lessons_ids) ? 'var(--success)' : 'var(--heading-color)'; ?>;">
                    <?php echo in_array($active_lesson['id'], $completed_lessons_ids) ? '✓ Clase Completada' : 'Marcar clase como completada'; ?>
                </span>
            </div>
            
            <!-- Botones de Navegación -->
            <div style="display: flex; gap: 10px;">
                <?php if ($prev_lesson): ?>
                    <a href="course_view.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $prev_lesson['id']; ?>" class="btn btn-secondary btn-sm">
                        ◀️ Anterior
                    </a>
                <?php endif; ?>
                
                <?php if ($next_lesson): ?>
                    <a href="course_view.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $next_lesson['id']; ?>" class="btn btn-primary btn-sm">
                        Siguiente ▶️
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detalle de la Clase -->
        <div class="lesson-info">
            <h2><?php echo h($active_lesson['title']); ?></h2>
            <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Curso: <?php echo h($course['title']); ?></p>
        </div>
        
        <!-- Recursos Descargables (PDFs) -->
        <div class="resources-section">
            <h3>📕 Recursos de Apoyo (PDFs)</h3>
            <div class="resources-list">
                <?php if (empty($resources)): ?>
                    <p style="font-size: 13px; color: var(--text-muted); grid-column: 1/-1;">No hay archivos PDFs subidos para este curso.</p>
                <?php else: ?>
                    <?php foreach ($resources as $res): ?>
                        <div class="resource-file">
                            <span class="resource-icon">📕</span>
                            <div class="resource-meta">
                                <span style="font-weight: 500; color: var(--heading-color);" title="<?php echo h($res['title']); ?>">
                                    <?php echo h($res['title']); ?>
                                </span>
                                <a href="../uploads/<?php echo h($res['file_path']); ?>" target="_blank" download>Descargar PDF</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Barra Lateral de Contenido -->
    <div class="lms-sidebar">
        <div class="sidebar-title">
            <span>Contenido del Curso</span>
            <span class="progress-pct" id="sidebar-pct-text"><?php echo $progress_pct; ?>%</span>
        </div>
        
        <!-- Barra de Progreso General -->
        <div style="margin-bottom: 25px;">
            <div class="progress-container">
                <div class="progress-bar" id="sidebar-progress-bar" style="width: <?php echo $progress_pct; ?>%;"></div>
            </div>
        </div>
        
        <!-- Listado de Clases -->
        <div class="lesson-list">
            <?php foreach ($lessons as $index => $l): ?>
                <?php 
                $is_completed = in_array($l['id'], $completed_lessons_ids);
                $is_active = ($l['id'] === $active_lesson_id);
                ?>
                <div class="lesson-item <?php echo $is_active ? 'active' : ''; ?>" onclick="navigateToLesson(<?php echo $l['id']; ?>)">
                    <label class="custom-checkbox" onclick="event.stopPropagation()">
                        <input type="checkbox" class="sidebar-lesson-check" data-lesson-id="<?php echo $l['id']; ?>" onchange="toggleLessonCompletion(<?php echo $l['id']; ?>, this.checked)" <?php echo $is_completed ? 'checked' : ''; ?>>
                        <span class="checkbox-mark"></span>
                    </label>
                    <span class="lesson-title">
                        <?php echo h($l['title']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Canvas oculto para confeti -->
<canvas id="confetti-canvas"></canvas>

<script>
const courseId = <?php echo $course_id; ?>;

function navigateToLesson(lessonId) {
    window.location.href = 'course_view.php?course_id=' + courseId + '&lesson_id=' + lessonId;
}

function toggleLessonCompletion(lessonId, isCompleted) {
    // 1. Sincronizar UI (ambas checkboxes: la principal de abajo y la de la barra lateral correspondientes a esta clase)
    const mainCheck = document.getElementById('class-checkbox');
    const sidebarChecks = document.querySelectorAll(`.sidebar-lesson-check[data-lesson-id="${lessonId}"]`);
    
    // Si estamos en la clase activa, actualizar el check principal
    if (lessonId === <?php echo $active_lesson_id; ?>) {
        mainCheck.checked = isCompleted;
        const checkLabel = document.getElementById('checkbox-label');
        if (isCompleted) {
            checkLabel.innerHTML = '✓ Clase Completada';
            checkLabel.style.color = 'var(--success)';
        } else {
            checkLabel.innerHTML = 'Marcar clase como completada';
            checkLabel.style.color = 'var(--heading-color)';
        }
    }
    
    // Actualizar la de la barra lateral
    sidebarChecks.forEach(chk => {
        chk.checked = isCompleted;
    });

    // 2. Enviar petición AJAX al endpoint para actualizar el progreso en BD
    fetch('toggle_progress.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            lesson_id: lessonId,
            completed: isCompleted ? 1 : 0
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar la barra y el porcentaje del sidebar
            const pctText = document.getElementById('sidebar-pct-text');
            const progressBar = document.getElementById('sidebar-progress-bar');
            
            pctText.innerText = data.progress_pct + '%';
            progressBar.style.width = data.progress_pct + '%';
            
            // Si el progreso llegó a 100%, disparar confeti
            if (data.progress_pct === 100 && isCompleted) {
                triggerConfetti();
            }
        } else {
            console.error("Error al actualizar progreso: " + data.message);
        }
    })
    .catch(error => {
        console.error("Error de conexión:", error);
    });
}

function triggerConfetti() {
    confetti({
        particleCount: 150,
        spread: 80,
        origin: { y: 0.6 },
        colors: ['#5564F1', '#a78bfa', '#10b981', '#ffffff']
    });
}
</script>

<?php
// Usamos un pie de página simplificado porque el layout LMS es de pantalla completa
?>
</body>
</html>
