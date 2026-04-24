<?php
/**
 * API — Heartbeat del repartidor
 *
 * POST  → actualiza repartidores.last_seen = NOW()
 *
 * El cliente lo invoca cada 30 s mientras la pestaña esté visible.
 * Admin determina "en línea" si last_seen está dentro de los últimos 60 s.
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// Garantizar columna last_seen
try {
    $pdo->query("SELECT last_seen FROM repartidores LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE repartidores ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL, ADD INDEX idx_last_seen (last_seen)");
    } catch (Throwable $e2) { /* silencioso */ }
}

try {
    $pdo->prepare("UPDATE repartidores SET last_seen = NOW() WHERE id = ?")->execute([$repId]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en heartbeat']);
}
