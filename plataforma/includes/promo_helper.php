<?php
// includes/promo_helper.php - Lógica de control de Ofertas Exclusivas con Contador Inquebrantable de 15 Minutos

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

/**
 * Genera u obtiene la clave única del visitante combinando Cookie, Sesión, IP y Parámetros URL.
 */
function get_promo_visitor_key($course_id) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Si viene un token específico por URL (ej: ?v=xxxx o ?ref=xxx)
    $url_token = trim($_GET['v'] ?? $_GET['ref'] ?? $_GET['token'] ?? '');
    if (!empty($url_token)) {
        return 'token_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $url_token);
    }

    // 2. Revisar si el usuario está logueado
    if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
        return 'user_' . intval($_SESSION['user']['id']);
    }

    // 3. Revisar cookie o sesión existente
    if (!empty($_COOKIE['santy_vkey'])) {
        $vkey = $_COOKIE['santy_vkey'];
    } elseif (!empty($_SESSION['santy_vkey'])) {
        $vkey = $_SESSION['santy_vkey'];
    } else {
        $vkey = bin2hex(random_bytes(16));
        setcookie('santy_vkey', $vkey, time() + (86400 * 365), '/'); // 1 año
        $_SESSION['santy_vkey'] = $vkey;
    }

    // 4. Combinar con IP para evitar que borren cookies o usen incógnito en la misma red
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return 'v_' . md5($ip . '_' . $vkey);
}

/**
 * Formatea un precio en Guaraníes a su equivalente en Dólares USD y Guaraníes (₲).
 */
function format_price_usd_pyg($pyg_amount) {
    if ($pyg_amount <= 0) return 'Gratis';
    // Conversión promedio: 1 USD = 7.500 PYG
    $usd_amount = round($pyg_amount / 7500);
    if ($usd_amount < 1) $usd_amount = 1;
    return '$' . $usd_amount . ' USD <span style="font-size: 0.85em; opacity: 0.85; font-weight: normal;">(' . number_format($pyg_amount, 0, ',', '.') . ' ₲)</span>';
}

/**
 * Obtiene el estado de la oferta promocional (Tiempo restante, Precio Efectivo y Estado Expirado/Activo)
 */
function get_course_promo_status($pdo, $course_id) {
    // Si viene parámetro de reset por URL (?reset=1 o ?reset_promo=1), reiniciar la sesión de prueba para este usuario
    $should_reset = isset($_GET['reset']) || isset($_GET['reset_promo']);

    $stmt = $pdo->prepare("SELECT price, promo_price, promo_duration_minutes FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        return [
            'has_promo' => false,
            'is_active' => false,
            'regular_price' => 0,
            'effective_price' => 0,
            'remaining_seconds' => 0
        ];
    }

    $regular_price = intval($course['price']);
    $promo_price = ($course['promo_price'] !== null && $course['promo_price'] > 0) ? intval($course['promo_price']) : null;
    $duration_minutes = intval($course['promo_duration_minutes'] ?: 15);
    $duration_seconds = $duration_minutes * 60;

    if ($promo_price === null || $promo_price >= $regular_price) {
        return [
            'has_promo' => false,
            'is_active' => false,
            'regular_price' => $regular_price,
            'effective_price' => $regular_price,
            'remaining_seconds' => 0,
            'duration_minutes' => $duration_minutes
        ];
    }

    // Obtener clave única del visitante
    $visitor_key = get_promo_visitor_key($course_id);

    if ($should_reset) {
        // Eliminar registro existente para forzar un nuevo inicio de 15 minutos
        $stmt = $pdo->prepare("DELETE FROM promo_sessions WHERE visitor_key = ? AND course_id = ?");
        $stmt->execute([$visitor_key, $course_id]);
        $created_at = false;
    } else {
        // Consultar si ya existe un registro de primer acceso para este visitante y curso
        $stmt = $pdo->prepare("SELECT created_at FROM promo_sessions WHERE visitor_key = ? AND course_id = ?");
        $stmt->execute([$visitor_key, $course_id]);
        $created_at = $stmt->fetchColumn();
    }

    if (!$created_at) {
        // Registrar primer acceso con timestamp actual
        $stmt = $pdo->prepare("INSERT IGNORE INTO promo_sessions (course_id, visitor_key, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$course_id, $visitor_key]);
        $first_access = time();
    } else {
        $first_access = strtotime($created_at);
    }

    $elapsed_seconds = time() - $first_access;
    $remaining_seconds = $duration_seconds - $elapsed_seconds;

    if ($remaining_seconds > 0) {
        return [
            'has_promo' => true,
            'is_active' => true,
            'regular_price' => $regular_price,
            'effective_price' => $promo_price,
            'promo_price' => $promo_price,
            'remaining_seconds' => $remaining_seconds,
            'duration_minutes' => $duration_minutes,
            'discount_pct' => round((($regular_price - $promo_price) / $regular_price) * 100)
        ];
    } else {
        return [
            'has_promo' => true,
            'is_active' => false,
            'regular_price' => $regular_price,
            'effective_price' => $regular_price,
            'promo_price' => $promo_price,
            'remaining_seconds' => 0,
            'duration_minutes' => $duration_minutes,
            'discount_pct' => 0
        ];
    }
}

