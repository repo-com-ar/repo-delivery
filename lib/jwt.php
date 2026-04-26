<?php
require_once __DIR__ . '/../../repo-api/config/db.php';

define('JWT_TTL', 60 * 60 * 24 * 7);

function _jwt_secret(): string {
    static $s = null;
    if ($s === null) $s = getConfigValue('jwt_secret_delivery');
    return $s;
}

function jwt_encode(array $payload): string {
    $header = _b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = _b64u(json_encode($payload));
    $sig    = _b64u(hash_hmac('sha256', "$header.$body", _jwt_secret(), true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = _b64u(hash_hmac('sha256', "$header.$body", _jwt_secret(), true));
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
