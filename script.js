let pendingKey = null;
let pendingData = null;

const FONT_STACK_SANS = "'Vazirmatn Local', 'Vazirmatn', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
const FONT_STACK_MONO = "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace";

const MAX_CUSTOM_EXPIRY_SECONDS = 31536000;
const MIN_CUSTOM_EXPIRY_SECONDS = 300;

function prefersInstantScroll() {
    return window.matchMedia('(max-width: 640px)').matches;
}

function smartScrollIntoView(element) {
    if (!element) return;
    element.scrollIntoView({ behavior: prefersInstantScroll() ? 'auto' : 'smooth', block: 'start' });
}

function showError(msg) {
    document.getElementById('errorDisplay').style.display = 'flex';
    document.getElementById('errorMessage').textContent = msg;
}

function clearError() {
    document.getElementById('errorDisplay').style.display = 'none';
}

function isRTL(text) {
    const rtlRegex = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\u0590-\u05FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
    return rtlRegex.test(text || '');
}

function applyDirectionalStyles(element, text, rtlFont, ltrFont) {
    if (!element) return;

    if (isRTL(text)) {
        element.style.direction = 'rtl';
        element.style.fontFamily = rtlFont;
    } else {
        element.style.direction = 'ltr';
        element.style.fontFamily = ltrFont;
    }
}

function updateTextareaDirection(e) {
    applyDirectionalStyles(e.target, e.target.value, FONT_STACK_SANS, FONT_STACK_MONO);
}

const contentTextarea = document.getElementById('content');
if (contentTextarea) {
    contentTextarea.addEventListener('input', updateTextareaDirection);
}

const subjectInput = document.getElementById('subject');
if (subjectInput) {
    subjectInput.addEventListener('input', function(e) {
        applyDirectionalStyles(e.target, e.target.value, FONT_STACK_SANS, FONT_STACK_SANS);
    });
}

const passwordInput = document.getElementById('passwordInput');
if (passwordInput) {
    passwordInput.addEventListener('input', updatePasswordStrength);
}

const expiresInSelect = document.getElementById('expiresIn');
if (expiresInSelect) {
    expiresInSelect.addEventListener('change', toggleCustomExpiry);
}

const editorToolbar = document.querySelector('.editor-toolbar');
if (editorToolbar && contentTextarea) {
    editorToolbar.addEventListener('click', handleToolbarClick);
}


function handleToolbarClick(event) {
    const button = event.target.closest('[data-action]');
    if (!button || !contentTextarea) return;

    const action = button.dataset.action;
    applyToolbarAction(action);
}

function applyToolbarAction(action) {
    switch (action) {
        case 'bold':
            wrapSelection('**', '**', 'bold text');
            break;
        case 'italic':
            wrapSelection('*', '*', 'italic text');
            break;
        case 'strike':
            wrapSelection('~~', '~~', 'strikethrough');
            break;
        case 'heading':
            transformSelectedLines(function(line) {
                return line ? `# ${line.replace(/^#{1,6}\s+/, '')}` : '# Heading';
            }, { fallback: '# Heading' });
            break;
        case 'quote':
            transformSelectedLines(function(line) {
                return line ? `> ${line.replace(/^>\s?/, '')}` : '> Quote';
            }, { fallback: '> Quote' });
            break;
        case 'bullet':
            transformSelectedLines(function(line) {
                return line ? `- ${line.replace(/^[-*+]\s+/, '')}` : '- List item';
            }, { fallback: '- List item' });
            break;
        case 'numbered':
            transformSelectedLines(function(line, index) {
                return `${index + 1}. ${line.replace(/^\d+\.\s+/, '') || 'List item'}`;
            }, { fallback: '1. List item' });
            break;
        case 'link': {
            const selectedText = getSelectedText(contentTextarea) || 'link text';
            const url = window.prompt('Enter the URL for this link:', 'https://');
            if (url === null) {
                focusEditor();
                return;
            }
            const trimmedUrl = url.trim() || 'https://';
            replaceSelection(`[${selectedText}](${trimmedUrl})`, { selectInserted: false });
            break;
        }
        case 'code':
            wrapSelection('`', '`', 'code');
            break;
        case 'codeblock':
            wrapSelection('```\n', '\n```', 'your code here');
            break;
        default:
            return;
    }

    updateTextareaDirection({ target: contentTextarea });
}

