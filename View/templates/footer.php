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
</body>
</html>
