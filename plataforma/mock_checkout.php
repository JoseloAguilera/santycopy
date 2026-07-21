<?php
// mock_checkout.php - Pantalla de simulación de Pagopar en modo desarrollo

require_once 'includes/auth_helper.php';
require_once 'config/db.php';
require_once 'config/pagopar.php';

require_login();
$user = get_logged_user();
$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // Obtener la orden y validar pertenencia
    $stmt = $pdo->prepare("
        SELECT o.*, c.title as course_title, c.payment_type, c.price 
        FROM orders o 
        JOIN courses c ON o.course_id = c.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user['id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die("La orden no existe o no tienes permiso para acceder.");
    }
    
    if ($order['status'] !== 'pending') {
        die("Esta orden ya fue procesada. Estado actual: " . h($order['status']));
    }
    
    // Generar un hash ficticio de transacción para la prueba
    $mock_hash = md5('MOCK_ORDER_' . $order_id);
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

$page_title = 'Simulador de Pago - Pagopar';
$active_tab = 'catalog';
require_once 'includes/header.php';
?>

<div class="auth-wrapper" style="padding-top: 10px;">
    <div class="card" style="max-width: 550px; width: 100%; text-align: left;">
        <div style="background: rgba(85, 100, 241, 0.1); border: 1px solid var(--primary-color); border-radius: 8px; padding: 15px; margin-bottom: 20px; font-size: 13px;">
            💻 <strong>MODO DESARROLLO ACTIVO:</strong><br>
            Estás visualizando el simulador local de Pagopar. Esta pantalla reemplaza la redirección a la pasarela real de Pagopar para facilitar tus pruebas locales.
        </div>
        
        <h2 style="font-size: 20px; margin-bottom: 5px;">💸 Factura de Compra (Simulación)</h2>
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Plataforma Santy Copy Academy</p>
        
        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                <span style="color: var(--text-muted);">Comprador:</span>
                <span style="color: var(--heading-color); font-weight: 500;"><?php echo h($user['name']); ?> (<?php echo h($user['email']); ?>)</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                <span style="color: var(--text-muted);">Concepto:</span>
                <span style="color: var(--heading-color); font-weight: 500;"><?php echo h($order['course_title']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                <span style="color: var(--text-muted);">Tipo de Compra:</span>
                <span style="color: var(--heading-color); font-weight: 500;">
                    <?php echo ($order['payment_type'] === 'subscription') ? 'Suscripción Mensual' : 'Compra Única (Acceso de por vida)'; ?>
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                <span style="color: var(--text-muted);">Nº de Pedido:</span>
                <span style="color: var(--heading-color); font-weight: 500;">#<?php echo $order_id; ?></span>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 25px;">
            <span style="font-weight: 600; color: var(--heading-color);">Monto Total:</span>
            <span style="font-size: 20px; font-weight: 700; color: var(--primary-color);"><?php echo number_format($order['price'], 0, ',', '.'); ?> ₲</span>
        </div>
        
        <div id="status-container" style="display: none; margin-bottom: 20px;">
            <!-- Indicador de procesamiento AJAX -->
        </div>

        <div style="display: flex; gap: 15px;">
            <button onclick="simulatePayment(true)" class="btn btn-primary" style="flex: 1; background: var(--success); box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);">
                ✅ Simular Pago Exitoso
            </button>
            <button onclick="simulatePayment(false)" class="btn btn-danger" style="flex: 1;">
                ❌ Simular Pago Fallido
            </button>
        </div>
        
        <div style="text-align: center; margin-top: 15px;">
            <a href="index.php" style="font-size: 13px; color: var(--text-muted);">Cancelar y volver al catálogo</a>
        </div>
    </div>
</div>

<script>
function simulatePayment(isSuccess) {
    const statusContainer = document.getElementById('status-container');
    statusContainer.style.display = 'block';
    statusContainer.innerHTML = '<div class="alert" style="background: rgba(255,255,255,0.05); color: #fff; border-color: var(--border-color);">⌛ Procesando transacción ficticia...</div>';
    
    if (isSuccess) {
        // Generar token SHA1 simulado en frontend para coincidir con la validación de desarrollo
        // token = sha1("DEVELOPMENT" + mock_hash)
        const mockHash = "<?php echo $mock_hash; ?>";
        
        // Simular llamada IPN en segundo plano
        // Usamos CryptoJS para generar SHA1 de forma rápida
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js';
        script.onload = function() {
            const tokenGenerado = CryptoJS.SHA1("DEVELOPMENT" + mockHash).toString();
            
            const payload = {
                "respuesta": true,
                "resultado": [
                    {
                        "pagado": true,
                        "numero_pedido": "<?php echo $order_id; ?>",
                        "hash_pedido": mockHash,
                        "monto": "<?php echo $order['price']; ?>.00",
                        "forma_pago": "Simulación Desarrollo",
                        "fecha_pago": new Date().toISOString().slice(0, 19).replace('T', ' '),
                        "cancelado": false,
                        "numero_comprobante_interno": "8230473",
                        "token": tokenGenerado
                    }
                ]
            };
            
            fetch('pagopar_callback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === 'OK') {
                    statusContainer.innerHTML = '<div class="alert alert-success">🎉 ¡Pago exitoso simulado! Redirigiendo a tu curso...</div>';
                    setTimeout(() => {
                        window.location.href = 'student/index.php?payment=success';
                    }, 1500);
                } else {
                    statusContainer.innerHTML = '<div class="alert alert-danger">❌ Error en la respuesta del Callback: ' + data + '</div>';
                }
            })
            .catch(error => {
                statusContainer.innerHTML = '<div class="alert alert-danger">❌ Error de conexión: ' + error + '</div>';
            });
        };
        document.head.appendChild(script);
    } else {
        // Marcar la orden como fallida localmente y redirigir
        const formData = new FormData();
        formData.add = function(k, v) { this.append(k, v); };
        
        fetch('pagopar_callback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                "respuesta": false,
                "resultado": [
                    {
                        "pagado": false,
                        "numero_pedido": "<?php echo $order_id; ?>",
                        "hash_pedido": "<?php echo $mock_hash; ?>",
                        "cancelado": true
                    }
                ]
            })
        })
        .then(() => {
            statusContainer.innerHTML = '<div class="alert alert-danger">❌ Pago rechazado. Redirigiendo al catálogo...</div>';
            setTimeout(() => {
                window.location.href = 'index.php?payment=failed';
            }, 1500);
        });
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>
