<?php
// plataforma/oferta.php - Página de Oferta Exclusiva de Bienvenida con Contador Inquebrantable de 15 Minutos

require_once 'includes/auth_helper.php';
require_once 'config/db.php';
require_once 'includes/promo_helper.php';

$course_id = intval($_GET['id'] ?? $_GET['course_id'] ?? 1);

try {
    // 1. Obtener datos del curso
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: index.php');
        exit;
    }

    // 2. Calcular estado promocional de 15 minutos para este visitante
    $promo_status = get_course_promo_status($pdo, $course_id);

} catch (PDOException $e) {
    die("Error cargando la oferta: " . $e->getMessage());
}

$page_title = 'Oferta Especial: ' . $course['title'];
$active_tab = 'catalog';

require_once 'includes/header.php';

// Renderizar Banner Flotante con Contador de 15 Minutos
echo render_promo_banner_html($promo_status);
?>

<div class="container" style="max-width: 900px; margin: 30px auto;">

    <!-- Cabecera Principal de la Oferta -->
    <div style="text-align: center; margin-bottom: 40px;">
        <span class="badge-role admin" style="font-size: 13px; padding: 6px 16px; margin-bottom: 15px; display: inline-block;">
            ⚡ ACCESO EXCLUSIVO POR TIEMPO LIMITADO
        </span>
        <h1 style="font-size: 34px; line-height: 1.25; margin-bottom: 15px; color: var(--heading-color);">
            <?php echo h($course['title']); ?>
        </h1>
        <p style="color: var(--text-color); font-size: 17px; max-width: 720px; margin: 0 auto; line-height: 1.6;">
            <?php echo h($course['description']); ?>
        </p>
    </div>

    <!-- Tarjeta Principal de la Oferta y Checkout -->
    <div class="card" style="background: linear-gradient(135deg, rgba(18,18,26,0.95) 0%, rgba(30,27,75,0.4) 100%); border: 1px solid var(--border-glow); padding: 35px; border-radius: 16px; margin-bottom: 40px; box-shadow: 0 12px 48px rgba(85,100,241,0.15);">
        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 30px; align-items: center;">
            
            <!-- Detalles y Garantías -->
            <div>
                <h3 style="font-size: 20px; margin-bottom: 15px; color: var(--heading-color);">
                    ✨ Lo que vas a desbloquear hoy:
                </h3>
                <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 12px; font-size: 15px;">
                    <li style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-weight: bold;">✓</span> Acceso inmediato e ilimitado a todas las lecciones en Video y Audio.
                    </li>
                    <li style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-weight: bold;">✓</span> Reproducción de audios descargables para escuchar sin conexión.
                    </li>
                    <li style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-weight: bold;">✓</span> Materiales y recursos de apoyo en PDF.
                    </li>
                    <li style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #10b981; font-weight: bold;">✓</span> Garantía de satisfacción y soporte prioritario.
                    </li>
                </ul>
            </div>

            <!-- Caja de Precio y Acción -->
            <div style="background: rgba(0, 0, 0, 0.4); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; text-align: center;">
                
                <?php if ($promo_status['is_active']): ?>
                    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #ef4444; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 12px;">
                        🔥 <?php echo $promo_status['discount_pct']; ?>% DE DESCUENTO
                    </div>
                    
                    <div style="font-size: 13px; color: var(--text-muted); text-decoration: line-through; margin-bottom: 4px;">
                        Precio Regular: <?php echo format_price_usd_pyg($promo_status['regular_price']); ?>
                    </div>
                    
                    <div style="font-size: 28px; font-weight: 800; color: #10b981; margin-bottom: 15px;">
                        <?php echo format_price_usd_pyg($promo_status['effective_price']); ?>
                    </div>
                    
                    <p style="font-size: 12px; color: #fbbf24; margin-bottom: 20px;">
                        ⚡ Esta oferta vence cuando el contador llegue a 00:00.
                    </p>

                    <a href="checkout.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-full" style="padding: 14px; font-size: 16px; font-weight: 700; box-shadow: 0 6px 20px rgba(85,100,241,0.5);">
                        🚀 Comprar Ahora con Descuento
                    </a>

                <?php else: ?>
                    <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-muted); font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 12px;">
                        ⏰ OFERTA EXPIRADA
                    </div>
                    
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">
                        Precio Regular Actual:
                    </div>
                    
                    <div style="font-size: 28px; font-weight: 800; color: var(--heading-color); margin-bottom: 15px;">
                        <?php echo format_price_usd_pyg($promo_status['regular_price']); ?>
                    </div>
                    
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">
                        El tiempo límite de 15 minutos ha finalizado para tu sesión.
                    </p>

                    <a href="checkout.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-full" style="padding: 14px; font-size: 15px;">
                        Comprar al Precio Regular
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Botón de Pruebas: Reiniciar Contador para Test -->
    <div style="text-align: center; margin-top: 20px; font-size: 13px;">
        <a href="oferta.php?id=<?php echo $course['id']; ?>&reset=1" class="btn btn-secondary btn-sm" style="opacity: 0.8;">
            🔄 Reiniciar Contador de Prueba (15 Minutos Nuevos)
        </a>
    </div>

</div>

<?php
require_once 'includes/footer.php';
?>
