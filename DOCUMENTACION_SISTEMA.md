# 📚 Documentación del Sistema - MACO AppLogística
## Última actualización: 2025-12-10

---

## 🎯 PROBLEMA RESUELTO

### **Síntoma:**
"A veces al entrar a un módulo debo iniciar sesión una y otra vez"

### **Solución:**
✅ **Implementada el 2025-12-10**

---

## 🔧 CAMBIOS IMPLEMENTADOS

### **1. Configuración Centralizada de Sesiones**

**Archivo:** `conexionBD/session_config.php` (NUEVO)

**Características:**
- ✅ Timeout: 30 minutos (antes: 3 minutos)
- ✅ Regeneración de ID: cada 15 minutos (antes: cada carga)
- ✅ Encabezados de seguridad HTTP
- ✅ Funciones helper: `verificarAutenticacion()`, `generarTokenCSRF()`, `validarTokenCSRF()`

---

### **2. Seguridad de Credenciales**

**Archivo:** `.env` (NUEVO)

Las credenciales de la base de datos ahora están protegidas en un archivo `.env` que **NO se sube al repositorio**.

**Archivo:** `conexionBD/conexion.php` (ACTUALIZADO)

```php
// Antes: $password = "contraseña en texto plano";
// Ahora:  $password = getenv('DB_PASSWORD');
```

---

### **3. Archivos View/ Actualizados**

Todos los archivos principales ahora usan la configuración centralizada:

**Patrón estándar:**
```php
<?php
// Incluir configuración centralizada de sesión
require_once __DIR__ . '/../conexionBD/session_config.php';

// Verificar autenticación y permisos
verificarAutenticacion([0, 2, 3, 5]); // IDs de pantalla permitidas

// Incluir conexión a BD (si es necesario)
require_once __DIR__ . '/../conexionBD/conexion.php';
```

**Archivos actualizados:**
- ✅ `View/facturas.php` - De 48 líneas a 3 líneas
- ✅ `View/Inicio.php` - De 18 líneas a 3 líneas
- ✅ `View/dashboard.php` - De 35 líneas a 10 líneas
- ✅ `View/CXC.php` - De 22 líneas a 4 líneas

---

## 📊 COMPARACIÓN

| Aspecto | ANTES | DESPUÉS |
|---------|-------|---------|
| Timeout sesión | 3 minutos | 30 minutos |
| session_regenerate_id() | Cada carga | Cada 15 min |
| Login repetido | Sí | NO |
| Múltiples tabs | No funcionan | Funcionan |
| Credenciales BD | Texto plano | Archivo .env |
| Código por archivo | ~30 líneas | 3-10 líneas |

---

## 🧪 CÓMO PROBAR

### **Test 1: Sesión NO expira rápidamente**
1. Iniciar sesión
2. Navegar a facturas.php
3. Esperar 10 minutos sin interactuar
4. Hacer clic en un filtro
5. ✅ **Esperado:** Sesión sigue activa

### **Test 2: Múltiples tabs funcionan**
1. Abrir facturas.php en Tab 1
2. Abrir dashboard.php en Tab 2
3. Alternar entre tabs
4. ✅ **Esperado:** Ambos tabs funcionan sin relogueo

### **Test 3: AJAX funciona correctamente**
1. Entrar a facturas.php
2. Esperar 3 minutos (auto-refresh)
3. ✅ **Esperado:** No redirige a login

---

## 📂 ESTRUCTURA DEL PROYECTO

```
MACO.AppLogistica.Web-1/
├── .env                          ← Credenciales (NO subir a Git)
├── .gitignore                    ← Incluye .env
├── conexionBD/
│   ├── conexion.php              ← Lee de .env
│   └── session_config.php        ← Configuración centralizada
├── View/
│   ├── facturas.php              ← Actualizado
│   ├── Inicio.php                ← Actualizado
│   ├── dashboard.php             ← Actualizado
│   ├── CXC.php                   ← Actualizado
│   └── [otros archivos...]
├── Logica/
├── database/
├── assets/
├── IMG/
└── DOCUMENTACION_SISTEMA.md      ← Este archivo
```

---

## 🔐 SEGURIDAD

