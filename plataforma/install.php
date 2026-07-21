<?php
// install.php - Inicializador de base de datos para Santy Copy LMS

define('ACCESS_ALLOWED', true);

$host = '127.0.0.1';
$username = 'root';
$password = '';
$dbname = 'santycopy_cursos';

$success = false;
$message = '';

try {
    // 1. Conectar a MySQL sin base de datos
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Crear la base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Conectar a la base de datos recién creada
    $pdo->exec("USE `$dbname`");

    // 4. Crear Tabla de Usuarios
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` VARCHAR(20) DEFAULT 'student',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 5. Crear Tabla de Cursos
    $pdo->exec("CREATE TABLE IF NOT EXISTS `courses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(150) NOT NULL,
        `description` TEXT,
        `thumbnail` VARCHAR(255) DEFAULT NULL,
        `price` INT NOT NULL DEFAULT 0,
        `payment_type` VARCHAR(20) DEFAULT 'one_time', -- 'one_time', 'subscription'
        `subscription_period` INT DEFAULT 30, -- en días
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 6. Crear Tabla de Clases / Lecciones
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lessons` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `video_url` VARCHAR(255) DEFAULT NULL,
        `order_number` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // 7. Crear Tabla de Recursos
    $pdo->exec("CREATE TABLE IF NOT EXISTS `resources` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // 8. Crear Tabla de Inscripciones (Enrollments)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `enrollments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `expires_at` DATETIME DEFAULT NULL, -- NULL significa acceso ilimitado (compra única)
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `user_course` (`user_id`, `course_id`)
    ) ENGINE=InnoDB;");

    // 9. Crear Tabla de Progreso
    $pdo->exec("CREATE TABLE IF NOT EXISTS `progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `lesson_id` INT NOT NULL,
        `completed` TINYINT(1) DEFAULT 1,
        `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `user_lesson` (`user_id`, `lesson_id`)
    ) ENGINE=InnoDB;");

    // 10. Crear Tabla de Órdenes
    $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `amount` INT NOT NULL,
        `payment_hash` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'pending', -- 'pending', 'paid', 'failed'
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // 11. Crear Tabla de Recuperación de Contraseña
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(100) NOT NULL,
        `token` VARCHAR(255) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 12. Insertar Datos Semilla
    
    // Eliminar usuarios y cursos anteriores si los hubiera para un reinicio limpio
    $pdo->exec("DELETE FROM `users`");
    $pdo->exec("DELETE FROM `courses`");
    
    // Insertar Administrador por defecto
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES (?, ?, ?, 'admin')");
    $stmt->execute(['Santy Admin', 'admin@santycopy.com', $adminPassword]);
    
    // Insertar Estudiante por defecto
    $studentPassword = password_hash('alumno123', PASSWORD_BCRYPT);
    $stmt->execute(['Juan Pérez', 'alumno@santycopy.com', $studentPassword]);

    // Insertar Cursos
    $stmtCourse = $pdo->prepare("INSERT INTO `courses` (`id`, `title`, `description`, `price`, `payment_type`, `subscription_period`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtCourse->execute([
        1,
        'Máster en Copywriting Conversacional',
        'Aprende a escribir textos que eliminen el ruido y atraigan clientes de forma magnética, basándote en historias inusuales y psicología de persuasión de alto nivel.',
        150000,
        'one_time',
        NULL
    ]);
    
    $stmtCourse->execute([
        2,
        'Club Copywriting - Suscripción Mensual',
        'Accede mensualmente a nuevas clases de análisis de embudos, plantillas de correo electrónico de alta conversión y sesiones de preguntas y respuestas en vivo.',
        45000,
        'subscription',
        30
    ]);

    // Insertar Clases / Videos para Curso 1
    $stmtLesson = $pdo->prepare("INSERT INTO `lessons` (`course_id`, `title`, `video_url`, `order_number`) VALUES (?, ?, ?, ?)");
    $stmtLesson->execute([1, '1. Bienvenida e Introducción al curso', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 1]);
    $stmtLesson->execute([1, '2. El Secreto del Viejo Desnudo en la Plaza', 'https://www.youtube.com/embed/5dbG4HJr-Uo', 2]);
    $stmtLesson->execute([1, '3. Estructura de Carta de Venta Irresistible', 'https://www.youtube.com/embed/Z8yW5cyXXa8', 3]);
    $stmtLesson->execute([1, '4. El Cierre Magnético y Manejo de Objeciones', 'https://www.youtube.com/embed/tgbNymZ7vqY', 4]);

    // Insertar Clases / Videos para Curso 2
    $stmtLesson->execute([2, '1. Introducción al Club y Hoja de Ruta', 'https://player.vimeo.com/video/76979871', 1]);
    $stmtLesson->execute([2, '2. Psicología del Comprador Moderno en 2026', 'https://player.vimeo.com/video/49987820', 2]);
    $stmtLesson->execute([2, '3. El Arte del Storytelling por Email', 'https://player.vimeo.com/video/51842036', 3]);

    $success = true;
    $message = "Base de datos y tablas inicializadas correctamente.";
    
} catch (PDOException $e) {
    $success = false;
    $message = "Error en la instalación: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador LMS - Santy Copy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #09090b;
            --card-bg: rgba(24, 24, 27, 0.6);
            --border-color: rgba(39, 39, 42, 0.8);
            --primary-color: #5564F1;
            --text-color: #c5c5c5;
            --heading-color: #ffffff;
            --success-color: #10b981;
            --error-color: #ef4444;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            text-align: center;
        }

        h1 {
            color: var(--heading-color);
            font-size: 28px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .status-badge.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .info-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }

        .info-box h3 {
            color: var(--heading-color);
            margin-top: 0;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .credentials {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 8px;
            font-size: 14px;
            margin-top: 10px;
        }

        .credentials span:first-child {
            font-weight: 600;
            color: var(--heading-color);
        }

        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 30px;
            transition: all 0.2s ease;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        p {
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalador LMS Santy Copy</h1>
        
        <?php if ($success): ?>
            <div class="status-badge success">Instalación Completada con Éxito</div>
            <p><?php echo htmlspecialchars($message); ?></p>
            
            <div class="info-box">
                <h3>🔑 Cuentas de Acceso Creadas:</h3>
                <p><strong>Rol: Administrador</strong></p>
                <div class="credentials">
                    <span>Usuario:</span>
                    <span>admin@santycopy.com</span>
                    <span>Password:</span>
                    <span>admin123</span>
                </div>
                
                <p style="margin-top: 20px;"><strong>Rol: Estudiante</strong></p>
                <div class="credentials">
                    <span>Usuario:</span>
                    <span>alumno@santycopy.com</span>
                    <span>Password:</span>
                    <span>alumno123</span>
                </div>
            </div>
            
            <a href="index.php" class="btn">Ir al Catálogo de Cursos</a>
        <?php else: ?>
            <div class="status-badge error">Fallo en la Instalación</div>
            <p><?php echo htmlspecialchars($message); ?></p>
            <p>Por favor asegúrate de que el servidor MySQL en tu XAMPP esté iniciado y vuelve a recargar la página.</p>
            <a href="install.php" class="btn">Reintentar Instalación</a>
        <?php endif; ?>
    </div>
</body>
</html>
