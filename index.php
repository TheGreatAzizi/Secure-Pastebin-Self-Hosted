<?php
// Secure Pastebin - router + API

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = ' --------- ';
const DB_PASS = ' --------- ';
const DB_NAME = ' --------- ';

const MIN_EXPIRY_SECONDS = 300;
const MAX_EXPIRY_SECONDS = 31536000;
const MAX_DATA_BYTES = 1024 * 1024 * 4;
const API_VERSION = '1.2';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = normalizeScriptDir(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

if ($scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
    $path = $path === '' ? '/' : $path;
}

$path = normalizeRoutePath($path);

$apiPaths = [
    '/api/create',
    '/api/options',
    '/api/health',
    '/api/docs',
];
$startsWithApi = strpos($path, '/api/') === 0;

if ($startsWithApi || in_array($path, $apiPaths, true)) {
    sendCorsHeaders();
    if ($method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

if ($path === '/api/docs' || $path === '/api-docs.php') {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/api-docs.php';
    exit;
}

if ($path === '/style.css') {
    serveFile(__DIR__ . '/style.css', 'text/css; charset=utf-8');
}

if ($path === '/script.js') {
    serveFile(__DIR__ . '/script.js', 'application/javascript; charset=utf-8');
}

if ($path === '/favicon.svg') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#6366f1"/><stop offset="100%" style="stop-color:#22d3ee"/></linearGradient></defs><rect width="100" height="100" rx="24" fill="url(#g)"/><path d="M50 24c-8.6 0-15 6.4-15 15v7h-3c-3.3 0-6 2.7-6 6v22c0 3.3 2.7 6 6 6h36c3.3 0 6-2.7 6-6V52c0-3.3-2.7-6-6-6h-3v-7c0-8.6-6.4-15-15-15Zm0 7c4.6 0 8 3.4 8 8v7H42v-7c0-4.6 3.4-8 8-8Z" fill="white"/></svg>';
    exit;
}

if ($path === '/' || $path === '/index.html' || preg_match('#^/p/[^/]+$#', $path)) {
    serveFile(__DIR__ . '/index.html', 'text/html; charset=utf-8');
}

if ($path === '/api/options' && $method === 'GET') {
    jsonResponse(buildApiOptions());
}

if ($path === '/api/health' && $method === 'GET') {
    $pdo = getPdo();
    jsonResponse([
        'success' => true,
        'status' => 'ok',
        'apiVersion' => API_VERSION,
        'database' => $pdo ? 'connected' : 'unavailable',
        'time' => time(),
    ]);
}

if (($path === '/api/pastes' || $path === '/api/create') && $method === 'POST') {
    $pdo = requirePdo();
    cleanupExpired($pdo);
    handleCreate($pdo);
}

if ($method === 'POST' && preg_match('#^/api/pastes/([A-Za-z0-9_-]+)$#', $path, $matches)) {
    $pdo = requirePdo();
    cleanupExpired($pdo);
    handleCreate($pdo, $matches[1]);
}

if ($method === 'GET' && preg_match('#^/api/pastes/([A-Za-z0-9_-]+)/(meta)$#', $path, $matches)) {
    $pdo = requirePdo();
    cleanupExpired($pdo);
    handleMeta($pdo, $matches[1]);
}

if ($method === 'GET' && preg_match('#^/api/pastes/([A-Za-z0-9_-]+)$#', $path, $matches)) {
    $pdo = requirePdo();
    cleanupExpired($pdo);
    handleGet($pdo, $matches[1]);
}

if ($method === 'GET' && preg_match('#^/api/get/([A-Za-z0-9_-]+)$#', $path, $matches)) {
    $pdo = requirePdo();
    cleanupExpired($pdo);
    handleGet($pdo, $matches[1]);
}

jsonResponse([
    'error' => 'Not found',
    'apiVersion' => API_VERSION,
    'available' => [
        'GET /api/health',
        'GET /api/options',
        'GET /api/docs',
        'POST /api/pastes',
        'POST /api/pastes/{id}',
        'GET /api/pastes/{id}',
        'GET /api/pastes/{id}/meta',
        'POST /api/create',
        'GET /api/get/{id}',
    ],
], 404);

function normalizeScriptDir(string $dir): string
{
    $dir = str_replace('\\', '/', $dir);
    if ($dir === '' || $dir === '.') {
        return '/';
    }
    if ($dir[0] !== '/') {
        $dir = '/' . $dir;
    }
    return rtrim($dir, '/') ?: '/';
}

function normalizeRoutePath(string $path): string
{
    if ($path === '') {
        return '/';
    }

    $path = preg_replace('#/+#', '/', $path) ?: '/';
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
        $path = $path === '' ? '/' : $path;
    }

    return $path;
}

function sendCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

function serveFile(string $path, string $contentType): void
{
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    header('Content-Type: ' . $contentType);
    readfile($path);
    exit;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function getPdo(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        $pdo = null;
    }

    return $pdo;
}

function requirePdo(): PDO
{
    $pdo = getPdo();
    if (!$pdo) {
        jsonResponse(['error' => 'Database connection failed', 'apiVersion' => API_VERSION], 500);
    }

    return $pdo;
}

function cleanupExpired(PDO $pdo): void
{
    $stmt = $pdo->prepare('DELETE FROM pastes WHERE expires_at < ?');
    $stmt->execute([time()]);
}

function isValidPasteId(string $id): bool
{
    return (bool) preg_match('/^(?:[a-f0-9]{32}|[A-Za-z0-9_-]{16})$/', $id);
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['error' => 'Invalid JSON body', 'apiVersion' => API_VERSION], 400);
    }

    return $data;
}

