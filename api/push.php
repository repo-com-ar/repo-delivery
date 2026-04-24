<?php
/**
 * Push Subscriptions API — Repartidores
 *
 * GET     → devuelve la VAPID public key para suscribirse
 * POST    { endpoint, keys:{p256dh,auth} }   → guarda suscripción del repartidor logueado
 * DELETE  { endpoint }                        → borra una suscripción
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
require_once __DIR__ . '/../../repo-api/lib/pushservice.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET no requiere auth — devuelve la public key para suscribirse
if ($method === 'GET') {
    $v = push_vapid_config();
    if (empty($v['public'])) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'VAPID no configurado']);
        exit;
    }
    echo json_encode(['ok' => true, 'publicKey' => $v['public']]);
    exit;
}

requireAuth();
$rep   = authRepartidor();
$repId = (int)($rep['id'] ?? 0);
if (!$repId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $endpoint = trim($body['endpoint'] ?? '');
    $p256dh   = trim($body['keys']['p256dh'] ?? '');
    $auth     = trim($body['keys']['auth']   ?? '');
    if (!$endpoint || !$p256dh || !$auth) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'endpoint, keys.p256dh y keys.auth son requeridos']);
        exit;
    }
    try {
        push_upsert(
            'repartidor',
            $repId,
            $endpoint,
            $p256dh,
            $auth,
            $_SERVER['HTTP_HOST'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)
        );
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $endpoint = trim($body['endpoint'] ?? '');
    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'endpoint requerido']);
        exit;
    }
    push_delete_by_endpoint($endpoint);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
