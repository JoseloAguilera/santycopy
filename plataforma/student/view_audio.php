<?php
// student/view_audio.php - Servidor protegido de streaming de audio con soporte de HTTP Range (206 Partial Content)
// Permite reproducir, pausar y adelantar audios sin restricciones en todos los navegadores (Chrome, Safari, iOS, Android)

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

require_login();
$user = get_logged_user();

$lesson_id = intval($_GET['lesson_id'] ?? 0);
$resource_id = intval($_GET['resource_id'] ?? ($_GET['id'] ?? 0));

if ($lesson_id <= 0 && $resource_id <= 0) {
    http_response_code(400);
    die("ID de clase o recurso de audio inválido.");
}

try {
    if ($lesson_id > 0) {
        $stmt = $pdo->prepare("
            SELECT l.id, l.title, l.audio_file AS file_path, l.course_id, c.title AS course_title
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            WHERE l.id = ?
        ");
        $stmt->execute([$lesson_id]);
        $res = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("
            SELECT r.id, r.title, r.file_path, r.course_id, c.title AS course_title
            FROM resources r
            JOIN courses c ON r.course_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$resource_id]);
        $res = $stmt->fetch();
    }

    if (!$res || empty($res['file_path'])) {
        http_response_code(404);
        die("La lección o recurso solicitado no tiene un archivo de audio asignado.");
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
            die("Acceso restringido: no estás matriculado en este curso.");
        }
    }

    // Ruta física al archivo de audio en uploads
    $safe_filename = basename($res['file_path']);
    $file_path = __DIR__ . '/../uploads/' . $safe_filename;

    if (!file_exists($file_path)) {
        http_response_code(404);
        die("El archivo de audio no se encuentra en el servidor.");
    }

    $file_size = filesize($file_path);
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    $mime_types = [
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'm4a'  => 'audio/mp4',
        'ogg'  => 'audio/ogg',
        'aac'  => 'audio/aac',
        'flac' => 'audio/flac'
    ];

    $content_type = $mime_types[$ext] ?? 'audio/mpeg';

    // Desactivar buffer de salida
    if (ob_get_level()) {
        ob_end_clean();
    }

    $start = 0;
    $end = $file_size - 1;

    header("Content-Type: " . $content_type);
    header("Accept-Ranges: bytes");
    header("Cache-Control: private, max-age=3600");
    header("X-Content-Type-Options: nosniff");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=\s*([0-9]+)?\s*-\s*([0-9]+)?/i', $range, $matches)) {
            if (!empty($matches[1])) {
                $start = intval($matches[1]);
            }
            if (!empty($matches[2])) {
                $end = intval($matches[2]);
            }
        }

        if ($start > $end || $start >= $file_size) {
            header("HTTP/1.1 416 Requested Range Not Satisfiable");
            header("Content-Range: bytes */$file_size");
            exit;
        }

        if ($end >= $file_size) {
            $end = $file_size - 1;
        }

        $length = ($end - $start) + 1;
        header("HTTP/1.1 206 Partial Content");
        header("Content-Range: bytes $start-$end/$file_size");
        header("Content-Length: " . $length);
    } else {
        header("HTTP/1.1 200 OK");
        header("Content-Length: " . $file_size);
    }

    $fp = fopen($file_path, 'rb');
    if ($fp) {
        fseek($fp, $start);
        $cur = $start;
        $buffer = 1024 * 64; // Chunks de 64KB para rendimiento óptimo
        while (!feof($fp) && $cur <= $end && (connection_status() === CONNECTION_NORMAL)) {
            $read = min($buffer, ($end - $cur) + 1);
            echo fread($fp, $read);
            flush();
            $cur += $read;
        }
        fclose($fp);
    }
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Error al transmitir audio: " . $e->getMessage());
}