function getSelectedText(textarea) {
    return textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
}

function replaceSelection(replacement, options) {
    if (!contentTextarea) return;

    const settings = Object.assign({ selectInserted: true, selectionStartOffset: 0, selectionEndOffset: 0 }, options || {});
    const start = contentTextarea.selectionStart;
    const end = contentTextarea.selectionEnd;
    const current = contentTextarea.value;

    contentTextarea.value = current.slice(0, start) + replacement + current.slice(end);

    if (settings.selectInserted) {
        contentTextarea.setSelectionRange(start + settings.selectionStartOffset, start + replacement.length - settings.selectionEndOffset);
    } else {
        const caret = start + replacement.length;
        contentTextarea.setSelectionRange(caret, caret);
    }

    focusEditor();
}

function wrapSelection(prefix, suffix, placeholder) {
    if (!contentTextarea) return;

    const selected = getSelectedText(contentTextarea);
    const inner = selected || placeholder;
    const replacement = `${prefix}${inner}${suffix}`;
    const selectionStartOffset = prefix.length;
    const selectionEndOffset = suffix.length;

    replaceSelection(replacement, {
        selectInserted: true,
        selectionStartOffset,
        selectionEndOffset
    });
}

function transformSelectedLines(transformer, options) {
    if (!contentTextarea) return;

    const settings = Object.assign({ fallback: '' }, options || {});
    const value = contentTextarea.value;
    const start = contentTextarea.selectionStart;
    const end = contentTextarea.selectionEnd;
    const lineStart = value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
    const nextNewlineIndex = value.indexOf('\n', end);
    const lineEnd = nextNewlineIndex === -1 ? value.length : nextNewlineIndex;
    const selectedBlock = value.slice(lineStart, lineEnd);
    const lines = selectedBlock ? selectedBlock.split('\n') : [settings.fallback];
    const transformed = lines.map(function(line, index) {
        return transformer(line, index);
    }).join('\n');

    contentTextarea.value = value.slice(0, lineStart) + transformed + value.slice(lineEnd);
    contentTextarea.setSelectionRange(lineStart, lineStart + transformed.length);
    focusEditor();
}

function focusEditor() {
    if (!contentTextarea) return;
    contentTextarea.focus();
}

function togglePassword() {
    const enabled = document.getElementById('enablePassword').checked;
    const wrapper = document.getElementById('passwordWrapper');
    wrapper.classList.toggle('show', enabled);

    if (!enabled) {
        document.getElementById('passwordInput').value = '';
        resetPasswordStrength();
    } else {
        updatePasswordStrength();
    }
}

function toggleCustomExpiry() {
    const isCustom = document.getElementById('expiresIn').value === 'custom';
    const wrapper = document.getElementById('customExpiryWrapper');
    wrapper.classList.toggle('show', isCustom);

    if (isCustom) {
        setCustomExpiryBounds();
        const input = document.getElementById('customExpiry');
        if (!input.value) {
            const defaultDate = new Date(Date.now() + 24 * 60 * 60 * 1000);
            input.value = toLocalDateTimeValue(defaultDate);
        }
    }
}

function setCustomExpiryBounds() {
    const input = document.getElementById('customExpiry');
    if (!input) return;

    input.min = toLocalDateTimeValue(new Date(Date.now() + MIN_CUSTOM_EXPIRY_SECONDS * 1000));
    input.max = toLocalDateTimeValue(new Date(Date.now() + MAX_CUSTOM_EXPIRY_SECONDS * 1000));
}

function toLocalDateTimeValue(date) {
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);
    return local.toISOString().slice(0, 16);
}

