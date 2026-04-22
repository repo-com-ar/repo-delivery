<?php
/**
 * Pedidos API — Repartidores
 *
 * GET  → disponibles (pendiente sin repartidor) + listos + entregados de hoy
 * PUT  { id, accion: 'tomar' }   → asigna este repartidor al pedido
 * PUT  { id, estado }            → cambiar estado (listo → entregado)
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();
$repPayload = authRepartidor();
$repId      = (int)($repPayload['id'] ?? 0);

require_once __DIR__ . '/../../repo-api/config/db.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// Migración inline: agregar repartidor_id si no existe
try {
    $pdo->query("SELECT repartidor_id FROM pedidos LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN repartidor_id INT UNSIGNED DEFAULT NULL AFTER cliente_id");
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: listar pedidos para repartidor ───────────────
if ($method === 'GET') {

    // Pedidos pendientes sin repartidor asignado (disponibles para tomar)
    $stmtDisp = $pdo->query("
        SELECT id, numero, cliente, celular, correo, direccion, notas,
               total, estado, lat, lng, distancia_km, tiempo_min,
               created_at AS fecha
        FROM pedidos
        WHERE estado = 'pendiente'
          AND (repartidor_id IS NULL OR repartidor_id = 0)
        ORDER BY id DESC
    ");
    $disponibles = $stmtDisp->fetchAll();

    // Para entregar: pedidos asignados a este repartidor (cualquier estado activo)
    //               + pedidos en 'listo' sin repartidor asignado aún
    $stmtListos = $pdo->prepare("
        SELECT id, numero, cliente, celular, correo, direccion, notas,
               total, estado, lat, lng, distancia_km, tiempo_min,
               created_at AS fecha
        FROM pedidos
        WHERE estado NOT IN ('entregado', 'cancelado')
          AND (
            repartidor_id = ?
            OR (estado = 'listo' AND (repartidor_id IS NULL OR repartidor_id = 0))
          )
        ORDER BY id ASC
    ");
    $stmtListos->execute([$repId]);
    $listos = $stmtListos->fetchAll();

    // Entregados hoy por este repartidor o sin asignar
    $stmtEntregados = $pdo->prepare("
        SELECT id, numero, cliente, celular, direccion, total, estado,
               lat, lng, distancia_km, tiempo_min, created_at AS fecha,
               updated_at AS entregado_at
        FROM pedidos
        WHERE estado = 'entregado'
          AND (repartidor_id = ? OR repartidor_id IS NULL OR repartidor_id = 0)
          AND DATE(updated_at) = CURDATE()
        ORDER BY updated_at DESC
        LIMIT 50
    ");
    $stmtEntregados->execute([$repId]);
    $entregados = $stmtEntregados->fetchAll();

    // Items
    foreach ($disponibles as &$p) {
        $st = $pdo->prepare("SELECT nombre, precio, cantidad FROM pedido_items WHERE pedido_id = ?");
        $st->execute([$p['id']]);
        $p['items'] = $st->fetchAll();
        $p['total'] = (float)$p['total'];
    }
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

    echo json_encode([
        'ok'          => true,
        'disponibles' => $disponibles,
        'listos'      => $listos,
        'entregados'  => $entregados,
        'stats'       => [
            'disponibles' => count($disponibles),
            'listos'      => count($listos),
            'entregados'  => count($entregados),
        ],
    ]);
    exit;
}

// ─── PUT: tomar pedido o cambiar estado ───────────────
if ($method === 'PUT') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = isset($body['id']) ? (int)$body['id'] : 0;
    $accion = trim($body['accion'] ?? '');

    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ID requerido']);
        exit;
    }

    // ── Tomar pedido ──
    if ($accion === 'tomar') {
        if (!$repId) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }
        // Atómico: solo asigna si aún no tiene repartidor y está pendiente
        $stmt = $pdo->prepare("
            UPDATE pedidos
            SET repartidor_id = ?
            WHERE id = ?
              AND estado = 'pendiente'
              AND (repartidor_id IS NULL OR repartidor_id = 0)
        ");
        $stmt->execute([$repId, $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Este pedido ya fue tomado por otro repartidor']);
            exit;
        }

        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    // ── Cambiar estado ──
    $estado    = trim($body['estado'] ?? '');
    $permitidos = ['entregado', 'listo'];
    if (!in_array($estado, $permitidos)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción o estado inválido']);
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
