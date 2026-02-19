let pendingKey = null;
let pendingData = null;

function showError(msg) {
    document.getElementById('errorDisplay').style.display = 'flex';
    document.getElementById('errorMessage').textContent = msg;
}

function clearError() {
    document.getElementById('errorDisplay').style.display = 'none';
}

function isRTL(text) {
    const rtlRegex = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\u0590-\u05FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
    return rtlRegex.test(text);
}

const contentTextarea = document.getElementById('content');
if (contentTextarea) {
    contentTextarea.addEventListener('input', function(e) {
        const text = e.target.value;
        if (isRTL(text)) {
            e.target.style.direction = 'rtl';
            e.target.style.fontFamily = "'Vazirmatn', sans-serif";
        } else {
            e.target.style.direction = 'ltr';
            e.target.style.fontFamily = "'JetBrains Mono', monospace";
        }
    });
}

function togglePassword() {
    const enabled = document.getElementById('enablePassword').checked;
    const wrapper = document.getElementById('passwordWrapper');
    wrapper.classList.toggle('show', enabled);
}

const Base64 = {
    encode(buf) {
        const bytes = new Uint8Array(buf);
        let bin = '';
        for (let b of bytes) bin += String.fromCharCode(b);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    },
    decode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const bin = atob(str);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
    }
};

async function deriveKeyFromPassword(password, salt) {
    const encoder = new TextEncoder();
    const keyMaterial = await crypto.subtle.importKey(
        'raw', encoder.encode(password), { name: 'PBKDF2' }, false, ['deriveKey']
    );
    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations: 100000, hash: 'SHA-256' },
        keyMaterial,
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
    );
}

function genId() {
    const arr = new Uint8Array(16);
    crypto.getRandomValues(arr);
    return Array.from(arr, b => b.toString(16).padStart(2, '0')).join('');
}

async function createPaste() {
    clearError();
    const content = document.getElementById('content').value.trim();
    if (!content) return showError('Please enter content to encrypt');

    const hasPassword = document.getElementById('enablePassword').checked;
    const password = document.getElementById('passwordInput').value;
    
    if (hasPassword && password.length < 4) {
        return showError('Password must be at least 4 characters');
    }

    const btn = document.getElementById('createBtn');
    const btnText = document.getElementById('btnText');
    btn.disabled = true;
    btnText.innerHTML = '<span class="loading"></span> Encrypting...';

    try {
        let key, keyToExport;
        
        if (hasPassword) {
            const salt = crypto.getRandomValues(new Uint8Array(16));
            key = await deriveKeyFromPassword(password, salt);
            keyToExport = salt;
        } else {
            key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
            keyToExport = await crypto.subtle.exportKey('raw', key);
        }
        
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv }, 
            key, 
            new TextEncoder().encode(content)
        );
        
        const id = genId();
        const payload = { 
            iv: Array.from(iv), 
            data: Array.from(new Uint8Array(encrypted)) 
        };
        
        const res = await fetch('/api/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                id, 
                encryptedData: payload, 
                expiresIn: parseInt(document.getElementById('expiresIn').value), 
                burnAfterRead: document.getElementById('burnAfterRead').checked,
                hasPassword: hasPassword
            })
        });
        
        const result = await res.json();
        if (!res.ok) throw new Error(result.error || 'Server error');

        let url;
        if (hasPassword) {
            url = `${location.origin}/#${id}:${Base64.encode(keyToExport)}:pwd`;
        } else {
            url = `${location.origin}/#${id}:${Base64.encode(keyToExport)}`;
        }
        
        document.getElementById('shareUrl').value = url;
        document.getElementById('expiryTime').textContent = new Date(Date.now() + parseInt(document.getElementById('expiresIn').value) * 1000).toLocaleString();
        document.getElementById('passwordNotice').style.display = hasPassword ? 'flex' : 'none';
        document.getElementById('resultBox').classList.add('show');
        document.getElementById('content').value = '';
        
    } catch (err) {
        showError(err.message);
    } finally {
        btn.disabled = false;
        btnText.textContent = '🔐 Encrypt & Save';
    }
}

async function decryptPaste() {
    const hash = location.hash.slice(1);
    if (!hash.includes(':')) return;
    
    const parts = hash.split(':');
    const id = parts[0];
    const keyData = parts[1];
    const isPasswordProtected = parts[2] === 'pwd';
    
    try {
        const res = await fetch(`/api/get/${id}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);

        if (data.hasPassword || isPasswordProtected) {
            pendingKey = keyData;
            pendingData = data;
            document.getElementById('createView').style.display = 'none';
            document.getElementById('passwordPrompt').classList.add('show');
            return;
        }
        
        await performDecryption(keyData, data);
        
    } catch (err) {
        showError('Failed: ' + err.message);
        setTimeout(() => location.href = '/', 3000);
    }
}

async function decryptWithPassword() {
    const password = document.getElementById('decryptPassword').value;
    if (!password) return;
    
    const btn = document.querySelector('#passwordPrompt .btn');
    const btnText = document.getElementById('decryptBtnText');
    btn.disabled = true;
    btnText.innerHTML = '<span class="loading"></span> Decrypting...';
    
    try {
        const salt = Base64.decode(pendingKey);
        const key = await deriveKeyFromPassword(password, salt);
        await performDecryption(key, pendingData, true);
    } catch (err) {
        document.getElementById('passwordError').style.display = 'block';
        btn.disabled = false;
        btnText.textContent = '🔓 Decrypt';
    }
}

async function performDecryption(keyOrData, data, isKeyObject = false) {
    let key;
    
    if (isKeyObject) {
        key = keyOrData;
    } else {
        const keyRaw = Base64.decode(keyOrData);
        key = await crypto.subtle.importKey(
            'raw', keyRaw, { name: 'AES-GCM', length: 256 }, false, ['decrypt']
        );
    }
    
    const decrypted = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: new Uint8Array(data.data.iv) }, 
        key, 
        new Uint8Array(data.data.data)
    );
    
    const text = new TextDecoder().decode(decrypted);
    
    document.getElementById('passwordPrompt').classList.remove('show');
    document.getElementById('decryptView').classList.add('show');
    
    if (data.burnAfterRead) {
        document.getElementById('burnNotice').style.display = 'flex';
    }
    
    const contentBox = document.getElementById('decryptedContent');
    contentBox.textContent = text;
    
    if (isRTL(text)) {
        contentBox.style.direction = 'rtl';
        contentBox.style.fontFamily = "'Vazirmatn', sans-serif";
    } else {
        contentBox.style.direction = 'ltr';
        contentBox.style.fontFamily = "'JetBrains Mono', monospace";
    }
    
    history.replaceState(null, null, ' ');
}

function copyUrl() {
    const inp = document.getElementById('shareUrl');
    inp.select();
    document.execCommand('copy');
    const btn = document.querySelector('.btn-copy');
    btn.textContent = '✅ Copied!';
    setTimeout(() => btn.textContent = '📋 Copy', 2000);
}

window.addEventListener('load', () => {
    if (location.hash.length > 1) decryptPaste();
});
