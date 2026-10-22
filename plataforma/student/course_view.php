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
    
    // 7. Separar clases regulares y bonus
    $regular_lessons = [];
    $bonus_lessons = [];
    foreach ($lessons as $l) {
        if (!empty($l['is_bonus'])) {
            $bonus_lessons[] = $l;
        } else {
            $regular_lessons[] = $l;
        }
    }
    
    // Separar recursos regulares y bonus
    $regular_resources = [];
    $bonus_resources = [];
    foreach ($resources as $res) {
        if (!empty($res['is_bonus'])) {
            $bonus_resources[] = $res;
        } else {
            $regular_resources[] = $res;
        }
    }
    
    // Calcular progreso basado ÚNICAMENTE en las clases regulares del curso
    $total_regular = count($regular_lessons);
    $completed_regular = 0;
    foreach ($regular_lessons as $rl) {
        if (in_array($rl['id'], $completed_lessons_ids)) {
            $completed_regular++;
        }
    }
    $progress_pct = ($total_regular > 0) ? round(($completed_regular / $total_regular) * 100) : 0;
    
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

// Función auxiliar para parsear URLs de video y retornar el reproductor PROTEGIDO
function render_video_player($url, $has_audio = false) {
    if (empty($url)) {
        if ($has_audio) {
            return '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;background:linear-gradient(135deg, rgba(85,100,241,0.15) 0%, rgba(15,15,25,0.95) 100%);color:var(--heading-color);text-align:center;padding:30px;">
                <span style="font-size:56px;margin-bottom:10px;">🎙️</span>
                <h3 style="font-size:20px;margin-bottom:6px;">Lección en Formato Audio</h3>
                <p style="font-size:13px;color:var(--text-muted);">Esta lección incluye audio alojado en el servidor. Utiliza el reproductor a continuación para escucharla.</p>
            </div>';
        }
        return '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">🎥 No hay video disponible para esta clase.</div>';
    }
    
    $video_id = null;
    
    // YouTube Estándar o Acortado
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
        $video_id = $match[1];
    } elseif (strpos($url, '/embed/') !== false) {
        preg_match('%/embed/([^"&?/ ]{11})%i', $url, $match);
        if (!empty($match[1])) {
            $video_id = $match[1];
        }
    }
    
    // Si es YouTube: Protección avanzada anti-descarga y anti-acceso a YouTube
    if ($video_id) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $yt_embed_url = 'https://www.youtube-nocookie.com/embed/' . $video_id . '?enablejsapi=1&origin=' . rawurlencode('http://' . $host) . '&modestbranding=1&rel=0&iv_load_policy=3&playsinline=1&controls=1&showinfo=0&fs=1&disablekb=0';
        
        return '
        <div class="protected-video-wrapper" oncontextmenu="return false;">
            <!-- Escudo superior invisible: Bloquea clics en título o botón compartir que abren youtube.com -->
            <div class="yt-shield-top" title="Reproducción protegida"></div>
            <!-- Escudo inferior derecho: Bloquea clic en el logotipo de YouTube -->
            <div class="yt-shield-bottom-right" title="Reproducción protegida"></div>
            
            <iframe id="yt-player" src="' . $yt_embed_url . '" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                    allowfullscreen>
            </iframe>
        </div>';
    }
    
    // Vimeo Estándar
    if (preg_match('%vimeo\.com/(?:channels/(?:\w+/)?|groups/([^/]*)/videos/|album/(\d+)/video/|video/|)(\d+)(?:$|[?&])%i', $url, $match)) {
        $vimeo_id = $match[3];
        return '<div class="protected-video-wrapper" oncontextmenu="return false;"><iframe src="https://player.vimeo.com/video/' . $vimeo_id . '?dnt=1&title=0&byline=0&portrait=0" allowfullscreen allow="autoplay; fullscreen"></iframe></div>';
    }
    
    // Video Directo en Servidor (MP4/WebM) - Protegido sin opción de descarga
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if ($ext === 'mp4' || $ext === 'webm' || $ext === 'ogg' || strpos($url, '.mp4') !== false) {
        return '<div class="protected-video-wrapper" oncontextmenu="return false;"><video src="' . h($url) . '" controls controlsList="nodownload nofullscreen" oncontextmenu="return false;" style="width:100%;height:100%;"></video></div>';
    }
    
    // Fallback: Si es otro iframe
    if (strpos($url, 'http') === 0) {
        return '<div class="protected-video-wrapper" oncontextmenu="return false;"><iframe src="' . h($url) . '" allowfullscreen></iframe></div>';
    }
    
    return '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">🎥 Video no disponible.</div>';
}

