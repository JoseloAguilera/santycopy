<?php
// subscribe.php

// Permitir peticiones (CORS básico por si acaso, aunque en el mismo dominio no hace falta)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// NOTA: Reemplaza esto con tu API Key real de Mailerlite
$apiToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI0IiwianRpIjoiZmY2Y2UzMmZhZjU2ODI0Y2VhZWJiNmQ5MDQ1Y2JmYjM0MjQyOTg5NzZkMTQ3Y2QxNzRiODczZmNiYzA5N2UzZjUzMmY1OTc4ZjZlYmM5MTMiLCJpYXQiOjE3NzkzMDc2NzAuMzAyMjUsIm5iZiI6MTc3OTMwNzY3MC4zMDIyNTIsImV4cCI6NDkzNDk4MTI3MC4yOTU4MTQsInN1YiI6IjE5OTA5MDciLCJzY29wZXMiOltdfQ.EE8AE8k0cng5pB54cWcBxxxgn0IbLhf6E8O7ccwZ_n2BCC_Gih0eyTZMo2Agg_ihMxllpX2TkeIw-9Xaa6gCI1nq_S9QCi2_lOUAUIVbv7_06_7cQ-dtzTBp6gBpouqbMiAc30sMZwHb_ybigOxga9wfApTxcI5CYeZ88hLu66qIOFx6V7TNITgDHG8nxi6QyRrSe2HjZbhxZ5Ku5YRBGTYHkGj5SyJMMjC3zmfU2WGxnMcjczoxnfDFxoI7v48KOMvivTCGEXVmbZE392jzy17lKeIQUXK4geNovJ22QH4a_ZAAXFvkT3i10vfaDC6_YtqdUsICs2I4tWkY7cRI-pnQB1ddpqf81KBKGJw-Lr7EgRXcJ2KbjuzVWLq568Mi9Td6RbHL7uaL9SKkZwUGfc8TqYWiXyIl0dCNsCxm8GwvXzAH280VX7BJTYhtADCZD_FL93zo1-oI_94RY0w74hfA4i4ShydRTz9YkSfS67qhJ_Zb1do7u1X-frUrANn7FGUh_UuGNUYVsDcBsPbDnO8F2tt6igr1zwRbQLuo-_moQ13R3wlxV_v296gzYAEbUc2oGSHN23y2-jy5kC63NwAsOe1G6H-MYjmx7d7s1iRCw76bG_1sjDWuvXXEE8BsUHTTK4maDPBzD9L-NjuhfLtj52KfOdXHsXjIopM6wEQ';

// Leer los datos JSON enviados por el formulario HTML
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$email = isset($data['email']) ? trim($data['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido o vacío']);
    exit;
}

// Datos para enviar a Mailerlite
$postData = [
    'email' => $email,
    'groups' => ['178405413052483423']
];

// Iniciar petición cURL a la API de Mailerlite
$ch = curl_init('https://connect.mailerlite.com/api/subscribers');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $apiToken
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Devolver la respuesta al Frontend
if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión del servidor: ' . $error]);
} else {
    http_response_code($httpCode);
    echo $response;
}
