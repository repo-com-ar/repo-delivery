<?php
/**
 * API — Seguimiento de ubicación del repartidor
 *
 * POST  { lat, lng }  → registra un punto en repartidores_ubicaciones
 *                       y actualiza repartidores.ubicacion_at
 *
 * Usa la tabla `repartidores_ubicaciones` y la columna `repartidores.ubicacion_at`
 * creadas por repo-api/setup/install.php.
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
require_once __DIR__ . '/../../repo-api/config/db.php';

requireAuth();
$rep   = authRepartidor();
$repId = (int)($rep['id'] ?? 0);
if (!$repId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// Garantizar columna ubicacion_activa (puede correrse antes del install)
try {
    $pdo->query("SELECT ubicacion_activa FROM repartidores LIMIT 1");
} catch (Throwable $e) {
    try { $pdo->exec("ALTER TABLE repartidores ADD COLUMN ubicacion_activa TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e2) { /* silencioso */ }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    // El repartidor apagó el seguimiento
    try {
        $pdo->prepare("UPDATE repartidores SET ubicacion_activa = 0 WHERE id = ?")
            ->execute([$repId]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al desactivar seguimiento']);
    }
    exit;
}

// PUT — activa el flag sin registrar coordenadas (llamado al encender el switch)
if ($method === 'PUT') {
    try {
        $pdo->prepare("UPDATE repartidores SET ubicacion_activa = 1 WHERE id = ?")
            ->execute([$repId]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al activar seguimiento']);
    }
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$lat = isset($body['lat']) ? (float)$body['lat'] : null;
$lng = isset($body['lng']) ? (float)$body['lng'] : null;

if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'lat y lng válidos son requeridos']);
    exit;
}

try {
    $pdo->prepare("INSERT INTO repartidores_ubicaciones (repartidor_id, lat, lng) VALUES (?, ?, ?)")
        ->execute([$repId, $lat, $lng]);
    $pdo->prepare("UPDATE repartidores SET ubicacion_at = NOW(), ubicacion_activa = 1 WHERE id = ?")
        ->execute([$repId]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al registrar ubicación']);
}
