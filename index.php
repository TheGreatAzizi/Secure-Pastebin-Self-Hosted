<?php
// Secure Pastebin API - cPanel/PHP Version
header('Content-Type: application/json');

// Database Config
define('DB_HOST', 'localhost');
define('DB_USER', ' --------- ');
define('DB_PASS', ' --------- ');
define('DB_NAME', ' --------- ');


// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// DB Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode(array('error' => 'Database connection failed')));
}

// Clean expired entries
$pdo->exec("DELETE FROM pastes WHERE expires_at < " . time());

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove subdirectory from path if exists
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
}
if (empty($path)) $path = '/';

// API Routes
if ($path === '/api/create' && $method === 'POST') {
    handleCreate($pdo);
    exit;
}

if (preg_match('/^\/api\/get\/(.+)$/', $path, $matches) && $method === 'GET') {
    handleGet($pdo, $matches[1]);
    exit;
}

// Serve static files
if ($path === '/' || $path === '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

if ($path === '/style.css') {
    header('Content-Type: text/css');
    readfile(__DIR__ . '/style.css');
    exit;
}

if ($path === '/script.js') {
    header('Content-Type: application/javascript');
    readfile(__DIR__ . '/script.js');
    exit;
}

if ($path === '/favicon.svg') {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#6366f1"/><stop offset="100%" style="stop-color:#ec4899"/></linearGradient></defs><rect width="100" height="100" rx="20" fill="url(#g)"/><path d="M50 25c-8 0-14 6-14 14v6h-4c-3 0-6 3-6 6v24c0 3 3 6 6 6h36c3 0 6-3 6-6v-24c0-3-3-6-6-6h-4v-6c0-8-6-14-14-14zm0 6c4 0 8 4 8 8v6H42v-6c0-4 4-8 8-8z" fill="white"/></svg>';
    exit;
}

http_response_code(404);
echo json_encode(array('error' => 'Not found'));

function handleCreate($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid JSON'));
        return;
    }
    
    $id = isset($input['id']) ? $input['id'] : '';
    $encryptedData = isset($input['encryptedData']) ? $input['encryptedData'] : null;
    $expiresIn = min(intval(isset($input['expiresIn']) ? $input['expiresIn'] : 3600), 2592000);
    $burnAfterRead = !empty($input['burnAfterRead']);
    $hasPassword = !empty($input['hasPassword']);
    
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid ID format'));
        return;
    }
    
    if (!$encryptedData || !isset($encryptedData['iv']) || !isset($encryptedData['data'])) {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid encrypted data format'));
        return;
    }
    
    $data = json_encode(array('iv' => $encryptedData['iv'], 'data' => $encryptedData['data']));
    $expiresAt = time() + $expiresIn;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO pastes (id, data, created_at, expires_at, burn_after_read, has_password, views) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute(array($id, $data, time() * 1000, $expiresAt, $burnAfterRead ? 1 : 0, $hasPassword ? 1 : 0));
        
        echo json_encode(array(
            'success' => true,
            'id' => $id,
            'expiresIn' => $expiresIn,
            'hasPassword' => $hasPassword,
            'url' => 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '/../#' . $id
        ));
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(array('error' => 'Failed to save paste: ' . $e->getMessage()));
    }
}

function handleGet($pdo, $id) {
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid ID'));
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pastes WHERE id = ? AND expires_at > ?");
        $stmt->execute(array($id, time()));
        $paste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paste) {
            http_response_code(404);
            echo json_encode(array('error' => 'Paste not found or expired'));
            return;
        }
        
        $data = json_decode($paste['data'], true);
        
        if ($paste['burn_after_read']) {
            $delStmt = $pdo->prepare("DELETE FROM pastes WHERE id = ?");
            $delStmt->execute(array($id));
        } else {
            $updStmt = $pdo->prepare("UPDATE pastes SET views = views + 1 WHERE id = ?");
            $updStmt->execute(array($id));
        }
        
        echo json_encode(array(
            'data' => $data,
            'burnAfterRead' => (bool)$paste['burn_after_read'],
            'hasPassword' => (bool)$paste['has_password'],
            'created' => intval($paste['created_at'])
        ));
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(array('error' => 'Server error'));
    }
}
