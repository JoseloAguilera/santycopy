-- =====================================================================
-- MIGRACIÓN / ACTUALIZACIÓN BASE DE DATOS (santycopy_cursos)
-- Ejecutar en phpMyAdmin (Hostinger) o consola MySQL en Producción
-- =====================================================================

-- 1. AGREGAR NUEVO CAMPO PARA CLASES EN FORMATO PDF
-- Permite que las lecciones del paso a paso acepten archivos PDF además de Video y Audio
ALTER TABLE `lessons` 
ADD COLUMN `pdf_file` VARCHAR(255) DEFAULT NULL AFTER `audio_file`;

-- 2. CORREGIR ROL DEL USUARIO ALUMNO DE PRUEBA
-- Cambia los privilegios de 'admin' a 'student' para alumno@santycopy.com
UPDATE `users` 
SET `role` = 'student' 
WHERE `email` = 'alumno@santycopy.com';

-- =====================================================================
-- NOTA / VERIFICACIÓN ADICIONAL:
-- Si tu base de datos en producción no tenía los cambios de la entrega
-- anterior (audios, bonus o temporizador de oferta), puedes descomentar
-- y ejecutar las siguientes sentencias según corresponda:
-- =====================================================================

-- ALTER TABLE `lessons` ADD COLUMN `audio_file` VARCHAR(255) DEFAULT NULL AFTER `video_url`;
-- ALTER TABLE `lessons` ADD COLUMN `is_bonus` TINYINT(1) DEFAULT 0 AFTER `order_number`;
-- ALTER TABLE `resources` ADD COLUMN `is_bonus` TINYINT(1) DEFAULT 0 AFTER `file_path`;
-- ALTER TABLE `courses` ADD COLUMN `promo_price` INT DEFAULT NULL AFTER `price`;
-- ALTER TABLE `courses` ADD COLUMN `promo_duration_minutes` INT DEFAULT 15 AFTER `promo_price`;

-- CREATE TABLE IF NOT EXISTS `promo_sessions` (
--     `id` INT AUTO_INCREMENT PRIMARY KEY,
--     `course_id` INT NOT NULL,
--     `visitor_key` VARCHAR(255) NOT NULL,
--     `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
--     UNIQUE KEY `visitor_course` (`visitor_key`, `course_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
