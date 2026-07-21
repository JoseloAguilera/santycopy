<?php
// student/toggle_progress.php - API AJAX para marcar progreso de lecciones

header('Content-Type: application/json');

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

// Validar que esté logueado
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

$user = get_logged_user();

// Leer datos JSON del cuerpo del POST
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$lesson_id = intval($data['lesson_id'] ?? 0);
$completed = intval($data['completed'] ?? 0); // 1 = Completada, 0 = Pendiente

if ($lesson_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de clase inválido.']);
    exit;
}

try {
    // 1. Obtener el course_id de la lección
    $stmt = $pdo->prepare("SELECT course_id FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    $course_id = $stmt->fetchColumn();
    
    if (!$course_id) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Clase no encontrada.']);
        exit;
    }
    
    // 2. Verificar que el estudiante esté inscrito de forma activa en el curso
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$user['id'], $course_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes acceso a este curso.']);
        exit;
    }
    
    // 3. Registrar o actualizar progreso
    if ($completed === 1) {
        // Marcar como completada
        $stmt = $pdo->prepare("
            INSERT INTO progress (user_id, lesson_id, completed, completed_at) 
            VALUES (?, ?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
        ");
        $stmt->execute([$user['id'], $lesson_id]);
    } else {
        // Desmarcar / Poner como pendiente
        $stmt = $pdo->prepare("DELETE FROM progress WHERE user_id = ? AND lesson_id = ?");
        $stmt->execute([$user['id'], $lesson_id]);
    }
    
    // 4. Calcular el nuevo porcentaje de progreso del curso completo
    
    // Total de clases en el curso
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $total_lessons = intval($stmt->fetchColumn());
    
    // Clases completadas por el usuario en este curso
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM progress p
        JOIN lessons l ON p.lesson_id = l.id
        WHERE l.course_id = ? AND p.user_id = ? AND p.completed = 1
    ");
    $stmt->execute([$course_id, $user['id']]);
    $completed_lessons = intval($stmt->fetchColumn());
    
    $progress_pct = ($total_lessons > 0) ? round(($completed_lessons / $total_lessons) * 100) : 0;
    
    echo json_encode([
        'success' => true,
        'progress_pct' => $progress_pct,
        'completed_count' => $completed_lessons,
        'total_count' => $total_lessons
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
