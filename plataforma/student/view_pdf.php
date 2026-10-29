<?php
// student/view_pdf.php - Servidor protegido de recursos (PDFs y Audios)
// Valida sesión y matrícula activa del alumno antes de entregar el archivo
// Soporta HTTP Range (206 Partial Content) para renderizado fluido en Chrome, Safari, iOS y Android
// Sirve el archivo en modo "inline" (sin descarga forzada)

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_login();
$user = get_logged_user();

function render_pdf_error($title, $message, $http_code = 404) {
    http_response_code($http_code);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Santy Copy LMS</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                background: #0d0e15;
                color: #e2e8f0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .error-card {
                background: rgba(22, 23, 34, 0.95);
                border: 1px solid rgba(239, 68, 68, 0.3);
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
                border-radius: 14px;
                padding: 35px 30px;
                max-width: 480px;
                text-align: center;
            }
            .error-icon {
                font-size: 44px;
                margin-bottom: 12px;
                display: block;
            }
            .error-title {
                color: #f87171;
                font-size: 19px;
                margin-bottom: 10px;
                font-weight: 700;
            }
            .error-desc {
                color: #94a3b8;
                font-size: 14px;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            .btn-back {
                display: inline-block;
                background: rgba(85, 100, 241, 0.2);
                color: #818cf8;
                border: 1px solid rgba(85, 100, 241, 0.4);
                padding: 8px 18px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
            }
            .btn-back:hover {
                background: rgba(85, 100, 241, 0.35);
                color: #fff;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <span class="error-icon">📕</span>
            <h3 class="error-title"><?php echo htmlspecialchars($title); ?></h3>
            <p class="error-desc"><?php echo htmlspecialchars($message); ?></p>
            <a href="javascript:history.back()" class="btn-back">← Volver Atrás</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$resource_id = intval($_GET['id'] ?? 0);
$lesson_id = intval($_GET['lesson_id'] ?? 0);
$type = $_GET['type'] ?? ($lesson_id > 0 ? 'lesson' : 'resource');

if ($resource_id <= 0 && $lesson_id <= 0) {
    render_pdf_error("Identificador Inválido", "No se proporcionó un ID de recurso o clase válido.", 400);
}

try {
    if ($type === 'lesson' || $lesson_id > 0) {
        $target_id = $lesson_id > 0 ? $lesson_id : $resource_id;
        $stmt = $pdo->prepare("
            SELECT l.id, l.title, l.pdf_file AS file_path, l.course_id, c.title AS course_title 
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            WHERE l.id = ?
        ");
        $stmt->execute([$target_id]);
        $res = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, c.title AS course_title 
            FROM resources r
            JOIN courses c ON r.course_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$resource_id]);
        $res = $stmt->fetch();
    }

    if (!$res || empty($res['file_path'])) {
        render_pdf_error("Material no encontrado", "El material solicitado no existe o todavía no tiene un archivo asignado por el administrador.", 404);
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
            render_pdf_error("Acceso Restringido", "No tienes una matrícula activa en este curso para visualizar este material.", 403);
        }
    }

    // Ruta segura al archivo en uploads
    $safe_filename = basename($res['file_path']);
    $file_path = __DIR__ . '/../uploads/' . $safe_filename;

    if (!file_exists($file_path)) {
        render_pdf_error("Archivo no encontrado", "El archivo físico no se encuentra en el directorio del servidor. Verifica que haya sido subido correctamente.", 404);
    }

    $file_size = filesize($file_path);
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

    $start = 0;
    $end = $file_size - 1;

    // Nombre de archivo seguro para la cabecera Content-Disposition
    $download_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $res['title']) . '.' . $ext;

    header("Content-Type: " . $content_type);
    header('Content-Disposition: inline; filename="' . $download_name . '"');
    header("Accept-Ranges: bytes");
    header("Cache-Control: private, max-age=3600, must-revalidate");
    header("X-Content-Type-Options: nosniff");

    // Soporte para solicitudes parciales HTTP Range (necesario para visores PDF integrados en Chrome/Safari)
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
        $buffer = 1024 * 64; // Chunks de 64KB
        while (!feof($fp) && $cur <= $end && (connection_status() === CONNECTION_NORMAL)) {
            $read = min($buffer, ($end - $cur) + 1);
            echo fread($fp, $read);
            flush();
            $cur += $read;
        }
        fclose($fp);
    }
    exit;

} catch (PDOException $e) {
    render_pdf_error("Error de Sistema", "Ocurrió un error al procesar el documento: " . $e->getMessage(), 500);
}