/**
 * Renderiza el Banner Flotante Premium con el Contador de Oferta de 15 Minutos
 */
function render_promo_banner_html($promo_status) {
    if (!$promo_status['has_promo']) {
        return '';
    }

    $remaining_seconds = $promo_status['remaining_seconds'];
    $is_active = $promo_status['is_active'];
    $regular_price_fmt = format_price_usd_pyg($promo_status['regular_price']);
    $promo_price_fmt = format_price_usd_pyg($promo_status['promo_price'] ?? 0);
    $discount_pct = $promo_status['discount_pct'] ?? 0;
    $duration_min = $promo_status['duration_minutes'] ?? 15;

    ob_start();
    ?>
    <div id="promo-sticky-banner" class="promo-banner <?php echo $is_active ? 'active' : 'expired'; ?>">
        <div class="container promo-banner-content">
            <div class="promo-info">
                <span class="promo-badge">
                    <?php echo $is_active ? "🔥 OFERTA DE BIENVENIDA ($-{$discount_pct}%)" : '⏰ OFERTA EXPIRADA'; ?>
                </span>
                <div class="promo-text">
                    <?php if ($is_active): ?>
                        <strong>¡Descuento exclusivo por <?php echo $duration_min; ?> minutos!</strong>
                        <span class="promo-price-tag">
                            <span class="old-price"><?php echo $regular_price_fmt; ?></span>
                            <span class="new-price"><?php echo $promo_price_fmt; ?></span>
                        </span>
                    <?php else: ?>
                        <strong>El tiempo límite de 15 minutos ha expirado.</strong>
                        <span class="promo-price-tag">
                            <span class="normal-price">Precio Regular: <?php echo $regular_price_fmt; ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="promo-timer-box">
                <span class="timer-label"><?php echo $is_active ? 'Tiempo Restante:' : 'Oferta Finalizada:'; ?></span>
                <div id="promo-countdown" class="timer-digits">
                    <span id="timer-min">00</span>:<span id="timer-sec">00</span>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var remainingSeconds = <?php echo intval($remaining_seconds); ?>;
        var minElem = document.getElementById('timer-min');
        var secElem = document.getElementById('timer-sec');
        var banner = document.getElementById('promo-sticky-banner');

        function updateTimerDisplay(sec) {
            if (sec < 0) sec = 0;
            var m = Math.floor(sec / 60);
            var s = sec % 60;
            if (minElem) minElem.innerText = (m < 10 ? '0' : '') + m;
            if (secElem) secElem.innerText = (s < 10 ? '0' : '') + s;
        }

        updateTimerDisplay(remainingSeconds);

        if (remainingSeconds > 0) {
            var interval = setInterval(function() {
                remainingSeconds--;
                updateTimerDisplay(remainingSeconds);
                if (remainingSeconds <= 0) {
                    clearInterval(interval);
                    if (banner) {
                        banner.classList.remove('active');
                        banner.classList.add('expired');
                    }
                    // Recargar la página suavemente para actualizar el precio del servidor a precio regular
                    setTimeout(function() {
                        window.location.reload();
                    }, 1200);
                }
            }, 1000);
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
