<?php
// checkout.php - Procesa el inicio de compra y redirige a la pasarela o simulador

require_once 'includes/auth_helper.php';
require_once 'config/db.php';
require_once 'config/pagopar.php';

// Validar que esté logueado. Si no, mandar a login y guardar redirección
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}

$user = get_logged_user();
$course_id = intval($_GET['course_id'] ?? 0);

if ($course_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // 1. Obtener datos del curso
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        die("El curso seleccionado no existe.");
    }
    
    // 2. Verificar si ya está inscrito de forma activa
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$user['id'], $course_id]);
    if ($stmt->fetch()) {
        header('Location: student/course_view.php?course_id=' . $course_id);
        exit;
    }
    
    // 3. Crear una orden de pago en estado 'pendiente'
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, course_id, amount, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$user['id'], $course_id, $course['price']]);
    $order_id = $pdo->lastInsertId();
    
    // 4. Direccionamiento según el Entorno
    if (PAGOPAR_ENV === 'development') {
        // Redirigir al simulador interactivo local
        header('Location: mock_checkout.php?order_id=' . $order_id);
        exit;
    } else {
        // --- MODO PRODUCCIÓN: CONEXIÓN CON PAGOPAR REAL ---
        
        // Generar firma SHA1
        // Fórmula: sha1(comercio_token_privado + idPedido + monto)
        $monto_total = floatval($course['price']);
        $token = sha1(PAGOPAR_PRIVATE_KEY . $order_id . strval($monto_total));
        
        // Preparar payload JSON
        $payload = [
            'token' => $token,
            'public_key' => PAGOPAR_PUBLIC_KEY,
            'monto_total' => $monto_total,
            'tipo_pedido' => 'VENTA-COMERCIO',
            'id_pedido_comercio' => (string)$order_id,
            'descripcion_resumen' => 'Acceso al curso: ' . $course['title'],
            'comprador' => [
                'nombre' => $user['name'],
                'email' => $user['email'],
                'documento' => '0000000', // Campo genérico o requerido
                'tipo_documento' => 'CI',
                'telefono' => '0900000000'
            ],
            'compras_items' => [
                [
                    'nombre' => $course['title'],
                    'cantidad' => 1,
                    'precio_total' => $monto_total
                ]
            ]
        ];
        
        // Ejecutar llamada cURL
        $ch = curl_init(PAGOPAR_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_curl = curl_error($ch);
        curl_close($ch);
        
        if ($error_curl) {
            die("Error de comunicación con la pasarela de pagos: " . h($error_curl));
        }
        
        $res_data = json_decode($response, true);
        
        if (isset($res_data['respuesta']) && $res_data['respuesta'] === true && !empty($res_data['resultado'])) {
            $payment_hash = $res_data['resultado'][0]['data'];
            
            // Guardar el hash en nuestra orden para contrastar en el callback
            $stmt = $pdo->prepare("UPDATE orders SET payment_hash = ? WHERE id = ?");
            $stmt->execute([$payment_hash, $order_id]);
            
            // Redirigir al Checkout de Pagopar
            header('Location: ' . PAGOPAR_CHECKOUT_URL . $payment_hash);
            exit;
        } else {
            // Mostrar error retornado por Pagopar
            $error_msg = isset($res_data['resultado']) ? $res_data['resultado'] : 'Error desconocido al procesar la orden.';
            if (is_array($error_msg)) {
                $error_msg = json_encode($error_msg, JSON_UNESCAPED_UNICODE);
            }
            
            die("Pagopar API Error (HTTP $http_code): " . h($error_msg) . "<br><br><a href='index.php'>Volver al catálogo</a>");
        }
    }
} catch (PDOException $e) {
    die("Error procesando checkout: " . $e->getMessage());
}
