-- =============================================
-- Script de Optimización: Creación de Índices
-- MACO AppLogística - Base de Datos
-- Fecha: 2025-12-09
-- =============================================

USE [db-apptransportistas-maco];
GO

PRINT '========================================';
PRINT 'INICIANDO CREACIÓN DE ÍNDICES';
PRINT '========================================';
PRINT '';

-- =============================================
-- TABLA: usuarios
-- Descripción: Login y gestión de usuarios
-- =============================================
PRINT 'Creando índices para tabla: usuarios';

-- Índice para búsqueda por usuario (login)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_usuarios_usuario' AND object_id = OBJECT_ID('usuarios'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX IX_usuarios_usuario
    ON usuarios(usuario)
    INCLUDE (password, pantalla, ventanilla);
    PRINT '✓ Creado: IX_usuarios_usuario';
END
ELSE
    PRINT '- Ya existe: IX_usuarios_usuario';

-- =============================================
-- TABLA: custinvoicejour
-- Descripción: Tabla principal de facturas
-- =============================================
PRINT '';
PRINT 'Creando índices para tabla: custinvoicejour';

-- Índice para búsqueda por factura
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_factura' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_factura
    ON custinvoicejour(Factura)
    INCLUDE (Fecha, Validar, Transportista, Fecha_scanner, Usuario, recepcion, Usuario_de_recepcion);
    PRINT '✓ Creado: IX_custinvoicejour_factura';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_factura';

-- Índice para búsqueda por transportista
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_transportista' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_transportista
    ON custinvoicejour(Transportista)
    INCLUDE (Factura, Fecha, Validar);
    PRINT '✓ Creado: IX_custinvoicejour_transportista';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_transportista';

-- Índice para búsqueda por fecha
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_fecha' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_fecha
    ON custinvoicejour(Fecha DESC)
    INCLUDE (Factura, Transportista, Validar);
    PRINT '✓ Creado: IX_custinvoicejour_fecha';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_fecha';

-- Índice para búsqueda por estado de validación
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_validar' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_validar
    ON custinvoicejour(Validar)
    INCLUDE (Factura, Fecha, Transportista);
    PRINT '✓ Creado: IX_custinvoicejour_validar';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_validar';

-- Índice para búsqueda por usuario
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_usuario' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_usuario
    ON custinvoicejour(Usuario)
    INCLUDE (Factura, Fecha, Validar);
    PRINT '✓ Creado: IX_custinvoicejour_usuario';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_usuario';

-- Índice para búsqueda por zona
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_zona' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_zona
    ON custinvoicejour(zona)
    WHERE zona IS NOT NULL;
    PRINT '✓ Creado: IX_custinvoicejour_zona';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_zona';

-- Índice compuesto para búsqueda por factura y transportista (usado en validación)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_factura_transportista' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_factura_transportista
    ON custinvoicejour(Factura, Transportista)
    INCLUDE (Validar, Fecha_scanner, Usuario);
    PRINT '✓ Creado: IX_custinvoicejour_factura_transportista';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_factura_transportista';

-- Índice para fecha_scanner (usado en filtros de recepción)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_fecha_scanner' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_fecha_scanner
    ON custinvoicejour(Fecha_scanner)
    WHERE Fecha_scanner IS NOT NULL;
    PRINT '✓ Creado: IX_custinvoicejour_fecha_scanner';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_fecha_scanner';

-- Índice para recepcion (usado en filtros de CxC)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_custinvoicejour_recepcion' AND object_id = OBJECT_ID('custinvoicejour'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_custinvoicejour_recepcion
    ON custinvoicejour(recepcion)
    WHERE recepcion IS NOT NULL;
    PRINT '✓ Creado: IX_custinvoicejour_recepcion';
END
ELSE
    PRINT '- Ya existe: IX_custinvoicejour_recepcion';

-- =============================================
-- TABLA: log
-- Descripción: Tickets de despacho
-- =============================================
PRINT '';
PRINT 'Creando índices para tabla: log';

-- Índice para búsqueda por ticket
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_log_tiket' AND object_id = OBJECT_ID('log'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX IX_log_tiket
    ON [log](Tiket)
    INCLUDE (Estatus, NombreTR, Empresa, Asignar, FechaCreacion, FechaModificacion);
    PRINT '✓ Creado: IX_log_tiket';
END
ELSE
    PRINT '- Ya existe: IX_log_tiket';

-- Índice para búsqueda por estatus (pantalla de tickets)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_log_estatus' AND object_id = OBJECT_ID('log'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_log_estatus
    ON [log](Estatus)
    INCLUDE (Tiket, NombreTR, Empresa, Asignar, FechaCreacion);
    PRINT '✓ Creado: IX_log_estatus';
