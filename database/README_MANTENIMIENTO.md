# 📊 Guía de Mantenimiento de Base de Datos
## MACO AppLogística

---

## 📋 Tabla de Contenidos

1. [Introducción](#introducción)
2. [Scripts Disponibles](#scripts-disponibles)
3. [Instalación Inicial](#instalación-inicial)
4. [Mantenimiento Rutinario](#mantenimiento-rutinario)
5. [Procedimientos Almacenados](#procedimientos-almacenados)
6. [Programación Automática](#programación-automática)
7. [Monitoreo y Diagnóstico](#monitoreo-y-diagnóstico)
8. [Solución de Problemas](#solución-de-problemas)

---

## 🎯 Introducción

Esta guía proporciona instrucciones detalladas para mantener optimizada la base de datos de MACO AppLogística. El mantenimiento regular mejorará significativamente el rendimiento del sistema.

### Beneficios del Mantenimiento Regular:
- ⚡ **Consultas más rápidas** (30-50% mejora en promedio)
- 💾 **Menor uso de espacio en disco**
- 🔒 **Mayor integridad de datos**
- 📈 **Mejor escalabilidad**
- 🚀 **Experiencia de usuario optimizada**

---

## 📁 Scripts Disponibles

### 1. `01_create_indexes.sql`
**Propósito:** Crea índices optimizados en todas las tablas principales.

**Tablas afectadas:**
- `usuarios` - Login y gestión de usuarios
- `custinvoicejour` - Facturas principales
- `log` - Tickets de despacho
- `codigos_acceso` - Acceso al dashboard
- `Facturas_CTE` - Programación de despacho
- `Facturas_lineas` - Líneas de factura

**Cuándo ejecutar:**
- ✅ Primera vez después de instalación
- ✅ Después de importaciones masivas de datos
- ✅ Si las consultas son muy lentas

### 2. `02_maintenance_routine.sql`
**Propósito:** Script de mantenimiento completo con 6 pasos.

**Acciones realizadas:**
1. Actualizar estadísticas
2. Reorganizar/reconstruir índices fragmentados
3. Limpiar datos obsoletos
4. Verificar integridad
5. Optimizar archivos de log
6. Reporte de espacio en disco

**Cuándo ejecutar:**
- ✅ Semanalmente (recomendado: domingos a las 2 AM)
- ✅ Mensualmente como mínimo

### 3. `03_stored_procedures.sql`
**Propósito:** Crea procedimientos almacenados reutilizables.

**Procedimientos incluidos:**
- `sp_LimpiezaAutomatica` - Limpia datos obsoletos
- `sp_OptimizarIndices` - Optimiza índices fragmentados
- `sp_ActualizarEstadisticas` - Actualiza estadísticas de tablas
- `sp_MantenimientoCompleto` - Ejecuta todo el mantenimiento

**Cuándo ejecutar:**
- ✅ Una sola vez para crear los procedimientos

---

## 🚀 Instalación Inicial

### Paso 1: Crear Índices (OBLIGATORIO)

```sql
-- Ejecutar en SQL Server Management Studio o Azure Data Studio
-- Conectarse a: sdb-apptransportistas-maco.privatelink.database.windows.net
-- Base de datos: db-apptransportistas-maco

-- Método 1: Desde archivo
:r C:\xampp\htdocs\MACO.AppLogistica.Web-1\database\01_create_indexes.sql

-- Método 2: Copiar y pegar contenido completo del archivo
```

**Tiempo estimado:** 2-5 minutos
**Requisitos:** Permisos de `db_ddladmin` o superior

### Paso 2: Crear Procedimientos Almacenados (OBLIGATORIO)

```sql
-- Ejecutar procedimientos almacenados
:r C:\xampp\htdocs\MACO.AppLogistica.Web-1\database\03_stored_procedures.sql
```

**Tiempo estimado:** 1 minuto
**Requisitos:** Permisos de `db_owner` o superior

### Paso 3: Verificar Instalación

```sql
-- Verificar índices creados
SELECT
    OBJECT_NAME(object_id) AS Tabla,
    name AS Indice,
    type_desc AS Tipo
FROM sys.indexes
WHERE OBJECT_NAME(object_id) IN (
    'usuarios', 'custinvoicejour', 'log',
    'Facturas_CTE', 'Facturas_lineas', 'codigos_acceso'
)
ORDER BY Tabla, name;

-- Verificar procedimientos almacenados
SELECT
    name AS Procedimiento,
    create_date AS Fecha_Creacion
FROM sys.procedures
WHERE name LIKE 'sp_%'
ORDER BY name;
```

---

## 🔧 Mantenimiento Rutinario

### Opción A: Mantenimiento Completo Automático (RECOMENDADO)

```sql
-- Ejecutar procedimiento completo con información detallada
EXEC sp_MantenimientoCompleto @ModoDebug = 1;
```

**Duración:** 5-15 minutos
**Frecuencia:** Semanal (domingos 2 AM recomendado)

### Opción B: Mantenimiento Manual con Script

```sql
-- Ejecutar script completo
:r C:\xampp\htdocs\MACO.AppLogistica.Web-1\database\02_maintenance_routine.sql
```

**Duración:** 10-20 minutos
**Frecuencia:** Semanal

### Opción C: Mantenimiento Selectivo

```sql
-- Solo limpiar datos obsoletos (rápido)
EXEC sp_LimpiezaAutomatica
    @DiasTickets = 180,      -- Mantener tickets 6 meses
    @DiasLogsAcceso = 90,    -- Mantener logs 3 meses
    @ModoDebug = 1;

-- Solo optimizar índices (medio)
EXEC sp_OptimizarIndices
    @UmbralFragmentacion = 10.0,    -- Reorganizar desde 10%
    @UmbralReconstruccion = 30.0,   -- Reconstruir desde 30%
    @ModoDebug = 1;

-- Solo actualizar estadísticas (rápido)
EXEC sp_ActualizarEstadisticas @ModoDebug = 1;
```

---

## 🤖 Procedimientos Almacenados

### 1. sp_MantenimientoCompleto

**Descripción:** Ejecuta todo el ciclo de mantenimiento.

**Sintaxis:**
```sql
EXEC sp_MantenimientoCompleto @ModoDebug = 1;
```

**Parámetros:**
- `@ModoDebug` (BIT): 1 = Mostrar detalles, 0 = Solo errores

**Ejemplo de uso:**
```sql
-- Con información detallada (recomendado para ejecución manual)
EXEC sp_MantenimientoCompleto @ModoDebug = 1;

-- Sin información detallada (recomendado para jobs automáticos)
EXEC sp_MantenimientoCompleto @ModoDebug = 0;
```

### 2. sp_LimpiezaAutomatica

**Descripción:** Elimina datos obsoletos y desactiva códigos expirados.

**Sintaxis:**
```sql
EXEC sp_LimpiezaAutomatica
    @DiasTickets = 180,
    @DiasLogsAcceso = 90,
    @ModoDebug = 1;
```

**Parámetros:**
- `@DiasTickets` (INT): Días para mantener tickets despachados (default: 180)
- `@DiasLogsAcceso` (INT): Días para mantener logs de acceso (default: 90)
- `@ModoDebug` (BIT): 1 = Mostrar detalles

**Acciones realizadas:**
- ❌ Elimina tickets con estado "Despachado" o "Se fue" > X días
- ❌ Desactiva códigos de acceso expirados
- ❌ Elimina logs de acceso > X días

**Ejemplo de uso:**
```sql
-- Limpiar tickets de más de 1 año
EXEC sp_LimpiezaAutomatica
    @DiasTickets = 365,
    @DiasLogsAcceso = 90,
    @ModoDebug = 1;
```

### 3. sp_OptimizarIndices

**Descripción:** Reorganiza/reconstruye índices fragmentados.

**Sintaxis:**
```sql
EXEC sp_OptimizarIndices
    @UmbralFragmentacion = 10.0,
    @UmbralReconstruccion = 30.0,
    @PaginasMinimas = 1000,
    @ModoDebug = 1;
```

**Parámetros:**
- `@UmbralFragmentacion` (FLOAT): % mínimo para reorganizar (default: 10.0)
- `@UmbralReconstruccion` (FLOAT): % mínimo para reconstruir (default: 30.0)
- `@PaginasMinimas` (INT): Páginas mínimas del índice (default: 1000)
- `@ModoDebug` (BIT): 1 = Mostrar detalles

**Lógica de optimización:**
- 📊 5-30% fragmentación → **REORGANIZE** (rápido, online)
- 🔨 >30% fragmentación → **REBUILD** (completo, offline)

**Ejemplo de uso:**
```sql
-- Optimización agresiva (umbrales más bajos)
EXEC sp_OptimizarIndices
    @UmbralFragmentacion = 5.0,
    @UmbralReconstruccion = 20.0,
    @ModoDebug = 1;
```

### 4. sp_ActualizarEstadisticas

**Descripción:** Actualiza estadísticas de todas las tablas principales.

**Sintaxis:**
```sql
EXEC sp_ActualizarEstadisticas @ModoDebug = 1;
```

**Parámetros:**
- `@ModoDebug` (BIT): 1 = Mostrar detalles

**Ejemplo de uso:**
```sql
-- Actualizar todas las estadísticas
EXEC sp_ActualizarEstadisticas @ModoDebug = 1;
```

---

## ⏰ Programación Automática

### Opción 1: SQL Server Agent Job (Recomendado para SQL Server On-Premise)

```sql
-- Crear Job de mantenimiento semanal
USE msdb;
GO

EXEC dbo.sp_add_job
    @job_name = N'MACO - Mantenimiento Semanal';

EXEC sp_add_jobstep
    @job_name = N'MACO - Mantenimiento Semanal',
    @step_name = N'Ejecutar Mantenimiento',
    @subsystem = N'TSQL',
    @database_name = N'db-apptransportistas-maco',
    @command = N'EXEC sp_MantenimientoCompleto @ModoDebug = 0;';

EXEC dbo.sp_add_schedule
    @schedule_name = N'Domingos 2AM',
    @freq_type = 8,        -- Semanal
    @freq_interval = 1,    -- Domingo
    @active_start_time = 020000; -- 2:00 AM

EXEC sp_attach_schedule
    @job_name = N'MACO - Mantenimiento Semanal',
    @schedule_name = N'Domingos 2AM';

EXEC dbo.sp_add_jobserver
    @job_name = N'MACO - Mantenimiento Semanal';
GO
```

### Opción 2: Azure SQL Database Elastic Jobs

Para Azure SQL Database, usar **Elastic Jobs** o **Azure Automation**.

Documentación: https://docs.microsoft.com/azure/azure-sql/database/elastic-jobs-overview

### Opción 3: Tarea Programada de Windows + SQLCMD

```batch
@echo off
REM Archivo: mantenimiento_semanal.bat
REM Guardar en: C:\MACO\Scripts\

sqlcmd -S sdb-apptransportistas-maco.privatelink.database.windows.net -d db-apptransportistas-maco -U ServiceAppTrans -P "contraseña" -Q "EXEC sp_MantenimientoCompleto @ModoDebug = 0;" -o C:\MACO\Logs\mantenimiento_%date:~-4,4%%date:~-10,2%%date:~-7,2%.log

echo Mantenimiento completado a las %time% >> C:\MACO\Logs\historial.txt
```

**Programar en Windows:**
```
Programador de Tareas → Crear Tarea Básica
Nombre: MACO Mantenimiento DB
Desencadenador: Semanal, Domingo 2:00 AM
Acción: Iniciar programa → C:\MACO\Scripts\mantenimiento_semanal.bat
```

---

## 📊 Monitoreo y Diagnóstico

### Verificar Fragmentación de Índices

```sql
SELECT
    OBJECT_NAME(ips.object_id) AS Tabla,
    i.name AS Indice,
    ips.avg_fragmentation_in_percent AS Fragmentacion_Porcentaje,
    ips.page_count AS Paginas,
    CASE
        WHEN ips.avg_fragmentation_in_percent >= 30 THEN '🔴 REBUILD necesario'
        WHEN ips.avg_fragmentation_in_percent >= 10 THEN '🟡 REORGANIZE recomendado'
        ELSE '🟢 Óptimo'
    END AS Estado
FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips
INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
WHERE OBJECT_NAME(ips.object_id) IN (
    'usuarios', 'custinvoicejour', 'log',
    'Facturas_CTE', 'Facturas_lineas', 'codigos_acceso'
)
    AND i.name IS NOT NULL
ORDER BY ips.avg_fragmentation_in_percent DESC;
```

### Verificar Uso de Espacio

```sql
SELECT
    t.name AS Tabla,
    SUM(p.rows) AS Total_Filas,
    SUM(a.total_pages) * 8 / 1024 AS Espacio_Total_MB,
    SUM(a.used_pages) * 8 / 1024 AS Espacio_Usado_MB,
    (SUM(a.total_pages) - SUM(a.used_pages)) * 8 / 1024 AS Espacio_Libre_MB
FROM sys.tables t
INNER JOIN sys.indexes i ON t.object_id = i.object_id
INNER JOIN sys.partitions p ON i.object_id = p.object_id AND i.index_id = p.index_id
INNER JOIN sys.allocation_units a ON p.partition_id = a.container_id
WHERE t.name IN (
    'usuarios', 'custinvoicejour', 'log',
    'Facturas_CTE', 'Facturas_lineas', 'codigos_acceso'
)
GROUP BY t.name
ORDER BY SUM(a.total_pages) DESC;
```

### Verificar Estadísticas Desactualizadas

```sql
SELECT
    OBJECT_NAME(s.object_id) AS Tabla,
    s.name AS Estadistica,
    sp.last_updated AS Ultima_Actualizacion,
    sp.rows AS Filas,
    sp.modification_counter AS Modificaciones,
    CASE
        WHEN DATEDIFF(DAY, sp.last_updated, GETDATE()) > 7 THEN '🔴 Actualizar'
        WHEN DATEDIFF(DAY, sp.last_updated, GETDATE()) > 3 THEN '🟡 Revisar'
        ELSE '🟢 OK'
    END AS Estado
FROM sys.stats s
CROSS APPLY sys.dm_db_stats_properties(s.object_id, s.stats_id) sp
WHERE OBJECT_NAME(s.object_id) IN (
    'usuarios', 'custinvoicejour', 'log',
    'Facturas_CTE', 'Facturas_lineas', 'codigos_acceso'
)
ORDER BY sp.last_updated;
```

### Top 10 Consultas Más Lentas

```sql
SELECT TOP 10
    SUBSTRING(qt.text, (qs.statement_start_offset/2)+1,
        ((CASE qs.statement_end_offset
            WHEN -1 THEN DATALENGTH(qt.text)
            ELSE qs.statement_end_offset
        END - qs.statement_start_offset)/2)+1) AS Consulta,
    qs.execution_count AS Ejecuciones,
    qs.total_elapsed_time / 1000000 AS Tiempo_Total_Seg,
    qs.total_elapsed_time / qs.execution_count / 1000 AS Promedio_MS,
    qs.creation_time AS Creada,
    qs.last_execution_time AS Ultima_Ejecucion
FROM sys.dm_exec_query_stats qs
CROSS APPLY sys.dm_exec_sql_text(qs.sql_handle) qt
WHERE qt.text LIKE '%custinvoicejour%'
   OR qt.text LIKE '%log%'
   OR qt.text LIKE '%Facturas_CTE%'
ORDER BY qs.total_elapsed_time DESC;
```

---

## 🆘 Solución de Problemas

### Problema: "El mantenimiento tarda demasiado"

**Solución:**
```sql
-- Ejecutar solo limpieza y estadísticas (más rápido)
EXEC sp_LimpiezaAutomatica @ModoDebug = 1;
EXEC sp_ActualizarEstadisticas @ModoDebug = 1;

-- Optimizar índices en otro momento
EXEC sp_OptimizarIndices @ModoDebug = 1;
```

### Problema: "Error de permisos al ejecutar scripts"

**Solución:**
```sql
-- Verificar permisos del usuario actual
SELECT
    dp.name AS Usuario,
    dp.type_desc AS Tipo,
    drm.role_principal_id,
    roles.name AS Rol
FROM sys.database_principals dp
LEFT JOIN sys.database_role_members drm ON dp.principal_id = drm.member_principal_id
LEFT JOIN sys.database_principals roles ON drm.role_principal_id = roles.principal_id
WHERE dp.name = USER_NAME();

-- Contactar al DBA para otorgar permisos necesarios:
-- - db_ddladmin (para crear índices)
-- - db_owner (para procedimientos almacenados)
```

### Problema: "Base de datos muy lenta después de mantenimiento"

**Solución:**
```sql
-- Limpiar cache de planes de ejecución
DBCC FREEPROCCACHE;

-- Limpiar cache de datos
DBCC DROPCLEANBUFFERS;

-- Actualizar todas las estadísticas nuevamente
EXEC sp_ActualizarEstadisticas @ModoDebug = 1;
```

### Problema: "Índice no se puede reconstruir (error de espacio)"

**Solución:**
```sql
-- Reorganizar en lugar de reconstruir (usa menos espacio)
EXEC sp_OptimizarIndices
    @UmbralReconstruccion = 100.0,  -- Nunca reconstruir
    @ModoDebug = 1;
```

---

## 📅 Calendario de Mantenimiento Recomendado

| Tarea | Frecuencia | Día/Hora | Procedimiento |
|-------|-----------|----------|---------------|
| **Mantenimiento Completo** | Semanal | Domingo 2:00 AM | `EXEC sp_MantenimientoCompleto @ModoDebug = 0;` |
| **Limpieza de Datos** | Diaria | Lunes-Sábado 3:00 AM | `EXEC sp_LimpiezaAutomatica @ModoDebug = 0;` |
| **Actualizar Estadísticas** | 2x semana | Miércoles/Sábado 1:00 AM | `EXEC sp_ActualizarEstadisticas @ModoDebug = 0;` |
| **Verificación de Integridad** | Mensual | Primer domingo 4:00 AM | `DBCC CHECKDB WITH NO_INFOMSGS;` |
| **Revisión de Fragmentación** | Mensual | Manual | Ver query de monitoreo |

---

## 📝 Notas Importantes

1. **Backups**: Siempre hacer backup ANTES de mantenimiento mayor
2. **Horarios**: Ejecutar durante horas de bajo tráfico (madrugada)
3. **Monitoreo**: Revisar logs después de cada mantenimiento
4. **Escalamiento**: Si la BD crece mucho, considerar particionamiento
5. **Azure**: Para Azure SQL, usar Elastic Jobs en lugar de SQL Agent

---

## 📞 Soporte

Para preguntas o problemas:
- Revisar logs de ejecución
- Consultar sección de Solución de Problemas
- Contactar al equipo de TI/DBA de MACO

---

**Última actualización:** 2025-12-09
**Versión:** 1.0
**Autor:** Sistema MACO AppLogística
