# MACO Design System - Guía de Diseño

## 🎨 Filosofía de Diseño

El sistema de diseño MACO está enfocado en crear una experiencia profesional, limpia y consistente. Se eliminaron todos los gradientes y efectos innecesarios para lograr un diseño corporativo moderno.

## 📐 Principios de Diseño

### 1. **Simplicidad**
- Diseños limpios sin decoraciones innecesarias
- Colores sólidos en lugar de gradientes
- Espaciado consistente y generoso

### 2. **Consistencia**
- Usar variables CSS en lugar de valores directos
- Aplicar el mismo patrón de diseño en todas las pantallas
- Utilizar las clases del design system

### 3. **Accesibilidad**
- Contraste adecuado entre texto y fondo
- Tamaños de fuente legibles (mínimo 0.85rem)
- Estados claros para elementos interactivos

## 🎨 Paleta de Colores

### Colores Principales
```css
--primary: #E63946        /* Rojo MACO */
--primary-light: #F25C66
--primary-dark: #D62839
```

### Colores de Acento
```css
--accent: #457B9D         /* Azul corporativo */
--accent-light: #5FA3C7
--accent-dark: #1D3557    /* Azul oscuro para headers */
```

### Grises
```css
--gray-50: #F9FAFB       /* Fondos alternativos */
--gray-100: #F3F4F6      /* Fondo secundario */
--gray-200: #E5E7EB      /* Bordes */
--gray-500: #6B7280      /* Texto secundario */
--gray-900: #111827      /* Texto principal */
```

### Estados
```css
--success: #10B981       /* Verde éxito */
--warning: #F59E0B       /* Amarillo advertencia */
--danger: #EF4444        /* Rojo error */
--info: #3B82F6          /* Azul información */
```

## 📦 Componentes

### Headers
```html
<!-- Usar siempre el template -->
<?php
$pageTitle = "Título de la Página | MACO";
$containerClass = "maco-container"; // o "maco-container-fluid"
include 'templates/header.php';
?>
```

### Cards
```html
<div class="maco-card">
    <div class="maco-card-header">
        <h2 class="maco-card-title">Título</h2>
        <p class="maco-card-description">Descripción opcional</p>
    </div>
    <!-- Contenido -->
</div>
```

### Botones
```html
<!-- Primario -->
<button class="maco-btn maco-btn-primary">
    <i class="fas fa-plus"></i> Agregar
</button>

<!-- Secundario -->
<button class="maco-btn maco-btn-secondary">Cancelar</button>

<!-- Éxito -->
<button class="maco-btn maco-btn-success">Guardar</button>

<!-- Peligro -->
<button class="maco-btn maco-btn-danger">Eliminar</button>

<!-- Outline -->
<button class="maco-btn maco-btn-outline">Ver más</button>
```

### Tablas
```html
<div class="maco-table-container">
    <table class="maco-table">
        <thead>
            <tr>
                <th>Columna 1</th>
                <th>Columna 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Dato 1</td>
                <td>Dato 2</td>
            </tr>
        </tbody>
    </table>
</div>
```

