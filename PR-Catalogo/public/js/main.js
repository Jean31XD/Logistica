// /public/js/main.js
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('search-form');
    const filterForm = document.getElementById('filter-form');
    const mainContent = document.getElementById('main-content');
    
    const categoriaSelect = document.getElementById('categoria');
    const marcaSelect = document.getElementById('marca');
    
    // --- LÓGICA DE LA GALERÍA (LIGHTBOX) ---
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxInfo = document.getElementById('lightbox-info'); // Panel de info
    const lightboxPrev = document.getElementById('lightbox-prev');
    const lightboxNext = document.getElementById('lightbox-next');
    
    let currentImageIndex = 0;
    let currentImageList = []; // Almacena los '.image-container'
    let currentInfoList = [];  // Almacena los '.product-info'

    // Variables para Swipe/Drag
    let touchStartX = 0;
    let touchEndX = 0;
    let isDragging = false;
    const minSwipeDistance = 50; // Mínimo 50px para considerarse swipe
    // --- FIN LÓGICA DE LA GALERÍA ---

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
            
            // --- MODIFICACIÓN IMPORTANTE ---
            // Manejar sesión expirada (401 Unauthorized)
            if (response.status === 401) {
                // La sesión expiró. Forzamos un reload de la página.
                // El reload será interceptado por el index.php y redirigido a login.php
                window.location.reload();
                return; 
            }
            // --- FIN MODIFICACIÓN ---

            if (!response.ok) {
                const errorText = await response.text(); 
                throw new Error(errorText || `Error ${response.status}: ${response.statusText}`);
            }

            const html = await response.text();
            
            history.pushState({page: page}, '', cleanUrl);
            mainContent.innerHTML = html;

        } catch (error) {
            console.error('Fetch error:', error);
            mainContent.innerHTML = `<div class="message-box"><h3>Error al cargar productos</h3><div>${error.message}</div></div>`;
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

            // --- MODIFICACIÓN IMPORTANTE ---
            // Manejar sesión expirada (401 Unauthorized)
            if (response.status === 401) {
                window.location.reload();
                return;
            }
            // --- FIN MODIFICACIÓN ---

            if (!response.ok) {
                let errorJson = {};
                try {
                    errorJson = await response.json();
                } catch(e) { /* No es JSON, usa el texto de estado */ }
                throw new Error(errorJson.error || `Error ${response.status}: ${response.statusText}`);
            }
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
            console.error('Stats fetch error:', error);
            statsContent.innerHTML = `<h3>Error al cargar resumen</h3><p>${error.message}</p>`;
        } finally {
            statsBox.classList.remove('loading');
        }
    }

    // --- EVENT LISTENERS ---
    
    searchForm.addEventListener('input', debounce(() => updateContent(1)));
    
    filterForm.addEventListener('change', () => {
        updateContent(1);
        updateStats();
    });

    // --- INICIO LÓGICA LIGHTBOX Y PAGINACIÓN ---
            
    function showImage(index) {
        if (!currentImageList.length) return;

        // Comportamiento circular
        if (index < 0) {
            index = currentImageList.length - 1;
        } else if (index >= currentImageList.length) {
            index = 0;
        }
        
        currentImageIndex = index;
        
        // 1. Actualizar Imagen
        const imageContainer = currentImageList[index];
        if (imageContainer) {
            lightboxImg.src = imageContainer.dataset.fullSrc || imageContainer.querySelector('img')?.src || '';
        }
        
        // 2. Actualizar Información
        const infoContainer = currentInfoList[index];
        if (infoContainer) {
            lightboxInfo.innerHTML = infoContainer.innerHTML;
        } else {
            lightboxInfo.innerHTML = ''; // Limpiar si no hay info
        }
    }

    mainContent.addEventListener('click', function(e) {
        
        // 1. Lógica de Paginación (CON SCROLL AL INICIO)
        const paginationLink = e.target.closest('.pagination a:not(.disabled)');
        if (paginationLink) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            const url = new URL(paginationLink.href);
            const page = url.searchParams.get('page') || 1;
            updateContent(page);
        }
        
        // 2. Lógica de Apertura de Lightbox (Galería)
        const imageContainer = e.target.closest('.image-container');
        if (imageContainer) {
            e.preventDefault(); 

            const allCards = Array.from(mainContent.querySelectorAll('.product-card'));
            
            currentImageList = [];
            currentInfoList = [];

            allCards.forEach(card => {
                const img = card.querySelector('.image-container');
                const info = card.querySelector('.product-info');
                
                if (img && info) {
                    currentImageList.push(img);
                    currentInfoList.push(info);
                }
            });
            
            currentImageIndex = currentImageList.indexOf(imageContainer);
            showImage(currentImageIndex);
            
            if (currentImageList.length > 1) {
                lightboxPrev.classList.remove('hidden');
                lightboxNext.classList.remove('hidden');
            } else {
                lightboxPrev.classList.add('hidden');
                lightboxNext.classList.add('hidden');
            }
            
            lightbox.classList.add('active');
        }
    });

    // --- Listeners para Swipe/Drag ---

    function handleGesture() {
        if (touchEndX === 0 || touchStartX === 0) return; 
        
        const distance = touchEndX - touchStartX;
        
        if (distance > minSwipeDistance) {
            showImage(currentImageIndex - 1);
        } else if (distance < -minSwipeDistance) {
            showImage(currentImageIndex + 1);
        }
        
        touchStartX = 0;
        touchEndX = 0;
    }

    lightboxImg.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    lightboxImg.addEventListener('touchmove', (e) => {
        touchEndX = e.changedTouches[0].screenX;
    }, { passive: true });

    lightboxImg.addEventListener('touchend', () => {
        handleGesture();
    });

    lightboxImg.addEventListener('mousedown', (e) => {
        e.preventDefault(); 
        isDragging = true;
        touchStartX = e.screenX;
        lightboxImg.style.cursor = 'grabbing'; 
    });

    lightbox.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        touchEndX = e.screenX;
    });

    lightbox.addEventListener('mouseup', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        isDragging = false;
        handleGesture();
        lightboxImg.style.cursor = 'grab'; 
    });

    lightbox.addEventListener('mouseleave', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        isDragging = false;
        handleGesture();
        lightboxImg.style.cursor = 'grab'; 
    });
    
    // --- FIN LÓGICA SWIPE ---

    // Listeners para botones de flechas
    lightboxPrev.addEventListener('click', (e) => {
        e.stopPropagation();
        showImage(currentImageIndex - 1);
    });

    lightboxNext.addEventListener('click', (e) => {
        e.stopPropagation();
        showImage(currentImageIndex + 1);
    });

    // Listener para CERRAR el lightbox
    lightbox.addEventListener('click', function(e) {
        if (e.target.id === 'lightbox' || e.target.classList.contains('close')) {
            this.classList.remove('active');
            lightboxPrev.classList.add('hidden');
            lightboxNext.classList.add('hidden');
        }
    });
    // --- FIN LÓGICA LIGHTBOX ---

    window.addEventListener('popstate', () => {
        location.reload(); 
    });
});