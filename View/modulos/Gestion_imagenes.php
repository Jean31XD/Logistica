<?php
/**
 * Gestión de Imágenes de Productos - Azure Blob Storage
 * MACO Design System
 */

// Incluir configuración centralizada de sesión y conexión a BD
require_once __DIR__ . '/../../conexionBD/session_config.php';

// Verificar autenticación básica
if (!isset($_SESSION['usuario'])) {
    header("Location: " . getLoginUrl());
    exit();
}

// Verificar permisos: Pantalla 0 (Admin), 5 (Panel Admin) y 13 (Gestión de Imágenes) pueden acceder
$pantallaUsuario = intval($_SESSION['pantalla'] ?? -1);
$pantallasPermitidas = [0, 5, 13];

if (!in_array($pantallaUsuario, $pantallasPermitidas)) {
    // Mostrar error informativo en lugar de redirigir
    die("
    <html>
    <head><title>Acceso Denegado</title></head>
    <body style='font-family: Arial; padding: 2rem; text-align: center;'>
        <h1 style='color: #E63946;'>⚠️ Acceso Denegado</h1>
        <p>No tienes permisos para acceder a este módulo.</p>
        <p><strong>Tu pantalla asignada:</strong> {$pantallaUsuario}</p>
        <p><strong>Pantallas permitidas:</strong> 0 (Admin), 13 (Gestión Imágenes)</p>
        <p><a href='../../View/pantallas/Portal.php'>Volver al Portal</a></p>
    </body>
    </html>
    ");
}

// Cargar autoloader de Composer para Azure SDK
$composer_autoload = __DIR__ . '/../../vendor/autoload.php';
$azure_available = file_exists($composer_autoload);

$pageTitle = "Gestión de Imágenes | MACO";
$csrfToken = generarTokenCSRF();

$additionalCSS = <<<'CSS'
<style>
    .admin-container {
        max-width: 1600px;
        margin: 0 auto;
    }

    .hero-section {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 3rem 2rem;
        border-radius: var(--radius-xl);
        margin-bottom: 2rem;
        color: white;
        text-align: center;
        box-shadow: var(--shadow-xl);
    }

    .hero-section h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .hero-section p {
        font-size: 1.125rem;
        opacity: 0.95;
    }

    .action-panel {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
    }

    .upload-area {
        border: 3px dashed var(--primary);
        border-radius: var(--radius-xl);
        padding: 2rem;
        text-align: center;
        background: rgba(230, 57, 70, 0.05);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .upload-area:hover {
        background: rgba(230, 57, 70, 0.1);
        border-color: var(--primary-dark);
    }

    .upload-area.drag-over {
        background: rgba(230, 57, 70, 0.15);
        border-color: var(--primary-dark);
        transform: scale(1.02);
    }

    .upload-icon {
        font-size: 4rem;
        color: var(--primary);
        margin-bottom: 1rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        display: none;
    }

    .alert-success {
        background: #d1fae5;
        border: 2px solid #10b981;
        color: #065f46;
    }

    .alert-error {
        background: #fee2e2;
        border: 2px solid #ef4444;
        color: #991b1b;
    }

    .alert-warning {
        background: #fef3c7;
        border: 2px solid #f59e0b;
        color: #92400e;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .loading-spinner {
        border: 5px solid rgba(255, 255, 255, 0.3);
        border-top: 5px solid white;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
        width: 100%;
    }

    .file-input-wrapper input[type=file] {
        position: absolute;
        left: -9999px;
    }

    #files-table-container {
        margin-top: 2rem;
        display: none;
    }

    .files-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    .files-table th {
        background: var(--gray-100);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--gray-200);
    }

    .files-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .files-table tr:hover {
        background: var(--gray-50);
    }

    .file-preview-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .remove-file-btn {
        background: var(--danger);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        cursor: pointer;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .remove-file-btn:hover {
        background: #dc2626;
        transform: scale(1.05);
    }

    .file-name {
        font-weight: 600;
        color: var(--text-primary);
        word-break: break-word;
    }

    .file-size {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .upload-summary {
        background: var(--gray-50);
        padding: 1rem;
        border-radius: var(--radius-md);
        margin-top: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .summary-text {
        font-weight: 600;
        color: var(--text-primary);
    }

    .progress-container {
        margin-top: 1rem;
        display: none;
    }

    .progress-bar {
        width: 100%;
        height: 30px;
        background: var(--gray-200);
        border-radius: var(--radius-md);
        overflow: hidden;
        position: relative;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
        width: 0%;
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
    }

    .results-container {
        margin-top: 2rem;
        display: none;
    }

    .result-item {
        padding: 1rem;
        margin-bottom: 0.5rem;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .result-success {
        background: #d1fae5;
        border-left: 4px solid #10b981;
    }

    .result-error {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
    }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<div class="admin-container">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-icon">
            <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <h1>Subir Imágenes a Azure</h1>
        <p>Selecciona múltiples imágenes - usa su nombre original o asigna un SKU personalizado</p>
    </div>

    <!-- Alertas -->
    <div id="alert-success" class="alert alert-success"></div>
    <div id="alert-error" class="alert alert-error"></div>
    <div id="alert-warning" class="alert alert-warning"></div>

    <?php if (!$azure_available): ?>
    <div class="alert alert-warning" style="display: block;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Azure SDK no instalado.</strong> Para usar esta funcionalidad, ejecute:
        <code>composer require microsoft/azure-storage-blob</code>
    </div>
    <?php else: ?>

    <!-- Panel de Subida -->
    <div class="action-panel">
        <h2 style="margin-bottom: 1.5rem; color: var(--text-primary);">
            <i class="fas fa-images" style="color: var(--primary);"></i>
            Seleccionar Imágenes
        </h2>

        <div class="form-group">
            <div class="file-input-wrapper">
                <label for="imagenes" class="upload-area" id="upload-area">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <p style="margin: 0; font-weight: 600; color: var(--text-primary); font-size: 1.125rem;">
                        Haz clic o arrastra múltiples imágenes aquí
                    </p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: var(--text-secondary);">
                        Formatos: JPG, PNG, GIF, WEBP (Máx. 5MB cada una)
                    </p>
                </label>
                <input type="file"
                       name="imagenes"
                       id="imagenes"
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                       multiple>
            </div>
        </div>

        <!-- Tabla de archivos seleccionados -->
        <div id="files-table-container">
            <h3 style="margin-bottom: 1rem; color: var(--text-primary);">
                <i class="fas fa-list"></i> Archivos Seleccionados
            </h3>

            <table class="files-table" id="files-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Vista Previa</th>
                        <th>Nombre del Archivo</th>
                        <th style="width: 250px;">SKU del Producto (opcional)</th>
                        <th style="width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="files-tbody">
                    <!-- Se llenará dinámicamente -->
                </tbody>
            </table>

            <div style="background: #fef3c7; padding: 0.75rem; border-radius: var(--radius-md); margin-top: 1rem; font-size: 0.9rem;">
                <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
                <strong>Nota:</strong> Si no ingresas un SKU, la imagen se subirá con su nombre original (sin extensión).
            </div>

            <div class="upload-summary">
                <span class="summary-text">
                    <i class="fas fa-info-circle"></i>
                    Total de archivos: <span id="total-files">0</span>
                </span>
                <button type="button" id="upload-all-btn" class="maco-btn maco-btn-primary maco-btn-lg">
                    <i class="fas fa-upload"></i> Subir Todas las Imágenes
                </button>
            </div>

            <!-- Barra de progreso -->
            <div class="progress-container" id="progress-container">
                <p style="margin-bottom: 0.5rem; font-weight: 600;">Progreso de subida:</p>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill">0%</div>
                </div>
            </div>

            <!-- Resultados -->
            <div class="results-container" id="results-container">
                <h3 style="margin-bottom: 1rem; color: var(--text-primary);">
                    <i class="fas fa-check-circle"></i> Resultados
            </div>
        </div>
    </div>

    <!-- Panel de Búsqueda y Eliminación -->
    <div class="action-panel">
        <h2 style="margin-bottom: 1.5rem; color: var(--text-primary);">
            <i class="fas fa-search" style="color: var(--primary);"></i>
            Buscar y Eliminar Imágenes
        </h2>

        <div class="form-group">
            <label for="buscarSku" class="form-label">Buscar por SKU del Producto</label>
            <div style="display: flex; gap: 1rem;">
                <input type="text" 
                       id="buscarSku" 
                       class="form-input" 
                       placeholder="Ingrese el SKU del producto (ej: ABC123)" 
                       style="flex: 1;">
                <button type="button" id="btnBuscar" class="maco-btn maco-btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
        </div>

        <!-- Resultado de búsqueda -->
        <div id="resultadoBusqueda" style="display: none; margin-top: 1.5rem;">
            <div id="imagenEncontrada" style="background: white; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 2rem;">
                <div>
                    <img id="previewBusqueda" src="" alt="Vista previa" style="max-width: 200px; max-height: 200px; border-radius: var(--radius-md); box-shadow: var(--shadow-md);">
                </div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 0.5rem; color: var(--text-primary);">
                        <i class="fas fa-image" style="color: var(--primary);"></i>
                        <span id="nombreImagenBusqueda"></span>
                    </h3>
                    <p style="color: var(--text-secondary); margin: 0 0 1rem;">Imagen encontrada en Azure Blob Storage</p>
                    <div style="display: flex; gap: 1rem;">
                        <a id="linkDescargar" href="#" target="_blank" class="maco-btn maco-btn-secondary">
                            <i class="fas fa-external-link-alt"></i> Ver en Nueva Pestaña
                        </a>
                        <button type="button" id="btnEliminar" class="maco-btn" style="background: #ef4444; color: white;">
                            <i class="fas fa-trash"></i> Eliminar Imagen
                        </button>
                    </div>
                </div>
            </div>
            <div id="imagenNoEncontrada" style="background: #fee2e2; border: 2px solid #ef4444; border-radius: var(--radius-lg); padding: 1.5rem; display: none;">
                <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                <strong>Imagen no encontrada.</strong> No existe una imagen con ese SKU en Azure.
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay">
    <div class="loading-spinner"></div>
</div>

<input type="hidden" id="csrf-token" value="<?= $csrfToken ?>">

<script>
let selectedFiles = [];
let fileCounter = 0;

document.addEventListener('DOMContentLoaded', function() {
    const imagenesInput = document.getElementById('imagenes');
    const uploadArea = document.getElementById('upload-area');
    const filesTableContainer = document.getElementById('files-table-container');
    const filesTbody = document.getElementById('files-tbody');
    const uploadAllBtn = document.getElementById('upload-all-btn');

    // Manejar selección de archivos
    imagenesInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, function() {
            uploadArea.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, function() {
            uploadArea.classList.remove('drag-over');
        });
    });

    uploadArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    });

    // Función para comprimir imagen
    async function compressImage(file) {
        return new Promise((resolve, reject) => {
            // Si el archivo ya es menor a 500KB, no comprimir
            if (file.size < 500 * 1024) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.readAsDataURL(file);

            reader.onload = function(e) {
                const img = new Image();
                img.src = e.target.result;

                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Calcular nuevas dimensiones manteniendo aspecto
                    let width = img.width;
                    let height = img.height;
                    const maxDimension = 1920; // Máximo ancho/alto

                    if (width > maxDimension || height > maxDimension) {
                        if (width > height) {
                            height = (height / width) * maxDimension;
                            width = maxDimension;
                        } else {
                            width = (width / height) * maxDimension;
                            height = maxDimension;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;

                    // Dibujar imagen redimensionada
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convertir a blob con calidad del 85%
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            // Crear nuevo archivo con el blob comprimido
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });

                            console.log(`Imagen comprimida: ${(file.size / 1024).toFixed(2)}KB -> ${(compressedFile.size / 1024).toFixed(2)}KB`);
                            resolve(compressedFile);
                        } else {
                            reject(new Error('Error al comprimir imagen'));
                        }
                    }, 'image/jpeg', 0.85);
                };

                img.onerror = function() {
                    reject(new Error('Error al cargar la imagen'));
                };
            };

            reader.onerror = function() {
                reject(new Error('Error al leer el archivo'));
            };
        });
    }

    // Manejar archivos seleccionados
    function handleFiles(files) {
        for (let file of files) {
            // Validar tipo de archivo
            if (!file.type.match('image.*')) {
                showAlert('error', `El archivo "${file.name}" no es una imagen válida.`);
                continue;
            }

            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showAlert('error', `El archivo "${file.name}" supera el tamaño máximo de 5MB.`);
                continue;
            }

            addFileToTable(file);
        }

        updateFileCount();
    }

    // Agregar archivo a la tabla
    function addFileToTable(file) {
        const fileId = 'file-' + (++fileCounter);

        // Obtener el nombre del archivo sin extensión
        const fileNameWithoutExt = file.name.replace(/\.[^/.]+$/, '');

        selectedFiles.push({
            id: fileId,
            file: file,
            defaultName: fileNameWithoutExt
        });

        const reader = new FileReader();
        reader.onload = function(e) {
            const row = document.createElement('tr');
            row.id = fileId;
            row.innerHTML = `
                <td>
                    <img src="${e.target.result}" class="file-preview-img" alt="Preview">
                </td>
                <td>
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </td>
                <td>
                    <input type="text"
                           class="form-input sku-input"
                           data-file-id="${fileId}"
                           placeholder="Dejar vacío para usar: ${fileNameWithoutExt}">
                </td>
                <td>
                    <button type="button"
                            class="remove-file-btn"
                            onclick="removeFile('${fileId}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            filesTbody.appendChild(row);
        };
        reader.readAsDataURL(file);

        filesTableContainer.style.display = 'block';
    }

    // Subir todas las imágenes
    uploadAllBtn.addEventListener('click', async function() {
        if (selectedFiles.length === 0) {
            showAlert('error', 'No hay archivos para subir.');
            return;
        }

        // Deshabilitar botón
        uploadAllBtn.disabled = true;
        uploadAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';

        // Mostrar barra de progreso
        const progressContainer = document.getElementById('progress-container');
        const progressFill = document.getElementById('progress-fill');
        const resultsContainer = document.getElementById('results-container');
        const resultsList = document.getElementById('results-list');

        progressContainer.style.display = 'block';
        resultsContainer.style.display = 'block';
        resultsList.innerHTML = '';

        let completed = 0;
        let successful = 0;
        let failed = 0;

        // Subir archivos uno por uno
        for (let fileData of selectedFiles) {
            const skuInput = document.querySelector(`.sku-input[data-file-id="${fileData.id}"]`);
            // Si el campo está vacío, usar el nombre del archivo sin extensión
            const sku = skuInput.value.trim() || fileData.defaultName;

            try {
                // Comprimir la imagen antes de subirla
                const compressedFile = await compressImage(fileData.file);

                const formData = new FormData();
                formData.append('imagen', compressedFile);
                formData.append('itemid', sku);
                formData.append('csrf_token', document.getElementById('csrf-token').value);

                const response = await fetch('../../Logica/subir_imagen.php', {
                    method: 'POST',
                    body: formData
                });

                // IMPORTANTE: Leer el body UNA SOLA VEZ como texto
                const responseText = await response.text();

                // Log para debugging
                if (!responseText) {
                    console.warn('Respuesta vacía del servidor para:', sku);
                }

                // Intentar parsear como JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    // No es JSON válido
                    console.error('Error al parsear JSON para:', sku);
                    console.error('Respuesta recibida:', responseText.substring(0, 500));

                    failed++;
                    resultsList.innerHTML += `
                        <div class="result-item result-error">
                            <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 1.25rem;"></i>
                            <span><strong>${sku}</strong> - Respuesta inválida del servidor</span>
                        </div>
                    `;

                    completed++;
                    const progress = Math.round((completed / selectedFiles.length) * 100);
                    progressFill.style.width = progress + '%';
                    progressFill.textContent = progress + '%';
                    continue;
                }

                // Verificar el resultado
                if (data.success) {
                    successful++;
                    resultsList.innerHTML += `
                        <div class="result-item result-success">
                            <i class="fas fa-check-circle" style="color: #10b981; font-size: 1.25rem;"></i>
                            <span><strong>${sku}.jpg</strong> - ${data.message || 'Imagen subida correctamente'}</span>
                        </div>
                    `;
                } else {
                    failed++;
                    const errorMsg = data.message || 'Error desconocido';
                    resultsList.innerHTML += `
                        <div class="result-item result-error">
                            <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 1.25rem;"></i>
                            <span><strong>${sku}</strong> - ${errorMsg}</span>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error inesperado al subir:', sku, error);
                failed++;

                let errorMessage = error.message;
                // Detectar si el error es de compresión
                if (error.message.includes('comprimir') || error.message.includes('cargar la imagen')) {
                    errorMessage = `Error al procesar imagen: ${error.message}`;
                } else {
                    errorMessage = `Error de red: ${error.message}`;
                }

                resultsList.innerHTML += `
                    <div class="result-item result-error">
                        <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 1.25rem;"></i>
                        <span><strong>${sku}</strong> - ${errorMessage}</span>
                    </div>
                `;
            }

            // Actualizar progreso
            completed++;
            const progress = Math.round((completed / selectedFiles.length) * 100);
            progressFill.style.width = progress + '%';
            progressFill.textContent = progress + '%';
        }

        // Mostrar resumen final
        uploadAllBtn.disabled = false;
        uploadAllBtn.innerHTML = '<i class="fas fa-upload"></i> Subir Más Imágenes';

        if (successful > 0) {
            showAlert('success', `¡Listo! ${successful} imágenes subidas exitosamente. ${failed > 0 ? `${failed} fallaron.` : ''}`);
        }

        if (successful === selectedFiles.length) {
            // Limpiar todo después de 3 segundos
            setTimeout(() => {
                resetForm();
            }, 3000);
        }
    });
});

