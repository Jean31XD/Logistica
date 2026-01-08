-- ========================================
-- RECREAR TABLA Arti_codigos COMPLETA
-- ========================================
-- Ejecutar este script en tu Azure SQL Database
-- ========================================

-- 1. Primero hacer backup si tienes datos importantes
-- SELECT * INTO Arti_codigos_backup FROM Arti_codigos;

-- 2. Eliminar tabla existente
IF OBJECT_ID('dbo.Arti_codigos', 'U') IS NOT NULL
    DROP TABLE [dbo].[Arti_codigos];
GO

-- 3. Crear tabla con estructura COMPLETA
CREATE TABLE [dbo].[Arti_codigos] (
    id VARCHAR(50) PRIMARY KEY,           -- ID formato 'MC-001', 'MC-002', etc.
    nombre VARCHAR(255) NOT NULL,          -- Nombre del artículo  
    Codigo_barra VARCHAR(50) NULL,         -- Código de barras escaneado
    Usuario VARCHAR(100) NULL              -- Usuario que escaneó el código
);
GO

-- 4. Crear índices para optimizar búsquedas
CREATE INDEX IX_Arti_codigos_nombre ON [dbo].[Arti_codigos](nombre);
CREATE INDEX IX_Arti_codigos_codigo ON [dbo].[Arti_codigos](Codigo_barra);
GO

-- 5. Insertar datos de prueba (primeros 10 artículos)
INSERT INTO [dbo].[Arti_codigos] (id, nombre) VALUES
('MC-001', 'ACEITE 20 W 50 MINERAL SHELL'),
('MC-002', 'ACEITE 5 W 30 SINTETICO SHELL'),
('MC-003', 'ACEITE CASTROL EDGE 5W30'),
('MC-004', 'ACEITE DE MOTOR'),
('MC-005', 'ACEITE HIDRAULICO'),
('MC-006', 'ACEITE MINERAL 20W50'),
('MC-007', 'ACEITE MOBIL 1 5W30'),
('MC-008', 'ACEITE SINTETICO 5W30'),
('MC-009', 'ACEITE TRANSMISION'),
('MC-010', 'ACUMULADOR');
GO

-- 6. Verificar que se creó correctamente
SELECT 'Tabla creada exitosamente' AS Resultado, COUNT(*) AS TotalRegistros
FROM [dbo].[Arti_codigos];
GO

-- 7. Mostrar estructura
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'Arti_codigos'
ORDER BY ORDINAL_POSITION;
GO
