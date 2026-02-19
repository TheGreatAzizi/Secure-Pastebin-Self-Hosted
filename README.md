<p align="center">
  <img src="https://sphost.theazizi.ir/favicon.svg" width="100" height="100" alt="Secure Pastebin Logo">
</p>

# 🔐 Secure Pastebin (Self-Hosted Ver)

> **Self-Hosted, Zero-Knowledge, End-to-End Encrypted Pastebin**
> 
> Share sensitive messages securely. Server cannot read your data. Ever.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![Crypto](https://img.shields.io/badge/Encryption-AES--256--GCM-green.svg)](https://en.wikipedia.org/wiki/Galois/Counter_Mode)
[![Deployment](https://img.shields.io/badge/Deployment-Self--Hosted-orange.svg)](#)
[![Security](https://img.shields.io/badge/Security-Zero--Knowledge-success.svg)](#)

🌐 **Live Demo:** https://sphost.theazizi.ir  
☁️ [**Cloudflare Worker Version**](https://github.com/TheGreatAzizi/Secure-Pastebin-Cloudflare-Worker)

---

## ✨ Features

| Feature | Description | Security Impact |
|---------|-------------|---------------|
| 🔒 **E2E Encryption** | AES-256-GCM in browser before transmission | Server sees only ciphertext |
| 🛡️ **Zero-Knowledge** | Server has zero access to keys or plaintext | Mathematically provable |
| 🔑 **Password Protection** | PBKDF2 with 100,000 iterations | Brute-force resistant |
| 🔥 **Burn After Read** | Auto-delete after first access | Forward secrecy |
| ⏱️ **Auto-Expiration** | 1 hour to 30 days configurable | Limits exposure window |
| 🌐 **RTL Support** | Persian, Arabic, Hebrew typography | Accessibility |
| 📱 **Responsive** | Mobile-first design | Usability |

---

## 🔐 Cryptography Architecture

### Zero-Knowledge Proof

```
┌────────────────────────────────────────────────────────────────┐
│                    ZERO-KNOWLEDGE GUARANTEE                    │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│   USER BROWSER                      SERVER / DATABASE          │
│   ─────────────                     ─────────────────          │
│                                                                │
│   ┌─────────────┐                   ┌─────────────────┐        │
│   │ Generate    │ ──NOT SENT──────► │                 │        │
│   │ AES-256 Key │                   │  NO KEYS STORED │        │
│   └─────────────┘                   │                 │        │
│                                     └─────────────────┘        │
│   ┌─────────────┐                                              │
│   │ Encrypt     │ ──NOT SENT──────────────────────────────────►│
│   │ Plaintext   │                                              │
│   │ with Key    │                                              │
│   └─────────────┘                                              │
│                                                                │
│   ┌─────────────┐                   ┌─────────────────┐        │
│   │ Send:       │ ──HTTPS─────────► │ Store:          │        │
│   │ • ID        │                   │ • ID            │        │
│   │ • IV        │                   │ • IV            │        │
│   │ • Ciphertext│                   │ • Ciphertext    │        │
│   │ • Metadata  │                   │ • Metadata      │        │
│   └─────────────┘                   │                 │        │
│                                     │  NO PLAINTEXT   │        │
│   ┌─────────────┐                   │  NO PASSWORD    │        │
│   │ Key Stored  │                   │  NO KEY         │        │
│   │ in URL:     │                   │                 │        │
│   │             │                   └─────────────────┘        │
│   │ #id:key   ◄─┘  NEVER in HTTP headers                       │
│   │             │                                              │
│   └─────────────┘  Fragment not sent to server                 │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

### Technical Specifications

| Component | Standard | Parameters |
|-----------|----------|------------|
| Symmetric Encryption | AES-256-GCM | 256-bit key, 96-bit nonce |
| Key Derivation | PBKDF2 | 100,000 iterations, SHA-256 |
| Random Generation | CSPRNG | Crypto.getRandomValues() |
| Key Encoding | Base64URL | URL-safe, no padding |
| Transport Security | TLS 1.3 | Certificate pinning recommended |

### Encryption Flow

```javascript
// 1. Key Generation (Browser)
const key = await crypto.subtle.generateKey(
  { name: 'AES-GCM', length: 256 },
  true,
  ['encrypt', 'decrypt']
);

// 2. Encryption (Browser - before network)
const iv = crypto.getRandomValues(new Uint8Array(12));
const ciphertext = await crypto.subtle.encrypt(
  { name: 'AES-GCM', iv },
  key,
  new TextEncoder().encode(plaintext)
);

// 3. Transmission (HTTPS only)
fetch('/api/create', {
  body: JSON.stringify({
    id: randomId,
    encryptedData: { iv: [...iv], data: [...ciphertext] },
    // NO KEY HERE - key stays in browser/URL
  })
});
```

---

## 🚀 Installation

### System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| Web Server | Apache 2.4 | Nginx + Apache |
| SSL | Required | Let's Encrypt |

### Step 1: Database Setup

```sql
CREATE DATABASE IF NOT EXISTS secure_pastebin
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE secure_pastebin;

CREATE TABLE pastes (
    id VARCHAR(32) PRIMARY KEY,
    data TEXT NOT NULL,
    created_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    burn_after_read TINYINT(1) DEFAULT 0,
    has_password TINYINT(1) DEFAULT 0,
    views INT DEFAULT 0,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Step 2: Configuration

Edit `index.php` (lines 5-8):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_cpanel_username_dbuser');
define('DB_PASS', 'your_secure_random_password');
define('DB_NAME', 'your_cpanel_username_pastebin');
```

### Step 3: File Upload

```
/public_html/pastebin/
├── index.php          # API backend + router
├── index.html         # Single-page application
├── style.css          # Complete styling (RTL included)
├── script.js          # Web Crypto implementation
├── .htaccess          # URL rewriting rules
└── database.sql       # Schema (already imported)
```

### Step 4: SSL Enforcement

**cPanel Method:**
1. SSL/TLS Status → Run AutoSSL
2. Force HTTPS Redirect: ON

**Cloudflare Method:**
1. DNS proxied through Cloudflare
2. SSL/TLS mode: Full (strict)
3. Always Use HTTPS: ON

### Step 5: Verification Checklist

- [ ] Database connection successful (check error logs)
- [ ] `POST /api/create` returns 201 with valid JSON
- [ ] `GET /api/get/{id}` returns encrypted data
- [ ] Auto-cleanup: Expired rows delete automatically

---

## 🗄️ Database Structure

### Schema Overview

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | VARCHAR(32) | NO | Cryptographically random hex |
| `data` | TEXT | NO | JSON: {iv: number[], data: number[]} |
| `created_at` | BIGINT | NO | Unix timestamp (ms) |
| `expires_at` | BIGINT | NO | Auto-deletion trigger (s) |
| `burn_after_read` | TINYINT(1) | NO | Boolean flag |
| `has_password` | TINYINT(1) | NO | PBKDF2 required flag |
| `views` | INT | NO | Access counter |

### Real-World Example

**User Input:**
```
سلام، این پیام محرمانه من است.
Hello, this is my secret message.
```

**What Gets Stored in Database:**

```json
{
  "id": "7a3f9e2b8c1d4e5f6a7b8c9d0e1f2a3b",
  "data": "{\"iv\":[187,45,92,201,78,34,156,89,234,12,67,189],\"data\":[45,189,234,67,123,89,45,234,89,123,45,67,234,89,123,45,67,234,89,123,45,67,234,89,123,45,67,234,89,123,45,67,234,89,123,45,67,234,89,123,45,67,234,89,123,45]}",
  "created_at": 1708368000000,
  "expires_at": 1708454400,
  "burn_after_read": 0,
  "has_password": 1,
  "views": 0
}
```

**URL Generated (CONTAINS KEY):**
```
https://yoursite.com/#7a3f9e2b8c1d4e5f6a7b8c9d0e1f2a3b:c2FsdHNhbHRzYWx0:pwd
                                    ↑                    ↑
                              Base64URL salt         Password flag
                              (for PBKDF2)
```

**Critical Security Note:**
- The URL fragment (`#...`) is **never sent** in HTTP headers
- Server logs contain: `GET /api/get/7a3f9e2b8c1d4e5f6a7b8c9d0e1f2a3b`
- Server logs **never contain**: The key, plaintext, or password

---

## 🔧 API Reference

### Authentication

No authentication required. Security through cryptography, not access control.

### Endpoints

#### POST `/api/create`

Create new encrypted paste.

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "id": "a1b2c3d4e5f6... (32 hex characters)",
  "encryptedData": {
    "iv": [12, 34, 56, 78, 90, 12, 34, 56, 78, 90, 12, 34],
    "data": [189, 45, 78, 201, 156, 78, 33, 45, 182, 91, 234, 12, 67]
  },
  "expiresIn": 86400,
  "burnAfterRead": false,
  "hasPassword": false
}
```

**Success Response (201):**
```json
{
  "success": true,
  "id": "a1b2c3d4e5f6...",
  "expiresIn": 86400,
  "hasPassword": false,
  "url": "https://yoursite.com/#a1b2c3d4e5f6:base64urlEncodedKey"
}
```

**Error Responses:**
| Code | Condition | Response |
|------|-----------|----------|
| 400 | Invalid ID format | `{"error": "Invalid ID format"}` |
| 400 | Malformed JSON | `{"error": "Invalid JSON"}` |
| 400 | Missing encryptedData | `{"error": "Invalid encrypted data format"}` |
| 500 | Database failure | `{"error": "Failed to save paste"}` |

#### GET `/api/get/{id}`

Retrieve encrypted paste by ID.

**Parameters:**
| Name | Type | Description |
|------|------|-------------|
| `id` | string | 32-character hexadecimal ID |

**Success Response (200):**
```json
{
  "data": {
    "iv": [12, 34, 56, 78, 90, 12, 34, 56, 78, 90, 12, 34],
    "data": [189, 45, 78, 201, 156, 78, 33, 45, 182, 91, 234, 12, 67]
  },
  "burnAfterRead": false,
  "hasPassword": false,
  "created": 1708368000000
}
```

**Error Responses:**
| Code | Condition |
|------|-----------|
| 400 | Invalid ID format |
| 404 | Paste not found or expired |

---

## 🛡️ Security Analysis

### Threat Model Matrix

| Attacker Capability | Data Access | Mitigation Status |
|---------------------|-------------|-------------------|
| **Passive network observer** | Encrypted TLS traffic only | ✅ Mitigated (TLS 1.3) |
| **Database breach** | Ciphertext, metadata only | ✅ Mitigated (no keys stored) |
| **Server compromise (root)** | Same as database | ✅ Mitigated (stateless design) |
| **Backup tape theft** | Historical ciphertext | ✅ Mitigated (keys not in backups) |
| **URL leak (no password)** | Full plaintext | ⚠️ **User responsibility** |
| **URL leak (with password)** | Partial (needs password) | ⚠️ **Reduced risk** |
| **Physical device access (post-decrypt)** | Plaintext in memory | ❌ **Not mitigated** |

### What We Protect Against

1. **Server-side attacks**: Even with full server compromise, attacker gains zero cryptographic material
2. **Legal requests**: No plaintext or keys to surrender (technical impossibility)
3. **Insider threats**: System administrators cannot access user content
4. **Database leaks**: Ciphertext without keys is information-theoretically secure

### What We Cannot Protect Against

1. **Endpoint compromise**: Malware on sender/recipient device
2. **Social engineering**: Users tricked into sharing URLs
3. **Shoulder surfing**: Visual observation of decrypted content
4. **Forensic analysis**: RAM dumps containing decrypted plaintext

### Operational Security Recommendations

| Risk | Mitigation Strategy |
|------|---------------------|
| URL in browser history | Use incognito/private mode |
| URL in cloud sync | Disable browser sync for sensitive operations |
| Screenshot exposure | Enable "Burn After Read" |
| Password guessing | Use 12+ character random passwords |
| Keylogger exposure | Use hardware security keys where possible |

---

## 🌐 Browser Compatibility

| Browser | Version | Web Crypto API | Status |
|---------|---------|----------------|--------|
| Chrome | 37+ | ✅ Full | Recommended |
| Firefox | 34+ | ✅ Full | Recommended |
| Safari | 7+ | ✅ Full | Supported |
| Edge | 12+ | ✅ Full | Supported |
| Opera | 24+ | ✅ Full | Supported |
| Internet Explorer | Any | ❌ None | Not Supported |

**Requirement:** Secure context (HTTPS or `localhost`) mandatory for `crypto.subtle` access.

---

## ⚖️ Deployment Comparison

### Self-Hosted (This Repository) vs Cloudflare Worker

| Dimension | Self-Hosted | Cloudflare Worker |
|-----------|-------------|-------------------|
| **Infrastructure** | Your server/cPanel | Cloudflare Edge Network |
| **Data Sovereignty** | Full control | Third-party processing |
| **Latency** | Server location dependent | Global edge (<50ms) |
| **Scalability** | Vertical scaling | Auto-scaling |
| **Setup Complexity** | Database + SSL required | Zero configuration |
| **Cost** | Hosting fees | Free tier generous |
| **Compliance** | GDPR/HIPAA self-managed | DPA required |
| **Availability** | Your SLA responsibility | 99.99% Cloudflare SLA |

**Recommendation:**
- Choose **Self-Hosted** for: Data sovereignty, compliance requirements, learning
- Choose **Cloudflare Worker** for: Global reach, zero maintenance, speed

---

## 🤝 Contributing

Contributions welcome! Areas to improve:

- [ ] File attachments (encrypted)
- [ ] QR code generation for sharing
- [ ] Custom themes
- [ ] Browser extension

---

## 📜 License & Attribution

**License:** MIT License - See [LICENSE](LICENSE)

**Cryptographic Implementation:**
- Web Crypto API W3C Specification
- NIST SP 800-132 (PBKDF2)
- FIPS 197 (AES)

**Typography:**
- Vazirmatn by Saber Rastikerdar (Persian/Arabic script support)

**Inspiration:**
- PrivateBin (PHP implementation)
- ZeroBin (original concept)
- CryptPad (collaborative editing)

---

**⚠️ Legal Disclaimer**

> THIS SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND. The authors and contributors assume no liability for data loss, security breaches, or misuse. Users are solely responsible for:
> - Key management and storage
> - Operational security practices
> - Compliance with local laws and regulations
> - Secure transmission of URLs and passwords

---

## 📊 Quick Stats

| Metric | Value |
|--------|-------|
| Encryption strength | 256-bit |
| Key derivation iterations | 100,000 |
| IV length | 96-bit (12 bytes) |
| Maximum message size | Limited by browser memory (~2GB) |
| Supported languages | All Unicode (UTF-8) |
| Database overhead | ~2x plaintext size (JSON + Base64) |

---

## 👤 Author

**TheGreatAzizi**

- GitHub: [@TheGreatAzizi](https://github.com/TheGreatAzizi)
- X/Twitter: [@the_azzi](https://x.com/the_azzi)
