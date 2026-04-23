<p align="center">
  <img src="https://sphost.theazizi.ir/favicon.svg" width="100" height="100" alt="Secure Pastebin Logo">
</p>

# 🔐 Secure Pastebin (Self-Hosted)

> **Self-hosted, zero-knowledge, end-to-end encrypted pastebin built with PHP, MySQL, Web Crypto API, and a responsive single-page UI.**

Share sensitive text securely. Encryption happens in the browser before upload, the server stores ciphertext only, and the decryption key stays in the URL fragment (`#...`) so it is never sent to the server.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![Crypto](https://img.shields.io/badge/Encryption-AES--256--GCM-green.svg)](https://en.wikipedia.org/wiki/Galois/Counter_Mode)
[![Deployment](https://img.shields.io/badge/Deployment-Self--Hosted-orange.svg)](#installation)
[![API](https://img.shields.io/badge/API-Documented-success.svg)](#api)

🌐 **Live Demo:** https://sphost.theazizi.ir  
☁️ **Cloudflare Worker version:** https://github.com/TheGreatAzizi/Secure-Pastebin-Cloudflare-Worker

---

## ✨ Features

- 🔒 **Client-side AES-256-GCM encryption** using the native Web Crypto API
- 🛡️ **Zero-knowledge architecture** — server never receives plaintext, password, or key
- 🧾 **Optional subject field** included inside the encrypted payload
- 🔑 **Optional password protection** with PBKDF2 (100,000 iterations, SHA-256)
- 🔥 **Burn after reading** support
- ⏱️ **Preset expiration** plus **custom expiration date & time**
- 📝 **Markdown authoring + rendering**
  - compact formatting toolbar in the composer
  - Markdown is rendered **after decryption only**
- 🔗 **Two share-link formats**
  - full link: `/p/{id}#key`
  - short link: `/#id:key`
- 📋 **Copy actions** for full link, short link, and decrypted text
- 📱 **Responsive UI** with improved mobile layout
- 🌍 **RTL-aware text handling** for Persian / Arabic / Hebrew content
- 🔤 **No external font CDN** — uses a local Vazirmatn-based font stack
- ⚙️ **Documented HTTP API** with `/api/docs`

---

## 🔐 How the security model works

1. The browser generates or derives the encryption key locally.
2. The browser encrypts the payload locally using AES-256-GCM.
3. The server receives only:
   - paste ID
   - IV
   - ciphertext
   - metadata such as expiration / burn-after-read / password flag
4. The decryption key is kept in the URL fragment (`#...`). URL fragments are not sent in normal HTTP requests.
5. The recipient opens the link, downloads the encrypted payload, and decrypts it locally.

### Important note

If the full share URL is lost, the message is **not recoverable**. The server cannot reconstruct the decryption key.

---

## 🧱 Current stack

- **Frontend:** HTML, CSS, vanilla JavaScript
- **Crypto:** Web Crypto API
- **Backend/router:** PHP
- **Database:** MySQL / MariaDB
- **Storage model:** encrypted payload + metadata only

---

## 🆕 What is included in this version

Compared with the older README, the current app now includes compact share links, custom expiration timestamps, a Markdown toolbar and renderer, password strength feedback, an API docs page, updated responsive layout, and a local-font setup with no external font dependency. The older README you uploaded still documents the earlier API shape and older sharing format. fileciteturn0file0

---

## 🧩 Share link formats

### Full share link

```text
https://your-domain.com/p/AbCdEf1234567890#BASE64URL_KEY
```

### Full share link with password flag

```text
https://your-domain.com/p/AbCdEf1234567890#BASE64URL_SALT:pwd
```

### Short share link

```text
https://your-domain.com/#AbCdEf1234567890:BASE64URL_KEY
```

### Short share link with password flag

```text
https://your-domain.com/#AbCdEf1234567890:BASE64URL_SALT:pwd
```

### ID compatibility

The backend currently accepts these ID formats:

- legacy **32-character lowercase hex** IDs
- compact **16-character URL-safe IDs** (`[A-Za-z0-9_-]{16}`)

The UI currently generates the compact 16-character format by default.

---

## 📝 Encrypted payload format

The browser encrypts a JSON payload. Subject and content are both inside the encrypted blob.

Example logical structure before encryption:

```json
{
  "subject": "Optional subject",
  "content": "Secret message with **Markdown** support"
}
```

The server never sees this plaintext object.

---

## 🚀 Installation

## 1) Database

Create a database, then import `database.sql`.

```sql
CREATE TABLE IF NOT EXISTS pastes (
    id VARCHAR(32) PRIMARY KEY,
    data TEXT NOT NULL,
    created_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    burn_after_read TINYINT(1) DEFAULT 0,
    has_password TINYINT(1) DEFAULT 0,
    views INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_expires ON pastes(expires_at);
```

## 2) Configure PHP

Edit the database constants in `index.php`:

```php
const DB_HOST = 'localhost';
const DB_USER = 'your_db_user';
const DB_PASS = 'your_db_password';
const DB_NAME = 'your_db_name';
```

## 3) Upload files

Upload the project files to your web root.

```text
/public_html/
├── .htaccess
├── api-docs.php
├── database.sql
├── index.html
├── index.php
├── LICENSE
├── README.md
├── script.js
└── style.css
```

## 4) Enable URL rewriting

Apache `.htaccess` used by the project:

```apache
RewriteEngine On
RewriteBase /

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

RewriteRule ^ index.php [L]
```

## 5) Use HTTPS

`crypto.subtle` requires a secure context in production. Use:

- HTTPS on your domain, or
- `localhost` during local development

---

## 📁 Project structure

| File | Purpose |
|------|---------|
| `index.php` | router + API backend |
| `index.html` | single-page app UI |
| `script.js` | encryption, decryption, UI behavior, Markdown tools |
| `style.css` | responsive styling |
| `api-docs.php` | human-friendly API documentation page |
| `database.sql` | MySQL schema |
| `.htaccess` | Apache rewrite rules |

---

## 🧠 Composer UI highlights

### Secure message composer

- optional subject
- Markdown toolbar
- multiline textarea
- preset expiration dropdown
- custom expiration datetime picker
- password-protection toggle
- burn-after-reading toggle
- password strength meter

### Decrypted result view

- subject display
- rendered Markdown output
- copy decrypted text button
- burn-after-read warning when applicable

---

## ✍️ Markdown support

Markdown is stored as plain text inside the encrypted payload and is rendered only after decryption.

Supported authoring helpers include:

- **Bold**
- *Italic*
- ~~Strikethrough~~
- headings
- quotes
- bullet lists
- numbered lists
- links
- inline code
- fenced code blocks

This keeps the stored payload simple while still giving the recipient a readable result view.

---

## 🔌 API

### Docs page

Once deployed, the built-in docs page is available at:

```text
https://your-domain.com/api/docs
```

### Base notes

- API stores **ciphertext only**
- API does **not** perform encryption for you
- client must encrypt before sending data
- password or raw key must **never** be sent to the server

### Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/health` | service health check |
| `GET` | `/api/options` | API capabilities, limits, routes |
| `GET` | `/api/docs` | HTML API documentation |
| `POST` | `/api/pastes` | create a paste |
| `POST` | `/api/pastes/{id}` | create a paste with a specific ID |
| `GET` | `/api/pastes/{id}` | fetch encrypted payload |
| `GET` | `/api/pastes/{id}/meta` | fetch metadata only |
| `POST` | `/api/create` | legacy create route |
| `GET` | `/api/get/{id}` | legacy read route |

### Limits

From the current backend:

- minimum expiration: **300 seconds**
- maximum expiration: **31536000 seconds** (365 days)
- max encrypted payload size: **4 MiB**

### Supported create payload formats

You can create a paste using any of these shapes:

1. nested byte arrays

```json
{
  "encryptedData": {
    "iv": [12, 34, 56],
    "data": [99, 88, 77]
  }
}
```

2. top-level byte arrays

```json
{
  "iv": [12, 34, 56],
  "data": [99, 88, 77]
}
```

3. nested base64url strings

```json
{
  "encryptedData": {
    "ivBase64": "AAECAwQFBgcICQoL",
    "dataBase64": "mYh3"
  }
}
```

4. top-level base64url strings

```json
{
  "ivBase64": "AAECAwQFBgcICQoL",
  "dataBase64": "mYh3"
}
```

`ivBase64url` / `dataBase64url` are also accepted.

### Create request fields

| Field | Required | Description |
|------|----------|-------------|
| `id` | optional | custom paste ID |
| `encryptedData` or equivalent | yes | ciphertext payload |
| `expiresIn` | optional | expiry in seconds |
| `customExpiresAt` | optional | exact Unix timestamp in seconds |
| `burnAfterRead` | optional | delete after first successful read |
| `hasPassword` | optional | indicates password is required on the client |

If `customExpiresAt` is present, it overrides `expiresIn`.

### Example: create a paste

```bash
curl -X POST https://your-domain.com/api/pastes \
  -H "Content-Type: application/json" \
  -d '{
    "id": "AbCdEf1234567890",
    "encryptedData": {
      "iv": [12, 34, 56, 78, 90, 12, 34, 56, 78, 90, 12, 34],
      "data": [189, 45, 78, 201, 156, 78, 33, 45]
    },
    "expiresIn": 86400,
    "burnAfterRead": false,
    "hasPassword": false
  }'
```

### Example success response

```json
{
  "success": true,
  "apiVersion": "1.2",
  "id": "AbCdEf1234567890",
  "expiresAt": 1735689600,
  "expiresIn": 86400,
  "burnAfterRead": false,
  "hasPassword": false,
  "url": "https://your-domain.com/p/AbCdEf1234567890",
  "retrieveUrl": "https://your-domain.com/api/pastes/AbCdEf1234567890",
  "metaUrl": "https://your-domain.com/api/pastes/AbCdEf1234567890/meta",
  "docsUrl": "https://your-domain.com/api/docs"
}
```

### Example: fetch encrypted payload

```bash
curl https://your-domain.com/api/pastes/AbCdEf1234567890
```

Example response:

```json
{
  "success": true,
  "apiVersion": "1.2",
  "id": "AbCdEf1234567890",
  "encryptedData": {
    "iv": [12, 34, 56, 78, 90, 12, 34, 56, 78, 90, 12, 34],
    "data": [189, 45, 78, 201, 156, 78, 33, 45],
    "ivBase64": "AAECAwQFBgcICQoL",
    "dataBase64": "mYh3"
  },
  "data": {
    "iv": [12, 34, 56, 78, 90, 12, 34, 56, 78, 90, 12, 34],
    "data": [189, 45, 78, 201, 156, 78, 33, 45]
  },
  "burnAfterRead": false,
  "hasPassword": false,
  "created": 1735603200000,
  "expiresAt": 1735689600,
  "views": 1
}
```

### Burn-after-read behavior

When `burnAfterRead` is enabled, the first successful fetch from `GET /api/pastes/{id}` returns the ciphertext and then removes that paste from storage.

### Metadata endpoint

```bash
curl https://your-domain.com/api/pastes/AbCdEf1234567890/meta
```

Example response:

```json
{
  "success": true,
  "apiVersion": "1.2",
  "id": "AbCdEf1234567890",
  "shareUrl": "https://your-domain.com/p/AbCdEf1234567890",
  "retrieveUrl": "https://your-domain.com/api/pastes/AbCdEf1234567890",
  "created": 1735603200000,
  "expiresAt": 1735689600,
  "remainingSeconds": 86400,
  "burnAfterRead": false,
  "hasPassword": false,
  "views": 0
}
```

---

## 🧪 Health and options

### Health

```bash
curl https://your-domain.com/api/health
```

### Options

```bash
curl https://your-domain.com/api/options
```

`/api/options` returns API version, limits, presets, capabilities, endpoints, and notes.

---

## 🌐 Browser compatibility

| Browser | Status |
|---------|--------|
| Chrome / Edge | ✅ |
| Firefox | ✅ |
| Safari | ✅ |
| Internet Explorer | ❌ |

A secure context is required for the Web Crypto API.

---

## 🛡️ Security notes

### Protected well

- database leaks still expose ciphertext only
- server admins do not have plaintext or keys
- password material stays client-side
- URL fragment is not sent to the backend

### Not protected well

- malware on sender or recipient device
- leaked full share URLs
- weak passwords chosen by users
- copied plaintext after decryption
- screenshots or shoulder surfing

### Recommended operational practices

- send password separately from the URL
- use burn-after-read for highly sensitive messages
- prefer long random passwords
- use private browsing on shared devices
- do not paste highly sensitive links into third-party chatbots or analytics tools

---

## 🎨 UX and design notes

- improved composer layout for desktop and mobile
- cleaner stacked settings cards for expiration and security options
- compact Markdown toolbar
- icon-only social links in the footer
- consistent visual style shared by the app and API docs page

---

## 📌 Roadmap ideas

- encrypted file attachments
- QR code for secure links
- separate key-sharing mode
- OpenAPI / Swagger export
- admin-only cleanup / moderation tools
- theme switcher

---

## 🤝 Contributing

Issues and pull requests are welcome.

If you open a PR, try to keep these guarantees intact:

- client-side encryption only
- zero-knowledge storage model
- no accidental leakage of key material to the server
- backward compatibility for existing shared links where possible

---

## 📜 License

MIT — see [`LICENSE`](LICENSE).

---

## 👤 Author

**TheGreatAzizi**

- GitHub: [@TheGreatAzizi](https://github.com/TheGreatAzizi)
- X: [@the_azzi](https://x.com/the_azzi)
- Telegram: [@luluch_code](https://t.me/luluch_code)

---

## ⚠️ Disclaimer

This project is provided **as is** without warranty of any kind. You are responsible for your own deployment security, backups, SSL/TLS configuration, database hardening, and safe sharing of URLs and passwords.