### **Encabezados HTTP configurados:**
- `X-Frame-Options: DENY` - Previene clickjacking
- `X-Content-Type-Options: nosniff` - Previene MIME sniffing
- `X-XSS-Protection: 1; mode=block` - Protección XSS
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Cache-Control: no-cache, no-store` - Sin cache en páginas autenticadas

### **Protección CSRF:**
Función helper disponible en todos los módulos:
```php
$csrfToken = generarTokenCSRF();  // Genera token
validarTokenCSRF($token);         // Valida token
```

---

## 📍 MAPEO DE PANTALLAS

| ID | Nombre | Archivo | Descripción |
|----|--------|---------|-------------|
| 0 | Admin | Admin.php | Administrador general |
| 1 | Gestión | Inicio_gestion.php | Gestión de operaciones |
| 2 | Facturas | facturas.php | Recepción de facturas |
| 3 | CXC | CXC.php | Cuentas por cobrar |
| 4 | Reportes | Reporte.php | Reportes |
| 5 | Panel Admin | Paneladmin.php | Panel administrativo |
| 6 | BI | BI.php | Business Intelligence |
| 8 | Etiquetas | Listo-etiquetas.php | Gestión de etiquetas |
| 9 | Dashboard | dashboard.php | Dashboard analítico |

---

## ⚠️ IMPORTANTE

### **Archivo .env**
El archivo `.env` contiene credenciales sensibles y **NUNCA debe subirse al repositorio Git**.

**Verificar antes de commit:**
```bash
git status
# .env NO debe aparecer en la lista
```

### **Si algo falla:**

1. **Cerrar todas las tabs del navegador**
2. **Limpiar cookies:** Ctrl + Shift + Del
3. **Abrir nueva ventana** e iniciar sesión
4. **Revisar logs:** `C:\xampp\php\logs\php_error_log`

---

## 🔄 SINCRONIZACIÓN DE FACTURAS

El procedimiento `SyncCustinvoicejour` se ejecuta automáticamente **al iniciar sesión**.

**Ubicación:** `index.php` líneas 61-69

```php
// Sincronizar facturas al iniciar sesión
$sqlSync = "{CALL SyncCustinvoicejour}";
$stmtSync = sqlsrv_query($conn, $sqlSync);
if ($stmtSync === false) {
    error_log("Error al sincronizar facturas en login: " . print_r(sqlsrv_errors(), true));
    // Continuar con el login aunque falle la sincronización
} else {
    sqlsrv_free_stmt($stmtSync);
}
```

---

## 📝 ARCHIVOS ELIMINADOS

Los siguientes archivos fueron eliminados por ser innecesarios:

### **Carpeta completa:**
- `config/` - 8 archivos no utilizados

### **Archivos de sincronización:**
- `tools/instalar_sync_automatico.bat`
- `tools/monitor_sync.bat`
- `tools/README_SYNC.md`
- `tools/sync_facturas_cron.php`
- `database/04_sync_scheduler_azure.sql`
- `database/REVERTIR_SYNC_BD.sql`

### **Archivos de documentación obsoleta:**
- `ANALISIS_SEGURIDAD_SESIONES.md`
- `REVERTIR_CAMBIOS_SYNC.md`
- `SOLUCION_BD_FACTURAS.md`
- `SYNC_EN_LOGIN.md`
- `ARCHIVOS_ELIMINADOS.md`
- `DIFERENCIAS_BI_DASHBOARD.md`
- `INSTALACION_SYNC.md`
- `ANALISIS_COMPLETO_SEGURIDAD.md`
- `RESUMEN_EJECUTIVO.md`

**Total eliminado:** ~150 KB de archivos innecesarios

---

## 🆘 SOPORTE

### **Problemas comunes:**

**1. "Sesión expirada constantemente"**
- Limpiar cookies del navegador
- Cerrar todas las tabs
- Volver a iniciar sesión

**2. "Error de conexión a BD"**
- Verificar que `.env` existe
- Verificar credenciales en `.env`
- Revisar `C:\xampp\php\logs\php_error_log`

**3. "Archivo .env no encontrado"**
- Verificar ubicación: `C:\xampp\htdocs\MACO.AppLogistica.Web-1\.env`
- Verificar que contiene las variables correctas

---

## ✅ ESTADO ACTUAL

**Versión:** 1.0
**Fecha:** 2025-12-10
**Estado:** ✅ Funcionando correctamente

**Problemas resueltos:**
- ✅ Login repetido constantemente
- ✅ Sesiones expiran muy rápido (3 min → 30 min)
- ✅ Múltiples tabs no funcionan
- ✅ AJAX rompe la sesión
- ✅ Credenciales expuestas en código

**Mejoras implementadas:**
- ✅ Configuración centralizada
- ✅ Seguridad mejorada
- ✅ Código más limpio y mantenible
- ✅ Encabezados de seguridad HTTP
- ✅ Protección CSRF disponible

---

**Desarrollado para:** MACO Logística
**Última revisión:** 2025-12-10