// Función auxiliar para renderizar el reproductor de Audio PROTEGIDO (Sin descarga)
function render_audio_player($audio_filename) {
    if (empty($audio_filename)) {
        return '';
    }
    
    $audio_url = '../uploads/' . rawurlencode($audio_filename);
    $ext = strtoupper(pathinfo($audio_filename, PATHINFO_EXTENSION));
    
    return '
    <div class="audio-player-card" oncontextmenu="return false;" style="background: rgba(18, 18, 24, 0.85); border: 1px solid var(--border-glow); backdrop-filter: blur(12px); border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, var(--primary-color) 0%, #a78bfa 100%); display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; box-shadow: 0 4px 14px rgba(85, 100, 241, 0.4);">
                    🎵
                </div>
                <div>
                    <h4 style="font-size: 15px; font-weight: 600; margin: 0; color: var(--heading-color);">Audio Protegido de la Clase</h4>
                    <span style="font-size: 12px; color: var(--text-muted);">🔒 Transmisión exclusiva para alumnos • Reproducción protegida</span>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.05); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <span style="font-size: 11px; color: var(--text-muted);">⚡ Velocidad:</span>
                    <select onchange="changeAudioSpeed(this.value)" style="background: transparent; border: none; color: var(--heading-color); font-size: 12px; font-weight: 600; cursor: pointer; outline: none;">
                        <option value="0.8" style="background: #121216;">0.8x</option>
                        <option value="1.0" selected style="background: #121216;">1.0x</option>
                        <option value="1.25" style="background: #121216;">1.25x</option>
                        <option value="1.5" style="background: #121216;">1.5x</option>
                        <option value="1.75" style="background: #121216;">1.75x</option>
                        <option value="2.0" style="background: #121216;">2.0x</option>
                    </select>
                </div>
                <span class="badge-role" style="font-size: 11px; color: #10b981; border: 1px solid rgba(16,185,129,0.3); background: rgba(16,185,129,0.08);">
                    🔒 Protegido
                </span>
            </div>
        </div>
        
        <audio id="lms-audio-player" controls controlsList="nodownload" oncontextmenu="return false;" style="width: 100%; height: 42px; border-radius: 8px; outline: none; background: rgba(255, 255, 255, 0.05);">
            <source src="' . $audio_url . '" type="audio/' . strtolower($ext) . '">
            Tu navegador no soporta el elemento de audio HTML5.
        </audio>
    </div>
    
    <script>
    function changeAudioSpeed(speed) {
        var audio = document.getElementById("lms-audio-player");
        if (audio) {
            audio.playbackRate = parseFloat(speed);
        }
    }
    </script>
    ';
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
        
        <!-- Contenedor del Reproductor de Video -->
        <div class="video-container">
            <?php echo render_video_player($active_lesson['video_url'], !empty($active_lesson['audio_file'])); ?>
        </div>
        
        <!-- Reproductor de Audio si la clase tiene audio subido al servidor -->
        <?php echo render_audio_player($active_lesson['audio_file'] ?? ''); ?>
        
        <!-- Acciones Rápidas debajo del Video/Audio -->
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
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h2><?php echo h($active_lesson['title']); ?></h2>
                <?php if (!empty($active_lesson['is_bonus'])): ?>
                    <span style="background: rgba(167, 139, 250, 0.2); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.4); font-size: 11px; padding: 4px 10px; border-radius: 6px; font-weight: 700;">🎁 MATERIAL BONUS</span>
                <?php endif; ?>
            </div>
            <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Curso: <?php echo h($course['title']); ?></p>
        </div>
        
        <!-- Recursos de Apoyo (Regulares) -->
        <div class="resources-section">
            <h3>📂 Recursos de Apoyo</h3>
            <div class="resources-list">
                <?php if (empty($regular_resources)): ?>
                    <p style="font-size: 13px; color: var(--text-muted); grid-column: 1/-1;">No hay archivos de apoyo regulares para este curso.</p>
                <?php else: ?>
                    <?php foreach ($regular_resources as $res): ?>
                        <?php 
                        $res_ext = strtolower(pathinfo($res['file_path'], PATHINFO_EXTENSION));
                        $is_audio_res = in_array($res_ext, ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac']);
                        ?>
                        <div class="resource-file">
                            <span class="resource-icon"><?php echo $is_audio_res ? '🎵' : '📕'; ?></span>
                            <div class="resource-meta">
                                <span style="font-weight: 500; color: var(--heading-color);" title="<?php echo h($res['title']); ?>">
                                    <?php echo h($res['title']); ?>
                                </span>
                                <?php if ($is_audio_res): ?>
                                    <button type="button" class="btn-resource-action" onclick="openAudioModal(<?php echo $res['id']; ?>, '<?php echo addslashes(h($res['title'])); ?>')">
                                        ▶️ Escuchar Audio Protegido
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-resource-action" onclick="openPdfViewer(<?php echo $res['id']; ?>, '<?php echo addslashes(h($res['title'])); ?>')">
                                        👁️ Leer PDF en Pantalla
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($bonus_resources)): ?>
        <!-- Recursos Bonus Exclusivos -->
        <div class="resources-section bonus-resources-card" style="margin-top: 20px; border-color: rgba(167, 139, 250, 0.4); background: rgba(167, 139, 250, 0.04);">
            <h3 style="color: #a78bfa;">🎁 Recursos y Guías Bonus Exclusivas</h3>
            <div class="resources-list">
                <?php foreach ($bonus_resources as $res): ?>
                    <?php 
                    $res_ext = strtolower(pathinfo($res['file_path'], PATHINFO_EXTENSION));
                    $is_audio_res = in_array($res_ext, ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac']);
                    ?>
                    <div class="resource-file" style="border-color: rgba(167, 139, 250, 0.3); background: rgba(167, 139, 250, 0.06);">
                        <span class="resource-icon"><?php echo $is_audio_res ? '🎵' : '🎁'; ?></span>
                        <div class="resource-meta">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-weight: 500; color: var(--heading-color);" title="<?php echo h($res['title']); ?>">
                                    <?php echo h($res['title']); ?>
                                </span>
                                <span style="background: rgba(167, 139, 250, 0.25); color: #a78bfa; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700;">BONUS</span>
                            </div>
                            <?php if ($is_audio_res): ?>
                                <button type="button" class="btn-resource-action bonus-btn" onclick="openAudioModal(<?php echo $res['id']; ?>, '<?php echo addslashes(h($res['title'])); ?>')">
                                    ▶️ Escuchar Audio Bonus
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-resource-action bonus-btn" onclick="openPdfViewer(<?php echo $res['id']; ?>, '<?php echo addslashes(h($res['title'])); ?>')">
                                    👁️ Leer PDF Bonus
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Barra Lateral de Contenido -->
    <div class="lms-sidebar">
        <div class="sidebar-title">
            <span>Contenido del Curso</span>
            <span class="progress-pct" id="sidebar-pct-text"><?php echo $progress_pct; ?>%</span>
        </div>
        
        <!-- Barra de Progreso General -->
        <div style="margin-bottom: 20px;">
            <div class="progress-container">
                <div class="progress-bar" id="sidebar-progress-bar" style="width: <?php echo $progress_pct; ?>%;"></div>
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px; display: flex; justify-content: space-between;">
                <span>Progreso de clases principales</span>
                <span id="sidebar-completed-text"><?php echo $completed_regular; ?> / <?php echo $total_regular; ?></span>
            </div>
        </div>
        
        <!-- Listado de Clases Principales -->
        <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 10px;">
            📚 Clases del Curso (<?php echo count($regular_lessons); ?>)
        </div>
        <div class="lesson-list">
            <?php foreach ($regular_lessons as $l): ?>
                <?php 
                $is_completed = in_array($l['id'], $completed_lessons_ids);
                $is_active = ($l['id'] === $active_lesson_id);
                $has_vid = !empty($l['video_url']);
                $has_aud = !empty($l['audio_file']);
                ?>
                <div class="lesson-item <?php echo $is_active ? 'active' : ''; ?>" onclick="navigateToLesson(<?php echo $l['id']; ?>)">
                    <label class="custom-checkbox" onclick="event.stopPropagation()">
                        <input type="checkbox" class="sidebar-lesson-check" data-lesson-id="<?php echo $l['id']; ?>" onchange="toggleLessonCompletion(<?php echo $l['id']; ?>, this.checked)" <?php echo $is_completed ? 'checked' : ''; ?>>
                        <span class="checkbox-mark"></span>
                    </label>
                    <span class="lesson-title" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <span><?php echo h($l['title']); ?></span>
                        <span style="font-size: 11px; opacity: 0.8; margin-left: 4px;">
                            <?php if ($has_vid && $has_aud): ?>
                                🎥🎵
                            <?php elseif ($has_aud): ?>
                                🎵
                            <?php elseif ($has_vid): ?>
                                🎥
                            <?php endif; ?>
                        </span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($bonus_lessons)): ?>
        <!-- Sección de Material Bonus en Sidebar -->
        <div class="bonus-sidebar-box" style="margin-top: 25px; padding-top: 20px; border-top: 1px dashed rgba(167, 139, 250, 0.3);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span style="font-size: 13px; font-weight: 700; color: #a78bfa; display: flex; align-items: center; gap: 6px;">
                    🎁 Material Bonus
                </span>
                <span style="background: rgba(167, 139, 250, 0.2); color: #a78bfa; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                    <?php echo count($bonus_lessons); ?> BONUS
                </span>
            </div>
            <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 12px;">
                Material de regalo exclusivo. No afecta el porcentaje de finalización.
            </p>
            
            <div class="lesson-list">
                <?php foreach ($bonus_lessons as $l): ?>
                    <?php 
                    $is_completed = in_array($l['id'], $completed_lessons_ids);
                    $is_active = ($l['id'] === $active_lesson_id);
                    $has_vid = !empty($l['video_url']);
                    $has_aud = !empty($l['audio_file']);
                    ?>
                    <div class="lesson-item bonus-lesson-item <?php echo $is_active ? 'active' : ''; ?>" onclick="navigateToLesson(<?php echo $l['id']; ?>)">
                        <label class="custom-checkbox" onclick="event.stopPropagation()">
                            <input type="checkbox" class="sidebar-lesson-check" data-lesson-id="<?php echo $l['id']; ?>" onchange="toggleLessonCompletion(<?php echo $l['id']; ?>, this.checked)" <?php echo $is_completed ? 'checked' : ''; ?>>
                            <span class="checkbox-mark"></span>
                        </label>
                        <span class="lesson-title" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <span style="color: <?php echo $is_active ? '#ffffff' : '#e0e7ff'; ?>;">
                                <?php echo h($l['title']); ?>
                            </span>
                            <span style="font-size: 11px; margin-left: 4px; display: flex; align-items: center; gap: 3px;">
                                <?php if ($has_vid && $has_aud): ?>
                                    🎥🎵
                                <?php elseif ($has_aud): ?>
                                    🎵
                                <?php elseif ($has_vid): ?>
                                    🎥
                                <?php endif; ?>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Protegido de Lectura de PDF -->
