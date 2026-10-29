    </div> <!-- Cierre del div container de contenido -->
    
    <footer style="background: rgba(5, 5, 7, 0.9); border-top: 1px solid var(--border-color); padding: 20px 0; margin-top: auto; font-size: 13px; text-align: center; color: var(--text-muted);">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Santy Copy LMS. Todos los derechos reservados.</p>
            <p style="margin-top: 5px; font-size: 11px;">Desarrollado con estándares premium de seguridad y gamificación para XAMPP y Hostinger.</p>
        </div>
    </footer>
    <script>
    // Desvanecer alertas de éxito automáticamente después de 3 segundos
    document.addEventListener('DOMContentLoaded', function() {
        var alerts = document.querySelectorAll('.alert.alert-success');
        alerts.forEach(function(alertBox) {
            setTimeout(function() {
                alertBox.style.transition = 'opacity 0.6s ease, transform 0.6s ease, max-height 0.6s ease, margin 0.6s ease, padding 0.6s ease';
                alertBox.style.opacity = '0';
                alertBox.style.transform = 'translateY(-8px)';
                alertBox.style.maxHeight = '0';
                alertBox.style.paddingTop = '0';
                alertBox.style.paddingBottom = '0';
                alertBox.style.marginTop = '0';
                alertBox.style.marginBottom = '0';
                alertBox.style.overflow = 'hidden';
                setTimeout(function() {
                    if (alertBox && alertBox.parentNode) {
                        alertBox.remove();
                    }
                }, 600);
            }, 3000);
        });
    });
    </script>
</body>
</html>
