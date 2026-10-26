<?php
// mail_helper.php - Sistema de notificaciones por correo electrónico del LMS Santy Copy

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

/**
 * Obtiene la URL base de la plataforma para enlaces en los correos
 */
function get_platform_base_url() {
    if ((defined('APP_ENV') && APP_ENV === 'production') || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'santycopy.com') !== false)) {
        return 'https://www.santycopy.com/plataforma';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/santycopy/plataforma';
}

/**
 * Obtiene el correo de administración para recibir avisos de ventas y registros
 */
function get_admin_notification_email($pdo = null) {
    if (!empty($_ENV['ADMIN_NOTIFICATION_EMAIL'])) {
        return $_ENV['ADMIN_NOTIFICATION_EMAIL'];
    }
    if (!empty($_ENV['ADMIN_EMAIL'])) {
        return $_ENV['ADMIN_EMAIL'];
    }
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $email = $stmt->fetchColumn();
            if ($email) return $email;
        } catch (Exception $e) {}
    }
    return 'admin@santycopy.com';
}

/**
 * Plantilla HTML responsive premium para emails
 */
function render_email_template($title, $content_html, $cta_text = '', $cta_url = '') {
    $base_url = get_platform_base_url();
    $cta_block = '';
    if (!empty($cta_text) && !empty($cta_url)) {
        $cta_block = '
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . htmlspecialchars($cta_url) . '" style="background: #5564f1; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: 600; font-size: 15px; display: inline-block; box-shadow: 0 4px 14px rgba(85, 100, 241, 0.35);">
                ' . htmlspecialchars($cta_text) . '
            </a>
        </div>';
    }

    return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #0b0f19; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #cbd5e1; line-height: 1.6;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0b0f19; padding: 40px 15px;">
            <tr>
                <td align="center">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 580px; background: #131b2e; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                        <!-- Header Logo -->
                        <tr>
                            <td style="padding: 30px 35px; border-bottom: 1px solid #1e293b; background: #0f172a; text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                    Santy <span style="color: #5564f1;">Copy LMS</span>
                                </div>
                            </td>
                        </tr>
                        <!-- Contenido Principal -->
                        <tr>
                            <td style="padding: 35px 35px 25px 35px; font-size: 15px; color: #cbd5e1;">
                                <h1 style="font-size: 20px; font-weight: 700; color: #ffffff; margin-top: 0; margin-bottom: 18px; line-height: 1.3;">
                                    ' . htmlspecialchars($title) . '
                                </h1>
                                ' . $content_html . '
                                ' . $cta_block . '
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style="padding: 20px 35px; background: #0f172a; border-top: 1px solid #1e293b; text-align: center; font-size: 12px; color: #64748b;">
                                <p style="margin: 0 0 6px 0;">Este es un correo automático enviado desde <a href="' . htmlspecialchars($base_url) . '" style="color: #5564f1; text-decoration: none;">Santy Copy LMS</a>.</p>
                                <p style="margin: 0;">© ' . date('Y') . ' Santy Copy. Todos los derechos reservados.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/**
 * Función central de envío de correos con logging seguro
 */
function send_platform_email($to, $subject, $html_body, $plain_body = '') {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Santy Copy LMS <no-reply@santycopy.com>',
        'Reply-To: no-reply@santycopy.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    $headers_str = implode("\r\n", $headers);

    // Registro de auditoría en log local para pruebas y trazabilidad
    $log_file = dirname(__DIR__) . '/email_notifications_log.txt';
    $log_entry = "[" . date('Y-m-d H:i:s') . "] A: " . $to . " | ASUNTO: " . $subject . "\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Envío silencioso mediante el MTA del servidor (Hostinger / sendmail)
    return @mail($to, $subject, $html_body, $headers_str);
}

/**
 * 1. Notificación de Nuevo Registro (Bienvenida al Alumno + Alerta al Admin)
 */
function notify_new_registration($pdo, $user_name, $user_email) {
    $base_url = get_platform_base_url();
    $login_url = $base_url . '/login.php';

    // A. Email al Alumno
    $student_subject = "¡Bienvenido a Santy Copy LMS! 🎉 Tu cuenta está lista";
    $student_content = '
        <p>Hola <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
        <p>¡Gracias por registrarte en <strong>Santy Copy LMS</strong>! Tu cuenta ha sido creada exitosamente y ya puedes acceder a tu panel de estudiante para explorar nuestros cursos y capacitaciones.</p>
        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid #1e293b; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <div style="margin-bottom: 6px;"><strong style="color: #ffffff;">Email de acceso:</strong> ' . htmlspecialchars($user_email) . '</div>
            <div><strong style="color: #ffffff;">Estado:</strong> Cuenta activa</div>
        </div>
        <p>Haz clic en el siguiente botón para iniciar sesión e ingresar al catálogo de cursos:</p>
    ';
    $student_html = render_email_template("¡Bienvenido a la Plataforma!", $student_content, "Iniciar Sesión en el LMS", $login_url);
    send_platform_email($user_email, $student_subject, $student_html);

    // B. Email al Administrador
    $admin_email = get_admin_notification_email($pdo);
    if ($admin_email) {
        $admin_subject = "👤 Nuevo Alumno Registrado: " . $user_name;
        $admin_content = '
            <p>Se ha registrado un nuevo estudiante en la plataforma:</p>
            <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid #1e293b; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Nombre:</strong> ' . htmlspecialchars($user_name) . '</div>
                <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Email:</strong> ' . htmlspecialchars($user_email) . '</div>
                <div><strong style="color: #ffffff;">Fecha:</strong> ' . date('d/m/Y H:i:s') . '</div>
            </div>
            <p>Puedes gestionar alumnos y cursos desde el panel de administración.</p>
        ';
        $admin_html = render_email_template("Nuevo Registro en el LMS", $admin_content, "Ver Panel de Administración", $base_url . '/admin/students.php');
        send_platform_email($admin_email, $admin_subject, $admin_html);
    }
}

/**
 * 2. Notificación de Compra / Pago Aprobado (Confirmación al Alumno + Alerta de Venta al Admin)
 */
function notify_course_purchase($pdo, $order_id, $user_name, $user_email, $course_id, $course_title, $amount, $payment_type = 'one_time') {
    $base_url = get_platform_base_url();
    $classroom_url = $base_url . '/student/course_view.php?course_id=' . intval($course_id);
    $monto_formateado = number_format($amount, 0, ',', '.') . " ₲";
    $modalidad = ($payment_type === 'subscription') ? 'Suscripción Mensual' : 'Acceso Ilimitado';

    // A. Email al Alumno
    $student_subject = "¡Pago confirmado! Ya tienes acceso a: " . $course_title . " 🎓";
    $student_content = '
        <p>Hola <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
        <p>¡Excelentes noticias! Tu pago ha sido aprobado exitosamente y tu matrícula ya está activa.</p>
        <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid #1e293b; padding: 18px; border-radius: 8px; margin: 22px 0;">
            <div style="font-size: 16px; font-weight: 700; color: #ffffff; margin-bottom: 12px; border-bottom: 1px solid #1e293b; padding-bottom: 8px;">
                Resumen de la Compra
            </div>
            <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Curso:</strong> ' . htmlspecialchars($course_title) . '</div>
            <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Monto abonado:</strong> <span style="color: #10b981; font-weight: 700;">' . $monto_formateado . '</span></div>
            <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Modalidad:</strong> ' . $modalidad . '</div>
            <div><strong style="color: #ffffff;">Pedido #:</strong> ' . intval($order_id) . '</div>
        </div>
        <p>Ya puedes ingresar al aula virtual para ver tus lecciones en video, audio y material en PDF:</p>
    ';
    $student_html = render_email_template("¡Tu acceso está listo!", $student_content, "Entrar al Curso Ahora", $classroom_url);
    send_platform_email($user_email, $student_subject, $student_html);

    // B. Email al Administrador
    $admin_email = get_admin_notification_email($pdo);
    if ($admin_email) {
        $admin_subject = "💰 ¡Nueva Venta Confirmada! " . $monto_formateado . " - " . $course_title;
        $admin_content = '
            <p>Se ha completado un nuevo cobro a través de Pagopar en la plataforma:</p>
            <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid #1e293b; padding: 18px; border-radius: 8px; margin: 22px 0;">
                <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Curso:</strong> ' . htmlspecialchars($course_title) . '</div>
                <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Monto Recibido:</strong> <span style="color: #10b981; font-weight: 700;">' . $monto_formateado . '</span></div>
                <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Estudiante:</strong> ' . htmlspecialchars($user_name) . ' (' . htmlspecialchars($user_email) . ')</div>
                <div style="margin-bottom: 8px;"><strong style="color: #ffffff;">Pedido #:</strong> ' . intval($order_id) . '</div>
                <div><strong style="color: #ffffff;">Fecha:</strong> ' . date('d/m/Y H:i:s') . '</div>
            </div>
            <p>Los ingresos y matrículas han sido actualizados en la base de datos.</p>
        ';
        $admin_html = render_email_template("Nueva Venta en el LMS", $admin_content, "Ver Órdenes en Admin", $base_url . '/admin/index.php');
        send_platform_email($admin_email, $admin_subject, $admin_html);
    }
}
