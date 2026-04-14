/**
 * AI Chat Widget — MACO Logística
 * Conecta con el agente Técnico MACOR via chat_proxy.php
 */
(function () {
    'use strict';

    const toggle   = document.getElementById('ai-chat-toggle');
    const panel    = document.getElementById('ai-chat-panel');
    const closeBtn = document.getElementById('ai-chat-close');
    const messages = document.getElementById('ai-chat-messages');
    const input    = document.getElementById('ai-chat-input');
    const sendBtn  = document.getElementById('ai-chat-send');
    const badge    = document.getElementById('ai-chat-badge');

    if (!toggle) return;

    // Inyectado por footer.php
    const PROXY_URL = window.AI_CHAT_PROXY_URL || '/Logica/chat_proxy.php';

    let isOpen    = false;
    let unreadCount = 0;
    let welcomed = false;

    // --- Abrir / cerrar ---
    function openPanel() {
        isOpen = true;
        panel.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        input.focus();
        clearBadge();
        if (!welcomed) {
            welcomed = true;
            addBubble('Hola, soy el Asistente Técnico MACOR. ¿En qué te puedo ayudar?', 'bot');
        }
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function clearBadge() {
        unreadCount = 0;
        badge.classList.remove('visible');
        badge.textContent = '';
    }

    toggle.addEventListener('click', function () {
        isOpen ? closePanel() : openPanel();
    });

    closeBtn.addEventListener('click', closePanel);

    // Cerrar con Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closePanel();
    });

    // --- Burbujas ---
    function addBubble(text, role) {
        var div = document.createElement('div');
        div.className = 'ai-bubble ' + role;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function showTyping() {
        var el = document.createElement('div');
        el.className = 'ai-typing';
        el.id = 'ai-typing-indicator';
        el.innerHTML = '<span></span><span></span><span></span>';
        messages.appendChild(el);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping() {
        var el = document.getElementById('ai-typing-indicator');
        if (el) el.remove();
    }

    // --- Enviar mensaje ---
    function sendMessage() {
        if (sendBtn.disabled) return;
        var text = input.value.trim();
        if (!text) return;

        addBubble(text, 'user');
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        showTyping();

        var csrfToken = document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            : (window.CSRF_TOKEN || '');
        fetch(PROXY_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ message: text })
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            removeTyping();
            var reply = data.reply || 'Sin respuesta del agente.';
            addBubble(reply, 'bot');
            if (!isOpen) {
                unreadCount++;
                badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
                badge.classList.add('visible');
            }
        })
        .catch(function (err) {
            removeTyping();
            addBubble('Error al conectar con el asistente. Intenta de nuevo.', 'bot');
            console.error('[AI Chat]', err);
        })
        .finally(function () {
            sendBtn.disabled = false;
            if (isOpen) input.focus();
        });
    }

    sendBtn.addEventListener('click', sendMessage);

    // Enter para enviar (Shift+Enter = nueva línea)
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });

}());
