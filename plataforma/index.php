<?php
// index.php - Catálogo de cursos público

require_once 'includes/auth_helper.php';
require_once 'config/db.php';

$page_title = 'Catálogo de Cursos';
$active_tab = 'catalog';

$user = get_logged_user();
$courses = [];

try {
    // Obtener todos los cursos
    $stmt = $pdo->query("SELECT * FROM courses ORDER BY created_at DESC");
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
            $formatted_price = number_format($course['price'], 0, ',', '.') . ' ₲';
            $is_subscription = ($course['payment_type'] === 'subscription');
            ?>
            <div class="card course-card">
                <div class="course-thumb">
                    <!-- Si tiene thumbnail se muestra, si no una tarjeta de diseño elegante con degradado -->
                    <?php if (!empty($course['thumbnail']) && file_exists('uploads/' . $course['thumbnail'])): ?>
                        <img src="uploads/<?php echo h($course['thumbnail']); ?>" alt="<?php echo h($course['title']); ?>">
                    <?php else: ?>
                        <div class="placeholder-icon">✍️</div>
                    <?php endif; ?>
                    
                    <span class="course-price-badge">
                        <?php echo $is_subscription ? 'Suscripción' : 'Compra Única'; ?>
                    </span>
                </div>
                
                <h3 class="course-title"><?php echo h($course['title']); ?></h3>
                <p class="course-desc"><?php echo h($course['description']); ?></p>
                
                <div class="course-footer">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted);">Precio:</div>
                        <div class="course-price">
                            <?php echo $formatted_price; ?>
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
                            <a href="checkout.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                <?php echo $is_subscription ? 'Suscribirse' : 'Comprar'; ?>
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
