-- Tabla para Logs de Seguridad
-- Ejecutar este script en la base de datos para crear la tabla de auditoría

-- Verificar si la tabla ya existe y eliminarla (comentar esta línea si no quieres eliminar datos existentes)
-- DROP TABLE IF EXISTS security_logs;

-- Crear tabla de logs de seguridad
CREATE TABLE security_logs (
    id INT IDENTITY(1,1) PRIMARY KEY,
    evento_tipo VARCHAR(50) NOT NULL,
    usuario VARCHAR(50) NULL,
    ip_address VARCHAR(45) NOT NULL,
    detalles NVARCHAR(MAX) NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),

    -- Índices para mejorar el rendimiento de consultas
    INDEX idx_fecha_hora (fecha_hora DESC),
    INDEX idx_evento_tipo (evento_tipo),
    INDEX idx_usuario (usuario),
    INDEX idx_ip_address (ip_address)
);

-- Comentarios en la tabla
EXEC sp_addextendedproperty
    @name = N'MS_Description',
    @value = N'Tabla de auditoría de seguridad - registra todos los eventos importantes del sistema',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'security_logs';

-- Comentarios en las columnas
EXEC sp_addextendedproperty
    @name = N'MS_Description',
    @value = N'Tipo de evento: login_exitoso, login_fallido, acceso_denegado, csrf_invalido, etc.',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'security_logs',
    @level2type = N'COLUMN', @level2name = N'evento_tipo';

-- Consultas útiles para monitorear seguridad:

-- Ver intentos de login fallidos recientes
-- SELECT TOP 50 * FROM security_logs WHERE evento_tipo = 'login_fallido' ORDER BY fecha_hora DESC;

-- Ver accesos por IP
-- SELECT ip_address, COUNT(*) as total, MAX(fecha_hora) as ultimo_acceso
-- FROM security_logs
-- GROUP BY ip_address
-- ORDER BY total DESC;

-- Ver eventos de los últimos 7 días
-- SELECT evento_tipo, COUNT(*) as total
-- FROM security_logs
-- WHERE fecha_hora >= DATEADD(day, -7, GETDATE())
-- GROUP BY evento_tipo
-- ORDER BY total DESC;

-- Procedimiento almacenado para limpiar logs antiguos (ejecutar mensualmente)
CREATE PROCEDURE sp_limpiar_logs_antiguos
    @dias_antiguedad INT = 90
AS
BEGIN
    DELETE FROM security_logs
    WHERE fecha_hora < DATEADD(day, -@dias_antiguedad, GETDATE());

    SELECT @@ROWCOUNT as filas_eliminadas;
END;
GO

-- Para ejecutar el procedimiento de limpieza:
-- EXEC sp_limpiar_logs_antiguos @dias_antiguedad = 90;