const Base64 = {
    encode(buf) {
        const bytes = new Uint8Array(buf);
        let bin = '';
        for (const b of bytes) bin += String.fromCharCode(b);
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
    const arr = new Uint8Array(12);
    crypto.getRandomValues(arr);
    return Base64.encode(arr);
}

function buildEncryptedPayload(subject, content) {
    return JSON.stringify({
        subject: subject || '',
        content: content
    });
}

function parseDecryptedPayload(text) {
    try {
        const parsed = JSON.parse(text);
        if (parsed && typeof parsed === 'object' && typeof parsed.content === 'string') {
            return {
                subject: typeof parsed.subject === 'string' ? parsed.subject : '',
                content: parsed.content
            };
        }
    } catch (error) {
        // Backward compatibility with old plain-text payloads.
    }

    return {
        subject: '',
        content: text
    };
}

function resetCreateForm() {
    document.getElementById('content').value = '';
    document.getElementById('subject').value = '';
    document.getElementById('passwordInput').value = '';
    document.getElementById('enablePassword').checked = false;
    document.getElementById('burnAfterRead').checked = false;
    document.getElementById('expiresIn').value = '86400';
    document.getElementById('customExpiry').value = '';
    document.getElementById('customExpiryWrapper').classList.remove('show');
    document.getElementById('passwordWrapper').classList.remove('show');
    resetPasswordStrength();
    applyDirectionalStyles(document.getElementById('content'), '', FONT_STACK_SANS, FONT_STACK_MONO);
    applyDirectionalStyles(document.getElementById('subject'), '', FONT_STACK_SANS, FONT_STACK_SANS);
    setCustomExpiryBounds();
}

function escapeHtml(text) {
    return (text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeAttribute(text) {
    return escapeHtml(text).replace(/`/g, '&#96;');
}

function sanitizeUrl(url) {
    const trimmed = (url || '').trim();
    if (/^(https?:\/\/|mailto:)/i.test(trimmed)) {
        return trimmed;
    }
    return null;
}

function renderInlineMarkdown(text) {
    if (!text) return '';

    const placeholders = [];
    let work = text.replace(/\r\n/g, '\n');

    work = work.replace(/`([^`\n]+)`/g, function(_, code) {
        const token = `@@MDTOKEN${placeholders.length}@@`;
        placeholders.push({ token, html: `<code>${escapeHtml(code)}</code>` });
        return token;
    });

    work = work.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/g, function(_, label, url, title) {
        const safeUrl = sanitizeUrl(url);
        if (!safeUrl) {
            return label;
        }
        const attrs = [`href="${escapeAttribute(safeUrl)}"`, 'target="_blank"', 'rel="noopener noreferrer"'];
        if (title) {
            attrs.push(`title="${escapeAttribute(title)}"`);
        }
        const token = `@@MDTOKEN${placeholders.length}@@`;
        placeholders.push({ token, html: `<a ${attrs.join(' ')}>${escapeHtml(label)}</a>` });
        return token;
    });

    let html = escapeHtml(work);
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    html = html.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
    html = html.replace(/(^|[^_])_([^_\n]+)_(?!_)/g, '$1<em>$2</em>');
    html = html.replace(/~~([^~]+)~~/g, '<del>$1</del>');

    placeholders.forEach(function(entry) {
        html = html.replace(entry.token, entry.html);
    });

    return html;
}

function renderMarkdown(content) {
    const normalized = (content || '').replace(/\r\n/g, '\n');
    const blocks = [];
    const lines = normalized.split('\n');
    let i = 0;

    while (i < lines.length) {
        if (!lines[i].trim()) {
            i += 1;
            continue;
        }

        if (lines[i].startsWith('```')) {
            const lang = lines[i].slice(3).trim();
            i += 1;
            const codeLines = [];
            while (i < lines.length && !lines[i].startsWith('```')) {
                codeLines.push(lines[i]);
                i += 1;
            }
            if (i < lines.length && lines[i].startsWith('```')) {
                i += 1;
            }
            const langAttr = lang ? ` data-lang="${escapeAttribute(lang)}"` : '';
            blocks.push(`<pre class="markdown-code"><code${langAttr}>${escapeHtml(codeLines.join('\n'))}</code></pre>`);
            continue;
        }

        if (/^#{1,6}\s+/.test(lines[i])) {
            const match = lines[i].match(/^(#{1,6})\s+(.*)$/);
            const level = match[1].length;
            blocks.push(`<h${level}>${renderInlineMarkdown(match[2].trim())}</h${level}>`);
            i += 1;
            continue;
        }

        if (/^>\s?/.test(lines[i])) {
            const quoteLines = [];
            while (i < lines.length && /^>\s?/.test(lines[i])) {
                quoteLines.push(lines[i].replace(/^>\s?/, ''));
                i += 1;
            }
            blocks.push(`<blockquote>${quoteLines.map(line => renderInlineMarkdown(line)).join('<br>')}</blockquote>`);
            continue;
        }

        if (/^[-*+]\s+/.test(lines[i])) {
            const items = [];
            while (i < lines.length && /^[-*+]\s+/.test(lines[i])) {
                items.push(`<li>${renderInlineMarkdown(lines[i].replace(/^[-*+]\s+/, ''))}</li>`);
                i += 1;
            }
            blocks.push(`<ul>${items.join('')}</ul>`);
            continue;
        }

        if (/^\d+\.\s+/.test(lines[i])) {
            const items = [];
            while (i < lines.length && /^\d+\.\s+/.test(lines[i])) {
                items.push(`<li>${renderInlineMarkdown(lines[i].replace(/^\d+\.\s+/, ''))}</li>`);
                i += 1;
            }
            blocks.push(`<ol>${items.join('')}</ol>`);
            continue;
        }

        const paragraphLines = [];
        while (
            i < lines.length &&
            lines[i].trim() &&
            !/^#{1,6}\s+/.test(lines[i]) &&
            !/^>\s?/.test(lines[i]) &&
            !/^[-*+]\s+/.test(lines[i]) &&
            !/^\d+\.\s+/.test(lines[i]) &&
            !lines[i].startsWith('```')
        ) {
            paragraphLines.push(lines[i]);
            i += 1;
        }
        blocks.push(`<p>${paragraphLines.map(line => renderInlineMarkdown(line)).join('<br>')}</p>`);
    }

    return blocks.join('');
}

function renderContent(content) {
    const contentBox = document.getElementById('decryptedContent');
    contentBox.dataset.rawContent = content || '';
    contentBox.innerHTML = renderMarkdown(content || '');
}

function showDecryptedContent(payload, burnAfterRead) {
    const subjectWrapper = document.getElementById('decryptedSubjectWrapper');
    const subjectBox = document.getElementById('decryptedSubject');
    const contentBox = document.getElementById('decryptedContent');

    document.getElementById('passwordPrompt').classList.remove('show');
    document.getElementById('decryptView').classList.add('show');
    document.getElementById('createView').style.display = 'none';

    if (burnAfterRead) {
        document.getElementById('burnNotice').style.display = 'flex';
    } else {
        document.getElementById('burnNotice').style.display = 'none';
    }

    if (payload.subject && payload.subject.trim()) {
        subjectWrapper.style.display = 'block';
        subjectBox.textContent = payload.subject;
        applyDirectionalStyles(subjectBox, payload.subject, FONT_STACK_SANS, FONT_STACK_SANS);
        document.title = `${payload.subject} - Secure Pastebin`;
    } else {
        subjectWrapper.style.display = 'none';
        subjectBox.textContent = '';
        document.title = 'Secure Pastebin - End-to-End Encrypted Message Sharing';
    }

    renderContent(payload.content);
    applyDirectionalStyles(contentBox, payload.content, FONT_STACK_SANS, FONT_STACK_MONO);
}

function getExpiryPayload() {
    const selected = document.getElementById('expiresIn').value;

    if (selected !== 'custom') {
        const expiresIn = parseInt(selected, 10);
        return {
            expiresIn,
            expiresAt: null,
            displayDate: new Date(Date.now() + expiresIn * 1000)
        };
    }

    const customValue = document.getElementById('customExpiry').value;
    if (!customValue) {
        throw new Error('Please choose a custom expiration date and time');
    }

    const customDate = new Date(customValue);
    if (Number.isNaN(customDate.getTime())) {
        throw new Error('Custom expiration date is invalid');
    }

    const expiresAt = Math.floor(customDate.getTime() / 1000);
    const deltaSeconds = expiresAt - Math.floor(Date.now() / 1000);

    if (deltaSeconds < MIN_CUSTOM_EXPIRY_SECONDS) {
        throw new Error('Custom expiration must be at least 5 minutes from now');
    }

    if (deltaSeconds > MAX_CUSTOM_EXPIRY_SECONDS) {
        throw new Error('Custom expiration cannot be more than 365 days from now');
    }

    return {
        expiresIn: deltaSeconds,
        expiresAt,
        displayDate: customDate
    };
}

function buildFullShareUrl(id, keyData, hasPassword) {
    const suffix = hasPassword ? `${keyData}:pwd` : keyData;
    return `${location.origin}/p/${encodeURIComponent(id)}#${suffix}`;
}

function buildShortShareUrl(id, keyData, hasPassword) {
    const suffix = hasPassword ? `${keyData}:pwd` : keyData;
    return `${location.origin}/#${encodeURIComponent(id)}:${suffix}`;
}

async function createPaste() {
    clearError();
    const subject = document.getElementById('subject').value.trim();
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
        const expiry = getExpiryPayload();
        let key;
        let keyToExport;

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
            new TextEncoder().encode(buildEncryptedPayload(subject, content))
        );

        const id = genId();
        const payload = {
            iv: Array.from(iv),
            data: Array.from(new Uint8Array(encrypted))
        };

        const res = await fetch('/api/pastes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id,
                encryptedData: payload,
                expiresIn: expiry.expiresIn,
                customExpiresAt: expiry.expiresAt,
                burnAfterRead: document.getElementById('burnAfterRead').checked,
                hasPassword: hasPassword
            })
        });

        const result = await res.json();
        if (!res.ok) throw new Error(result.error || 'Server error');

        const keyFragment = Base64.encode(keyToExport);
        const fullShareUrl = buildFullShareUrl(id, keyFragment, hasPassword);
        const shortShareUrl = buildShortShareUrl(id, keyFragment, hasPassword);
        document.getElementById('shareUrl').value = fullShareUrl;
        document.getElementById('shareUrl').dataset.shortUrl = shortShareUrl;
        document.getElementById('expiryTime').textContent = expiry.displayDate.toLocaleString();
        document.getElementById('passwordNotice').style.display = hasPassword ? 'flex' : 'none';
        document.getElementById('resultBox').classList.add('show');
        smartScrollIntoView(document.getElementById('resultBox'));
        resetCreateForm();

    } catch (err) {
        showError(err.message);
    } finally {
        btn.disabled = false;
        btnText.textContent = '🔐 Encrypt & Save';
    }
}

function parseLocationForPaste() {
    const hash = location.hash.slice(1);
    const pathMatch = location.pathname.match(/^\/p\/([^/]+)\/?$/);

    if (pathMatch && hash) {
        const id = decodeURIComponent(pathMatch[1]);
        const isPasswordProtected = hash.endsWith(':pwd');
        const keyData = isPasswordProtected ? hash.slice(0, -4) : hash;

        if (id && keyData) {
            return { id, keyData, isPasswordProtected };
        }
    }

    if (hash.includes(':')) {
        const parts = hash.split(':');
        const id = parts[0];
        const keyData = parts[1];
        const isPasswordProtected = parts[2] === 'pwd';

        if (id && keyData) {
            return { id, keyData, isPasswordProtected };
        }
    }

    return null;
}

async function decryptPaste() {
    const pasteRef = parseLocationForPaste();
    if (!pasteRef) return;

    try {
        const res = await fetch(`/api/get/${encodeURIComponent(pasteRef.id)}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);

        if (data.hasPassword || pasteRef.isPasswordProtected) {
            pendingKey = pasteRef.keyData;
            pendingData = data;
            document.getElementById('createView').style.display = 'none';
            document.getElementById('passwordPrompt').classList.add('show');
            smartScrollIntoView(document.getElementById('passwordPrompt'));
            return;
        }

        await performDecryption(pasteRef.keyData, data);

    } catch (err) {
        showError('Failed: ' + err.message);
        setTimeout(() => {
            location.href = '/';
        }, 3000);
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
        document.getElementById('passwordError').style.display = 'none';
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

    const decoded = new TextDecoder().decode(decrypted);
    const payload = parseDecryptedPayload(decoded);
    showDecryptedContent(payload, data.burnAfterRead);

    history.replaceState(null, '', location.pathname + location.search);
}

async function copyTextToClipboard(text) {
    if (!text) return;

    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const temp = document.createElement('textarea');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);
}

async function copyUrl() {
    const btn = document.getElementById('copyUrlBtn');

    try {
        await copyTextToClipboard(document.getElementById('shareUrl').value);
        btn.textContent = '✅ Copied!';
        setTimeout(() => {
            btn.textContent = '📋 Copy';
        }, 2000);
    } catch (error) {
        showError('Unable to copy the full share URL');
    }
}

async function copyShortUrl() {
    const btn = document.getElementById('copyShortUrlBtn');
    const shareUrl = document.getElementById('shareUrl');
    const shortUrl = shareUrl ? shareUrl.dataset.shortUrl : '';

    if (!shortUrl) {
        showError('Short link is not available yet');
        return;
    }

    try {
        await copyTextToClipboard(shortUrl);
        btn.textContent = '✅ Copied!';
        setTimeout(() => {
            btn.textContent = '⚡ Copy short';
        }, 2000);
    } catch (error) {
        showError('Unable to copy the short share URL');
    }
}

async function copyDecryptedContent() {
    const btn = document.getElementById('copyDecryptedBtn');
    const subject = document.getElementById('decryptedSubject').textContent.trim();
    const content = document.getElementById('decryptedContent').dataset.rawContent || '';
    const textToCopy = subject ? `Subject: ${subject}\n\n${content}` : content;

    try {
        await copyTextToClipboard(textToCopy);
        btn.textContent = '✅ Copied!';
        setTimeout(() => {
            btn.textContent = '📋 Copy Text';
        }, 2000);
    } catch (error) {
        showError('Unable to copy decrypted content');
    }
}

function resetPasswordStrength() {
    const meterFill = document.getElementById('passwordStrengthFill');
    const meterLabel = document.getElementById('passwordStrengthLabel');
    const wrapper = document.getElementById('passwordStrength');
    if (!meterFill || !meterLabel || !wrapper) return;

    wrapper.dataset.strength = 'empty';
    meterFill.style.width = '0%';
    meterLabel.textContent = 'Password strength will appear here';
}

function calculatePasswordStrength(password) {
    let score = 0;

    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    if (password.length >= 16) score += 1;
    if (/[a-z]/.test(password)) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/\d/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;

    if (password.length > 0 && password.length < 6) {
        score = Math.min(score, 1);
    }

    if (score <= 1) return { label: 'Weak', width: 25, level: 'weak' };
    if (score <= 3) return { label: 'Fair', width: 50, level: 'fair' };
    if (score <= 5) return { label: 'Good', width: 75, level: 'good' };
    return { label: 'Strong', width: 100, level: 'strong' };
}

function updatePasswordStrength() {
    const wrapper = document.getElementById('passwordStrength');
    const input = document.getElementById('passwordInput');
    const meterFill = document.getElementById('passwordStrengthFill');
    const meterLabel = document.getElementById('passwordStrengthLabel');
    if (!wrapper || !input || !meterFill || !meterLabel) return;

    const password = input.value;
    if (!password) {
        resetPasswordStrength();
        return;
    }

    const result = calculatePasswordStrength(password);
    wrapper.dataset.strength = result.level;
    meterFill.style.width = `${result.width}%`;
    meterLabel.textContent = `${result.label} password`;
}

document.addEventListener('keydown', function(event) {
    if (!contentTextarea) return;
    if (document.activeElement !== contentTextarea) return;

    const key = event.key.toLowerCase();
    if ((event.ctrlKey || event.metaKey) && !event.altKey) {
        if (key === 'b') {
            event.preventDefault();
            applyToolbarAction('bold');
        } else if (key === 'i') {
            event.preventDefault();
            applyToolbarAction('italic');
        }
    }
});

window.addEventListener('load', () => {
    setCustomExpiryBounds();
    resetPasswordStrength();
    if (location.hash.length > 0 || /^\/p\//.test(location.pathname)) {
        decryptPaste();
    }
});
