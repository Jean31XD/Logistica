// /public/js/main.js
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('search-form');
    const filterForm = document.getElementById('filter-form');
    const mainContent = document.getElementById('main-content');
    
    const categoriaSelect = document.getElementById('categoria');
    const marcaSelect = document.getElementById('marca');
    
    const initialGlobalStatsHTML = document.getElementById('stats-content').innerHTML;

    function debounce(func, delay = 350) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    async function updateContent(page = 1) {
        mainContent.classList.add('loading');
        
        const formData = new FormData(filterForm);
        formData.append('q', new FormData(searchForm).get('q'));
        formData.append('page', page);
        
        const params = new URLSearchParams(formData);
        
        const productUrl = `index.php?action=get_products&${params.toString()}`;
        const cleanUrl = `index.php?${new URLSearchParams(Object.fromEntries(Array.from(params.entries()).filter(([key]) => key !== 'action'))).toString()}`;

        try {
            const response = await fetch(productUrl);
            
            // --- MANEJO DE FALLOS MEJORADO ---
            if (!response.ok) {
                // Si el servidor envía un error (4xx, 5xx), captúralo
                // El endpoint de productos devuelve HTML, así que leemos el texto
                const errorText = await response.text(); 
                // Lanza un error para que lo capture el 'catch'
                throw new Error(errorText || `Error ${response.status}: ${response.statusText}`);
            }
            // --- FIN MANEJO DE FALLOS ---

            const html = await response.text();
            
            history.pushState({page: page}, '', cleanUrl);
            mainContent.innerHTML = html;
        } catch (error) {
            // --- MANEJO DE FALLOS MEJORADO ---
            console.error('Fetch error:', error);
            // Muestra el error. Si es un error de servidor, error.message contendrá el HTML/texto
            // Asumimos que el error puede ser HTML (como el div.message-box de get_products.php)
            mainContent.innerHTML = `<div class="message-box"><h3>Error al cargar productos</h3><div>${error.message}</div></div>`;
            // --- FIN MANEJO DE FALLOS ---
        } finally {
            mainContent.classList.remove('loading');
        }
    }
    
    async function updateStats() {
        const selectedCategory = categoriaSelect.value;
        const selectedMarca = marcaSelect.value;
        
        const statsBox = document.getElementById('stats-box');
        const statsContent = document.getElementById('stats-content');

        if (!selectedCategory && !selectedMarca) {
            statsContent.innerHTML = initialGlobalStatsHTML;
            return;
        }
        
        statsBox.classList.add('loading');
        
        const params = new URLSearchParams({
            action: 'get_stats',
            categoria: selectedCategory,
            marca: selectedMarca
        });
        const url = `index.php?${params.toString()}`;
        
        try {
            const response = await fetch(url);
            
            // --- MANEJO DE FALLOS MEJORADO ---
            if (!response.ok) {
                // Si el servidor envía un error (4xx, 5xx), intenta leer el JSON de error
                let errorJson = {};
                try {
                    // El endpoint de stats devuelve JSON
                    errorJson = await response.json();
                } catch(e) { /* No es JSON, usa el texto de estado */ }
                // Lanza un error para que lo capture el 'catch'
                throw new Error(errorJson.error || `Error ${response.status}: ${response.statusText}`);
            }
            // --- FIN MANEJO DE FALLOS ---
            
            const data = await response.json();
            
            const titleParts = [];
            if (selectedCategory) titleParts.push(`"${selectedCategory}"`);
            if (selectedMarca) titleParts.push(`"${selectedMarca}"`);
            const newTitle = `Resumen de ${titleParts.join(' / ')}`;
            
            statsContent.innerHTML = `
                <h3>${newTitle}</h3>
                <p><span>Artículos Totales:</span> <strong>${(data.total_items || 0).toLocaleString()}</strong></p>
                <p><span>Con Imagen:</span> <strong>${(data.with_image || 0).toLocaleString()}</strong></p>
                <p><span>Sin Imagen:</span> <strong>${(data.without_image || 0).toLocaleString()}</strong></p>
            `;

        } catch (error) {
            // --- MANEJO DE FALLOS MEJORADO ---
            console.error('Stats fetch error:', error);
            statsContent.innerHTML = `<h3>Error al cargar resumen</h3><p>${error.message}</p>`;
            // --- FIN MANEJO DE FALLOS ---
        } finally {
            statsBox.classList.remove('loading');
        }
    }

    // --- EVENT LISTENERS ---
    
    searchForm.addEventListener('input', debounce(() => updateContent(1)));
    
    // Sobre "El boton no me modifica todas las categorias":
    // Este listener YA llama a updateContent Y a updateStats cuando cambia un filtro.
    // Si los productos no se actualizan, el problema debe estar en el backend
    // (p.ej. la función fetch_product_data que no está usando los filtros).
    // El frontend (este JS) está haciendo lo correcto.
    filterForm.addEventListener('change', () => {
        updateContent(1);
        updateStats();
    });

    mainContent.addEventListener('click', function(e) {
        const paginationLink = e.target.closest('.pagination a:not(.disabled)');
        if (paginationLink) {
            e.preventDefault();
            const url = new URL(paginationLink.href);
            const page = url.searchParams.get('page') || 1;
            updateContent(page);
        }
        
        const imageContainer = e.target.closest('.image-container');
        if (imageContainer) {
            document.getElementById('lightbox-img').src = imageContainer.dataset.fullSrc;
            document.getElementById('lightbox').classList.add('active');
        }
    });

    document.getElementById('lightbox').addEventListener('click', function(e) {
        if (e.target.id === 'lightbox' || e.target.classList.contains('close')) {
            this.classList.remove('active');
        }
    });

    window.addEventListener('popstate', () => {
        location.reload(); 
    });
});