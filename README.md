# Santy Copy - Landing Page & LMS (Plataforma de Cursos)

Este repositorio contiene el sitio web oficial de Santy Copy, el cual integra su Landing Page de ventas/conversión junto con una plataforma prémium de gestión de cursos (LMS) que cuenta con registro de alumnos, catálogo de cursos (compras únicas o suscripciones), panel de administración para subir videos y PDFs, y un reproductor interactivo con seguimiento de progreso AJAX e integración para la pasarela de pagos paraguaya **Pagopar**.

---

## 📁 Estructura del Proyecto

*   **Página Principal y Ventas:**
    *   `index.php` / `santycopy-landing.html`: Landing page principal para captar suscriptores de Mailerlite.
    *   `venta.php`: Carta de ventas persuasiva del Máster de Copywriting.
    *   `subscribe.php`: Script que conecta por API con Mailerlite.
*   **Plataforma de Cursos (`plataforma/`):**
    *   `config/`: Ajustes de Base de Datos y de entorno para Pagopar.
    *   `assets/`: Hoja de estilos `style.css` con estética premium de modo oscuro y glassmorphism.
    *   `includes/`: Layouts compartidos (`header.php`, `footer.php`) y helper de seguridad/sesiones (`auth_helper.php`).
    *   `admin/`: Backend de gestión para crear cursos, videos, recursos y altas/bajas de alumnos.
    *   `student/`: Portal de estudio con barra de progreso y reproductor adaptativo de video (confeti al 100%).
    *   `diagnostic.php` / `test_flow.php`: Módulos visual y por terminal para diagnóstico del sistema.
    *   `install.php`: Script de instalación automática de base de datos.
    *   `uploads/`: Carpeta de almacenamiento protegida con `.htaccess` para evitar ejecución de scripts maliciosos.

---

## 🛠️ Requisitos del Sistema

*   **Servidor Web:** Apache (recomendado para soporte del archivo `.htaccess`).
*   **PHP:** Versión 7.4 o superior (con extensión `cURL` activa para procesar la API de Pagopar).
*   **Base de datos:** MySQL o MariaDB.

---

## 💻 Instalación en Servidor Local (XAMPP)

1.  **Copiar los archivos:**
    Clona este repositorio o copia la carpeta del proyecto dentro del directorio de publicación de tu servidor local (ej: `C:\xampp\htdocs\santycopy` o `/Applications/XAMPP/xamppfiles/htdocs/santycopy`).
2.  **Iniciar Servidores:**
    Abre el Panel de Control de tu XAMPP y presiona "Start" en los servicios de **Apache** y **MySQL**.
3.  **Ejecutar Instalador Automático:**
    Abre tu navegador e ingresa a la siguiente URL:
    [http://localhost/santycopy/plataforma/install.php](http://localhost/santycopy/plataforma/install.php)
    
    *El script creará automáticamente la base de datos `santycopy_cursos`, estructurará todas las tablas necesarias e inyectará dos cursos y usuarios de prueba.*
4.  **Listo:**
    Puedes ingresar al catálogo en [http://localhost/santycopy/plataforma/index.php](http://localhost/santycopy/plataforma/index.php).

---

## ☁️ Instalación en Servidor Web (Hostinger u otros)

1.  **Subir archivos:**
    Sube todos los archivos del proyecto al directorio público de tu hosting (usualmente `public_html/` o un subdirectorio).
2.  **Crear Base de Datos:**
    Ingresa al panel de control de tu hosting (ej. hPanel de Hostinger), ve a la sección de **Bases de Datos MySQL** y crea una nueva base de datos, asignándole un usuario y contraseña.
3.  **Actualizar Credenciales:**
    Abre el archivo [plataforma/config/db.php](file:///Applications/XAMPP/xamppfiles/htdocs/santycopy/plataforma/config/db.php) y coloca las credenciales recién creadas:
    ```php
    $host = '127.0.0.1'; // Generalmente localhost o la IP provista por tu hosting
    $dbname = 'nombre_de_tu_base_de_datos';
    $username = 'usuario_mysql';
    $password = 'contraseńa_mysql';
    ```
4.  **Ejecutar Instalación:**
    Accede en tu navegador a:
    `https://tudominio.com/plataforma/install.php`
5.  **Seguridad:**
    Una vez finalizada la instalación con éxito en producción, **elimina o renombra** el archivo `plataforma/install.php` para evitar reinstalaciones accidentales.

---

## 💳 Configuración de Pagopar (Pase a Producción)

Por defecto, el sistema se instala en entorno de desarrollo (`PAGOPAR_ENV = 'development'`). En este modo, cuando un estudiante hace clic en "Comprar", es redirigido a una pasarela local ficticia que permite simular pagos exitosos o fallidos con un solo clic sin usar tarjetas reales.

Cuando estés listo para recibir pagos reales:

1.  Ingresa a tu panel de **Pagopar.com** y ve a la sección **Desarrollador** > **Integrar con mi sitio web**.
2.  Copia tu **Token Público** y **Token Privado**.
3.  Define la **URL de respuesta (Webhook)** apuntando a: `https://tudominio.com/plataforma/pagopar_callback.php`
4.  Edita el archivo [plataforma/config/pagopar.php](file:///Applications/XAMPP/xamppfiles/htdocs/santycopy/plataforma/config/pagopar.php) y actualiza los valores:
    ```php
    define('PAGOPAR_ENV', 'production'); // Cambiar de 'development' a 'production'
    define('PAGOPAR_PUBLIC_KEY', 'TU_TOKEN_PUBLICO');
    define('PAGOPAR_PRIVATE_KEY', 'TU_TOKEN_PRIVADO');
    ```

---

## 🔑 Credenciales por Defecto (Seed Data)

Para ingresar de inmediato y evaluar la plataforma, puedes usar los siguientes accesos creados por el instalador:

*   **Perfil Administrador (Gestión de Cursos y Alumnos):**
    *   **Email:** `admin@santycopy.com`
    *   **Password:** `admin123`
*   **Perfil Estudiante (Visualización y Progreso):**
    *   **Email:** `alumno@santycopy.com`
    *   **Password:** `alumno123`

---

## 🩺 Herramientas de Diagnóstico

La plataforma cuenta con sistemas automáticos de self-check para validar que tu base de datos y la pasarela respondan correctamente:

*   **Consola (CLI):** Ejecuta en terminal: `/Applications/XAMPP/xamppfiles/bin/php plataforma/test_flow.php`
*   **Visual (Navegador):** Accede a: [http://localhost/santycopy/plataforma/diagnostic.php](http://localhost/santycopy/plataforma/diagnostic.php)
