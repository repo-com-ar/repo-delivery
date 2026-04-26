<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../repo-api/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$token      = trim($body['token']      ?? '');
$contrasena = trim($body['contrasena'] ?? '');

if (!$token || !$contrasena) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}

if (strlen($contrasena) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id FROM repartidores WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $rep = $stmt->fetch();

    if (!$rep) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'El enlace expiró o no es válido']);
        exit;
    }

    $pdo->prepare("UPDATE repartidores SET contrasena = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
        ->execute([$contrasena, $rep['id']]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno']);
}