function normalizeByteList(array $list, string $fieldName): array
{
    $normalized = [];
    foreach ($list as $value) {
        if (!is_numeric($value)) {
            jsonResponse(['error' => 'Invalid byte value in ' . $fieldName, 'apiVersion' => API_VERSION], 400);
        }

        $int = (int) $value;
        if ($int < 0 || $int > 255) {
            jsonResponse(['error' => 'Byte values in ' . $fieldName . ' must be between 0 and 255', 'apiVersion' => API_VERSION], 400);
        }
        $normalized[] = $int;
    }

    return array_values($normalized);
}

function normalizeEncryptedData(array $encryptedData): array
{
    if (isset($encryptedData['iv'], $encryptedData['data']) && is_array($encryptedData['iv']) && is_array($encryptedData['data'])) {
        return [
            'iv' => normalizeByteList($encryptedData['iv'], 'encryptedData.iv'),
            'data' => normalizeByteList($encryptedData['data'], 'encryptedData.data'),
        ];
    }

    if (isset($encryptedData['ivBase64'], $encryptedData['dataBase64']) && is_string($encryptedData['ivBase64']) && is_string($encryptedData['dataBase64'])) {
        $ivBytes = array_values(unpack('C*', base64urlDecode($encryptedData['ivBase64'])) ?: []);
        $dataBytes = array_values(unpack('C*', base64urlDecode($encryptedData['dataBase64'])) ?: []);
        return ['iv' => $ivBytes, 'data' => $dataBytes];
    }

    if (isset($encryptedData['ivBase64url'], $encryptedData['dataBase64url']) && is_string($encryptedData['ivBase64url']) && is_string($encryptedData['dataBase64url'])) {
        $ivBytes = array_values(unpack('C*', base64urlDecode($encryptedData['ivBase64url'])) ?: []);
        $dataBytes = array_values(unpack('C*', base64urlDecode($encryptedData['dataBase64url'])) ?: []);
        return ['iv' => $ivBytes, 'data' => $dataBytes];
    }

    jsonResponse(['error' => 'Invalid encrypted data format', 'apiVersion' => API_VERSION], 400);
}

function parseEncryptedPayloadInput(array $input): array
{
    if (isset($input['encryptedData']) && is_array($input['encryptedData'])) {
        return normalizeEncryptedData($input['encryptedData']);
    }

    if (isset($input['iv'], $input['data']) && is_array($input['iv']) && is_array($input['data'])) {
        return normalizeEncryptedData([
            'iv' => $input['iv'],
            'data' => $input['data'],
        ]);
    }

    if (isset($input['ivBase64'], $input['dataBase64']) && is_string($input['ivBase64']) && is_string($input['dataBase64'])) {
        return normalizeEncryptedData([
            'ivBase64' => $input['ivBase64'],
            'dataBase64' => $input['dataBase64'],
        ]);
    }

    if (isset($input['ivBase64url'], $input['dataBase64url']) && is_string($input['ivBase64url']) && is_string($input['dataBase64url'])) {
        return normalizeEncryptedData([
            'ivBase64url' => $input['ivBase64url'],
            'dataBase64url' => $input['dataBase64url'],
        ]);
    }

    jsonResponse(['error' => 'Missing encrypted data. Provide encryptedData, iv/data, or ivBase64/dataBase64', 'apiVersion' => API_VERSION], 400);
}

function base64urlDecode(string $value): string
{
    $value = trim($value);
    $value = strtr($value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        jsonResponse(['error' => 'Invalid base64url payload', 'apiVersion' => API_VERSION], 400);
    }

    return $decoded;
}

