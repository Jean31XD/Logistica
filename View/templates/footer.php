        </main>

        <!-- Footer de Ayuda -->
        <footer class="maco-footer">
            <div class="maco-footer-content">
                <div class="maco-footer-section">
                    <div class="footer-icon">
                        <i class="fas fa-life-ring"></i>
                    </div>
                    <div class="footer-text">
                        <h4>¿Necesitas Ayuda?</h4>
                        <p>Estamos aquí para asistirte con cualquier duda o problema</p>
                    </div>
                </div>

                <div class="maco-footer-section">
                    <div class="footer-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="footer-text">
                        <h4>Soporte Técnico</h4>
                        <p><a href="https://gcmda.corripio.com.do">Zendesk</a></p>
                    </div>
                </div>

                <div class="maco-footer-section">
                    <div class="footer-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="footer-text">
                        <h4>Horario de Atención</h4>
                        <p>Lunes a Viernes: 8:00 AM - 6:00 PM</p>
                    </div>
                </div>
            </div>

            <div class="maco-footer-bottom">
                <p>&copy; <?= date('Y') ?> MACO - Sistema de Logística. Todos los derechos reservados.</p>
            </div>
        </footer>
    </div>

    <style>
        .maco-footer {
            background: white;
            border-top: 3px solid var(--primary);
            margin-top: auto;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        }

        .maco-footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-6);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-6);
        }

        .maco-footer-section {
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
        }

        .footer-icon {
            width: 48px;
            height: 48px;
            background: var(--primary);
            color: white;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .footer-text h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 var(--space-2) 0;
        }

        .footer-text p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.5;
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .footer-text a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .maco-footer-bottom {
            border-top: 1px solid var(--border-color);
            padding: var(--space-4) var(--space-6);
            text-align: center;
            background: var(--gray-50);
        }

        .maco-footer-bottom p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .maco-footer-content {
                grid-template-columns: 1fr;
                padding: var(--space-6) var(--space-4);
            }

            .footer-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }
        }
    </style>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Scripts adicionales de la página -->
    <?php if (isset($additionalJS)): ?>
        <?= $additionalJS ?>
    <?php endif; ?>

    <!-- Script global -->
    <script>
        // Remover loader cuando la página cargue
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');

            // Animación de entrada para las cards
            const cards = document.querySelectorAll('.maco-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('maco-fade-in');
            });

            // Confirmar logout
            const logoutBtn = document.querySelector('.maco-logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    if (!confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                        e.preventDefault();
                    }
                });
            }
        });

        // Prevenir envío de formularios duplicados
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
                }
            });
        });
    </script>
</body>
</html>
