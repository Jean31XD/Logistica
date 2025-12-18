<?php
/**
 * Gestión de Transportistas - MACO
 * Interfaz AJAX sin recarga de página
 */

require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion();

$csrfToken = generarTokenCSRF();

$pageTitle = "Gestión de Transportistas | MACO";
$additionalCSS = <<<'CSS'
<style>
    .transportistas-container { max-width: 1600px; margin: 0 auto; }

    .hero-section {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        padding: 2rem; border-radius: 20px; margin-bottom: 2rem;
        color: white; position: relative; overflow: hidden;
    }
    .hero-section::before {
        content: ''; position: absolute; width: 300px; height: 300px;
        background: rgba(230, 57, 70, 0.15); border-radius: 50%; top: -100px; right: -100px;
    }
    .hero-section h1 { font-size: 1.75rem; font-weight: 700; margin: 0; position: relative; z-index: 2; }
    .hero-section p { opacity: 0.8; margin: 0.5rem 0 0; font-size: 0.95rem; position: relative; z-index: 2; }

    .stats-row { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
    .stat-card {
        background: white; padding: 1.25rem 1.5rem; border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 180px;
    }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: white; }
    .stat-icon.primary { background: linear-gradient(135deg, #E63946, #c1121f); }
    .stat-icon.success { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-icon.info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .stat-info h4 { font-size: 1.5rem; font-weight: 700; margin: 0; color: #1a1a2e; }
    .stat-info span { font-size: 0.85rem; color: #666; }

    .content-grid { display: grid; grid-template-columns: 350px 1fr; gap: 2rem; }
    @media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

    .form-card { background: white; border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .form-card-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
    .form-card-header .icon { width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; }
    .form-card-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }

    .table-card { background: white; border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .table-title { display: flex; align-items: center; gap: 0.75rem; }
    .table-title h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }

    .search-box { display: flex; gap: 0.5rem; }
    .search-box input { padding: 0.5rem 1rem; border: 2px solid #e5e7eb; border-radius: 10px; width: 220px; transition: all 0.3s; }
    .search-box input:focus { border-color: #E63946; outline: none; }

    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 600px; }
    .data-table th { background: #f8fafc; padding: 0.75rem 0.5rem; text-align: left; font-weight: 600; font-size: 0.75rem; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e5e7eb; }
    .data-table td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.9rem; }
    .data-table tbody tr { transition: all 0.2s; }
    .data-table tbody tr:hover { background: #f8fafc; }

    .user-cell { display: flex; align-items: center; gap: 0.75rem; }
    .user-avatar { width: 38px; height: 38px; background: linear-gradient(135deg, #E63946, #c1121f); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.85rem; flex-shrink: 0; }
    .user-info strong { display: block; color: #1a1a2e; font-size: 0.9rem; }
    .user-info span { font-size: 0.8rem; color: #64748b; }

    .badge { padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 500; }
    .badge-empresa { background: #dbeafe; color: #1d4ed8; }
    .badge-matricula { background: #fef3c7; color: #b45309; }

    .action-btns { display: flex; gap: 0.25rem; }
    .btn-action { width: 30px; height: 30px; border: none; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
    .btn-action.edit { background: #fef3c7; color: #b45309; }
    .btn-action.edit:hover { background: #fcd34d; transform: scale(1.1); }
    .btn-action.delete { background: #fee2e2; color: #dc2626; }
    .btn-action.delete:hover { background: #fca5a5; transform: scale(1.1); }

    .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; flex-wrap: wrap; gap: 1rem; }
    .pagination-info { color: #64748b; font-size: 0.85rem; }
    .pagination { display: flex; gap: 0.25rem; margin: 0; }
    .pagination button { padding: 0.4rem 0.75rem; border-radius: 8px; border: none; font-weight: 500; cursor: pointer; transition: all 0.2s; background: #f1f5f9; color: #475569; }
    .pagination button:hover:not(:disabled) { background: #E63946; color: white; }
    .pagination button.active { background: #E63946; color: white; }
    .pagination button:disabled { opacity: 0.5; cursor: not-allowed; }

    .empty-state { text-align: center; padding: 3rem; color: #64748b; }
    .empty-state i { font-size: 3rem; color: #e5e7eb; margin-bottom: 1rem; display: block; }

    .alert-toast { position: fixed; top: 100px; right: 20px; padding: 1rem 1.5rem; border-radius: 12px; color: white; font-weight: 500; z-index: 9999; animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .alert-toast.success { background: linear-gradient(135deg, #10b981, #059669); }
    .alert-toast.danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    .form-control { border: 2px solid #e5e7eb; border-radius: 10px; padding: 0.65rem 1rem; transition: all 0.2s; }
    .form-control:focus { border-color: #E63946; box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1); }

    .btn-create { background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s; }
    .btn-create:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
    .btn-create:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    .loading { display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .spinner { width: 40px; height: 40px; border: 4px solid #f0f0f0; border-top-color: #E63946; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<div class="transportistas-container">
    <div class="hero-section">
        <h1><i class="fas fa-truck me-2"></i>Gestión de Transportistas</h1>
        <p>Administra los transportistas registrados en el sistema</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="fas fa-users"></i></div>
            <div class="stat-info"><h4 id="statTotal">0</h4><span>Total Transportistas</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-truck"></i></div>
            <div class="stat-info"><h4 id="statPagina">0</h4><span>En esta página</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info"><h4 id="statPaginas">0</h4><span>Páginas</span></div>
        </div>
    </div>

    <div class="content-grid">
        <div class="form-card">
            <div class="form-card-header">
                <div class="icon"><i class="fas fa-user-plus"></i></div>
                <h3>Nuevo Transportista</h3>
            </div>
            <form id="formCrear" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre *</label>
                    <input type="text" name="nombre" id="crear_nombre" class="form-control" required placeholder="Nombre completo">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cédula *</label>
                    <input type="text" name="cedula" id="crear_cedula" class="form-control" required placeholder="000-0000000-0">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Empresa</label>
                    <input type="text" name="empresa" id="crear_empresa" class="form-control" placeholder="Empresa">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">RNC</label>
                    <input type="text" name="rnc" id="crear_rnc" class="form-control" placeholder="RNC">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Matrícula</label>
                    <input type="text" name="matricula" id="crear_matricula" class="form-control" placeholder="Placa">
                </div>
                <button type="submit" class="btn-create" id="btnCrear">
                    <i class="fas fa-plus-circle"></i>Crear Transportista
                </button>
            </form>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list text-primary"></i>
                    <h3>Transportistas Registrados</h3>
                </div>
                <div class="search-box">
                    <input type="text" id="inputBuscar" placeholder="Buscar...">
                    <button type="button" class="maco-btn maco-btn-primary maco-btn-sm" onclick="buscar()">
                        <i class="fas fa-search"></i>
                    </button>
                    <button type="button" class="maco-btn maco-btn-secondary maco-btn-sm" onclick="limpiarBusqueda()" id="btnLimpiar" style="display:none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div id="tableContainer">
                <div class="loading"><div class="spinner"></div></div>
            </div>

            <div class="pagination-wrapper" id="paginationWrapper" style="display:none;">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <form id="formEditar">
                <div class="modal-header" style="border-bottom: 2px solid #f0f0f0;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit text-warning me-2"></i>Editar Transportista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="cedula" id="edit_cedula">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Empresa</label>
                        <input type="text" name="empresa" id="edit_empresa" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">RNC</label>
                        <input type="text" name="rnc" id="edit_rnc" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Matrícula</label>
                        <input type="text" name="matricula" id="edit_matricula" class="form-control">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 2px solid #f0f0f0;">
                    <button type="button" class="maco-btn maco-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="maco-btn maco-btn-primary"><i class="fas fa-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additionalJS = <<<JSEND
<script>
const csrfToken = '{$csrfToken}';
const API = '../../Logica/api_transportistas.php';
let paginaActual = 1;
let busquedaActual = '';

document.addEventListener('DOMContentLoaded', () => {
    cargarDatos();
    
    document.getElementById('formCrear').addEventListener('submit', crearTransportista);
    document.getElementById('formEditar').addEventListener('submit', editarTransportista);
    document.getElementById('inputBuscar').addEventListener('keypress', e => { if (e.key === 'Enter') buscar(); });
});

function cargarDatos() {
    const url = API + '?action=list&pagina=' + paginaActual + (busquedaActual ? '&buscar=' + encodeURIComponent(busquedaActual) : '');
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTabla(data.data);
                renderPaginacion(data);
                document.getElementById('statTotal').textContent = data.total;
                document.getElementById('statPagina').textContent = data.data.length;
                document.getElementById('statPaginas').textContent = data.paginas;
            }
        })
        .catch(e => mostrarToast('Error de conexión', 'danger'));
}

function renderTabla(transportistas) {
    const container = document.getElementById('tableContainer');
    
    if (transportistas.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-truck"></i><h4>No hay transportistas</h4></div>';
        document.getElementById('paginationWrapper').style.display = 'none';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="data-table"><thead><tr><th>Transportista</th><th>Empresa</th><th>RNC</th><th>Matrícula</th><th style="width:80px;text-align:center;">Acciones</th></tr></thead><tbody>';
    
    transportistas.forEach(t => {
        const iniciales = (t.Nombres || 'NN').substring(0, 2).toUpperCase();
        const nombre = escapeHtml(t.Nombres || '');
        const cedula = escapeHtml(t.Cedula || '');
        const empresa = t.Empresa ? '<span class="badge badge-empresa">' + escapeHtml(t.Empresa) + '</span>' : '-';
        const rnc = escapeHtml(t.RNC || '-');
        const matricula = t.Matricula ? '<span class="badge badge-matricula">' + escapeHtml(t.Matricula) + '</span>' : '-';
        
        html += '<tr data-cedula="' + cedula + '">';
        html += '<td><div class="user-cell"><div class="user-avatar">' + iniciales + '</div><div class="user-info"><strong>' + nombre + '</strong><span>' + cedula + '</span></div></div></td>';
        html += '<td>' + empresa + '</td>';
        html += '<td>' + rnc + '</td>';
        html += '<td>' + matricula + '</td>';
        html += '<td><div class="action-btns">';
        html += '<button class="btn-action edit" onclick="abrirEditar(\'' + cedula + '\')"><i class="fas fa-edit"></i></button>';
        html += '<button class="btn-action delete" onclick="eliminar(\'' + cedula + '\')"><i class="fas fa-trash"></i></button>';
        html += '</div></td></tr>';
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    document.getElementById('paginationWrapper').style.display = 'flex';
}

function renderPaginacion(data) {
    const info = document.getElementById('paginationInfo');
    const pag = document.getElementById('pagination');
    const inicio = (data.pagina - 1) * data.porPagina + 1;
    const fin = Math.min(data.pagina * data.porPagina, data.total);
    
    info.textContent = 'Mostrando ' + inicio + ' - ' + fin + ' de ' + data.total;
    
    let html = '<button onclick="irPagina(' + (data.pagina - 1) + ')"' + (data.pagina <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';
    
    for (let i = Math.max(1, data.pagina - 2); i <= Math.min(data.paginas, data.pagina + 2); i++) {
        html += '<button onclick="irPagina(' + i + ')"' + (i === data.pagina ? ' class="active"' : '') + '>' + i + '</button>';
    }
    
    html += '<button onclick="irPagina(' + (data.pagina + 1) + ')"' + (data.pagina >= data.paginas ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
    pag.innerHTML = html;
}

function irPagina(p) {
    paginaActual = p;
    cargarDatos();
}

function buscar() {
    busquedaActual = document.getElementById('inputBuscar').value.trim();
    paginaActual = 1;
    document.getElementById('btnLimpiar').style.display = busquedaActual ? 'inline-flex' : 'none';
    cargarDatos();
}

function limpiarBusqueda() {
    document.getElementById('inputBuscar').value = '';
    busquedaActual = '';
    paginaActual = 1;
    document.getElementById('btnLimpiar').style.display = 'none';
    cargarDatos();
}

function crearTransportista(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCrear');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('csrf_token', csrfToken);
    formData.append('nombre', document.getElementById('crear_nombre').value);
    formData.append('cedula', document.getElementById('crear_cedula').value);
    formData.append('empresa', document.getElementById('crear_empresa').value);
    formData.append('rnc', document.getElementById('crear_rnc').value);
    formData.append('matricula', document.getElementById('crear_matricula').value);
    
    fetch(API, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                mostrarToast(data.message, 'success');
                document.getElementById('formCrear').reset();
                cargarDatos();
            } else {
                mostrarToast(data.error, 'danger');
            }
        })
        .catch(() => mostrarToast('Error de conexión', 'danger'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus-circle"></i>Crear Transportista';
        });
}

function abrirEditar(cedula) {
    const row = document.querySelector('tr[data-cedula="' + cedula + '"]');
    if (!row) return;
    
    const nombre = row.querySelector('.user-info strong').textContent;
    const empresa = row.querySelectorAll('td')[1].textContent.trim();
    const rnc = row.querySelectorAll('td')[2].textContent.trim();
    const matricula = row.querySelectorAll('td')[3].textContent.trim();
    
    document.getElementById('edit_cedula').value = cedula;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_empresa').value = empresa === '-' ? '' : empresa;
    document.getElementById('edit_rnc').value = rnc === '-' ? '' : rnc;
    document.getElementById('edit_matricula').value = matricula === '-' ? '' : matricula;
    
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();
}

function editarTransportista(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('csrf_token', csrfToken);
    formData.append('cedula', document.getElementById('edit_cedula').value);
    formData.append('nombre', document.getElementById('edit_nombre').value);
    formData.append('empresa', document.getElementById('edit_empresa').value);
    formData.append('rnc', document.getElementById('edit_rnc').value);
    formData.append('matricula', document.getElementById('edit_matricula').value);
    
    fetch(API, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('modalEditar')).hide();
            if (data.success) {
                mostrarToast(data.message, 'success');
                cargarDatos();
            } else {
                mostrarToast(data.error, 'danger');
            }
        })
        .catch(() => mostrarToast('Error de conexión', 'danger'));
}

function eliminar(cedula) {
    if (!confirm('¿Eliminar este transportista?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('csrf_token', csrfToken);
    formData.append('cedula', cedula);
    
    fetch(API, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                mostrarToast(data.message, 'success');
                cargarDatos();
            } else {
                mostrarToast(data.error, 'danger');
            }
        })
        .catch(() => mostrarToast('Error de conexión', 'danger'));
}

function mostrarToast(msg, type) {
    const toast = document.createElement('div');
    toast.className = 'alert-toast ' + type;
    toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'times-circle') + '"></i>' + msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
JSEND;

include __DIR__ . '/../templates/footer.php';
?>
