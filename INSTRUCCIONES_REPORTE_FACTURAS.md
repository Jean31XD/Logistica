# Instrucciones de Instalación - Módulo Reporte de Facturas

## 📋 Resumen

Se ha creado el nuevo módulo **Reporte de Facturas** basado en la tabla `custinvoicejour` con el mismo diseño y funcionalidad que el módulo BI.php.

## 📁 Archivos Creados/Modificados

### Archivos Creados:
1. `View/modulos/ReporteFacturas.php` - Módulo principal del reporte
2. `database/setup_reporte_facturas.php` - Script de instalación y permisos
3. `INSTRUCCIONES_REPORTE_FACTURAS.md` - Este archivo

### Archivos Modificados:
1. `config/app.php` - Agregado módulo 'reporte_facturas' a la configuración
2. `View/pantallas/Portal.php` - Agregado link e ícono del módulo

## 🚀 Pasos de Instalación

### Paso 1: Ejecutar el Script de Configuración

1. Abre tu navegador web
2. Navega a la siguiente URL:
   ```
   http://localhost/MACO.AppLogistica.Web-1/database/setup_reporte_facturas.php
   ```
3. El script automáticamente:
   - Registrará el módulo 'reporte_facturas' en la tabla `usuario_modulos`
   - Asignará permisos a todos los usuarios administradores (pantalla = 0)
   - Mostrará un resumen de los permisos asignados

### Paso 2: Verificar el Módulo

1. Cierra sesión e inicia sesión nuevamente en el sistema
2. Ve al Portal de Módulos: `View/pantallas/Portal.php`
3. Deberías ver la nueva tarjeta "Reporte de Facturas" con:
   - Ícono: 📄 (fa-file-invoice-dollar)
   - Descripción: "Análisis completo de facturas registradas en el sistema..."
   - Tag: REPORTES

### Paso 3: Acceder al Módulo

1. Haz clic en el botón "Acceder" de la tarjeta "Reporte de Facturas"
2. Serás redirigido a: `View/modulos/ReporteFacturas.php`
3. El módulo cargará automáticamente los datos de la tabla `custinvoicejour`

## ✨ Características del Módulo

El nuevo módulo incluye:

- **Dashboard con KPIs en tiempo real:**
  - Total de facturas
  - Completadas
  - Pendientes
  - Pendientes CxC

- **Filtros avanzados:**
  - Rango de fechas (Desde - Hasta)
  - Búsqueda por número de factura
  - Estado (Completada, RE, Sin Estado)
  - Transportista (Select2 con búsqueda)
  - Usuario ALM
  - Zona
  - Almacén
  - Prefijo (NC/FT)
  - Recibido CxC (Sí/No)

- **Tabla paginada:**
  - Muestra 50 registros por página
  - Columnas: Factura, Fecha, Estado, Transportista, Usuario ALM, Usuario CC, Almacén, Zona
  - Click en factura para ver detalles

- **Modal de detalles:**
  - Información completa de la factura seleccionada
  - Totales calculados (Subtotal, Impuesto, Total)

## 🔧 Configuración Adicional

### Asignar Permisos a Usuarios No-Admin

Si necesitas asignar el módulo a usuarios que no son administradores:

1. Ve a: `View/modulos/Gestion_de_usuario.php?tab=permisos`
2. Selecciona el usuario al que quieres asignar permisos
3. Activa el checkbox "Reporte de Facturas"
4. Guarda los cambios

### Archivos Backend Compartidos

El módulo utiliza los mismos archivos de procesamiento que BI.php:

- `Logica/procesar_filtros_ajax.php` - Procesa filtros y devuelve datos en JSON
- `Logica/api_factura_detalle.php` - Devuelve detalles de una factura específica

Estos archivos ya existen y están optimizados para el rendimiento.

## 📊 Tabla de Base de Datos

El módulo consulta principalmente la tabla `custinvoicejour` con los siguientes campos:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| Factura | VARCHAR | Número de factura |
| Fecha | DATETIME | Fecha de la factura |
| Validar | VARCHAR | Estado (Completada, RE, etc.) |
| Transportista | VARCHAR | Nombre del transportista |
| Usuario | VARCHAR | Usuario de almacén |
| Usuario_de_recepcion | VARCHAR | Usuario de CxC |
| Fecha_scanner | DATETIME | Fecha de recepción en almacén |
| recepcion | DATETIME | Fecha de recepción en CxC |
| zona | VARCHAR | Zona o almacén |

## 🎨 Diseño Visual

El módulo utiliza el mismo diseño moderno que BI.php:

- **Colores:**
  - Primario: #E63946 (Rojo MACO)
  - Secundario: #1D3557 (Azul Oscuro)
  - Acento: #457B9D (Azul Claro)
- **Fuentes:** Inter (Google Fonts)
- **Framework:** Bootstrap 5.3.3
- **Iconos:** Font Awesome 6.5.2
- **Componentes:** Select2 para selectores

## 🔒 Seguridad y Permisos

- **Verificación de sesión:** Requiere login activo
- **Control de permisos:** Solo usuarios con el módulo 'reporte_facturas' asignado
- **Rate limiting:** Protección contra sobrecarga (50 req/10 seg)
- **Timeout SQL:** 15 segundos máximo por query

## 📝 Notas Importantes

1. **No reemplaza BI.php:** El módulo original `BI.php` se mantiene intacto y funcional
2. **Mismo backend:** Ambos módulos comparten la lógica de procesamiento
3. **Independiente:** El nuevo módulo funciona de manera completamente independiente
4. **Permisos separados:** Usa el identificador 'reporte_facturas' (diferente de 'business_intelligence')

## 🐛 Solución de Problemas

### El módulo no aparece en el Portal
- Verifica que ejecutaste el script `setup_reporte_facturas.php`
- Cierra sesión y vuelve a iniciar sesión
- Verifica en la tabla `usuario_modulos` que el registro existe

### Error "No autorizado - permisos"
- Ejecuta el script de setup nuevamente
- Verifica que el usuario tenga el módulo asignado en la base de datos

### La tabla no carga datos
- Verifica la conexión a la base de datos
- Revisa el log de errores del navegador (F12 > Console)
- Verifica que la tabla `custinvoicejour` exista y tenga datos

## ✅ Checklist de Instalación

- [ ] Ejecutar `database/setup_reporte_facturas.php`
- [ ] Verificar que aparece en el Portal
- [ ] Probar acceso al módulo
- [ ] Verificar que los filtros funcionan
- [ ] Probar la paginación
- [ ] Verificar el modal de detalles
- [ ] (Opcional) Asignar permisos a usuarios adicionales

## 📞 Soporte

Si encuentras algún problema durante la instalación o uso del módulo, verifica:

1. Los logs de error de PHP
2. La consola del navegador (F12)
3. La conexión a la base de datos SQL Server
4. Que todos los archivos se hayan creado correctamente

---

**Creado:** 2026-01-13
**Versión:** 1.0.0
**Autor:** Claude Code
