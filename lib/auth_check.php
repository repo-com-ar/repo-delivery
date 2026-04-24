<?php
/**
 * Middleware de autenticación para repartidores.
 * Cookie: delivery_token (JWT)
 *
 * authRepartidor()  → devuelve el payload o null
 * requireAuth()     → corta la ejecución si no hay sesión válida
 */

require_once __DIR__ . '/jwt.php';

function authRepartidor(): ?array {
    $token = $_COOKIE['delivery_token'] ?? '';
    if (!$token) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            $token = $m[1];
        }
    }
    if (!$token) return null;
    return jwt_decode($token);
}

function requireAuth(): void {
    if (authRepartidor()) return;

    $uri   = $_SERVER['REQUEST_URI'] ?? '';
    $isApi = strpos($uri, '/api/') !== false;

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'No autorizado', 'login' => true]);
        exit;
    }

    header('Location: login.php');
    exit;
}

function setAuthCookie(string $token): void {
    setcookie('delivery_token', $token, time() + JWT_TTL, '/');
}

function clearAuthCookie(): void {
    setcookie('delivery_token', '', time() - 3600, '/');
    unset($_COOKIE['delivery_token']);
}
