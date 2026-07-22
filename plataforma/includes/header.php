<?php
// header.php - Cabecera compartida para el LMS Santy Copy

require_once __DIR__ . '/auth_helper.php';
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - Santy Copy LMS' : 'Santy Copy LMS'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo get_base_path(); ?>/assets/style.css">
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="<?php echo get_base_path(); ?>/index.php" class="brand-logo">
                Santy <span>Copy LMS</span>
            </a>
            
            <div class="nav-links">
                <a href="<?php echo get_base_path(); ?>/index.php" class="<?php echo ($active_tab === 'catalog') ? 'active' : ''; ?>">Catálogo</a>
                
                <?php if ($user): ?>
                    <a href="<?php echo get_base_path(); ?>/student/index.php" class="<?php echo ($active_tab === 'student') ? 'active' : ''; ?>">Mis Cursos</a>
                    
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="<?php echo get_base_path(); ?>/admin/index.php" class="<?php echo ($active_tab === 'admin') ? 'active' : ''; ?>" style="color: #a78bfa; font-weight: 600;">Administración</a>
                    <?php endif; ?>
                    
                    <span style="font-size: 13px; color: var(--text-muted); margin-left: 10px;">
                        👤 <?php echo h($user['name']); ?> (<?php echo h($user['role'] === 'admin' ? 'Admin' : 'Alumno'); ?>)
                    </span>
                    <a href="<?php echo get_base_path(); ?>/logout.php" class="btn-nav-logout">Salir</a>
                <?php else: ?>
                    <a href="<?php echo get_base_path(); ?>/login.php" class="btn btn-secondary btn-sm">Iniciar Sesión</a>
                    <a href="<?php echo get_base_path(); ?>/register.php" class="btn btn-primary btn-sm">Registrarse</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container" style="flex-grow: 1; padding-top: 30px; padding-bottom: 30px;">
