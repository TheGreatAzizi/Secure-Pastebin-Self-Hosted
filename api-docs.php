<?php

declare(strict_types=1);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDir = ($scriptDir === '.' || $scriptDir === '/') ? '' : rtrim($scriptDir, '/');
$baseUrl = $scheme . '://' . $host . $scriptDir;
$assetBase = $scriptDir;
$homeUrl = ($assetBase === '' ? '/' : $assetBase . '/');
$docsPath = ($assetBase === '' ? '' : $assetBase) . '/api/docs';
$styleHref = ($assetBase === '' ? '/style.css' : $assetBase . '/style.css');

$createExample = <<<'JSON'
{
  "encryptedData": {
    "iv": [12, 83, 144, 221],
    "data": [167, 44, 222, 1]
  },
  "expiresIn": 86400,
  "customExpiresAt": 0,
  "burnAfterRead": true,
  "hasPassword": true
}
JSON;

$createCurlTemplate = <<<'CURL'
curl -X POST {{BASE_URL}}/api/pastes \
  -H "Content-Type: application/json" \
  -d '{
    "encryptedData": {
      "iv": [12, 83, 144, 221],
      "data": [167, 44, 222, 1]
    },
    "expiresIn": 86400,
    "customExpiresAt": 0,
    "burnAfterRead": true,
    "hasPassword": true
  }'
CURL;
$createCurlExample = str_replace('{{BASE_URL}}', $baseUrl, $createCurlTemplate);

$createWithPathTemplate = <<<'CURL'
curl -X POST {{BASE_URL}}/api/pastes/customPasteId123 \
  -H "Content-Type: application/json" \
  -d '{
    "ivBase64": "A1Jz4Q",
    "dataBase64": "m6xK4w8t...",
    "expiresIn": 3600,
    "burnAfterRead": false,
    "hasPassword": false
  }'
CURL;
$createWithPathExample = str_replace('{{BASE_URL}}', $baseUrl, $createWithPathTemplate);

$createResponseTemplate = <<<'JSON'
{
  "success": true,
  "apiVersion": "1.2",
  "id": "customPasteId123",
  "expiresAt": 1770000000,
  "expiresIn": 3600,
  "burnAfterRead": false,
  "hasPassword": false,
  "url": "{{BASE_URL}}/p/customPasteId123",
  "retrieveUrl": "{{BASE_URL}}/api/pastes/customPasteId123",
  "metaUrl": "{{BASE_URL}}/api/pastes/customPasteId123/meta",
  "docsUrl": "{{BASE_URL}}/api/docs"
}
JSON;
$createResponseExample = str_replace('{{BASE_URL}}', $baseUrl, $createResponseTemplate);

$getResponseExample = <<<'JSON'
{
  "success": true,
  "apiVersion": "1.2",
  "id": "customPasteId123",
  "encryptedData": {
    "iv": [12, 83, 144, 221],
    "data": [167, 44, 222, 1],
    "ivBase64": "A1Jz4Q",
    "dataBase64": "m6xK4w8t..."
  },
  "data": {
    "iv": [12, 83, 144, 221],
    "data": [167, 44, 222, 1]
  },
  "burnAfterRead": false,
  "hasPassword": false,
  "created": 1769990000000,
  "expiresAt": 1770000000,
  "views": 1
}
JSON;

$optionsTemplate = <<<'JSON'
{
  "success": true,
  "service": "Secure Pastebin API",
  "apiVersion": "1.2",
  "baseUrl": "{{BASE_URL}}",
  "docsUrl": "{{BASE_URL}}/api/docs",
  "limits": {
    "minExpirySeconds": 300,
    "maxExpirySeconds": 31536000,
    "maxEncryptedPayloadBytes": 4194304
  },
  "capabilities": [
    "create encrypted pastes",
    "create with custom id",
    "fetch encrypted pastes",
    "fetch paste metadata without consuming the payload",
    "custom expiration",
    "burn after read",
    "password-aware flag"
  ]
}
JSON;
$optionsExample = str_replace('{{BASE_URL}}', $baseUrl, $optionsTemplate);

