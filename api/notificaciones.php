<?php
/**
 * API delivery — Notificaciones del repartidor logueado
 *
 * GET  → lista + cantidad sin leer
 * POST { accion:'marcar_leidas', ids:[...] } → marca como leídas
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

$rep = authRepartidor();
$repId = (int)($rep['id'] ?? 0);
if (!$repId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sin sesión']);
    exit;
}

require_once __DIR__ . '/../../repo-api/config/db.php';
try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Últimas 50 notificaciones del repartidor, sin leer primero
    $st = $pdo->prepare("
        SELECT id, titulo, cuerpo, leida, leida_at, created_at
        FROM notificaciones
        WHERE actor_type = 'repartidor' AND actor_id = ?
        ORDER BY leida ASC, created_at DESC
        LIMIT 50
    ");
    $st->execute([$repId]);
    $rows = $st->fetchAll();

    $sinLeer = 0;
    foreach ($rows as $r) {
        if ((int)$r['leida'] === 0) $sinLeer++;
    }

    echo json_encode([
        'ok'       => true,
        'data'     => $rows,
        'sin_leer' => $sinLeer,
    ]);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $accion = trim($body['accion'] ?? '');

    if ($accion === 'marcar_leidas') {
        $ids = array_filter(array_map('intval', $body['ids'] ?? []));
        if (empty($ids)) {
            // Marcar todas las de este repartidor
            $st = $pdo->prepare("
                UPDATE notificaciones
                SET leida = 1, leida_at = NOW()
                WHERE actor_type = 'repartidor' AND actor_id = ? AND leida = 0
            ");
            $st->execute([$repId]);
        } else {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$repId]);
            $st = $pdo->prepare("
                UPDATE notificaciones
                SET leida = 1, leida_at = NOW()
                WHERE id IN ($place) AND actor_type = 'repartidor' AND actor_id = ? AND leida = 0
            ");
            $st->execute($params);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
