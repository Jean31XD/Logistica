-- ========================================
-- CREAR TABLA Arti_codigos con VARCHAR ID
-- ========================================
-- La tabla usa VARCHAR(20) como llave primaria para soportar IDs como 'MC-001'

-- 1. Eliminar tabla existente si es necesario
-- DROP TABLE IF EXISTS Arti_codigos;

-- 2. Crear tabla con ID VARCHAR
CREATE TABLE Arti_codigos (
    id VARCHAR(20) PRIMARY KEY,  -- IDs como 'MC-001', 'MC-002', etc.
    nombre VARCHAR(255),
    Codigo_barra VARCHAR(50) NULL,
    Usuario VARCHAR(100) NULL
);

-- 3. Crear índices para optimizar búsquedas
CREATE INDEX IX_Arti_codigos_nombre ON Arti_codigos(nombre);
CREATE INDEX IX_Arti_codigos_codigo ON Arti_codigos(Codigo_barra);

PRINT 'Tabla Arti_codigos creada exitosamente con ID VARCHAR(20)';
GO