<div id="pdf-viewer-modal" class="pdf-modal-backdrop" style="display: none;" oncontextmenu="return false;">
    <div class="pdf-modal-container">
        <div class="pdf-modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">📕</span>
                <div>
                    <h3 id="pdf-modal-title" style="font-size: 15px; margin: 0; color: var(--heading-color);">Visualizador Protegido</h3>
                    <span style="font-size: 11px; color: #10b981;">🔒 Modo Lectura Exclusiva en Plataforma (Descarga Desactivada)</span>
                </div>
            </div>
            <button type="button" onclick="closePdfViewer()" class="btn btn-secondary btn-sm" style="padding: 6px 14px; font-size: 12px; cursor: pointer;">✕ Cerrar</button>
        </div>
        <div class="pdf-modal-body" oncontextmenu="return false;">
            <iframe id="pdf-modal-frame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Modal Protegido de Reproducción de Audio de Recursos -->
<div id="audio-viewer-modal" class="pdf-modal-backdrop" style="display: none;" oncontextmenu="return false;">
    <div class="pdf-modal-container" style="max-width: 500px; height: auto;">
        <div class="pdf-modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">🎵</span>
                <div>
                    <h3 id="audio-modal-title" style="font-size: 15px; margin: 0; color: var(--heading-color);">Reproductor de Audio</h3>
                    <span style="font-size: 11px; color: #10b981;">🔒 Reproducción Protegida</span>
                </div>
            </div>
            <button type="button" onclick="closeAudioModal()" class="btn btn-secondary btn-sm" style="padding: 6px 14px; font-size: 12px; cursor: pointer;">✕ Cerrar</button>
        </div>
        <div style="padding: 24px; text-align: center;">
            <audio id="resource-audio-player" controls controlsList="nodownload" oncontextmenu="return false;" style="width: 100%; outline: none;">
                <source id="resource-audio-source" src="" type="audio/mpeg">
                Tu navegador no soporta reproducción de audio.
            </audio>
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

