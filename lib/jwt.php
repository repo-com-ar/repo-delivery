<?php
/**
 * JWT HS256 — implementación mínima sin dependencias externas.
 * Exclusivo para repartidores (secret independiente del admin).
 */

define('JWT_SECRET', 'repo-delivery-s3cr3t-2026-dR4kW7nQ');
define('JWT_TTL',    60 * 60 * 24 * 7); // 7 días

function jwt_encode(array $payload): string {
    $header = _b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = _b64u(json_encode($payload));
    $sig    = _b64u(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = _b64u(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(_b64d($body), true);
    if (!$data) return null;
    if (isset($data['exp']) && $data['exp'] < time()) return null;
    return $data;
}

function _b64u(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function _b64d(string $s): string {
    return base64_decode(strtr($s, '-_', '+/'));
}
