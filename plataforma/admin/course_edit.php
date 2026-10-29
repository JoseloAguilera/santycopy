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
if (!empty($_GET['success_msg'])) {
    $success = $_GET['success_msg'];
}

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
        $promo_price_input = trim($_POST['promo_price'] ?? '');
        $promo_price = ($promo_price_input !== '') ? intval($promo_price_input) : null;
        $promo_duration_minutes = max(1, intval($_POST['promo_duration_minutes'] ?? 15));
        $payment_type = trim($_POST['payment_type'] ?? 'one_time');
        $subscription_period = intval($_POST['subscription_period'] ?? 30);
        $status = in_array($_POST['status'] ?? '', ['publicado', 'despublicado', 'archivado']) ? $_POST['status'] : 'publicado';
        
        if (empty($title)) {
            $error = 'El título del curso no puede estar vacío.';
        } else {
            try {
                // Gestionar subida de miniatura si se proporcionó una nueva
                $thumbnail_clause = "";
                $params = [$title, $description, $price, $promo_price, $promo_duration_minutes, $payment_type, ($payment_type === 'subscription' ? $subscription_period : null), $status];
                
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
                    $sql = "UPDATE courses SET title = ?, description = ?, price = ?, promo_price = ?, promo_duration_minutes = ?, payment_type = ?, subscription_period = ?, status = ? $thumbnail_clause WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = 'Curso y estado actualizados correctamente.';
                }
            } catch (PDOException $e) {
                $error = 'Error al actualizar el curso: ' . $e->getMessage();
            }
        }
    }
    
    // 2. Agregar lección / video / audio / pdf
    elseif ($action === 'add_lesson') {
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $order_number = intval($_POST['order_number'] ?? 0);
        $audio_file_name = null;
        $pdf_file_name = null;
        
        if (empty($lesson_title)) {
            $error = 'El título de la clase es obligatorio.';
        } else {
            // Procesar subida de archivo de audio si fue proporcionado
            if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['audio_file']['tmp_name'];
                $file_name = $_FILES['audio_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_audio_exts = ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'];
                
                if (in_array($file_ext, $allowed_audio_exts)) {
                    $audio_file_name = 'audio_' . $course_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    $upload_path = '../uploads/' . $audio_file_name;
                    
                    if (!move_uploaded_file($file_tmp, $upload_path)) {
                        $error = 'No se pudo mover el archivo de audio a la carpeta de descargas del servidor.';
                        $audio_file_name = null;
                    } else {
                        @chmod($upload_path, 0644);
                    }
                } else {
                    $error = 'Formato de audio no permitido. Solo se aceptan MP3, WAV, M4A, OGG, AAC y FLAC.';
                }
            }

            // Procesar subida de archivo PDF de lectura si fue proporcionado
            if (empty($error) && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['pdf_file']['tmp_name'];
                $file_name = $_FILES['pdf_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if ($file_ext === 'pdf') {
                    $pdf_file_name = 'pdf_lesson_' . $course_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $upload_path = '../uploads/' . $pdf_file_name;
                    
                    if (!move_uploaded_file($file_tmp, $upload_path)) {
                        $error = 'No se pudo mover el archivo PDF de la clase al servidor.';
                        $pdf_file_name = null;
                    } else {
                        @chmod($upload_path, 0644);
                    }
                } else {
                    $error = 'Formato de lectura no permitido. Solo se aceptan documentos PDF (.pdf).';
                }
            }
            
            $is_bonus = (isset($_POST['is_bonus']) && $_POST['is_bonus'] == '1') ? 1 : 0;

            if (empty($error)) {
                if (empty($video_url) && empty($audio_file_name) && empty($pdf_file_name)) {
                    $error = 'Debes proporcionar al menos un tipo de contenido para la clase: enlace de Video, archivo de Audio o documento PDF.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, video_url, audio_file, pdf_file, order_number, is_bonus) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$course_id, $lesson_title, $video_url, $audio_file_name, $pdf_file_name, $order_number, $is_bonus]);
                        $success = $is_bonus ? '¡Material Bonus agregado correctamente!' : 'Clase agregada correctamente al paso a paso.';
                    } catch (PDOException $e) {
                        if ($audio_file_name && file_exists('../uploads/' . $audio_file_name)) {
                            @unlink('../uploads/' . $audio_file_name);
                        }
                        if ($pdf_file_name && file_exists('../uploads/' . $pdf_file_name)) {
                            @unlink('../uploads/' . $pdf_file_name);
                        }
                        $error = 'Error al agregar la clase: ' . $e->getMessage();
                    }
                }
            }
        }
    }
    
    // 2b. Editar clase / lección existente
    elseif ($action === 'update_lesson') {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $order_number = max(1, intval($_POST['order_number'] ?? 1));
        $is_bonus = (isset($_POST['is_bonus']) && $_POST['is_bonus'] == '1') ? 1 : 0;
        $remove_audio = !empty($_POST['remove_audio']);
        $remove_pdf = !empty($_POST['remove_pdf']);

        if ($lesson_id <= 0) {
            $error = 'ID de clase no válido.';
        } elseif (empty($lesson_title)) {
            $error = 'El título de la clase no puede estar vacío.';
        } else {
            // Verificar que la clase pertenece a este curso
            $stmt = $pdo->prepare("SELECT audio_file, pdf_file FROM lessons WHERE id = ? AND course_id = ?");
            $stmt->execute([$lesson_id, $course_id]);
            $current_lesson = $stmt->fetch();

            if (!$current_lesson) {
                $error = 'La clase no existe o no pertenece a este curso.';
            } else {
                $audio_file_name = $current_lesson['audio_file'];
                $pdf_file_name = $current_lesson['pdf_file'];

                // Eliminar audio si se solicitó
                if ($remove_audio && !empty($audio_file_name)) {
                    if (file_exists('../uploads/' . $audio_file_name)) {
                        @unlink('../uploads/' . $audio_file_name);
                    }
                    $audio_file_name = null;
                }

                // Subir nuevo audio si se proporcionó
                if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['audio_file']['tmp_name'];
                    $file_name = $_FILES['audio_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_audio_exts = ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'];

                    if (in_array($file_ext, $allowed_audio_exts)) {
                        $new_audio = 'audio_' . $course_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $upload_path = '../uploads/' . $new_audio;
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            @chmod($upload_path, 0644);
                            if (!empty($audio_file_name) && file_exists('../uploads/' . $audio_file_name)) {
                                @unlink('../uploads/' . $audio_file_name);
                            }
                            $audio_file_name = $new_audio;
                        } else {
                            $error = 'Error al mover el nuevo archivo de audio al servidor.';
                        }
                    } else {
                        $error = 'Formato de audio no permitido. Solo se aceptan archivos MP3, WAV, M4A, OGG, AAC, FLAC.';
                    }
                }

                // Eliminar PDF si se solicitó
                if ($remove_pdf && !empty($pdf_file_name)) {
                    if (file_exists('../uploads/' . $pdf_file_name)) {
                        @unlink('../uploads/' . $pdf_file_name);
                    }
                    $pdf_file_name = null;
                }

                // Subir nuevo PDF si se proporcionó
                if (empty($error) && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['pdf_file']['tmp_name'];
                    $file_name = $_FILES['pdf_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if ($file_ext === 'pdf') {
                        $new_pdf = 'pdf_lesson_' . $course_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                        $upload_path = '../uploads/' . $new_pdf;
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            @chmod($upload_path, 0644);
                            if (!empty($pdf_file_name) && file_exists('../uploads/' . $pdf_file_name)) {
                                @unlink('../uploads/' . $pdf_file_name);
                            }
                            $pdf_file_name = $new_pdf;
                        } else {
                            $error = 'Error al subir el nuevo documento PDF.';
                        }
                    } else {
                        $error = 'Formato de lectura no permitido. Solo se aceptan documentos PDF (.pdf).';
                    }
                }

                if (empty($error)) {
                    if (empty($video_url) && empty($audio_file_name) && empty($pdf_file_name)) {
                        $error = 'La clase debe contener al menos un material: video, audio o documento PDF.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("UPDATE lessons SET title = ?, video_url = ?, audio_file = ?, pdf_file = ?, order_number = ?, is_bonus = ? WHERE id = ? AND course_id = ?");
                            $stmt->execute([$lesson_title, $video_url, $audio_file_name, $pdf_file_name, $order_number, $is_bonus, $lesson_id, $course_id]);
                            $success = '¡Clase actualizada correctamente!';
                        } catch (PDOException $e) {
                            $error = 'Error al actualizar la clase en la base de datos: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
    
    // 3. Eliminar lección
    elseif ($action === 'delete_lesson') {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        if ($lesson_id > 0) {
            try {
                // Obtener archivos asociados (audio y pdf) para eliminarlos del servidor
                $stmt = $pdo->prepare("SELECT audio_file, pdf_file FROM lessons WHERE id = ? AND course_id = ?");
                $stmt->execute([$lesson_id, $course_id]);
                $files = $stmt->fetch();
                
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
                $stmt->execute([$lesson_id, $course_id]);
                
                if (!empty($files['audio_file']) && file_exists('../uploads/' . $files['audio_file'])) {
                    @unlink('../uploads/' . $files['audio_file']);
                }
                if (!empty($files['pdf_file']) && file_exists('../uploads/' . $files['pdf_file'])) {
                    @unlink('../uploads/' . $files['pdf_file']);
                }
                
                $success = 'Clase eliminada correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al eliminar la clase: ' . $e->getMessage();
            }
        }
    }
    
    // 4. Subir recurso (PDF o Audio)
    elseif ($action === 'add_resource') {
        $resource_title = trim($_POST['resource_title'] ?? '');
        $order_number = intval($_POST['order_number'] ?? 0);
        $is_bonus = (isset($_POST['is_bonus']) && $_POST['is_bonus'] == '1') ? 1 : 0;
        
        if ($order_number <= 0) {
            $stmtMax = $pdo->prepare("SELECT MAX(order_number) FROM resources WHERE course_id = ?");
            $stmtMax->execute([$course_id]);
            $order_number = intval($stmtMax->fetchColumn()) + 1;
        }

        if (empty($resource_title)) {
            $error = 'El título del recurso es obligatorio.';
        } elseif (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Debes seleccionar un archivo válido (PDF o Audio) para subir.';
        } else {
            $file_tmp = $_FILES['resource_file']['tmp_name'];
            $file_name = $_FILES['resource_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                $error = 'Seguridad: Solo se permite la subida de archivos PDF o de Audio (.pdf, .mp3, .wav, .m4a, .ogg, .aac, .flac).';
            } else {
                $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resource_title);
                $new_file_name = 'res_' . $course_id . '_' . time() . '_' . $clean_title . '.' . $file_ext;
                $upload_path = '../uploads/' . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    @chmod($upload_path, 0644);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO resources (course_id, title, file_path, order_number, is_bonus) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$course_id, $resource_title, $new_file_name, $order_number, $is_bonus]);
                        $success = $is_bonus ? 'Recurso Bonus subido correctamente al servidor.' : 'Recurso subido correctamente al servidor.';
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
    
    // 5. Eliminar recurso
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
    
    // 5b. Editar recurso de apoyo
    elseif ($action === 'update_resource') {
        $resource_id = intval($_POST['resource_id'] ?? 0);
        $resource_title = trim($_POST['resource_title'] ?? '');
        $order_number = max(1, intval($_POST['order_number'] ?? 1));
        $is_bonus = (isset($_POST['is_bonus']) && $_POST['is_bonus'] == '1') ? 1 : 0;

        if ($resource_id <= 0) {
            $error = 'ID de recurso no válido.';
        } elseif (empty($resource_title)) {
            $error = 'El nombre del recurso es obligatorio.';
        } else {
            $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = ? AND course_id = ?");
            $stmt->execute([$resource_id, $course_id]);
            $current_res = $stmt->fetch();

            if (!$current_res) {
                $error = 'El recurso no existe o no pertenece a este curso.';
            } else {
                $file_path = $current_res['file_path'];

                // Si se sube archivo de reemplazo
                if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['resource_file']['tmp_name'];
                    $file_name = $_FILES['resource_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['pdf', 'mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'];

                    if (in_array($file_ext, $allowed_exts)) {
                        $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resource_title);
                        $new_file_name = 'res_' . $course_id . '_' . time() . '_' . $clean_title . '.' . $file_ext;
                        $upload_path = '../uploads/' . $new_file_name;

                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            @chmod($upload_path, 0644);
                            if (!empty($file_path) && file_exists('../uploads/' . $file_path)) {
                                @unlink('../uploads/' . $file_path);
                            }
                            $file_path = $new_file_name;
                        } else {
                            $error = 'Error al subir el nuevo archivo de recurso.';
                        }
                    } else {
                        $error = 'Formato de archivo no válido. Solo se admiten PDFs o Audios.';
                    }
                }

                if (empty($error)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE resources SET title = ?, file_path = ?, order_number = ?, is_bonus = ? WHERE id = ? AND course_id = ?");
                        $stmt->execute([$resource_title, $file_path, $order_number, $is_bonus, $resource_id, $course_id]);
                        $success = 'Recurso actualizado correctamente.';
                    } catch (PDOException $e) {
                        $error = 'Error al actualizar el recurso: ' . $e->getMessage();
                    }
                }
            }
        }
    }
    
    // 6. Cambiar estado Bonus de una clase (Toggle)
    elseif ($action === 'toggle_lesson_bonus') {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        if ($lesson_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE lessons SET is_bonus = IF(is_bonus = 1, 0, 1) WHERE id = ? AND course_id = ?");
                $stmt->execute([$lesson_id, $course_id]);
                $success = 'Estado bonus de la clase actualizado.';
            } catch (PDOException $e) {
                $error = 'Error al cambiar estado bonus: ' . $e->getMessage();
            }
        }
    }
    
    // 7. Cambiar estado Bonus de un recurso (Toggle)
    elseif ($action === 'toggle_resource_bonus') {
        $resource_id = intval($_POST['resource_id'] ?? 0);
        if ($resource_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE resources SET is_bonus = IF(is_bonus = 1, 0, 1) WHERE id = ? AND course_id = ?");
                $stmt->execute([$resource_id, $course_id]);
                $success = 'Estado bonus del recurso actualizado.';
            } catch (PDOException $e) {
                $error = 'Error al cambiar estado bonus: ' . $e->getMessage();
            }
        }
    }
    
    // 8. Reordenar recurso (Subir/Bajar posición)
    elseif ($action === 'reorder_resource') {
        $resource_id = intval($_POST['resource_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        if ($resource_id > 0 && in_array($direction, ['up', 'down'])) {
            try {
                $stmt = $pdo->prepare("SELECT id, order_number FROM resources WHERE course_id = ? ORDER BY order_number ASC, id ASC");
                $stmt->execute([$course_id]);
                $all_res = $stmt->fetchAll();
                
                $target_idx = -1;
                foreach ($all_res as $idx => $r) {
                    if ($r['id'] == $resource_id) {
                        $target_idx = $idx;
                        break;
                    }
                }
                
                if ($target_idx !== -1) {
                    $swap_idx = ($direction === 'up') ? $target_idx - 1 : $target_idx + 1;
                    if ($swap_idx >= 0 && $swap_idx < count($all_res)) {
                        $temp = $all_res[$target_idx];
                        $all_res[$target_idx] = $all_res[$swap_idx];
                        $all_res[$swap_idx] = $temp;
                        
                        foreach ($all_res as $new_order => $r) {
                            $stmtUpd = $pdo->prepare("UPDATE resources SET order_number = ? WHERE id = ?");
                            $stmtUpd->execute([$new_order + 1, $r['id']]);
                        }
                        $success = 'Orden del recurso actualizado correctamente.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error al reordenar recurso: ' . $e->getMessage();
            }
        }
    }
    
    // 9. Reordenar clase (Subir/Bajar posición)
    elseif ($action === 'reorder_lesson') {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        if ($lesson_id > 0 && in_array($direction, ['up', 'down'])) {
            try {
                $stmt = $pdo->prepare("SELECT id, order_number FROM lessons WHERE course_id = ? ORDER BY order_number ASC, id ASC");
                $stmt->execute([$course_id]);
                $all_les = $stmt->fetchAll();
                
                $target_idx = -1;
                foreach ($all_les as $idx => $l) {
                    if ($l['id'] == $lesson_id) {
                        $target_idx = $idx;
                        break;
                    }
                }
                
                if ($target_idx !== -1) {
                    $swap_idx = ($direction === 'up') ? $target_idx - 1 : $target_idx + 1;
                    if ($swap_idx >= 0 && $swap_idx < count($all_les)) {
                        $temp = $all_les[$target_idx];
                        $all_les[$target_idx] = $all_les[$swap_idx];
                        $all_les[$swap_idx] = $temp;
                        
                        foreach ($all_les as $new_order => $l) {
                            $stmtUpd = $pdo->prepare("UPDATE lessons SET order_number = ? WHERE id = ?");
                            $stmtUpd->execute([$new_order + 1, $l['id']]);
                        }
                        $success = 'Orden de la clase actualizado correctamente.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error al reordenar clase: ' . $e->getMessage();
            }
        }
    }
    
    // 7b. Reordenamiento por Drag and Drop (AJAX)
    elseif ($action === 'save_order_drag') {
        header('Content-Type: application/json');
        $type = $_POST['type'] ?? 'lessons';
        $ordered_ids = $_POST['ordered_ids'] ?? [];

        if (!is_array($ordered_ids) || empty($ordered_ids)) {
            echo json_encode(['success' => false, 'error' => 'Datos de orden no válidos']);
            exit;
        }

        try {
            $table = ($type === 'resources') ? 'resources' : 'lessons';
            $stmt = $pdo->prepare("UPDATE `$table` SET order_number = ? WHERE id = ? AND course_id = ?");
            foreach ($ordered_ids as $index => $item_id) {
                $stmt->execute([$index + 1, intval($item_id), $course_id]);
            }
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // 8. Inscribir alumno manualmente (Dar acceso)
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

    // Respuesta JSON si la petición fue una subida AJAX con barra de progreso
    if (!empty($_POST['ajax_upload'])) {
        header('Content-Type: application/json');
        if (!empty($error)) {
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode(['success' => true, 'message' => $success]);
        }
        exit;
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
    
    // 3. Obtener recursos PDFs y Audios (ordenados)
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE course_id = ? ORDER BY order_number ASC, id ASC");
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
        <?php 
        $c_status = $course['status'] ?? 'publicado';
        $status_badge = '';
        if ($c_status === 'publicado') {
            $status_badge = '<span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4); padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">🟢 Publicado</span>';
        } elseif ($c_status === 'despublicado') {
            $status_badge = '<span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">🟡 Despublicado (Borrador)</span>';
        } else {
            $status_badge = '<span style="background: rgba(156, 163, 175, 0.15); color: #9ca3af; border: 1px solid rgba(156, 163, 175, 0.4); padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">⚪ Archivado</span>';
        }
        ?>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 5px;">
            <h1 style="font-size: 26px; margin: 0;">Editar: <?php echo h($course['title']); ?></h1>
            <div><?php echo $status_badge; ?></div>
        </div>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 30px;">Agrega videos en orden, sube recursos PDFs y gestiona el acceso de los alumnos.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" id="courseSuccessAlert"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <!-- 1. Datos Generales del Curso -->
        <div class="card" style="margin-bottom: 30px;">
            <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">✏️ Datos del Curso</h3>
            <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="update_course">
                
                <div class="admin-two-cols-equal">
                    <div>
                        <div class="form-group">
                            <label class="form-label" for="title">Título del Curso</label>
                            <input class="form-control" type="text" id="title" name="title" required value="<?php echo h($course['title']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="description">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo h($course['description']); ?></textarea>
                        </div>

                        <div class="form-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 12px; border-radius: 8px;">
                            <label class="form-label" for="status" style="font-weight: 600; color: var(--heading-color); display: flex; align-items: center; justify-content: space-between;">
                                <span>🌐 Estado del Curso</span>
                                <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">Visibilidad en Catálogo</span>
                            </label>
                            <select class="form-control" id="status" name="status" style="font-weight: 500;">
                                <option value="publicado" <?php echo ($c_status === 'publicado') ? 'selected' : ''; ?>>🟢 Publicado (Visible en catálogo y abierto a inscripciones)</option>
                                <option value="despublicado" <?php echo ($c_status === 'despublicado') ? 'selected' : ''; ?>>🟡 Despublicado / Borrador (Oculto del público mientras lo construyes)</option>
                                <option value="archivado" <?php echo ($c_status === 'archivado') ? 'selected' : ''; ?>>⚪ Archivado (Oculto, sin nuevas compras)</option>
                            </select>
                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                                Déjalo en <strong>Despublicado</strong> mientras creas las clases. Nadie podrá verlo ni comprarlo hasta que lo pongas en Publicado.
                            </span>
                        </div>
                    </div>

                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label class="form-label" for="price">Precio Regular (Guaraníes)</label>
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

                        <!-- Configuración de Oferta Flash 15 Minutos -->
                        <div style="background: rgba(85, 100, 241, 0.08); border: 1px solid var(--border-glow); padding: 12px 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="font-size: 13px; color: #a78bfa; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                                🔥 Oferta Flash de Bienvenida (Contador Limitado)
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" for="promo_price" style="font-size: 11px;">Precio Oferta (₲)</label>
                                    <input class="form-control" type="number" id="promo_price" name="promo_price" placeholder="Ej: 65000" value="<?php echo $course['promo_price'] ?? ''; ?>">
                                    <span style="font-size: 10px; color: var(--text-muted); margin-top: 2px; display: block;">Vacío = sin oferta.</span>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" for="promo_duration_minutes" style="font-size: 11px;">Duración (Minutos)</label>
                                    <input class="form-control" type="number" id="promo_duration_minutes" name="promo_duration_minutes" min="1" value="<?php echo $course['promo_duration_minutes'] ?: 15; ?>">
                                    <span style="font-size: 10px; color: var(--text-muted); margin-top: 2px; display: block;">Límite para comprar.</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group" id="sub-period-group-edit" style="display: <?php echo ($course['payment_type'] === 'subscription') ? 'block' : 'none'; ?>;">
                            <label class="form-label" for="subscription_period">Periodo de Suscripción (en días)</label>
                            <input class="form-control" type="number" id="subscription_period" name="subscription_period" min="1" value="<?php echo $course['subscription_period'] ?: 30; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="thumbnail">Miniatura del Curso</label>
                            <?php if (!empty($course['thumbnail']) && file_exists('../uploads/' . $course['thumbnail'])): ?>
                                <div style="margin-bottom: 8px;">
                                    <img src="../uploads/<?php echo h($course['thumbnail']); ?>" style="width: 100px; border-radius: 4px; border: 1px solid var(--border-color);" alt="Vista previa">
                                </div>
                            <?php endif; ?>
                            <input class="form-control" type="file" id="thumbnail" name="thumbnail" accept="image/*" style="padding: 6px;">
                        </div>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">💾 Guardar Cambios del Curso</button>
                </div>
            </form>
        </div>
        
        <!-- 2. SECCIÓN PRINCIPAL: Clases del Curso (Paso a Paso) -->
        <div class="admin-two-cols">
            <!-- Tabla de Clases del Paso a Paso -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    <h3 style="font-size: 16px; margin: 0;">🎓 Paso a Paso del Curso (<?php echo count($lessons); ?> Clases)</h3>
                    <span style="font-size: 12px; color: var(--text-muted);">Videos, Audios o Lecturas PDF</span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-lessons">
                        <thead>
                            <tr>
                                <th style="width: 75px; text-align: center;">Orden</th>
                                <th>Título de la Clase</th>
                                <th>Formato / Archivo</th>
                                <th style="width: 170px; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="lessons-table-body">
                            <?php if (empty($lessons)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 25px;">
                                        No hay clases agregadas todavía.<br>Crea tu primera clase usando el formulario de la derecha.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lessons as $index => $les): ?>
                                    <?php 
                                    $is_les_bonus = !empty($les['is_bonus']);
                                    $has_vid = !empty($les['video_url']);
                                    $has_aud = !empty($les['audio_file']);
                                    $has_pdf = !empty($les['pdf_file']);
                                    ?>
                                    <tr class="draggable-lesson-row" draggable="true"
                                        data-id="<?php echo $les['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($les['title'], ENT_QUOTES); ?>"
                                        data-video="<?php echo htmlspecialchars($les['video_url'] ?? '', ENT_QUOTES); ?>"
                                        data-audio="<?php echo htmlspecialchars($les['audio_file'] ?? '', ENT_QUOTES); ?>"
                                        data-pdf="<?php echo htmlspecialchars($les['pdf_file'] ?? '', ENT_QUOTES); ?>"
                                        data-order="<?php echo $les['order_number']; ?>"
                                        data-bonus="<?php echo $is_les_bonus ? '1' : '0'; ?>"
                                        style="<?php echo $is_les_bonus ? 'background: rgba(167, 139, 250, 0.05);' : ''; ?>">
                                        <td style="text-align: center; vertical-align: middle;">
                                            <div style="display: inline-flex; align-items: center; justify-content: center; gap: 5px;">
                                                <span class="drag-handle" title="Arrastra para reordenar clases">⠿</span>
                                                <span class="order-badge" style="font-weight: 700; color: var(--primary-color); min-width: 24px;">
                                                    #<?php echo $les['order_number']; ?>
                                                </span>
                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                    <?php if ($index > 0): ?>
                                                    <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" style="margin: 0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="reorder_lesson">
                                                        <input type="hidden" name="lesson_id" value="<?php echo $les['id']; ?>">
                                                        <input type="hidden" name="direction" value="up">
                                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 1px 5px; font-size: 8px; line-height: 1; min-height: unset; height: 16px;" title="Subir posición">▲</button>
                                                    </form>
                                                    <?php else: ?>
                                                        <button disabled class="btn btn-secondary btn-sm" style="padding: 1px 5px; font-size: 8px; line-height: 1; min-height: unset; height: 16px; opacity: 0.2; cursor: default;">▲</button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($index < count($lessons) - 1): ?>
                                                    <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" style="margin: 0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="reorder_lesson">
                                                        <input type="hidden" name="lesson_id" value="<?php echo $les['id']; ?>">
                                                        <input type="hidden" name="direction" value="down">
                                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 1px 5px; font-size: 8px; line-height: 1; min-height: unset; height: 16px;" title="Bajar posición">▼</button>
                                                    </form>
                                                    <?php else: ?>
                                                        <button disabled class="btn btn-secondary btn-sm" style="padding: 1px 5px; font-size: 8px; line-height: 1; min-height: unset; height: 16px; opacity: 0.2; cursor: default;">▼</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="color: var(--heading-color); font-weight: 500;">
                                            <div class="lesson-title-display"><?php echo h($les['title']); ?></div>
                                            <?php if ($is_les_bonus): ?>
                                                <span style="background: rgba(167, 139, 250, 0.2); color: #a78bfa; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; border: 1px solid rgba(167,139,250,0.4); display: inline-block; margin-top: 3px;">🎁 BONUS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 12px; color: var(--text-muted);">
                                            <?php if ($has_vid): ?>
                                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px;">
                                                    🎥 Video: <span title="<?php echo h($les['video_url']); ?>"><?php echo h($les['video_url']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($has_aud): ?>
                                                <div style="color: #a78bfa; margin-top: 2px;">
                                                    🎵 Audio: <a href="../uploads/<?php echo h($les['audio_file']); ?>" target="_blank" style="color: #a78bfa; text-decoration: underline;">Escuchar</a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($has_pdf): ?>
                                                <div style="color: #ef4444; margin-top: 2px;">
                                                    📕 Lectura PDF: <a href="../student/view_pdf.php?lesson_id=<?php echo $les['id']; ?>&type=lesson" target="_blank" style="color: #f87171; text-decoration: underline;">Abrir PDF</a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!$has_vid && !$has_aud && !$has_pdf): ?>
                                                <span style="color: var(--text-muted); font-style: italic;">Sin archivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="openEditLessonModal(this.closest('tr'))" style="padding: 4px 7px; font-size: 10px; color: #a78bfa; font-weight: 600; border-color: rgba(167, 139, 250, 0.4);" title="Editar título y contenido de la clase">
                                                    ✏️ Editar
                                                </button>
                                                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="toggle_lesson_bonus">
                                                    <input type="hidden" name="lesson_id" value="<?php echo $les['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 6px; font-size: 10px;" title="<?php echo $is_les_bonus ? 'Convertir en clase regular' : 'Marcar como material bonus'; ?>">
                                                        <?php echo $is_les_bonus ? 'Quitar Bonus' : '🎁 Bonus'; ?>
                                                    </button>
                                                </form>
                                                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" onsubmit="return confirm('¿Seguro que quieres borrar esta clase?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete_lesson">
                                                    <input type="hidden" name="lesson_id" value="<?php echo $les['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 6px; font-size: 10px;" title="Eliminar clase">✕</button>
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
            
            <!-- Formulario para Agregar Clase (Video, Audio o PDF) -->
            <div class="card" style="border-color: var(--primary-color);">
                <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    ➕ Agregar Clase al Paso a Paso
                </h3>
                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" class="upload-progress-form">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_lesson">
                    
                    <div class="form-group">
                        <label class="form-label" for="lesson_title">Título de la Clase</label>
                        <input class="form-control" type="text" id="lesson_title" name="lesson_title" required placeholder="Ej: Clase 1: Los secretos de un buen gancho">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="video_url">🎥 Enlace de Video (YouTube / Vimeo / MP4)</label>
                        <input class="form-control" type="url" id="video_url" name="video_url" placeholder="https://www.youtube.com/embed/... o https://youtu.be/...">
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                            Enlace de video en YouTube (Privado/No listado) o Vimeo.
                        </span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="audio_file">🎵 Archivo de Audio (Guardado en Servidor)</label>
                        <input class="form-control" type="file" id="audio_file" name="audio_file" accept="audio/*,.mp3,.wav,.m4a,.ogg,.aac,.flac" style="padding: 6px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                            Sube un audio (MP3, WAV, M4A) para reproducir de forma protegida.
                        </span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pdf_file">📕 Documento PDF de Lectura (Clase en PDF)</label>
                        <input class="form-control" type="file" id="pdf_file" name="pdf_file" accept=".pdf,application/pdf" style="padding: 6px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                            Sube una guía o lección en PDF que el alumno podrá leer en pantalla.
                        </span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="order_number">Número de Orden</label>
                        <input class="form-control" type="number" id="order_number" name="order_number" min="1" value="<?php echo empty($lessons) ? 1 : (max(array_column($lessons, 'order_number')) + 1); ?>">
                    </div>

                    <div class="form-group" style="background: rgba(167, 139, 250, 0.08); border: 1px solid var(--border-glow); padding: 12px; border-radius: 8px;">
                        <label class="bonus-checkbox-label">
                            <input type="checkbox" name="is_bonus" value="1" style="width: 16px; height: 16px; margin-top: 2px;">
                            <span>🎁 Marcar como Contenido Bonus (No afectará el % de progreso)</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full" style="font-weight: 600;">➕ Añadir Clase al Paso a Paso</button>
                </form>
            </div>
        </div>
        
        <!-- 3. SECCIÓN SECUNDARIA: Recursos de Apoyo Adicionales -->
        <div class="admin-two-cols">
            <!-- Listado de Recursos de Apoyo -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    <h3 style="font-size: 16px; margin: 0;">📂 Recursos y Material de Apoyo Adicional (<?php echo count($resources); ?>)</h3>
                    <span style="font-size: 12px; color: var(--text-muted);"><span style="color: #a78bfa;">⠿ Arrastra</span> para reordenar</span>
                </div>
                
                <div id="resources-list-container" style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if (empty($resources)): ?>
                        <p style="font-size: 13px; color: var(--text-muted); text-align: center; padding: 20px;">
                            No hay recursos de apoyo adicionales subidos para este curso.
                        </p>
                    <?php else: ?>
                        <?php foreach ($resources as $res_idx => $res): ?>
                            <?php 
                            $res_ext = strtolower(pathinfo($res['file_path'], PATHINFO_EXTENSION));
                            $is_audio = in_array($res_ext, ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac']);
                            $is_res_bonus = !empty($res['is_bonus']);
                            ?>
                            <div class="resource-file draggable-resource-item" draggable="true"
                                data-id="<?php echo $res['id']; ?>"
                                data-title="<?php echo htmlspecialchars($res['title'], ENT_QUOTES); ?>"
                                data-file="<?php echo htmlspecialchars($res['file_path'] ?? '', ENT_QUOTES); ?>"
                                data-order="<?php echo $res['order_number']; ?>"
                                data-bonus="<?php echo $is_res_bonus ? '1' : '0'; ?>"
                                style="justify-content: space-between; align-items: center; <?php echo $is_res_bonus ? 'border-color: #a78bfa; background: rgba(167, 139, 250, 0.05);' : ''; ?>">
                                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden; flex: 1;">
                                    <div style="display: inline-flex; align-items: center; justify-content: center; gap: 3px; margin-right: 4px;">
                                        <span class="drag-handle" title="Arrastra para reordenar recursos">⠿</span>
                                        <span class="order-badge" style="font-weight: 700; color: var(--primary-color); font-size: 11px; min-width: 22px; text-align: center;">
                                            #<?php echo $res['order_number']; ?>
                                        </span>
                                        <div style="display: flex; flex-direction: column; gap: 2px;">
                                            <?php if ($res_idx > 0): ?>
                                            <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="action" value="reorder_resource">
                                                <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 8px; line-height: 1; min-height: unset; height: 15px;" title="Subir posición">▲</button>
                                            </form>
                                            <?php else: ?>
                                                <button disabled class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 8px; line-height: 1; min-height: unset; height: 15px; opacity: 0.2; cursor: default;">▲</button>
                                            <?php endif; ?>
                                            
                                            <?php if ($res_idx < count($resources) - 1): ?>
                                            <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="action" value="reorder_resource">
                                                <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 8px; line-height: 1; min-height: unset; height: 15px;" title="Bajar posición">▼</button>
                                            </form>
                                            <?php else: ?>
                                                <button disabled class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 8px; line-height: 1; min-height: unset; height: 15px; opacity: 0.2; cursor: default;">▼</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="resource-icon"><?php echo $is_audio ? '🎵' : '📕'; ?></span>
                                    <div class="resource-meta">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <span style="font-weight: 500; color: var(--heading-color);"><?php echo h($res['title']); ?></span>
                                            <?php if ($is_res_bonus): ?>
                                                <span style="background: rgba(167, 139, 250, 0.2); color: #a78bfa; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; border: 1px solid rgba(167,139,250,0.4);">🎁 BONUS</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
                                            <a href="../student/view_pdf.php?id=<?php echo $res['id']; ?>" target="_blank" style="font-size: 11px;">
                                                <?php echo $is_audio ? 'Escuchar Audio' : 'Abrir PDF'; ?>
                                            </a>
                                            <span style="color: var(--text-muted); font-size: 10px;">•</span>
                                            <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="action" value="toggle_resource_bonus">
                                                <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                                <button type="submit" style="background: none; border: none; color: <?php echo $is_res_bonus ? '#f59e0b' : '#a78bfa'; ?>; font-size: 11px; cursor: pointer; padding: 0; text-decoration: underline;">
                                                    <?php echo $is_res_bonus ? 'Quitar Bonus' : 'Hacer Bonus'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEditResourceModal(this.closest('.resource-file'))" style="padding: 4px 7px; font-size: 11px; color: #a78bfa; font-weight: 600; border-color: rgba(167, 139, 250, 0.4);" title="Editar recurso">
                                        ✏️ Editar
                                    </button>
                                    <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" onsubmit="return confirm('¿Eliminar este recurso de apoyo?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete_resource">
                                        <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 11px;">Borrar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Subir Recurso de Apoyo -->
            <div class="card">
                <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    📤 Subir Recurso de Apoyo
                </h3>
                <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" class="upload-progress-form">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_resource">
                    
                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label" for="resource_title">Nombre del Recurso</label>
                            <input class="form-control" type="text" id="resource_title" name="resource_title" required placeholder="Ej: Plantilla de correo o PDF de apoyo">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="resource_order_number">N° Orden</label>
                            <input class="form-control" type="number" id="resource_order_number" name="order_number" min="1" value="<?php echo empty($resources) ? 1 : (max(array_column($resources, 'order_number')) + 1); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="resource_file">Archivo (PDF o Audio MP3/WAV)</label>
                        <input class="form-control" type="file" id="resource_file" name="resource_file" accept=".pdf,audio/*,.mp3,.wav,.m4a,.ogg,.aac,.flac" required style="padding: 6px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--heading-color);">
                            <input type="checkbox" name="is_bonus" value="1" style="width: 16px; height: 16px;">
                            <span>🎁 Marcar como Material Bonus</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary btn-full">📤 Subir Recurso al Servidor</button>
                </form>
            </div>
        </div>
        
        <!-- Gestión de Accesos (Alumnos Inscriptos) -->
        <div class="card">
            <h3 style="font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">👥 Alumnos Inscritos y Control de Acceso</h3>
            
            <div class="admin-two-cols-sidebar">
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

<!-- Modal: Editar Clase / Lección -->
<div id="editLessonModal" class="lms-modal-backdrop" onclick="if(event.target === this) closeEditLessonModal();">
    <div class="lms-modal-card" style="max-width: 650px;">
        <div class="lms-modal-header">
            <h3>✏️ Editar Clase del Paso a Paso</h3>
            <button type="button" class="lms-modal-close" onclick="closeEditLessonModal()" aria-label="Cerrar modal">&times;</button>
        </div>
        <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" class="upload-progress-form">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update_lesson">
            <input type="hidden" name="lesson_id" id="edit_lesson_id" value="">
            
            <div class="lms-modal-body" style="display: flex; flex-direction: column; gap: 14px; max-height: 70vh;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_lesson_title">Título de la Clase *</label>
                    <input class="form-control" type="text" id="edit_lesson_title" name="lesson_title" required placeholder="Ej: Clase 1: Fundamentos de persuasión">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_video_url">🎥 Enlace de Video (YouTube / Vimeo / MP4)</label>
                    <input class="form-control" type="url" id="edit_video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=... o https://youtu.be/...">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                        Deja vacío si esta clase es solo de audio o lectura PDF.
                    </span>
                </div>

                <div class="form-group" style="margin-bottom: 0; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <label class="form-label" for="edit_audio_file" style="margin-bottom: 4px;">🎵 Archivo de Audio (MP3, WAV, M4A)</label>
                    <div id="edit_current_audio_box" style="font-size: 12px; color: #a78bfa; margin-bottom: 8px; display: none;">
                        <span>Audio actual: <strong id="edit_current_audio_name"></strong></span>
                        <label style="display: inline-flex; align-items: center; gap: 4px; margin-left: 12px; color: #ef4444; cursor: pointer; font-size: 11px;">
                            <input type="checkbox" name="remove_audio" id="edit_remove_audio" value="1">
                            <span>Quitar audio actual</span>
                        </label>
                    </div>
                    <input class="form-control" type="file" id="edit_audio_file" name="audio_file" accept="audio/*,.mp3,.wav,.m4a,.ogg,.aac,.flac" style="padding: 5px;">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                        Sube un archivo nuevo solo si deseas reemplazar el actual.
                    </span>
                </div>

                <div class="form-group" style="margin-bottom: 0; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <label class="form-label" for="edit_pdf_file" style="margin-bottom: 4px;">📕 Documento PDF de Lectura</label>
                    <div id="edit_current_pdf_box" style="font-size: 12px; color: #f87171; margin-bottom: 8px; display: none;">
                        <span>PDF actual: <strong id="edit_current_pdf_name"></strong></span>
                        <label style="display: inline-flex; align-items: center; gap: 4px; margin-left: 12px; color: #ef4444; cursor: pointer; font-size: 11px;">
                            <input type="checkbox" name="remove_pdf" id="edit_remove_pdf" value="1">
                            <span>Quitar PDF actual</span>
                        </label>
                    </div>
                    <input class="form-control" type="file" id="edit_pdf_file" name="pdf_file" accept=".pdf,application/pdf" style="padding: 5px;">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                        Sube un PDF nuevo solo si deseas reemplazar el actual.
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: 140px 1fr; gap: 12px; align-items: center;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="edit_lesson_order">N° Orden</label>
                        <input class="form-control" type="number" id="edit_lesson_order" name="order_number" min="1" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; background: rgba(167, 139, 250, 0.08); border: 1px solid var(--border-glow); padding: 10px; border-radius: 8px; margin-top: 18px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12px; margin: 0; color: var(--heading-color);">
                            <input type="checkbox" name="is_bonus" id="edit_lesson_bonus" value="1" style="width: 16px; height: 16px;">
                            <span>🎁 Marcar como Contenido Bonus</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="lms-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditLessonModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">💾 Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Recurso de Apoyo -->
<div id="editResourceModal" class="lms-modal-backdrop" onclick="if(event.target === this) closeEditResourceModal();">
    <div class="lms-modal-card" style="max-width: 580px;">
        <div class="lms-modal-header">
            <h3>✏️ Editar Recurso de Apoyo</h3>
            <button type="button" class="lms-modal-close" onclick="closeEditResourceModal()" aria-label="Cerrar modal">&times;</button>
        </div>
        <form action="course_edit.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" class="upload-progress-form">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update_resource">
            <input type="hidden" name="resource_id" id="edit_resource_id" value="">
            
            <div class="lms-modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_resource_title">Nombre del Recurso *</label>
                    <input class="form-control" type="text" id="edit_resource_title" name="resource_title" required placeholder="Ej: Plantilla o Guía en PDF">
                </div>

                <div class="form-group" style="margin-bottom: 0; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <label class="form-label" for="edit_resource_file" style="margin-bottom: 4px;">Reemplazar Archivo (PDF o Audio)</label>
                    <div id="edit_current_res_file_box" style="font-size: 12px; color: var(--primary-color); margin-bottom: 8px; display: none;">
                        <span>Archivo actual: <strong id="edit_current_res_file_name"></strong></span>
                    </div>
                    <input class="form-control" type="file" id="edit_resource_file" name="resource_file" accept=".pdf,audio/*,.mp3,.wav,.m4a,.ogg,.aac,.flac" style="padding: 5px;">
                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                        Deja vacío para conservar el archivo actual en el servidor.
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 12px; align-items: center;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="edit_resource_order">N° Orden</label>
                        <input class="form-control" type="number" id="edit_resource_order" name="order_number" min="1" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; background: rgba(167, 139, 250, 0.08); border: 1px solid var(--border-glow); padding: 10px; border-radius: 8px; margin-top: 18px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12px; margin: 0; color: var(--heading-color);">
                            <input type="checkbox" name="is_bonus" id="edit_resource_bonus" value="1" style="width: 16px; height: 16px;">
                            <span>🎁 Marcar como Material Bonus</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="lms-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditResourceModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">💾 Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Floating Toast -->
<div id="lmsToast" class="lms-toast">
    <span id="lmsToastIcon">✓</span>
    <span id="lmsToastMsg">Operación completada</span>
</div>

<!-- Modal de Progreso de Subida de Archivos -->
<div id="uploadProgressModal" class="upload-progress-backdrop">
    <div class="upload-progress-card">
        <div id="uploadProgressIcon" class="upload-progress-icon">📤</div>
        <div id="uploadProgressTitle" class="upload-progress-title">Subiendo Archivo al Servidor</div>
        <div id="uploadProgressFilename" class="upload-progress-filename">archivo.mp3</div>
        
        <div class="upload-progress-track">
            <div id="uploadProgressBar" class="upload-progress-fill"></div>
        </div>
        
        <div class="upload-progress-stats">
            <span id="uploadProgressLoaded">0 MB / 0 MB</span>
            <span id="uploadProgressPct" class="upload-progress-pct">0%</span>
        </div>
        
        <div id="uploadProgressStatus" class="upload-progress-status">Iniciando subida... Por favor espera sin cerrar la ventana.</div>
        
        <div id="uploadProgressActions" style="margin-top: 18px; display: none;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeUploadProgressModal()">Cerrar y Revisar</button>
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

// Modal open/close functions
function openEditLessonModal(row) {
    if (!row) return;
    document.getElementById('edit_lesson_id').value = row.dataset.id || '';
    document.getElementById('edit_lesson_title').value = row.dataset.title || '';
    document.getElementById('edit_video_url').value = row.dataset.video || '';
    document.getElementById('edit_lesson_order').value = row.dataset.order || '1';
    document.getElementById('edit_lesson_bonus').checked = (row.dataset.bonus === '1');
    
    // Audio info
    const audioBox = document.getElementById('edit_current_audio_box');
    const audioName = document.getElementById('edit_current_audio_name');
    const removeAudio = document.getElementById('edit_remove_audio');
    if (removeAudio) removeAudio.checked = false;
    if (row.dataset.audio) {
        audioName.textContent = row.dataset.audio;
        audioBox.style.display = 'block';
    } else {
        audioBox.style.display = 'none';
    }
    document.getElementById('edit_audio_file').value = '';

    // PDF info
    const pdfBox = document.getElementById('edit_current_pdf_box');
    const pdfName = document.getElementById('edit_current_pdf_name');
    const removePdf = document.getElementById('edit_remove_pdf');
    if (removePdf) removePdf.checked = false;
    if (row.dataset.pdf) {
        pdfName.textContent = row.dataset.pdf;
        pdfBox.style.display = 'block';
    } else {
        pdfBox.style.display = 'none';
    }
    document.getElementById('edit_pdf_file').value = '';

    const modal = document.getElementById('editLessonModal');
    modal.classList.add('active');
    setTimeout(() => document.getElementById('edit_lesson_title').focus(), 50);
}

function closeEditLessonModal() {
    const modal = document.getElementById('editLessonModal');
    if (modal) modal.classList.remove('active');
}

function openEditResourceModal(el) {
    if (!el) return;
    document.getElementById('edit_resource_id').value = el.dataset.id || '';
    document.getElementById('edit_resource_title').value = el.dataset.title || '';
    document.getElementById('edit_resource_order').value = el.dataset.order || '1';
    document.getElementById('edit_resource_bonus').checked = (el.dataset.bonus === '1');
    
    const fileBox = document.getElementById('edit_current_res_file_box');
    const fileName = document.getElementById('edit_current_res_file_name');
    if (el.dataset.file) {
        fileName.textContent = el.dataset.file;
        fileBox.style.display = 'block';
    } else {
        fileBox.style.display = 'none';
    }
    document.getElementById('edit_resource_file').value = '';

    const modal = document.getElementById('editResourceModal');
    modal.classList.add('active');
    setTimeout(() => document.getElementById('edit_resource_title').focus(), 50);
}

function closeEditResourceModal() {
    const modal = document.getElementById('editResourceModal');
    if (modal) modal.classList.remove('active');
}

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditLessonModal();
        closeEditResourceModal();
    }
});

function showLmsToast(message, isError) {
    const toast = document.getElementById('lmsToast');
    const msg = document.getElementById('lmsToastMsg');
    const icon = document.getElementById('lmsToastIcon');
    if (!toast) return;
    
    msg.textContent = message;
    icon.textContent = isError ? '⚠️' : '✓';
    toast.style.borderColor = isError ? 'var(--danger)' : 'var(--primary-color)';
    toast.classList.add('show');
    
    clearTimeout(window.__lmsToastTimer);
    window.__lmsToastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3200);
}

// Drag and drop engine
function setupDragAndDrop(container, itemSelector, type) {
    if (!container) return;
    let draggedItem = null;
    let currentDropTarget = null;
    let dropPosition = null;

    const getItems = () => Array.from(container.querySelectorAll(itemSelector));

    container.addEventListener('dragstart', (e) => {
        const item = e.target.closest(itemSelector);
        if (!item) return;
        draggedItem = item;
        item.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.dataset.id || '');
    });

    container.addEventListener('dragend', () => {
        if (draggedItem) {
            draggedItem.classList.remove('is-dragging');
            draggedItem = null;
        }
        clearDropIndicators();
    });

    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const target = e.target.closest(itemSelector);
        if (!target || target === draggedItem) {
            clearDropIndicators();
            return;
        }

        const rect = target.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        const newPos = e.clientY < midY ? 'above' : 'below';

        if (currentDropTarget !== target || dropPosition !== newPos) {
            clearDropIndicators();
            currentDropTarget = target;
            dropPosition = newPos;
            target.classList.add(dropPosition === 'above' ? 'drop-target-above' : 'drop-target-below');
        }
    });

    container.addEventListener('dragleave', (e) => {
        if (!container.contains(e.relatedTarget)) {
            clearDropIndicators();
        }
    });

    container.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!draggedItem || !currentDropTarget || draggedItem === currentDropTarget) {
            clearDropIndicators();
            return;
        }

        if (dropPosition === 'above') {
            container.insertBefore(draggedItem, currentDropTarget);
        } else {
            container.insertBefore(draggedItem, currentDropTarget.nextSibling);
        }

        clearDropIndicators();
        updateAndSaveOrder();
    });

    function clearDropIndicators() {
        if (currentDropTarget) {
            currentDropTarget.classList.remove('drop-target-above', 'drop-target-below');
            currentDropTarget = null;
        }
        dropPosition = null;
    }

    function updateAndSaveOrder() {
        const items = getItems();
        const orderedIds = [];

        items.forEach((item, index) => {
            const newOrder = index + 1;
            item.dataset.order = newOrder;
            const badge = item.querySelector('.order-badge');
            if (badge) {
                badge.textContent = '#' + newOrder;
            }
            if (item.dataset.id) {
                orderedIds.push(item.dataset.id);
            }
        });

        if (orderedIds.length === 0) return;

        const formData = new FormData();
        formData.append('csrf_token', '<?php echo csrf_token(); ?>');
        formData.append('action', 'save_order_drag');
        formData.append('type', type);
        orderedIds.forEach(id => formData.append('ordered_ids[]', id));

        fetch('course_edit.php?id=<?php echo $course_id; ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showLmsToast(type === 'lessons' ? '✓ Orden de clases guardado correctamente' : '✓ Orden de recursos guardado correctamente');
            } else {
                showLmsToast('Error al guardar el orden: ' + (data.error || 'Desconocido'), true);
            }
        })
        .catch(err => {
            console.error('Error reordenando:', err);
            showLmsToast('Error al guardar el orden', true);
        });
    }
}