END
ELSE
    PRINT '- Ya existe: IX_log_estatus';

-- Índice para búsqueda por usuario asignado
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_log_asignar' AND object_id = OBJECT_ID('log'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_log_asignar
    ON [log](Asignar)
    INCLUDE (Tiket, Estatus, FechaModificacion);
    PRINT '✓ Creado: IX_log_asignar';
END
ELSE
    PRINT '- Ya existe: IX_log_asignar';

-- Índice para ordenamiento por fecha
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_log_fechacreacion' AND object_id = OBJECT_ID('log'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_log_fechacreacion
    ON [log](FechaCreacion DESC)
    INCLUDE (Tiket, Estatus);
    PRINT '✓ Creado: IX_log_fechacreacion';
END
ELSE
    PRINT '- Ya existe: IX_log_fechacreacion';

-- =============================================
-- TABLA: codigos_acceso
-- Descripción: Códigos de acceso al dashboard
-- =============================================
PRINT '';
PRINT 'Creando índices para tabla: codigos_acceso';

-- Índice para búsqueda por código activo
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_codigos_acceso_codigo_activo' AND object_id = OBJECT_ID('codigos_acceso'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_codigos_acceso_codigo_activo
    ON codigos_acceso(codigo, activo)
    INCLUDE (ultimo_acceso, almacen, es_admin);
    PRINT '✓ Creado: IX_codigos_acceso_codigo_activo';
END
ELSE
    PRINT '- Ya existe: IX_codigos_acceso_codigo_activo';

-- =============================================
-- TABLA: Facturas_CTE
-- Descripción: Facturas y programación de despacho
-- =============================================
PRINT '';
PRINT 'Creando índices para tabla: Facturas_CTE';

-- Índice para búsqueda por invoice ID
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_CTE_invoiceid' AND object_id = OBJECT_ID('Facturas_CTE'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_CTE_invoiceid
    ON Facturas_CTE(invoiceid)
    INCLUDE (Estado, invoicedate, invoicingname);
    PRINT '✓ Creado: IX_Facturas_CTE_invoiceid';
END
ELSE
    PRINT '- Ya existe: IX_Facturas_CTE_invoiceid';

-- Índice para búsqueda por estado
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_CTE_estado' AND object_id = OBJECT_ID('Facturas_CTE'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_CTE_estado
    ON Facturas_CTE(Estado)
    INCLUDE (invoiceid, invoicedate);
    PRINT '✓ Creado: IX_Facturas_CTE_estado';
END
ELSE
    PRINT '- Ya existe: IX_Facturas_CTE_estado';

-- Índice para búsqueda por fecha
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_CTE_invoicedate' AND object_id = OBJECT_ID('Facturas_CTE'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_CTE_invoicedate
    ON Facturas_CTE(invoicedate DESC)
    INCLUDE (invoiceid, Estado);
    PRINT '✓ Creado: IX_Facturas_CTE_invoicedate';
END
ELSE
    PRINT '- Ya existe: IX_Facturas_CTE_invoicedate';

-- Índice para búsqueda por cliente (invoicingname)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_CTE_invoicingname' AND object_id = OBJECT_ID('Facturas_CTE'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_CTE_invoicingname
    ON Facturas_CTE(invoicingname)
    INCLUDE (invoiceid, Estado);
    PRINT '✓ Creado: IX_Facturas_CTE_invoicingname';
END
ELSE
    PRINT '- Ya existe: IX_Facturas_CTE_invoicingname';

-- =============================================
-- TABLA: Facturas_lineas
-- Descripción: Líneas de factura
-- =============================================
PRINT '';
PRINT 'Creando índices para tabla: Facturas_lineas';

-- Índice para búsqueda por invoice ID
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_lineas_invoiceid' AND object_id = OBJECT_ID('Facturas_lineas'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_lineas_invoiceid
    ON Facturas_lineas(invoiceid)
    INCLUDE (inventlocationid, qty, lineamount);
    PRINT '✓ Creado: IX_Facturas_lineas_invoiceid';
END
ELSE
    PRINT '- Ya existe: IX_Facturas_lineas_invoiceid';

-- Índice para búsqueda por ubicación
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Facturas_lineas_inventlocationid' AND object_id = OBJECT_ID('Facturas_lineas'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_Facturas_lineas_inventlocationid
    ON Facturas_lineas(inventlocationid)
    INCLUDE (invoiceid, qty);
    PRINT '✓ Creado: IX_Facturas_lineas_inventlocationid';
END
ELSE
    PRINT '- Ya existe: IX_Facturas_lineas_inventlocationid';

PRINT '';
PRINT '========================================';
PRINT 'ÍNDICES CREADOS EXITOSAMENTE';
PRINT '========================================';
GO
