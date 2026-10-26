<?php
// student/index.php - Portal / Dashboard del estudiante

require_once '../includes/auth_helper.php';
require_once '../config/db.php';

require_login();
$user = get_logged_user();

$page_title = 'Mis Cursos';
$active_tab = 'student';

$my_courses = [];
$success_msg = '';

if (isset($_GET['payment']) && $_GET['payment'] === 'success') {
    $success_msg = '🎉 ¡Pago confirmado! Tu curso ha sido activado correctamente.';
    
    // Verificación y activación inmediata en caso de que el webhook de Pagopar aún no haya impactado
    $return_hash = trim($_GET['hash'] ?? '');
    if (!empty($return_hash) && $return_hash !== '($hash)') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE payment_hash = ? AND user_id = ?");
            $stmt->execute([$return_hash, $user['id']]);
            $pending_order = $stmt->fetch();
            
            if ($pending_order && $pending_order['status'] === 'pending') {
                require_once '../config/pagopar.php';
                $key_to_use = (PAGOPAR_ENV === 'development') ? 'DEVELOPMENT' : PAGOPAR_PRIVATE_KEY;
                $token_consulta = sha1($key_to_use . 'CONSULTA');
                
                $ch = curl_init('https://api.pagopar.com/api/pedidos/1.1/traer');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'hash_pedido' => $return_hash,
                    'token' => $token_consulta,
                    'token_publico' => PAGOPAR_PUBLIC_KEY
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $resp = curl_exec($ch);
                curl_close($ch);
                
                $pagopar_data = json_decode($resp, true);
                $is_really_paid = false;
                if ($pagopar_data && !empty($pagopar_data['resultado'])) {
                    foreach ($pagopar_data['resultado'] as $p_item) {
                        if (!empty($p_item['pagado']) && $p_item['pagado'] == true) {
                            $is_really_paid = true;
                            break;
                        }
                    }
                }
                
                if ($is_really_paid) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                    $stmt->execute([$pending_order['id']]);
                    
                    $stmt = $pdo->prepare("SELECT id, title, payment_type, subscription_period FROM courses WHERE id = ?");
                    $stmt->execute([$pending_order['course_id']]);
                    $course = $stmt->fetch();
                    
                    $expires_at = null;
                    if ($course && $course['payment_type'] === 'subscription') {
                        $period = intval($course['subscription_period'] ?: 30);
                        $expires_at = date('Y-m-d H:i:s', strtotime("+$period days"));
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO enrollments (user_id, course_id, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE expires_at = ?, created_at = NOW()
                    ");
                    $stmt->execute([$pending_order['user_id'], $pending_order['course_id'], $expires_at, $expires_at]);
                    $pdo->commit();
                    
                    try {
                        require_once '../includes/mail_helper.php';
                        notify_course_purchase($pdo, $pending_order['id'], $user['name'], $user['email'], $pending_order['course_id'], $course['title'], $pending_order['amount'], $course['payment_type']);
                    } catch (Exception $mailEx) {}
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

try {
    // Consultar cursos en los que el alumno está inscrito y el acceso no haya caducado
    $stmt = $pdo->prepare("
        SELECT c.*, e.expires_at, e.created_at as enrolled_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ? AND (e.expires_at IS NULL OR e.expires_at > NOW())
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $enrolled_courses = $stmt->fetchAll();
    
    // Calcular progreso para cada curso
    foreach ($enrolled_courses as $c) {
        // Cantidad de clases regulares del curso (los bonus no forman parte del porcentaje)
        $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ? AND is_bonus = 0");
        $stmt_total->execute([$c['id']]);
        $total_lessons = intval($stmt_total->fetchColumn());
        
        // Clases regulares marcadas como completadas
        $stmt_completed = $pdo->prepare("
            SELECT COUNT(*) 
            FROM progress p
            JOIN lessons l ON p.lesson_id = l.id
            WHERE l.course_id = ? AND l.is_bonus = 0 AND p.user_id = ? AND p.completed = 1
        ");
        $stmt_completed->execute([$c['id'], $user['id']]);
        $completed_lessons = intval($stmt_completed->fetchColumn());
        
        // Calcular porcentaje basado únicamente en las clases principales
        $pct = ($total_lessons > 0) ? round(($completed_lessons / $total_lessons) * 100) : 0;
        
        $my_courses[] = [
            'id' => $c['id'],
            'title' => $c['title'],
            'description' => $c['description'],
            'thumbnail' => $c['thumbnail'],
            'payment_type' => $c['payment_type'],
            'expires_at' => $c['expires_at'],
            'total_lessons' => $total_lessons,
            'completed_lessons' => $completed_lessons,
            'progress_pct' => $pct
        ];
    }
} catch (PDOException $e) {
    die("Error al cargar tus cursos: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="dashboard-header">
    <div>
        <h1 style="font-size: 28px;">👋 ¡Hola, <?php echo h($user['name']); ?>!</h1>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Este es tu portal de estudio. Haz clic en un curso para continuar aprendiendo.</p>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><?php echo h($success_msg); ?></div>
<?php endif; ?>

<div class="catalog-grid" style="margin-top: 20px;">
    <?php if (empty($my_courses)): ?>
        <div class="card" style="grid-column: 1/-1; text-align: center; padding: 40px;">
            <h3 style="margin-bottom: 10px;">Aún no tienes cursos inscritos</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;">Visita nuestro catálogo de capacitación y desbloquea el acceso a tus clases.</p>
            <a href="../index.php" class="btn btn-primary">Ver Catálogo de Cursos</a>
        </div>
    <?php else: ?>
        <?php foreach ($my_courses as $mc): ?>
            <div class="card course-card">
                <div class="course-thumb">
                    <?php if (!empty($mc['thumbnail']) && file_exists('../uploads/' . $mc['thumbnail'])): ?>
                        <img src="../uploads/<?php echo h($mc['thumbnail']); ?>" alt="<?php echo h($mc['title']); ?>">
                    <?php else: ?>
                        <div class="placeholder-icon">✍️</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($mc['expires_at'])): ?>
                        <span class="course-price-badge" style="background: rgba(245, 158, 11, 0.9); font-weight: 600;">
                            Suscripción Activa
                        </span>
                    <?php endif; ?>
                </div>
                
                <h3 class="course-title"><?php echo h($mc['title']); ?></h3>
                <p class="course-desc"><?php echo h($mc['description']); ?></p>
                
                <!-- Barra de Progreso del Alumno -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; color: var(--text-muted);">
                        <span>Progreso: <?php echo $mc['completed_lessons']; ?> / <?php echo $mc['total_lessons']; ?> clases</span>
                        <span style="font-weight: 600; color: var(--primary-color);"><?php echo $mc['progress_pct']; ?>%</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $mc['progress_pct']; ?>%;"></div>
                    </div>
                </div>
                
                <div class="course-footer">
                    <span style="font-size: 11px; color: var(--text-muted);">
                        <?php if (empty($mc['expires_at'])): ?>
                            Acceso de por vida
                        <?php else: ?>
                            Expira el: <?php echo date('d/m/Y', strtotime($mc['expires_at'])); ?>
                        <?php endif; ?>
                    </span>
                    <a href="course_view.php?course_id=<?php echo $mc['id']; ?>" class="btn btn-primary btn-sm">
                        <?php echo ($mc['completed_lessons'] > 0) ? 'Continuar ➡️' : 'Empezar 🚀'; ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
