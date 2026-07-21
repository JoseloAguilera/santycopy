<?php
// pagopar_callback.php - Receptor de notificaciones de pago (IPN) de Pagopar

require_once 'config/db.php';
require_once 'config/pagopar.php';

// Leer datos JSON enviados en el cuerpo del POST
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data) {
    http_response_code(400);
    die("Petición inválida: sin cuerpo JSON.");
}

// Loguear la petición entrante para auditoría y depuración en archivo local
file_put_contents('pagopar_ipn_log.txt', "[" . date('Y-m-d H:i:s') . "] IPN RECIBIDA: " . $raw_input . "\n", FILE_APPEND);

// Verificar la respuesta general
$respuesta_exitosa = isset($data['respuesta']) && $data['respuesta'] === true;

try {
    if ($respuesta_exitosa && !empty($data['resultado'])) {
        // Recorrer los resultados notificados
        foreach ($data['resultado'] as $result) {
            $order_id = intval($result['numero_pedido'] ?? 0);
            $hash_pedido = $result['hash_pedido'] ?? '';
            $token_recibido = $result['token'] ?? '';
            $pagado = isset($result['pagado']) && $result['pagado'] === true;
            
            if ($order_id <= 0 || empty($hash_pedido)) {
                continue;
            }
            
            // Validar firma del token
            $key_to_use = (PAGOPAR_ENV === 'development') ? 'DEVELOPMENT' : PAGOPAR_PRIVATE_KEY;
            $token_esperado = sha1($key_to_use . $hash_pedido);
            
            if ($token_recibido !== $token_esperado) {
                // Token inválido, posible fraude
                file_put_contents('pagopar_ipn_log.txt', "[" . date('Y-m-d H:i:s') . "] ❌ FRAUDE DETECTADO: Token recibido ($token_recibido) no coincide con esperado ($token_esperado) para el pedido #$order_id.\n", FILE_APPEND);
                http_response_code(403);
                die("Token inválido");
            }
            
            if ($pagado) {
                // 1. Obtener detalles de la orden
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                
                if ($order && $order['status'] === 'pending') {
                    // Iniciar transacción de base de datos
                    $pdo->beginTransaction();
                    
                    // 2. Marcar la orden como pagada
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', payment_hash = ? WHERE id = ?");
                    $stmt->execute([$hash_pedido, $order_id]);
                    
                    // 3. Consultar datos del curso para saber tipo de pago y duración
                    $stmt = $pdo->prepare("SELECT payment_type, subscription_period FROM courses WHERE id = ?");
                    $stmt->execute([$order['course_id']]);
                    $course = $stmt->fetch();
                    
                    $expires_at = null;
                    if ($course && $course['payment_type'] === 'subscription') {
                        $period = intval($course['subscription_period'] ?: 30);
                        $expires_at = date('Y-m-d H:i:s', strtotime("+$period days"));
                    }
                    
                    // 4. Inscribir al estudiante o extender acceso
                    $stmt = $pdo->prepare("
                        INSERT INTO enrollments (user_id, course_id, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE expires_at = ?, created_at = NOW()
                    ");
                    $stmt->execute([$order['user_id'], $order['course_id'], $expires_at, $expires_at]);
                    
                    $pdo->commit();
                    file_put_contents('pagopar_ipn_log.txt', "[" . date('Y-m-d H:i:s') . "] ✅ PEDIDO APROBADO: Acceso otorgado al usuario #" . $order['user_id'] . " para el curso #" . $order['course_id'] . " (Expira: " . ($expires_at ?: 'Ilimitado') . ").\n", FILE_APPEND);
                }
            } else {
                // Notificación recibida pero el pago no se concretó
                $stmt = $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ? AND status = 'pending'");
                $stmt->execute([$order_id]);
                file_put_contents('pagopar_ipn_log.txt', "[" . date('Y-m-d H:i:s') . "] ⚠️ NOTIFICACIÓN: Pedido #$order_id no está pagado en Pagopar.\n", FILE_APPEND);
            }
        }
    } else {
        // Transacción cancelada o fallida por completo
        if (!empty($data['resultado'])) {
            foreach ($data['resultado'] as $result) {
                $order_id = intval($result['numero_pedido'] ?? 0);
                if ($order_id > 0) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$order_id]);
                    file_put_contents('pagopar_ipn_log.txt', "[" . date('Y-m-d H:i:s') . "] ❌ TRANSACCIÓN CANCELADA: Pedido #$order_id cancelado por el usuario.\n", FILE_APPEND);
                }
            }
        }
    }
    
    // Pagopar recomienda retornar el mismo JSON que enviaron o 'OK'
    echo "OK";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents('pagopar_ipn_log.txt', "[" . date('Y-m-d H:i:s') . "] ❌ ERROR FATAL IPN: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "Error del servidor al procesar callback.";
}