// Upload progress handling
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function openUploadProgressModal(fileName, totalBytes) {
    const modal = document.getElementById('uploadProgressModal');
    const bar = document.getElementById('uploadProgressBar');
    const icon = document.getElementById('uploadProgressIcon');
    const title = document.getElementById('uploadProgressTitle');
    const nameEl = document.getElementById('uploadProgressFilename');
    const stats = document.getElementById('uploadProgressLoaded');
    const pct = document.getElementById('uploadProgressPct');
    const status = document.getElementById('uploadProgressStatus');
    const actions = document.getElementById('uploadProgressActions');

    bar.className = 'upload-progress-fill';
    bar.style.width = '0%';
    icon.textContent = '📤';
    title.textContent = 'Subiendo Archivo al Servidor';
    nameEl.textContent = fileName || 'Procesando contenido...';
    stats.textContent = totalBytes > 0 ? ('0 MB / ' + formatBytes(totalBytes)) : 'Calculando tamaño...';
    pct.textContent = '0%';
    status.textContent = 'Subiendo al servidor... Por favor espera sin cerrar la ventana.';
    actions.style.display = 'none';

    modal.classList.add('active');
}

function closeUploadProgressModal() {
    const modal = document.getElementById('uploadProgressModal');
    if (modal) modal.classList.remove('active');
}

