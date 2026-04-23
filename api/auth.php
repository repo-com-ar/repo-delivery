<?php
/**
 * Auth API — Repartidores
 *
 * POST  { celular, clave }  → login, setea cookie delivery_token
 * DELETE                    → logout, limpia cookie
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
require_once __DIR__ . '/../../repo-api/config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    clearAuthCookie();
    echo json_encode(['ok' => true]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$correo    = trim($body['correo']    ?? '');
$contrasena= trim($body['contrasena']?? '');

if (!$correo || !$contrasena) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Correo y contraseña requeridos']);
    exit;
}

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre, correo, celular FROM repartidores WHERE correo = ? AND contrasena = ? LIMIT 1");
$stmt->execute([$correo, $contrasena]);
$rep = $stmt->fetch();

if (!$rep) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Celular o clave incorrectos']);
    exit;
}

$token = jwt_encode([
    'id'     => (int)$rep['id'],
    'nombre' => $rep['nombre'],
    'correo' => $rep['correo'] ?? '',
    'celular'=> $rep['celular'] ?? '',
    'rol'    => 'repartidor',
    'exp'    => time() + JWT_TTL,
]);

setAuthCookie($token);

echo json_encode([
    'ok'     => true,
    'nombre' => $rep['nombre'],
    'id'     => (int)$rep['id'],
]);
