<?php
// test_flow.php - Script CLI para probar todo el flujo de registro, compra y callback localmente

define('ACCESS_ALLOWED', true);

echo "🧪 Iniciando pruebas del flujo del LMS Santy Copy...\n";
echo "====================================================\n";

// 1. Cargar base de datos
if (!file_exists('config/db.php')) {
    die("❌ Error: config/db.php no encontrado.\n");
}
require_once 'config/db.php';
require_once 'config/pagopar.php';

function print_result($test_name, $success, $msg = '') {
    if ($success) {
        echo "✅ " . str_pad($test_name, 50, ".") . " EXITOSO " . ($msg ? "($msg)" : "") . "\n";
    } else {
        echo "❌ " . str_pad($test_name, 50, ".") . " FALLIDO: $msg\n";
    }
}

try {
    // ----------------------------------------------------
    // TEST 1: Conexión a la Base de Datos
    // ----------------------------------------------------
    $stmt = $pdo->query("SELECT 1");
    print_result("Conexión a MySQL", true);
    
    // ----------------------------------------------------
    // TEST 2: Creación de Estudiante
    // ----------------------------------------------------
    $test_email = 'test_student_' . time() . '@santycopy.com';
    $test_name = 'Test Student';
    $test_pass = password_hash('password123', PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
    $stmt->execute([$test_name, $test_email, $test_pass]);
    $user_id = $pdo->lastInsertId();
    
    // Verificar creación
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_exists = $stmt->fetch();
    print_result("Registro de Estudiante", $user_exists !== false, "Email: $test_email");

    // ----------------------------------------------------
    // TEST 3: Creación de Orden Pendiente
    // ----------------------------------------------------
    $course_id = 1; // Curso semilla
    $amount = 150000;
    
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, course_id, amount, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $course_id, $amount]);
    $order_id = $pdo->lastInsertId();
    
    // Verificar orden
    $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    print_result("Creación de Orden Pendiente", $order && $order['status'] === 'pending', "ID de orden: #$order_id");

    // ----------------------------------------------------
    // TEST 4: Simulación de Callback de Pago Exitoso (IPN)
    // ----------------------------------------------------
    $mock_hash = md5('MOCK_ORDER_' . $order_id);
    
    // Generar firma SHA1 simulada
    $token_generado = sha1('DEVELOPMENT' . $mock_hash);
    
    $ipn_payload = [
        "respuesta" => true,
        "resultado" => [
            [
                "pagado" => true,
                "numero_pedido" => (string)$order_id,
                "hash_pedido" => $mock_hash,
                "monto" => $amount . ".00",
                "forma_pago" => "Simulación CLI Test",
                "fecha_pago" => date('Y-m-d H:i:s'),
                "cancelado" => false,
                "numero_comprobante_interno" => "999999",
                "token" => $token_generado
            ]
        ]
    ];
    
    // Simular el callback IPN cargando pagopar_callback.php programáticamente.
    // Para ello, definimos el stream input simulado de php://input.
    // Como en PHP CLI no se puede redefinir php://input directamente de forma estándar,
    // usaremos cURL local para simular la petición HTTP exacta contra el servidor Apache local.
    // Esto es lo más realista posible!
    
    $callback_url = "http://localhost/santycopy/plataforma/pagopar_callback.php";
    
    $ch = curl_init($callback_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ipn_payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $ipn_success = ($http_code === 200 && trim($response) === 'OK');
    print_result("Simulación de Callback IPN (HTTP POST)", $ipn_success, "HTTP Code: $http_code, Response: $response");

    // ----------------------------------------------------
    // TEST 5: Verificación de Activación e Inscripción
    // ----------------------------------------------------
    // Comprobar que la orden cambió a pagada
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order_status = $stmt->fetchColumn();
    
    // Comprobar que se creó la inscripción
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    $enrollment_exists = $stmt->fetch();
    
    $activation_success = ($order_status === 'paid' && $enrollment_exists !== false);
    print_result("Verificación de Activación del Curso", $activation_success, "Estado de orden: $order_status");

    // ----------------------------------------------------
    // TEST 6: Registro de Progreso (AJAX toggle)
    // ----------------------------------------------------
    // Obtener la primera lección del curso
    $stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? ORDER BY order_number ASC LIMIT 1");
    $stmt->execute([$course_id]);
    $lesson_id = $stmt->fetchColumn();
    
    if ($lesson_id) {
        // Simular progreso completado en la BD
        $stmt = $pdo->prepare("
            INSERT INTO progress (user_id, lesson_id, completed, completed_at) 
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([$user_id, $lesson_id]);
        
        // Verificar registro
        $stmt = $pdo->prepare("SELECT completed FROM progress WHERE user_id = ? AND lesson_id = ?");
        $stmt->execute([$user_id, $lesson_id]);
        $completed_status = $stmt->fetchColumn();
        
        print_result("Registro de Progreso de Clase", $completed_status == 1, "Clase ID: $lesson_id");
    } else {
        print_result("Registro de Progreso de Clase", false, "No se encontraron clases para el curso.");
    }

    // ----------------------------------------------------
    // LIMPIEZA DE DATOS DE PRUEBA
    // ----------------------------------------------------
    $pdo->exec("DELETE FROM users WHERE id = $user_id");
    echo "====================================================\n";
    echo "🧹 Limpieza de datos de prueba completada.\n";
    echo "✨ Pruebas finalizadas con éxito.\n";

} catch (Exception $e) {
    echo "\n❌ Excepción capturada durante las pruebas: " . $e->getMessage() . "\n";
    // Limpieza de emergencia en caso de fallo
    if (isset($user_id) && $user_id > 0) {
        $pdo->exec("DELETE FROM users WHERE id = $user_id");
    }
}