function setupUploadProgressForms() {
    document.querySelectorAll('form.upload-progress-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                return;
            }

            const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
            let totalFilesSize = 0;
            let fileNames = [];

            fileInputs.forEach(input => {
                if (input.files && input.files.length > 0) {
                    for (let i = 0; i < input.files.length; i++) {
                        totalFilesSize += input.files[i].size;
                        fileNames.push(input.files[i].name);
                    }
                }
            });

            e.preventDefault();

            const displayName = fileNames.length > 0 ? fileNames.join(', ') : 'Guardando información...';
            openUploadProgressModal(displayName, totalFilesSize);

            const formData = new FormData(form);
            formData.append('ajax_upload', '1');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.getAttribute('action') || window.location.href, true);

            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.min(99, Math.round((event.loaded / event.total) * 100));
                    const bar = document.getElementById('uploadProgressBar');
                    const stats = document.getElementById('uploadProgressLoaded');
                    const pct = document.getElementById('uploadProgressPct');
                    const status = document.getElementById('uploadProgressStatus');
                    const title = document.getElementById('uploadProgressTitle');

                    bar.style.width = percentComplete + '%';
                    pct.textContent = percentComplete + '%';
                    stats.textContent = formatBytes(event.loaded) + ' / ' + formatBytes(event.total);

                    if (percentComplete >= 98) {
                        title.textContent = '⚙️ Procesando en el Servidor...';
                        status.textContent = 'El archivo se ha subido por completo. El servidor lo está guardando en el disco...';
                    }
                }
            };

            xhr.onload = function() {
                const bar = document.getElementById('uploadProgressBar');
                const icon = document.getElementById('uploadProgressIcon');
                const title = document.getElementById('uploadProgressTitle');
                const pct = document.getElementById('uploadProgressPct');
                const status = document.getElementById('uploadProgressStatus');
                const actions = document.getElementById('uploadProgressActions');

                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            bar.style.width = '100%';
                            bar.classList.add('success');
                            icon.textContent = '✓';
                            title.textContent = '¡Completado con Éxito!';
                            pct.textContent = '100%';
                            status.textContent = data.message || 'Contenido guardado correctamente.';

                            setTimeout(() => {
                                const targetUrl = 'course_edit.php?id=<?php echo $course_id; ?>&success_msg=' + encodeURIComponent(data.message || 'Operación completada con éxito.');
                                window.location.href = targetUrl;
                            }, 500);
                            return;
                        } else {
                            bar.classList.add('error');
                            icon.textContent = '⚠️';
                            title.textContent = 'No se pudo guardar';
                            status.textContent = data.error || 'Ocurrió un error al procesar los datos.';
                            actions.style.display = 'block';
                        }
                    } catch (parseErr) {
                        bar.classList.add('error');
                        icon.textContent = '⚠️';
                        title.textContent = 'Respuesta inesperada';
                        status.textContent = 'El servidor devolvió una respuesta no válida.';
                        actions.style.display = 'block';
                    }
                } else {
                    bar.classList.add('error');
                    icon.textContent = '⚠️';
                    title.textContent = 'Error del Servidor';
                    status.textContent = 'Error HTTP ' + xhr.status + ' al procesar la subida.';
                    actions.style.display = 'block';
                }
            };

            xhr.onerror = function() {
                const bar = document.getElementById('uploadProgressBar');
                const icon = document.getElementById('uploadProgressIcon');
                const title = document.getElementById('uploadProgressTitle');
                const status = document.getElementById('uploadProgressStatus');
                const actions = document.getElementById('uploadProgressActions');

                bar.classList.add('error');
                icon.textContent = '⚠️';
                title.textContent = 'Error de Conexión';
                status.textContent = 'Se perdió la conexión con el servidor durante la subida.';
                actions.style.display = 'block';
            };

            xhr.send(formData);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setupDragAndDrop(document.getElementById('lessons-table-body'), '.draggable-lesson-row', 'lessons');
    setupDragAndDrop(document.getElementById('resources-list-container'), '.draggable-resource-item', 'resources');
    setupUploadProgressForms();
});
</script>

<?php
require_once '../includes/footer.php';
?>
