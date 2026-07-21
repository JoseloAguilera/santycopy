-- schema.sql - Script de inicialización de la Base de Datos del LMS Santy Copy
-- Para ejecutar desde phpMyAdmin de Hostinger

-- 1. Crear Tabla de Usuarios
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) DEFAULT 'student',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Crear Tabla de Cursos
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `thumbnail` VARCHAR(255) DEFAULT NULL,
    `price` INT NOT NULL DEFAULT 0,
    `payment_type` VARCHAR(20) DEFAULT 'one_time', -- 'one_time', 'subscription'
    `subscription_period` INT DEFAULT 30, -- en días
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crear Tabla de Clases / Lecciones
CREATE TABLE IF NOT EXISTS `lessons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `video_url` VARCHAR(255) DEFAULT NULL,
    `order_number` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Crear Tabla de Recursos (PDFs)
CREATE TABLE IF NOT EXISTS `resources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Crear Tabla de Inscripciones (Enrollments)
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `expires_at` DATETIME DEFAULT NULL, -- NULL significa acceso permanente
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `user_course` (`user_id`, `course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Crear Tabla de Progreso de Clases
CREATE TABLE IF NOT EXISTS `progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `lesson_id` INT NOT NULL,
    `completed` TINYINT(1) DEFAULT 1,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `user_lesson` (`user_id`, `lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Crear Tabla de Órdenes de Pago
CREATE TABLE IF NOT EXISTS `orders` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Crear Tabla de Tokens de Recuperación
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(100) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------
-- INSERCIÓN DE DATOS DE PRUEBA (SEED DATA)
-- ----------------------------------------------------

-- Vaciar datos si existieran para evitar duplicados
TRUNCATE TABLE `users`;
TRUNCATE TABLE `courses`;

-- Insertar Administrador por defecto (Contraseña: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES 
('Santy Admin', 'admin@santycopy.com', '$2y$10$CQr1T8aGOSQbScpuU3azXeyar9RwWvdo4eEw2/MI3AGuMYgVcaaCy', 'admin');

-- Insertar Estudiante por defecto (Contraseña: alumno123)
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES 
('Juan Pérez', 'alumno@santycopy.com', '$2y$10$GThsogsPv9rtU3vRgnUabeUyy4BtYOIeG9OLuVrjRGyXu4k9AT8e6', 'student');

-- Insertar Cursos de Prueba
INSERT INTO `courses` (`id`, `title`, `description`, `price`, `payment_type`, `subscription_period`) VALUES
(1, 'Máster en Copywriting Conversacional', 'Aprende a escribir textos que eliminen el ruido y atraigan clientes de forma magnética, basándote en historias inusuales y psicología de persuasión de alto nivel.', 150000, 'one_time', NULL),
(2, 'Club Copywriting - Suscripción Mensual', 'Accede mensualmente a nuevas clases de análisis de embudos, plantillas de correo electrónico de alta conversión y sesiones de preguntas y respuestas en vivo.', 45000, 'subscription', 30);

-- Insertar Clases / Lecciones para el Curso 1 (Máster)
INSERT INTO `lessons` (`course_id`, `title`, `video_url`, `order_number`) VALUES 
(1, '1. Bienvenida e Introducción al curso', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 1),
(1, '2. El Secreto del Viejo Desnudo en la Plaza', 'https://www.youtube.com/embed/5dbG4HJr-Uo', 2),
(1, '3. Estructura de Carta de Venta Irresistible', 'https://www.youtube.com/embed/Z8yW5cyXXa8', 3),
(1, '4. El Cierre Magnético y Manejo de Objeciones', 'https://www.youtube.com/embed/tgbNymZ7vqY', 4);

-- Insertar Clases / Lecciones para el Curso 2 (Club)
INSERT INTO `lessons` (`course_id`, `title`, `video_url`, `order_number`) VALUES 
(2, '1. Introducción al Club y Hoja de Ruta', 'https://player.vimeo.com/video/76979871', 1),
(2, '2. Psicología del Comprador Moderno en 2026', 'https://player.vimeo.com/video/49987820', 2),
(2, '3. El Arte del Storytelling por Email', 'https://player.vimeo.com/video/51842036', 3);
