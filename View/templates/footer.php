    </main><!-- /#main-content -->

    <!-- Footer -->
    <footer class="maco-footer" role="contentinfo">
        <div class="maco-footer-content">

            <div class="maco-footer-section">
                <div class="maco-footer-icon" aria-hidden="true">
                    <i class="fas fa-life-ring"></i>
                </div>
                <div class="maco-footer-text">
                    <h4>¿Necesitas Ayuda?</h4>
                    <p>Estamos aquí para asistirte con cualquier duda o problema</p>
                </div>
            </div>

            <div class="maco-footer-section">
                <div class="maco-footer-icon" aria-hidden="true">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="maco-footer-text">
                    <h4>Soporte Técnico</h4>
                    <p><a href="https://gcmda.corripio.com.do" target="_blank" rel="noopener noreferrer">Abrir ticket en Zendesk</a></p>
                </div>
            </div>

            <div class="maco-footer-section">
                <div class="maco-footer-icon" aria-hidden="true">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="maco-footer-text">
                    <h4>Horario de Atención</h4>
                    <p>Lunes a Viernes: 8:00 AM – 6:00 PM</p>
                </div>
            </div>

        </div>

        <div class="maco-footer-bottom">
            <p>&copy; <?= date('Y') ?> MACO – Sistema de Logística. Todos los derechos reservados.</p>
        </div>
    </footer>

</div><!-- /.maco-app -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scripts adicionales de la página -->
<?php if (isset($additionalJS)): ?>
    <?= $additionalJS ?>
<?php endif; ?>

<script>
(function () {
    // Mostrar página solo cuando el DOM esté listo (evita flash)
    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('loaded');

        // Animación escalonada de entrada para cards
        document.querySelectorAll('.maco-card').forEach(function (card, i) {
            card.style.animationDelay = (i * 80) + 'ms';
            card.classList.add('maco-fade-in');
        });

        // Confirmar cierre de sesión
        var logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function (e) {
                if (!confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                    e.preventDefault();
                }
            });
        }

        // Prevenir envío de formularios duplicado
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = this.querySelector('[type="submit"]');
                if (btn && !btn.disabled) {
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-spinner fa-spin me-2';
                    }
                }
            });
        });
    });
}());
</script>

<!-- AI Chat Widget -->
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<script>
<?php
    // Detect context to provide a correct path for the proxy
    $currentUrl = $_SERVER['REQUEST_URI'];
    $isSubfolder = (strpos($currentUrl, '/View/pantallas') !== false || strpos($currentUrl, '/View/modulos') !== false);
    $proxyRelativePath = $isSubfolder ? '../../Logica/chat_proxy.php' : './Logica/chat_proxy.php';
?>
window.AI_CHAT_PROXY_URL = '<?= $proxyRelativePath ?>';
</script>

<button id="ai-chat-toggle"
        aria-label="Abrir asistente virtual"
        aria-expanded="false"
        aria-controls="ai-chat-panel"
        title="Asistente Técnico MACOR">
    <i class="fas fa-robot" aria-hidden="true"></i>
    <span class="ai-chat-badge" id="ai-chat-badge" aria-live="polite"></span>
</button>

<div id="ai-chat-panel"
     role="dialog"
     aria-modal="true"
     aria-label="Asistente Técnico MACOR"
     aria-hidden="true">
    <div class="ai-chat-header">
        <div class="ai-chat-header-title">
            <i class="fas fa-robot" aria-hidden="true"></i>
            <span>Asistente MACOR</span>
        </div>
        <button class="ai-chat-close" id="ai-chat-close" aria-label="Cerrar chat">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <div class="ai-chat-messages" id="ai-chat-messages" role="log"></div>
    <div class="ai-chat-footer">
        <textarea
            class="ai-chat-input"
            id="ai-chat-input"
            placeholder="Escribe tu pregunta..."
            rows="1"
            aria-label="Mensaje al asistente"
            maxlength="2000"
        ></textarea>
        <button class="ai-chat-send" id="ai-chat-send" aria-label="Enviar mensaje">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
        </button>
    </div>
</div>

<script src="<?= $assetsPath ?>/js/ai-chat.js"></script>
</body>
</html>
