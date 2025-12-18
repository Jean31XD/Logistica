-- =====================================================
-- Script para añadir columna dashboard_almacen a usuarios
-- Ejecutar en la base de datos del proyecto MACO
-- =====================================================

-- Añadir columna para almacén del dashboard
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('usuarios') AND name = 'dashboard_almacen')
BEGIN
    ALTER TABLE usuarios ADD dashboard_almacen VARCHAR(50) NULL;
    PRINT 'Columna dashboard_almacen añadida a tabla usuarios.';
END
ELSE
BEGIN
    PRINT 'La columna dashboard_almacen ya existe.';
END
GO

-- Comentario: 
-- NULL o '' = Usuario puede ver TODOS los almacenes (admin)
-- Valor específico = Usuario solo puede ver ese almacén