function openPdfViewer(resourceId, title) {
    document.getElementById('pdf-modal-title').innerText = title;
    const url = 'view_pdf.php?id=' + resourceId + '#toolbar=0&navpanes=0&scrollbar=0';
    document.getElementById('pdf-modal-frame').src = url;
    document.getElementById('pdf-viewer-modal').style.display = 'flex';
}

function closePdfViewer() {
    document.getElementById('pdf-viewer-modal').style.display = 'none';
    document.getElementById('pdf-modal-frame').src = '';
}

function openAudioModal(resourceId, title) {
    document.getElementById('audio-modal-title').innerText = title;
    const audioUrl = 'view_pdf.php?id=' + resourceId;
    const player = document.getElementById('resource-audio-player');
    const source = document.getElementById('resource-audio-source');
    source.src = audioUrl;
    player.load();
    player.play().catch(() => {});
    document.getElementById('audio-viewer-modal').style.display = 'flex';
}

function closeAudioModal() {
    const player = document.getElementById('resource-audio-player');
    player.pause();
    document.getElementById('audio-viewer-modal').style.display = 'none';
    document.getElementById('resource-audio-source').src = '';
}

// Bloqueo de atajos de teclado para guardar/imprimir en la ventana protegida (Ctrl+S, Ctrl+P, etc.)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'S' || e.key === 'P')) {
        e.preventDefault();
        return false;
    }
    if (e.key === 'Escape') {
        closePdfViewer();
        closeAudioModal();
    }
});

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
            const completedText = document.getElementById('sidebar-completed-text');
            
            pctText.innerText = data.progress_pct + '%';
            progressBar.style.width = data.progress_pct + '%';
            if (completedText && data.completed_count !== undefined && data.total_count !== undefined) {
                completedText.innerText = data.completed_count + ' / ' + data.total_count;
            }
            
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
