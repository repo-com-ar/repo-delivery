<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../../repo-api/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$correo = trim($body['correo'] ?? '');

if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Correo inválido']);
    exit;
}

try {
    $pdo = getDB();

    // Agregar columnas si no existen
    try { $pdo->exec("ALTER TABLE repartidores ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE repartidores ADD COLUMN reset_expires DATETIME NULL DEFAULT NULL"); } catch (PDOException $e) {}

    $stmt = $pdo->prepare("SELECT id, nombre FROM repartidores WHERE correo = ? LIMIT 1");
    $stmt->execute([$correo]);
    $rep = $stmt->fetch();

    if ($rep) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $pdo->prepare("UPDATE repartidores SET reset_token = ?, reset_expires = ? WHERE id = ?")
            ->execute([$token, $expires, $rep['id']]);

        // Leer config DataRocket desde tabla configuracion (igual que enviar_mensaje.php)
        $cfgStmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'datarocket_%'");
        $cfg = [];
        foreach ($cfgStmt->fetchAll() as $row) {
            $cfg[$row['clave']] = $row['valor'];
        }

        $apiUrl     = rtrim($cfg['datarocket_url']         ?? DATAROCKET_URL,          '/');
        $apikey     = $cfg['datarocket_apikey']             ?? DATAROCKET_APIKEY;
        $proyecto   = $cfg['datarocket_proyecto']           ?? DATAROCKET_PROYECTO;
        $canalEmail = $cfg['datarocket_canal_email']        ?? DATAROCKET_CANAL_EMAIL;
        $remitente  = $cfg['datarocket_remitente']          ?? DATAROCKET_REMITENTE;
        $remite     = $cfg['datarocket_remite']             ?? DATAROCKET_REMITE;

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];
        $appDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
        $url    = $scheme . '://' . $host . $appDir . '/reset?token=' . $token;

        $nombre = $rep['nombre'];
        $cuerpo = "Hola {$nombre},\n\nRecibimos una solicitud para restablecer tu contraseña de Repo Delivery.\n\nHacé clic en el siguiente enlace para crear una nueva contraseña (válido por 1 hora):\n\n{$url}\n\nSi no solicitaste este cambio, podés ignorar este mensaje.\n\nRepo Online";

        $payload = [
            'servicio'     => 'awsses',
            'proyecto'     => $proyecto,
            'canal'        => $canalEmail,
            'plantilla'    => 'repo',
            'remitente'    => $remitente,
            'remite'       => $remite,
            'destinatario' => $nombre,
            'destino'      => $correo,
            'asunto'       => 'Recuperar contraseña – Repo Delivery',
            'cuerpo'       => $cuerpo,
            'formato'      => 'T',
        ];

        $ch = curl_init($apiUrl . '/v3/datarocket/mensajes/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
} catch (Exception $e) {
    // No revelamos errores internos al cliente
}

// Siempre ok — no revelamos si el correo existe o no
echo json_encode(['ok' => true]);
