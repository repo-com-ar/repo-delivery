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
require_once __DIR__ . '/../../repo-api/lib/pushservice.php';

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

// Tarifa según tipo de vehículo del repartidor (solo para mostrar pago estimado)
$tarifa_base      = 0;
$tarifa_km        = 0;
$repartidorNombre = '';
if ($repId) {
    try {
        // Paso 1: obtener tipo de vehículo del repartidor
        $stVeh = $pdo->prepare("SELECT vehiculo, nombre FROM repartidores WHERE id = ? LIMIT 1");
        $stVeh->execute([$repId]);
        $vehRow = $stVeh->fetch();
        $vehiculo          = $vehRow['vehiculo'] ?? null;
        $repartidorNombre  = $vehRow['nombre']   ?? '';

        // Paso 2: buscar tarifa para ese vehículo (activa primero, cualquiera como fallback)
        if ($vehiculo) {
            $stTar = $pdo->prepare("
                SELECT precio_base, precio_por_km
                FROM tarifas
                WHERE tipo_vehiculo = ?
                ORDER BY activa DESC, id DESC
                LIMIT 1
            ");
            $stTar->execute([$vehiculo]);
            $tr = $stTar->fetch();
            if ($tr) {
                $tarifa_base = (float)$tr['precio_base'];
                $tarifa_km   = (float)$tr['precio_por_km'];
            }
        }
    } catch (Exception $e) { /* tabla no disponible, continúa con pago_estimado = 0 */ }
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: listar pedidos para repartidor ───────────────
if ($method === 'GET') {

    // Pedidos en asignacion sin repartidor asignado (disponibles para tomar)
    $stmtDisp = $pdo->query("
        SELECT p.id, p.numero, p.cliente, p.celular, p.correo, p.direccion, p.notas,
               p.total, p.estado, p.retiro_lat, p.retiro_lng, p.entrega_lat, p.entrega_lng,
               p.distancia_km, p.tiempo_min, p.created_at AS fecha,
               d.nombre AS deposito_nombre, d.domicilio AS deposito_domicilio
        FROM pedidos p
        LEFT JOIN depositos d ON d.id = p.deposito_id
        WHERE p.estado = 'asignacion'
          AND (p.repartidor_id IS NULL OR p.repartidor_id = 0)
        ORDER BY p.id DESC
    ");
    $disponibles = $stmtDisp->fetchAll();

    // Para entregar: pedidos asignados a este repartidor
    $stmtListos = $pdo->prepare("
        SELECT p.id, p.numero, p.cliente, p.celular, p.correo, p.direccion, p.notas,
               p.total, p.repartidor_tarifa, p.estado, p.retiro_lat, p.retiro_lng, p.entrega_lat, p.entrega_lng,
               p.distancia_km, p.tiempo_min, p.created_at AS fecha,
               d.nombre AS deposito_nombre, d.domicilio AS deposito_domicilio
        FROM pedidos p
        LEFT JOIN depositos d ON d.id = p.deposito_id
        WHERE p.estado = 'reparto'
          AND p.repartidor_id = ?
        ORDER BY p.id ASC
    ");
    $stmtListos->execute([$repId]);
    $listos = $stmtListos->fetchAll();

    // Entregados hoy por este repartidor o sin asignar
    $stmtEntregados = $pdo->prepare("
        SELECT p.id, p.numero, p.cliente, p.celular, p.direccion, p.total, p.repartidor_tarifa, p.estado,
               p.retiro_lat, p.retiro_lng, p.entrega_lat, p.entrega_lng,
               p.distancia_km, p.tiempo_min, p.created_at AS fecha,
               p.updated_at AS entregado_at,
               d.nombre AS deposito_nombre, d.domicilio AS deposito_domicilio
        FROM pedidos p
        LEFT JOIN depositos d ON d.id = p.deposito_id
        WHERE p.estado = 'entregado'
          AND (p.repartidor_id = ? OR p.repartidor_id IS NULL OR p.repartidor_id = 0)
          AND DATE(p.updated_at) = CURDATE()
        ORDER BY p.updated_at DESC
        LIMIT 50
    ");
    $stmtEntregados->execute([$repId]);
    $entregados = $stmtEntregados->fetchAll();

    // Items + pago estimado solo para disponibles
    foreach ($disponibles as &$p) {
        $st = $pdo->prepare("SELECT nombre, precio, cantidad FROM pedido_items WHERE pedido_id = ?");
        $st->execute([$p['id']]);
        $p['items'] = $st->fetchAll();
        $p['total'] = (float)$p['total'];
        $km = (float)($p['distancia_km'] ?? 0);
        $p['pago_estimado'] = round($tarifa_base + ($km * $tarifa_km), 2);
    }
    foreach ($listos as &$p) {
        $st = $pdo->prepare("SELECT nombre, precio, cantidad FROM pedido_items WHERE pedido_id = ?");
        $st->execute([$p['id']]);
        $p['items'] = $st->fetchAll();
        $p['total']             = (float)$p['total'];
        $p['repartidor_tarifa'] = (float)($p['repartidor_tarifa'] ?? 0);
    }
    foreach ($entregados as &$p) {
        $p['total']             = (float)$p['total'];
        $p['repartidor_tarifa'] = (float)($p['repartidor_tarifa'] ?? 0);
        $p['items'] = [];
    }

    echo json_encode([
        'ok'                => true,
        'repartidor_nombre' => $repartidorNombre,
        'disponibles'       => $disponibles,
        'listos'            => $listos,
        'entregados'        => $entregados,
        'stats'             => [
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
        // Atómico: asigna repartidor, calcula pago y cambia estado en una sola operación
        $stmt = $pdo->prepare("
            UPDATE pedidos
            SET repartidor_id   = ?,
                repartidor_tarifa = ROUND(? + (COALESCE(distancia_km, 0) * ?), 2),
                estado          = 'reparto'
            WHERE id = ?
              AND estado = 'asignacion'
              AND (repartidor_id IS NULL OR repartidor_id = 0)
        ");
        $stmt->execute([$repId, $tarifa_base, $tarifa_km, $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Este pedido ya fue tomado por otro repartidor']);
            exit;
        }

        // Notificar al cliente que el pedido salió en reparto
        $info = $pdo->prepare("SELECT cliente_id, numero FROM pedidos WHERE id = ?");
        $info->execute([$id]);
        $row = $info->fetch();
        if ($row && !empty($row['cliente_id'])) {
            @push_enviar_a('cliente', (int)$row['cliente_id'],
                '🛵 Tu pedido está en camino',
                'Pedido ' . ($row['numero'] ?? '') . ' — Llega pronto a tu domicilio.',
                [
                    'url'       => './',
                    'tag'       => 'pedido-cli-' . $id,
                    'pedido_id' => $id,
                ]
            );
        }

        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    // ── Abandonar pedido (devolverlo a asignacion para que lo tome otro) ──
    if ($accion === 'abandonar') {
        if (!$repId) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }
        // Atómico: solo permite abandonar si el pedido está en reparto y asignado a este repartidor
        $stmt = $pdo->prepare("
            UPDATE pedidos
            SET repartidor_id     = NULL,
                repartidor_tarifa = 0,
                estado            = 'asignacion'
            WHERE id = ?
              AND estado = 'reparto'
              AND repartidor_id = ?
        ");
        $stmt->execute([$id, $repId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'No podés abandonar este pedido']);
            exit;
        }

        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    // ── Cambiar estado ──
    $estado    = trim($body['estado'] ?? '');
    $permitidos = ['entregado'];
    if (!in_array($estado, $permitidos)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción o estado inválido']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Solo liberar stock_comprometido si la transición es desde un estado activo
        // (evita doble descuento si se reenvía la petición con el pedido ya entregado)
        $prev = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ?");
        $prev->execute([$id]);
        $estadoAnterior = $prev->fetchColumn();

        if ($estadoAnterior === false) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }

        // Estados terminales: no se puede modificar un pedido cancelado ni uno ya entregado.
        if (in_array($estadoAnterior, ['cancelado', 'entregado'], true) && $estadoAnterior !== $estado) {
            $pdo->rollBack();
            http_response_code(409);
            $msg = $estadoAnterior === 'cancelado'
                ? 'Un pedido cancelado no puede reactivarse'
                : 'Un pedido entregado no puede modificar su estado';
            echo json_encode(['ok' => false, 'error' => $msg]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);

        // Liberar stock_comprometido al entregar (la mercadería salió del depósito)
        if ($estado === 'entregado' &&
            $estadoAnterior !== 'entregado' &&
            $estadoAnterior !== 'cancelado') {
            $items = $pdo->prepare("SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = ?");
            $items->execute([$id]);
            $libera = $pdo->prepare("
                UPDATE productos
                SET stock_comprometido = GREATEST(0, stock_comprometido - ?)
                WHERE id = ?
            ");
            foreach ($items->fetchAll() as $it) {
                if (!empty($it['producto_id'])) {
                    $libera->execute([(int)$it['cantidad'], (int)$it['producto_id']]);
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al cambiar estado']);
        exit;
    }

    // Notificar al cliente cuando el pedido fue entregado
    if ($estado === 'entregado') {
        $info = $pdo->prepare("SELECT cliente_id, numero FROM pedidos WHERE id = ?");
        $info->execute([$id]);
        $row = $info->fetch();
        if ($row && !empty($row['cliente_id'])) {
            @push_enviar_a('cliente', (int)$row['cliente_id'],
                '✅ ¡Pedido entregado!',
                'Pedido ' . ($row['numero'] ?? '') . ' — ¡Gracias por tu compra!',
                [
                    'url'       => './',
                    'tag'       => 'pedido-cli-' . $id,
                    'pedido_id' => $id,
                ]
            );
        }
    }

    echo json_encode(['ok' => true, 'id' => $id, 'estado' => $estado]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
