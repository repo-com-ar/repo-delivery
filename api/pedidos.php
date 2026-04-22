<?php
/**
 * Pedidos API — Repartidores
 *
 * GET  → pedidos en estado 'listo' + entregados de hoy
 * PUT  { id, estado }  → cambiar estado (listo → entregado)
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

require_once __DIR__ . '/../../repo-api/config/db.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: listar pedidos para repartidor ───────────────
if ($method === 'GET') {
    // Pedidos listos (pendientes de entregar)
    $stmtListos = $pdo->query("
        SELECT id, numero, cliente, celular, correo, direccion, notas,
               total, estado, lat, lng, distancia_km, tiempo_min,
               created_at AS fecha
        FROM pedidos
        WHERE estado = 'listo'
        ORDER BY id ASC
    ");
    $listos = $stmtListos->fetchAll();

    // Entregados hoy
    $stmtEntregados = $pdo->query("
        SELECT id, numero, cliente, celular, direccion, total, estado,
               lat, lng, distancia_km, tiempo_min, created_at AS fecha,
               updated_at AS entregado_at
        FROM pedidos
        WHERE estado = 'entregado'
          AND DATE(updated_at) = CURDATE()
        ORDER BY updated_at DESC
        LIMIT 50
    ");
    $entregados = $stmtEntregados->fetchAll();

    // Items de los pedidos listos
    foreach ($listos as &$p) {
        $st = $pdo->prepare("SELECT nombre, precio, cantidad FROM pedido_items WHERE pedido_id = ?");
        $st->execute([$p['id']]);
        $p['items'] = $st->fetchAll();
        $p['total'] = (float)$p['total'];
    }
    foreach ($entregados as &$p) {
        $p['total'] = (float)$p['total'];
        $p['items'] = [];
    }

    $stats = [
        'listos'     => count($listos),
        'entregados' => count($entregados),
    ];

    echo json_encode([
        'ok'        => true,
        'listos'    => $listos,
        'entregados'=> $entregados,
        'stats'     => $stats,
    ]);
    exit;
}

// ─── PUT: cambiar estado ───────────────────────────────
if ($method === 'PUT') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = isset($body['id'])     ? (int)$body['id']      : 0;
    $estado = isset($body['estado']) ? trim($body['estado']) : '';

    $permitidos = ['entregado', 'listo'];
    if (!$id || !in_array($estado, $permitidos)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ID y estado válido requeridos']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
    $stmt->execute([$estado, $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    echo json_encode(['ok' => true, 'id' => $id, 'estado' => $estado]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