function base64urlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function buildBaseUrl(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = normalizeScriptDir(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    return $scheme . '://' . $host . ($scriptDir === '/' ? '' : $scriptDir);
}

function buildApiOptions(): array
{
    $baseUrl = buildBaseUrl();

    return [
        'success' => true,
        'service' => 'Secure Pastebin API',
        'apiVersion' => API_VERSION,
        'baseUrl' => $baseUrl,
        'docsUrl' => $baseUrl . '/api/docs',
        'limits' => [
            'minExpirySeconds' => MIN_EXPIRY_SECONDS,
            'maxExpirySeconds' => MAX_EXPIRY_SECONDS,
            'maxEncryptedPayloadBytes' => MAX_DATA_BYTES,
        ],
        'presets' => [
            ['label' => '5 minutes', 'seconds' => 300],
            ['label' => '1 hour', 'seconds' => 3600],
            ['label' => '1 day', 'seconds' => 86400],
            ['label' => '1 week', 'seconds' => 604800],
            ['label' => '30 days', 'seconds' => 2592000],
        ],
        'capabilities' => [
            'create encrypted pastes',
            'fetch encrypted pastes',
            'fetch paste metadata without consuming the payload',
            'clean short share links',
            'custom expiration',
            'burn after read',
            'password-aware flag',
            'custom IDs in the request body or path',
            'byte-array and base64url payload formats',
        ],
        'createRequestFormats' => [
            'body.encryptedData.iv + body.encryptedData.data',
            'body.iv + body.data',
            'body.encryptedData.ivBase64 + body.encryptedData.dataBase64',
            'body.ivBase64 + body.dataBase64',
        ],
        'endpoints' => [
            'docs' => '/api/docs',
            'health' => '/api/health',
            'options' => '/api/options',
            'create' => '/api/pastes',
            'createWithCustomId' => '/api/pastes/{id}',
            'retrieve' => '/api/pastes/{id}',
            'meta' => '/api/pastes/{id}/meta',
            'legacyCreate' => '/api/create',
            'legacyGet' => '/api/get/{id}',
        ],
        'notes' => [
            'Encrypt on the client side. The API stores ciphertext only.',
            'Password material should never be sent to the server.',
            'Markdown and subject live inside the encrypted payload.',
            'GET /api/pastes/{id} deletes a burn-after-read paste immediately after returning it once.',
        ],
    ];
}

function calculateExpiry(array $input): array
{
    $requestedExpiresIn = isset($input['expiresIn']) ? (int) $input['expiresIn'] : 86400;
    $customExpiresAt = isset($input['customExpiresAt']) ? (int) $input['customExpiresAt'] : 0;
    $now = time();

    if ($customExpiresAt > 0) {
        if ($customExpiresAt < ($now + MIN_EXPIRY_SECONDS)) {
            jsonResponse(['error' => 'Custom expiration must be at least 5 minutes from now', 'apiVersion' => API_VERSION], 400);
        }

        if ($customExpiresAt > ($now + MAX_EXPIRY_SECONDS)) {
            jsonResponse(['error' => 'Custom expiration cannot be more than 365 days from now', 'apiVersion' => API_VERSION], 400);
        }

        $expiresAt = $customExpiresAt;
    } else {
        $expiresIn = max(MIN_EXPIRY_SECONDS, min($requestedExpiresIn, MAX_EXPIRY_SECONDS));
        $expiresAt = $now + $expiresIn;
    }

    return ['expiresAt' => $expiresAt, 'expiresIn' => $expiresAt - $now];
}

function handleCreate(PDO $pdo, ?string $routeId = null): void
{
    $input = readJsonInput();

    $bodyId = isset($input['id']) && is_string($input['id']) && $input['id'] !== '' ? $input['id'] : null;
    $id = $routeId ?? $bodyId ?? generateFallbackId();

    if (!isValidPasteId($id)) {
        jsonResponse(['error' => 'Invalid ID format', 'apiVersion' => API_VERSION], 400);
    }

    if ($routeId !== null && $bodyId !== null && $routeId !== $bodyId) {
        jsonResponse(['error' => 'Route ID and body ID do not match', 'apiVersion' => API_VERSION], 400);
    }

    $encryptedData = parseEncryptedPayloadInput($input);
    $payloadBytes = count($encryptedData['iv']) + count($encryptedData['data']);
    if ($payloadBytes > MAX_DATA_BYTES) {
        jsonResponse(['error' => 'Encrypted payload is too large', 'apiVersion' => API_VERSION], 413);
    }

    $expiry = calculateExpiry($input);
    $burnAfterRead = !empty($input['burnAfterRead']);
    $hasPassword = !empty($input['hasPassword']);
    $storedData = json_encode($encryptedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    try {
        $stmt = $pdo->prepare('INSERT INTO pastes (id, data, created_at, expires_at, burn_after_read, has_password, views) VALUES (?, ?, ?, ?, ?, ?, 0)');
        $stmt->execute([$id, $storedData, time() * 1000, $expiry['expiresAt'], $burnAfterRead ? 1 : 0, $hasPassword ? 1 : 0]);
    } catch (PDOException $e) {
        $message = $e->getCode() === '23000' ? 'A paste with this ID already exists' : 'Failed to save paste';
        jsonResponse(['error' => $message, 'apiVersion' => API_VERSION], 500);
    }

    $baseUrl = buildBaseUrl();
    jsonResponse([
        'success' => true,
        'apiVersion' => API_VERSION,
        'id' => $id,
        'expiresAt' => $expiry['expiresAt'],
        'expiresIn' => $expiry['expiresIn'],
        'burnAfterRead' => $burnAfterRead,
        'hasPassword' => $hasPassword,
        'url' => $baseUrl . '/p/' . rawurlencode($id),
        'retrieveUrl' => $baseUrl . '/api/pastes/' . rawurlencode($id),
        'metaUrl' => $baseUrl . '/api/pastes/' . rawurlencode($id) . '/meta',
        'docsUrl' => $baseUrl . '/api/docs',
    ], 201);
}

function buildEncryptedPayloadResponse(array $data): array
{
    $ivBinary = empty($data['iv']) ? '' : pack('C*', ...$data['iv']);
    $cipherBinary = empty($data['data']) ? '' : pack('C*', ...$data['data']);

    return [
        'iv' => array_values($data['iv']),
        'data' => array_values($data['data']),
        'ivBase64' => base64urlEncode($ivBinary),
        'dataBase64' => base64urlEncode($cipherBinary),
    ];
}

function handleGet(PDO $pdo, string $id): void
{
    if (!isValidPasteId($id)) {
        jsonResponse(['error' => 'Invalid ID', 'apiVersion' => API_VERSION], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM pastes WHERE id = ? AND expires_at > ?');
    $stmt->execute([$id, time()]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paste) {
        jsonResponse(['error' => 'Paste not found or expired', 'apiVersion' => API_VERSION], 404);
    }

    $data = json_decode((string) $paste['data'], true);
    if (!is_array($data) || !isset($data['iv'], $data['data'])) {
        jsonResponse(['error' => 'Stored payload is invalid', 'apiVersion' => API_VERSION], 500);
    }

    $responsePayload = buildEncryptedPayloadResponse($data);

    if (!empty($paste['burn_after_read'])) {
        $deleteStmt = $pdo->prepare('DELETE FROM pastes WHERE id = ?');
        $deleteStmt->execute([$id]);
    } else {
        $updateStmt = $pdo->prepare('UPDATE pastes SET views = views + 1 WHERE id = ?');
        $updateStmt->execute([$id]);
        $paste['views'] = ((int) $paste['views']) + 1;
    }

    jsonResponse([
        'success' => true,
        'apiVersion' => API_VERSION,
        'id' => $id,
        'encryptedData' => $responsePayload,
        'data' => [
            'iv' => $responsePayload['iv'],
            'data' => $responsePayload['data'],
        ],
        'burnAfterRead' => (bool) $paste['burn_after_read'],
        'hasPassword' => (bool) $paste['has_password'],
        'created' => (int) $paste['created_at'],
        'expiresAt' => (int) $paste['expires_at'],
        'views' => (int) $paste['views'],
    ]);
}

function handleMeta(PDO $pdo, string $id): void
{
    if (!isValidPasteId($id)) {
        jsonResponse(['error' => 'Invalid ID', 'apiVersion' => API_VERSION], 400);
    }

    $stmt = $pdo->prepare('SELECT id, created_at, expires_at, burn_after_read, has_password, views FROM pastes WHERE id = ? AND expires_at > ?');
    $stmt->execute([$id, time()]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paste) {
        jsonResponse(['error' => 'Paste not found or expired', 'apiVersion' => API_VERSION], 404);
    }

    $baseUrl = buildBaseUrl();
    jsonResponse([
        'success' => true,
        'apiVersion' => API_VERSION,
        'id' => $paste['id'],
        'shareUrl' => $baseUrl . '/p/' . rawurlencode((string) $paste['id']),
        'retrieveUrl' => $baseUrl . '/api/pastes/' . rawurlencode((string) $paste['id']),
        'created' => (int) $paste['created_at'],
        'expiresAt' => (int) $paste['expires_at'],
        'remainingSeconds' => max(0, (int) $paste['expires_at'] - time()),
        'burnAfterRead' => (bool) $paste['burn_after_read'],
        'hasPassword' => (bool) $paste['has_password'],
        'views' => (int) $paste['views'],
    ]);
}

function generateFallbackId(): string
{
    $bytes = random_bytes(12);
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}
