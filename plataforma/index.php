<?php
// index.php - Catálogo de cursos público

require_once 'includes/auth_helper.php';
require_once 'config/db.php';

$page_title = 'Catálogo de Cursos';
$active_tab = 'catalog';

$user = get_logged_user();
$courses = [];

try {
    // Obtener cursos: solo publicados para el público, o todos si es administrador
    if ($user && $user['role'] === 'admin') {
        $stmt = $pdo->query("SELECT * FROM courses ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->query("SELECT * FROM courses WHERE status = 'publicado' ORDER BY created_at DESC");
    }
    $courses = $stmt->fetchAll();
    
    // Obtener los IDs de cursos en los que el usuario logueado ya está inscrito y no han caducado
    $my_enrollments = [];
    if ($user) {
        $stmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$user['id']]);
        $my_enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<?php
require_once 'includes/promo_helper.php';
?>

<div style="text-align: center; margin-bottom: 40px;">
    <h1 style="font-size: 32px; margin-bottom: 10px;">Aprende Copywriting y Persuasión</h1>
    <p style="color: var(--text-color); max-width: 600px; margin: 0 auto; font-size: 16px;">
        Descubre cómo convertir palabras en oro. Accede a nuestros programas de formación exclusivos, creados para copywriters y dueños de negocio.
    </p>
</div>

<div class="catalog-grid">
    <?php if (empty($courses)): ?>
        <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No hay cursos disponibles en este momento.</p>
    <?php else: ?>
        <?php foreach ($courses as $course): ?>
            <?php 
            $is_enrolled = in_array($course['id'], $my_enrollments);
            $is_subscription = ($course['payment_type'] === 'subscription');
            $promo = get_course_promo_status($pdo, $course['id']);
            $formatted_regular = format_price_usd_pyg($promo['regular_price']);
            $formatted_effective = format_price_usd_pyg($promo['effective_price']);
            ?>
            <div class="card course-card">
                <div class="course-thumb">
                    <!-- Si tiene thumbnail se muestra, si no una tarjeta de diseño elegante con degradado -->
                    <?php if (!empty($course['thumbnail']) && file_exists('uploads/' . $course['thumbnail'])): ?>
                        <img src="uploads/<?php echo h($course['thumbnail']); ?>" alt="<?php echo h($course['title']); ?>">
                    <?php else: ?>
                        <div class="placeholder-icon">✍️</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($course['status']) && $course['status'] !== 'publicado' && $user && $user['role'] === 'admin'): ?>
                        <span class="course-price-badge" style="background: <?php echo ($course['status'] === 'despublicado' ? '#f59e0b' : '#6b7280'); ?>; color: #111; font-weight: 700; left: 12px; right: auto;">
                            <?php echo ($course['status'] === 'despublicado') ? '🟡 Borrador (Oculto)' : '⚪ Archivado'; ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($promo['is_active']): ?>
                        <span class="course-price-badge" style="background: #ef4444; color: white;">
                            🔥 Oferta 15 Min ($-<?php echo $promo['discount_pct']; ?>%)
                        </span>
                    <?php else: ?>
                        <span class="course-price-badge">
                            <?php echo $is_subscription ? 'Suscripción' : 'Compra Única'; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <h3 class="course-title"><?php echo h($course['title']); ?></h3>
                <p class="course-desc"><?php echo h($course['description']); ?></p>
                
                <div class="course-footer">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted);">Precio:</div>
                        <div class="course-price" style="font-size: 14px;">
                            <?php if ($promo['is_active']): ?>
                                <span style="font-size: 12px; text-decoration: line-through; color: var(--text-muted); margin-right: 4px; font-weight: normal;"><?php echo $formatted_regular; ?></span>
                                <br><span style="color: #10b981; font-weight: 700;"><?php echo $formatted_effective; ?></span>
                            <?php else: ?>
                                <span><?php echo $formatted_regular; ?></span>
                            <?php endif; ?>
                            <?php if ($is_subscription): ?>
                                <span style="font-size: 12px; font-weight: normal; color: var(--text-muted);">/ mes</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <?php if ($is_enrolled): ?>
                            <a href="student/course_view.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success btn-sm">
                                🚀 Estudiar
                            </a>
                        <?php else: ?>
                            <a href="oferta.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                <?php echo $promo['is_active'] ? 'Ver Oferta ⚡' : ($is_subscription ? 'Suscribirse' : 'Comprar'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?>