**Características de las tablas:**
- Headers con fondo `--accent-dark` (#1D3557)
- Filas pares con fondo `--gray-50`
- Hover sutil en filas
- Bordes limpios y consistentes

### Formularios
```html
<div class="maco-form-group">
    <label class="maco-label">Nombre del campo</label>
    <input type="text" class="maco-input" placeholder="Ingresa el valor">
</div>

<div class="maco-form-group">
    <label class="maco-label">Selecciona una opción</label>
    <select class="maco-select">
        <option>Opción 1</option>
        <option>Opción 2</option>
    </select>
</div>
```

### Badges
```html
<span class="maco-badge maco-badge-success">Completado</span>
<span class="maco-badge maco-badge-warning">Pendiente</span>
<span class="maco-badge maco-badge-danger">Error</span>
<span class="maco-badge maco-badge-info">Info</span>
```

### Alertas
```html
<div class="maco-alert maco-alert-success">
    <i class="fas fa-check-circle"></i>
    <span>Operación exitosa</span>
</div>

<div class="maco-alert maco-alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span>Ocurrió un error</span>
</div>
```

## 📏 Sistema de Espaciado

Usar variables de espaciado para consistencia:

```css
--space-1: 0.25rem   (4px)
--space-2: 0.5rem    (8px)
--space-3: 0.75rem   (12px)
--space-4: 1rem      (16px)
--space-5: 1.25rem   (20px)
--space-6: 1.5rem    (24px)
--space-8: 2rem      (32px)
--space-10: 2.5rem   (40px)
--space-12: 3rem     (48px)
```

### Clases de utilidad:
```html
<!-- Márgenes -->
<div class="maco-mt-4">Margen superior</div>
<div class="maco-mb-6">Margen inferior</div>

<!-- Padding -->
<div class="maco-p-4">Padding uniforme</div>

<!-- Gap (flex/grid) -->
<div class="maco-flex maco-gap-4">Items con separación</div>
```

## 🔤 Tipografía

### Tamaños
```css
--text-sm: 0.875rem   (14px)
--text-base: 1rem     (16px)
--text-lg: 1.125rem   (18px)
--text-xl: 1.25rem    (20px)
```

### Pesos
```css
--font-medium: 500
--font-semibold: 600
--font-bold: 700
```

### Títulos
```html
<h1 class="maco-title">Título Principal</h1>
<h2 class="maco-subtitle">Subtítulo</h2>
<p class="maco-text-muted">Texto secundario</p>
```

## 🎯 Grid System

```html
<!-- Grid de 2 columnas -->
<div class="maco-grid maco-grid-2">
    <div class="maco-card">Columna 1</div>
    <div class="maco-card">Columna 2</div>
</div>

<!-- Grid de 3 columnas -->
<div class="maco-grid maco-grid-3">
    <div class="maco-card">1</div>
    <div class="maco-card">2</div>
    <div class="maco-card">3</div>
</div>

<!-- Grid de 4 columnas -->
<div class="maco-grid maco-grid-4">
    <div class="maco-card">1</div>
    <div class="maco-card">2</div>
    <div class="maco-card">3</div>
    <div class="maco-card">4</div>
</div>
```

## ⚡ Border Radius

```css
--radius-sm: 0.375rem  (6px)
--radius: 0.5rem       (8px)
--radius-md: 0.75rem   (12px)
--radius-lg: 1rem      (16px)
--radius-xl: 1.5rem    (24px)
```

## 🎭 Sombras

```css
--shadow-sm: Sombra mínima (botones)
--shadow: Sombra estándar (cards)
--shadow-md: Sombra media (headers)
--shadow-lg: Sombra grande (hover)
--shadow-xl: Sombra extra grande (modals)
```

## ✅ Buenas Prácticas

### ✔️ **HACER**
- Usar variables CSS en lugar de colores directos
- Aplicar clases del design system
- Mantener espaciado consistente
- Usar colores sólidos (no gradientes)
- Diseños limpios y minimalistas
- Headers de tabla con `--accent-dark`
- Filas alternadas en tablas con `--gray-50`

### ❌ **NO HACER**
- No usar gradientes (diseño corporativo limpio)
- No mezclar diferentes sistemas de diseño
- No usar valores mágicos (ej: `padding: 13px`)
- No crear estilos inline complejos
- No usar colores sin variables
- No agregar animaciones innecesarias
- No usar fuentes decorativas

## 📱 Responsive Design

El sistema es mobile-first y responsive por defecto:

- Cards se apilan en móvil
- Tablas tienen scroll horizontal
- Headers se ajustan al ancho
- Espaciado se reduce en móvil

## 🔧 Estructura de Archivos

```
View/
├── templates/
│   ├── header.php      # Header unificado
│   └── footer.php      # Footer unificado
├── [pantalla].php      # Pantallas del sistema
assets/
└── css/
    └── maco-design-system.css  # Sistema de diseño central
```

## 📝 Ejemplo de Página Completa

```php
<?php
// Configuración de la página
$pageTitle = "Mi Pantalla | MACO";
$containerClass = "maco-container";

// CSS adicional si es necesario
$additionalCSS = <<<'CSS'
<style>
    .custom-class {
        /* Estilos específicos de esta pantalla */
    }
</style>
CSS;

// Incluir header
include 'templates/header.php';
?>

<!-- Contenido de la página -->
<h1 class="maco-title">Título de la Pantalla</h1>

<div class="maco-grid maco-grid-2">
    <div class="maco-card">
        <div class="maco-card-header">
            <h2 class="maco-card-title">Card 1</h2>
        </div>
        <p>Contenido del card</p>
    </div>

    <div class="maco-card">
        <div class="maco-card-header">
            <h2 class="maco-card-title">Card 2</h2>
        </div>
        <p>Contenido del card</p>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
```

## 🚀 Migración de Páginas Antiguas

Para actualizar páginas antiguas al nuevo sistema:

1. Reemplazar el header por `include 'templates/header.php'`
2. Eliminar estilos inline innecesarios
3. Reemplazar clases Bootstrap por clases MACO
4. Cambiar gradientes por colores sólidos
5. Usar variables CSS en lugar de valores directos
6. Aplicar estructura de grid consistente

## 📞 Soporte

Para dudas sobre el design system, revisar:
- Este archivo (DESIGN_GUIDE.md)
- El archivo CSS (assets/css/maco-design-system.css)
- Ejemplos en pantallas existentes (facturas.php, dashboard.php)