// Remover archivo de la lista
function removeFile(fileId) {
    selectedFiles = selectedFiles.filter(f => f.id !== fileId);
    document.getElementById(fileId).remove();
    updateFileCount();

    if (selectedFiles.length === 0) {
        document.getElementById('files-table-container').style.display = 'none';
        document.getElementById('progress-container').style.display = 'none';
        document.getElementById('results-container').style.display = 'none';
    }
}

// Actualizar contador de archivos
function updateFileCount() {
    document.getElementById('total-files').textContent = selectedFiles.length;
}

// Formatear tamaño de archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Resetear formulario
function resetForm() {
    selectedFiles = [];
    fileCounter = 0;
    document.getElementById('files-tbody').innerHTML = '';
    document.getElementById('imagenes').value = '';
    document.getElementById('files-table-container').style.display = 'none';
    document.getElementById('progress-container').style.display = 'none';
    document.getElementById('results-container').style.display = 'none';
    updateFileCount();
}

// Mostrar alertas
function showAlert(type, message) {
    const alert = document.getElementById('alert-' + type);
    alert.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    alert.style.display = 'block';

    setTimeout(() => {
        alert.style.display = 'none';
    }, 5000);
}

// ==========================================
// BUSCAR Y ELIMINAR IMÁGENES
// ==========================================
let currentSearchSku = '';
const AZURE_CONTAINER_URL = 'https://catalogodeimagenes.blob.core.windows.net/imagenes-productos/';

