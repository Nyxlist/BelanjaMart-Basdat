/**
 * Real-time-ish chat using short polling.
 *
 * The page must define `window.BM_CHAT = { chatId, currentUserId, apiBase }`.
 */
(function () {
    if (!window.BM_CHAT) return;

    const { chatId, currentUserId, apiBase } = window.BM_CHAT;
    const body  = document.getElementById('chatBody');
    const input = document.getElementById('chatInput');
    const form  = document.getElementById('chatForm');
    const typing = document.getElementById('typingIndicator');
    if (!body || !form) return;

    let lastId = parseInt(body.dataset.lastId || '0', 10);
    let typingTimer = null;

    function fmtTime(d) {
        const t = new Date(d);
        return t.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(s) {
        return (s || '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function renderMessage(m) {
        const mine = parseInt(m.sender_id, 10) === parseInt(currentUserId, 10);
        const wrap = document.createElement('div');
        wrap.className = 'chat-msg' + (mine ? ' mine' : '');
        let html = '';
        if (!mine) html += `<div class="fw-600 fs-12">${escapeHtml(m.sender_name)}</div>`;
        if (m.body) html += `<div>${escapeHtml(m.body)}</div>`;
        if (m.attachment) {
            const url = '../../storage/' + m.attachment;
            if (m.attachment_type === 'image') {
                html += `<img src="${url}" alt="attachment">`;
            } else {
                html += `<a href="${url}" target="_blank">Attachment</a>`;
            }
        }
        const tick = mine ? (m.is_read ? '✓✓' : '✓') : '';
        html += `<div class="meta">${fmtTime(m.created_at)} ${tick}</div>`;
        wrap.innerHTML = html;
        body.appendChild(wrap);
    }

    async function poll() {
        try {
            const r = await fetch(`${apiBase}/?route=chat&action=poll&chat_id=${chatId}&after_id=${lastId}`, {
                headers: { 'Authorization': 'Bearer ' + (window.BM_TOKEN || '') }
            });
            const json = await r.json();
            if (json.success) {
                (json.data.messages || []).forEach(m => {
                    renderMessage(m);
                    lastId = Math.max(lastId, parseInt(m.message_id, 10));
                });
                if (json.data.messages && json.data.messages.length) {
                    body.scrollTop = body.scrollHeight;
                }
                if (typing) {
                    const others = (json.data.typing || []).filter(u => parseInt(u.user_id, 10) !== parseInt(currentUserId, 10));
                    typing.textContent = others.length ? others[0].name + ' is typing…' : '';
                }
            }
        } catch (e) {
            /* network glitch - try again next tick */
        }
    }

    async function send(e) {
        e.preventDefault();
        const text = input.value.trim();
        const file = form.querySelector('input[type=file]')?.files?.[0];
        if (!text && !file) return;

        const fd = new FormData();
        fd.append('chat_id', chatId);
        fd.append('body', text);
        if (file) fd.append('attachment', file);

        await fetch(`${apiBase}/?route=chat&action=send`, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + (window.BM_TOKEN || '') },
            body: fd
        });
        input.value = '';
        if (file) form.reset();
        poll();
    }

    function pingTyping() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            fetch(`${apiBase}/?route=chat&action=typing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (window.BM_TOKEN || '')
                },
                body: JSON.stringify({ chat_id: chatId })
            });
        }, 200);
    }

    form.addEventListener('submit', send);
    input.addEventListener('input', pingTyping);
    body.scrollTop = body.scrollHeight;

    // Polling loop
    poll();
    setInterval(poll, 2500);
})();