$jsTemplate = <<<'JS'
const payload = {
  encryptedData: {
    iv: Array.from(ivBytes),
    data: Array.from(cipherBytes)
  },
  expiresIn: 86400,
  customExpiresAt: 0,
  burnAfterRead: false,
  hasPassword: false
};

const createRes = await fetch('{{BASE_URL}}/api/pastes', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(payload)
});

const created = await createRes.json();
const readRes = await fetch(created.retrieveUrl);
const encryptedPaste = await readRes.json();
JS;
$jsExample = str_replace('{{BASE_URL}}', $baseUrl, $jsTemplate);

$phpTemplate = <<<'PHP2'
<?php

$payload = [
    'encryptedData' => [
        'iv' => [12, 83, 144, 221],
        'data' => [167, 44, 222, 1],
    ],
    'expiresIn' => 86400,
    'burnAfterRead' => false,
    'hasPassword' => false,
];

$ch = curl_init('{{BASE_URL}}/api/pastes');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
PHP2;
$phpExample = str_replace('{{BASE_URL}}', $baseUrl, $phpTemplate);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Pastebin API Docs</title>
    <meta name="description" content="Secure Pastebin API documentation and usage guide.">
    <link rel="stylesheet" href="<?= h($styleHref) ?>">
</head>
<body class="api-page">
    <div class="container">
        <header>
            <div class="logo">🔐</div>
            <h1>Secure Pastebin API</h1>
            <p class="subtitle">Zero-Knowledge API for encrypted notes, subjects, and Markdown payloads</p>
            <div class="security-badges">
                <span class="badge">POST /api/pastes</span>
                <span class="badge">POST /api/pastes/{id}</span>
                <span class="badge">GET /api/pastes/{id}</span>
                <span class="badge">GET /api/pastes/{id}/meta</span>
                <span class="badge">GET /api/options</span>
                <span class="badge">GET /api/health</span>
            </div>
        </header>

        <section class="card docs-intro">
            <div class="alert alert-info">
                <span>ℹ️</span>
                <div>
                    <strong>Important</strong><br>
                    Subject, Markdown text, and any password-derived key handling must stay on the client side. The API should receive only encrypted bytes and metadata like expiration and burn-after-read flags.
                </div>
            </div>

            <div class="docs-grid docs-grid-2">
                <div class="docs-panel">
                    <h2>Base URL</h2>
                    <div class="inline-code-block"><?= h($baseUrl) ?></div>
                </div>
                <div class="docs-panel">
                    <h2>Supported capabilities</h2>
                    <div class="chip-list">
                        <span class="chip">create</span>
                        <span class="chip">custom id</span>
                        <span class="chip">retrieve</span>
                        <span class="chip">metadata</span>
                        <span class="chip">custom expiration</span>
                        <span class="chip">burn after read</span>
                        <span class="chip">password flag</span>
                        <span class="chip">byte arrays</span>
                        <span class="chip">base64url payloads</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Endpoint overview</h2>
            <div class="table-wrap">
                <table class="docs-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Endpoint</th>
                            <th>What it does</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="endpoint endpoint-post">POST</span></td>
                            <td><code>/api/pastes</code></td>
                            <td>Create a new encrypted paste. The server generates a short id unless you pass one in the body.</td>
                        </tr>
                        <tr>
                            <td><span class="endpoint endpoint-post">POST</span></td>
                            <td><code>/api/pastes/{id}</code></td>
                            <td>Create a new encrypted paste with a custom short id in the URL path.</td>
                        </tr>
                        <tr>
                            <td><span class="endpoint endpoint-get">GET</span></td>
                            <td><code>/api/pastes/{id}</code></td>
                            <td>Fetch the encrypted payload plus metadata. Burn-after-read pastes are deleted after the first successful read.</td>
                        </tr>
                        <tr>
                            <td><span class="endpoint endpoint-get">GET</span></td>
                            <td><code>/api/pastes/{id}/meta</code></td>
                            <td>Read metadata only without consuming the encrypted payload.</td>
                        </tr>
                        <tr>
                            <td><span class="endpoint endpoint-get">GET</span></td>
                            <td><code>/api/options</code></td>
                            <td>Return limits, presets, supported formats, and the endpoint map.</td>
                        </tr>
                        <tr>
                            <td><span class="endpoint endpoint-get">GET</span></td>
                            <td><code>/api/health</code></td>
                            <td>Simple health check for uptime monitoring.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="docs-note">Legacy routes <code>/api/create</code> and <code>/api/get/{id}</code> still work for backward compatibility.</p>
        </section>

        <section class="card docs-grid docs-grid-2">
            <div class="docs-panel">
                <h2>Create request body</h2>
                <pre class="code-block"><code><?= h($createExample) ?></code></pre>
            </div>
            <div class="docs-panel">
                <h2>Create field notes</h2>
                <ul class="docs-list">
                    <li><strong>encryptedData.iv</strong> and <strong>encryptedData.data</strong> can be sent as byte arrays.</li>
                    <li>You can also send <strong>iv/data</strong> at the top level instead of nesting under <code>encryptedData</code>.</li>
                    <li>You can send <strong>ivBase64/dataBase64</strong> or <strong>encryptedData.ivBase64/encryptedData.dataBase64</strong> instead of byte arrays.</li>
                    <li><strong>customExpiresAt</strong> is a Unix timestamp in seconds and overrides <strong>expiresIn</strong>.</li>
                    <li><strong>hasPassword</strong> is a client hint only. Never send the password itself to the API.</li>
                    <li>Subject and Markdown stay inside the encrypted payload so the API remains zero-knowledge.</li>
                </ul>
            </div>
        </section>

        <section class="card docs-grid docs-grid-2">
            <div class="docs-panel">
                <h2>Create example</h2>
                <pre class="code-block"><code><?= h($createCurlExample) ?></code></pre>
            </div>
            <div class="docs-panel">
                <h2>Create with custom path ID</h2>
                <pre class="code-block"><code><?= h($createWithPathExample) ?></code></pre>
            </div>
        </section>

        <section class="card docs-grid docs-grid-2">
            <div class="docs-panel">
                <h2>Create response</h2>
                <pre class="code-block"><code><?= h($createResponseExample) ?></code></pre>
            </div>
            <div class="docs-panel">
                <h2>Read response</h2>
                <pre class="code-block"><code><?= h($getResponseExample) ?></code></pre>
            </div>
        </section>

        <section class="card docs-grid docs-grid-2">
            <div class="docs-panel">
                <h2>Options response</h2>
                <pre class="code-block"><code><?= h($optionsExample) ?></code></pre>
            </div>
            <div class="docs-panel">
                <h2>Behavior notes</h2>
                <ul class="docs-list">
                    <li><strong>url</strong> is the clean short-link base without the key fragment.</li>
                    <li><strong>retrieveUrl</strong> is the API endpoint for programmatic reads.</li>
                    <li><strong>metaUrl</strong> gives metadata without consuming the ciphertext.</li>
                    <li>Reading a burn-after-read paste from <code>/api/pastes/{id}</code> removes it after the first successful response.</li>
                    <li>Byte arrays and base64url are both returned on reads for easier client integration.</li>
                </ul>
            </div>
        </section>

        <section class="card docs-grid docs-grid-2">
            <div class="docs-panel">
                <h2>JavaScript example</h2>
                <pre class="code-block"><code><?= h($jsExample) ?></code></pre>
            </div>
            <div class="docs-panel">
                <h2>PHP example</h2>
                <pre class="code-block"><code><?= h($phpExample) ?></code></pre>
            </div>
        </section>

        <section class="card docs-grid docs-grid-2">
            <div class="docs-panel">
                <h2>Typical integration flow</h2>
                <ol class="docs-list ordered">
                    <li>Generate the AES key in the browser or in your app.</li>
                    <li>Optionally derive the key from a password locally.</li>
                    <li>Encrypt a JSON payload that contains subject and Markdown content.</li>
                    <li>Send the encrypted bytes plus expiration settings to <code>/api/pastes</code> or <code>/api/pastes/{id}</code>.</li>
                    <li>Share <code>result.url#keyFragment</code> with the recipient.</li>
                    <li>The recipient calls <code>/api/pastes/{id}</code>, then decrypts locally.</li>
                </ol>
            </div>
            <div class="docs-panel">
                <h2>Quick links</h2>
                <div class="chip-list">
                    <a class="chip" href="<?= h($homeUrl) ?>">Main app</a>
                    <a class="chip" href="<?= h($docsPath) ?>">This docs page</a>
                    <a class="chip" href="<?= h(($assetBase === '' ? '' : $assetBase) . '/api/options') ?>">/api/options</a>
                    <a class="chip" href="<?= h(($assetBase === '' ? '' : $assetBase) . '/api/health') ?>">/api/health</a>
                </div>
            </div>
        </section>

        <footer>
            <p>🛡️ Zero-Knowledge Architecture • Server cannot read your data</p>
            <p class="footer-subtitle"></p>
            <div class="footer-links">
                <a href="<?= h($homeUrl) ?>" title="Main app" aria-label="Main app">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 9v12h18V9l-9-6Zm0 2.2 7 4.67V19h-4v-5H9v5H5V9.87l7-4.67Z"/></svg>
                </a>
                <a href="https://github.com/TheGreatAzizi/Secure-Pastebin-Self-Hosted/" target="_blank" rel="noopener" title="GitHub" aria-label="GitHub">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.6.111.793-.261.793-.577v-2.173c-3.338.724-4.034-1.416-4.034-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.088-.744.084-.729.084-.729 1.205.083 1.838 1.236 1.838 1.236 1.07 1.834 2.808 1.304 3.493.997.108-.775.418-1.304.762-1.604-2.665-.304-5.467-1.333-5.467-5.93 0-1.31.469-2.381 1.236-3.221-.124-.302-.536-1.523.117-3.176 0 0 1.008-.322 3.302 1.23A11.49 11.49 0 0 1 12 5.798c1.02.005 2.047.139 3.006.404 2.292-1.552 3.3-1.23 3.3-1.23.654 1.653.242 2.874.118 3.176.768.84 1.235 1.911 1.235 3.221 0 4.609-2.806 5.624-5.479 5.92.43.372.823 1.103.823 2.223v3.293c0 .319.192.69.802.576C20.565 21.796 24 17.3 24 12 24 5.373 18.627 0 12 0Z"/></svg>
                </a>
                <a href="https://x.com/the_azzi" target="_blank" rel="noopener" title="X" aria-label="X">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.901 1.153h3.68l-8.04 9.19 9.458 12.504h-7.406l-5.8-7.584-6.637 7.584H.476l8.601-9.83L0 1.154h7.594l5.243 6.932 6.064-6.932Zm-1.293 19.487h2.039L6.486 3.24H4.298L17.608 20.64Z"/></svg>
                </a>
                <a href="https://t.me/luluch_code" target="_blank" rel="noopener" title="Telegram" aria-label="Telegram">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.993 15.674 9.62 20.92c.534 0 .765-.229 1.042-.504l2.502-2.394 5.185 3.796c.951.524 1.624.248 1.881-.875l3.408-15.97.001-.001c.302-1.41-.51-1.962-1.437-1.617L2.155 11.11c-1.367.534-1.346 1.296-.233 1.64l5.115 1.595L18.86 6.88c.556-.368 1.062-.164.646.204"/></svg>
                </a>
            </div>
            <a class="footer-doc-link" href="<?= h($docsPath) ?>">API Docs</a>
        </footer>
    </div>
</body>
</html>