document.getElementById('btnBuscar')?.addEventListener('click', buscarImagen);
document.getElementById('buscarSku')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') buscarImagen();
});
document.getElementById('btnEliminar')?.addEventListener('click', eliminarImagen);

function buscarImagen() {
    const sku = document.getElementById('buscarSku').value.trim();
    if (!sku) {
        showAlert('error', 'Por favor ingrese un SKU para buscar.');
        return;
    }

    currentSearchSku = sku;
    const imageName = sku + '.jpg';
    const imageUrl = AZURE_CONTAINER_URL + imageName + '?t=' + Date.now(); // Cache buster

    // Mostrar contenedor de resultados
    document.getElementById('resultadoBusqueda').style.display = 'block';
    document.getElementById('imagenEncontrada').style.display = 'none';
    document.getElementById('imagenNoEncontrada').style.display = 'none';

    // Intentar cargar la imagen
    const img = new Image();
    img.onload = function() {
        // Imagen encontrada
        document.getElementById('imagenEncontrada').style.display = 'flex';
        document.getElementById('previewBusqueda').src = imageUrl;
        document.getElementById('nombreImagenBusqueda').textContent = imageName;
        document.getElementById('linkDescargar').href = AZURE_CONTAINER_URL + imageName;
    };
    img.onerror = function() {
        // Imagen no encontrada
        document.getElementById('imagenNoEncontrada').style.display = 'block';
    };
    img.src = imageUrl;
}

async function eliminarImagen() {
    if (!currentSearchSku) return;

    const imageName = currentSearchSku + '.jpg';
    
    if (!confirm(`¿Estás seguro de eliminar la imagen "${imageName}"?\n\nEsta acción no se puede deshacer.`)) {
        return;
    }

    const btn = document.getElementById('btnEliminar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

    try {
        const formData = new FormData();
        formData.append('blob_name', imageName);
        formData.append('csrf_token', document.getElementById('csrf-token').value);

        const response = await fetch('../../Logica/eliminar_imagen.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showAlert('success', `Imagen "${imageName}" eliminada exitosamente.`);
            // Ocultar resultado
            document.getElementById('resultadoBusqueda').style.display = 'none';
            document.getElementById('buscarSku').value = '';
            currentSearchSku = '';
        } else {
            showAlert('error', 'Error al eliminar: ' + (data.message || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('error', 'Error de comunicación al eliminar la imagen.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Eliminar Imagen';
    }
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
