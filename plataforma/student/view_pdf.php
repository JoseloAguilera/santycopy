<?php
// student/view_pdf.php - Servidor protegido de recursos (PDFs y Audios)
// Valida sesión y matrícula activa del alumno antes de entregar el archivo
// Sirve el archivo en modo "inline" (sin descarga directa forzada)

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_login();
$user = get_logged_user();

$resource_id = intval($_GET['id'] ?? 0);

if ($resource_id <= 0) {
    http_response_code(400);
    die("ID de recurso inválido.");
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*, c.title AS course_title 
        FROM resources r
        JOIN courses c ON r.course_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$resource_id]);
    $res = $stmt->fetch();

    if (!$res) {
        http_response_code(404);
        die("El recurso solicitado no existe.");
    }

    // Si el usuario no es admin, validar que esté matriculado activamente en el curso
    if ($user['role'] !== 'admin') {
        $stmt = $pdo->prepare("
            SELECT id FROM enrollments 
            WHERE user_id = ? AND course_id = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$user['id'], $res['course_id']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die("Acceso restringido: no tienes acceso activo a este material.");
        }
    }

    // Ruta segura al archivo en uploads
    $safe_filename = basename($res['file_path']);
    $file_path = __DIR__ . '/../uploads/' . $safe_filename;

    if (!file_exists($file_path)) {
        http_response_code(404);
        die("El archivo no se encuentra en el servidor.");
    }

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    $mime_types = [
        'pdf'  => 'application/pdf',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'm4a'  => 'audio/mp4',
        'ogg'  => 'audio/ogg',
        'aac'  => 'audio/aac',
        'flac' => 'audio/flac'
    ];

    $content_type = $mime_types[$ext] ?? 'application/octet-stream';

    // Desactivar buffer de salida previo
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Cabeceras estrictas de protección:
    // - inline: se visualiza en el navegador sin forzar descarga
    // - no-store: evita que se quede guardado en caché externa
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . rawurlencode($res['title']) . '.' . $ext . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');

    // Transmitir el archivo de manera eficiente
    readfile($file_path);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Error al procesar el archivo: " . $e->getMessage());
}
