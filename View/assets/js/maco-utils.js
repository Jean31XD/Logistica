/**
 * Theme Manager - MACO
 * 
 * Gestiona el cambio entre tema claro y oscuro.
 * 
 * @package    MACO
 * @author     MACO Team
 * @version    1.0.0
 */

class ThemeManager {
    constructor() {
        this.theme = this.getStoredTheme() || 'light';
        this.init();
    }

    init() {
        // Aplicar tema guardado
        this.applyTheme(this.theme);

        // Escuchar cambios en preferencias del sistema
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!this.getStoredTheme()) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    getStoredTheme() {
        return localStorage.getItem('maco-theme');
    }

    setStoredTheme(theme) {
        localStorage.setItem('maco-theme', theme);
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.theme = theme;
        this.updateToggleButton();
    }

    toggle() {
        const newTheme = this.theme === 'dark' ? 'light' : 'dark';
        this.applyTheme(newTheme);
        this.setStoredTheme(newTheme);
    }

    updateToggleButton() {
        const toggleBtn = document.querySelector('.theme-toggle');
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-pressed', this.theme === 'dark');
        }
    }
}

// Inicializar gestor de tema
const themeManager = new ThemeManager();

// Función global para toggle
window.toggleTheme = function() {
    themeManager.toggle();
};

/**
 * Export Manager - MACO
 * 
 * Gestiona la exportación de datos.
 */
class ExportManager {
    constructor() {
        this.baseUrl = '../Logica/api_exportar.php';
    }

    /**
     * Exportar facturas a Excel
     */
    exportFacturasExcel(fechaInicio, fechaFin, almacen = '') {
        this.export('excel', 'facturas', fechaInicio, fechaFin, almacen);
    }

    /**
     * Exportar facturas a PDF
     */
    exportFacturasPdf(fechaInicio, fechaFin, almacen = '') {
        this.export('pdf', 'facturas', fechaInicio, fechaFin, almacen);
    }

    /**
     * Exportar entregas a Excel
     */
    exportEntregasExcel(fechaInicio, fechaFin, almacen = '') {
        this.export('excel', 'entregas', fechaInicio, fechaFin, almacen);
    }

    /**
     * Exportar resumen a PDF
     */
    exportResumenPdf(fechaInicio, fechaFin, almacen = '') {
        this.export('pdf', 'resumen', fechaInicio, fechaFin, almacen);
    }

    /**
     * Ejecutar exportación
     */
    export(tipo, reporte, fechaInicio, fechaFin, almacen) {
        const params = new URLSearchParams({
            tipo: tipo,
            reporte: reporte,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin,
            almacen: almacen
        });

        const url = `${this.baseUrl}?${params.toString()}`;
        
        if (tipo === 'pdf') {
            // Abrir en nueva ventana para PDF
            window.open(url, '_blank');
        } else {
            // Descargar directo para Excel
            window.location.href = url;
        }
    }
}

// Instancia global
const exportManager = new ExportManager();

// Funciones globales de exportación
window.exportToExcel = function(reporte = 'facturas') {
    const fechaInicio = document.getElementById('fecha_inicio')?.value || '';
    const fechaFin = document.getElementById('fecha_fin')?.value || '';
    const almacen = document.getElementById('filtro_almacen')?.value || '';
    
    exportManager.export('excel', reporte, fechaInicio, fechaFin, almacen);
};

window.exportToPdf = function(reporte = 'facturas') {
    const fechaInicio = document.getElementById('fecha_inicio')?.value || '';
    const fechaFin = document.getElementById('fecha_fin')?.value || '';
    const almacen = document.getElementById('filtro_almacen')?.value || '';
    
    exportManager.export('pdf', reporte, fechaInicio, fechaFin, almacen);
};

/**
 * Notification Manager - MACO
 * 
 * Gestiona notificaciones toast.
 */
class NotificationManager {
    constructor() {
        this.container = this.getOrCreateContainer();
    }

    getOrCreateContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    show(message, type = 'info', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `maco-toast maco-toast-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        toast.innerHTML = `
            <i class="fas ${icons[type]} toast-icon"></i>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.container.appendChild(toast);

        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);

        // Auto remove
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    success(message) {
        this.show(message, 'success');
    }

    error(message) {
        this.show(message, 'error');
    }

    warning(message) {
        this.show(message, 'warning');
    }

    info(message) {
        this.show(message, 'info');
    }
}

// Instancia global
const notify = new NotificationManager();

// Funciones globales
window.showToast = (message, type) => notify.show(message, type);
window.showSuccess = (message) => notify.success(message);
window.showError = (message) => notify.error(message);
window.showWarning = (message) => notify.warning(message);
window.showInfo = (message) => notify.info(message);
