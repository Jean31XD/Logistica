-- Script de Diagnóstico BI.php
-- Ejecutar en SQL Server Management Studio

-- 1. Ver cuántas facturas hay HOY
SELECT COUNT(*) AS TotalHoy
FROM custinvoicejour
WHERE Fecha = CAST(GETDATE() AS DATE);

-- 2. Ver facturas del usuario 'Jean' HOY
SELECT COUNT(*) AS TotalJean
FROM custinvoicejour
WHERE Fecha = CAST(GETDATE() AS DATE)
AND Usuario = 'Jean';

-- 3. Ver si hay facturas hoy (todas)
SELECT TOP 10
    Factura, Fecha, Usuario, Transportista, Validar
FROM custinvoicejour
WHERE Fecha = CAST(GETDATE() AS DATE)
ORDER BY Fecha DESC;

-- 4. Ver usuarios que tienen facturas hoy
SELECT DISTINCT Usuario, COUNT(*) AS Total
FROM custinvoicejour
WHERE Fecha = CAST(GETDATE() AS DATE)
GROUP BY Usuario
ORDER BY Total DESC;

-- 5. Probar la consulta exacta que usa BI.php
DECLARE @desde DATE = CAST(GETDATE() AS DATE);
DECLARE @hasta DATE = CAST(GETDATE() AS DATE);
DECLARE @usuario VARCHAR(50) = 'Jean';

SELECT
    COUNT(*) as TotalFacturas,
    SUM(CASE WHEN Validar = 'Completada' THEN 1 ELSE 0 END) AS Completadas,
    SUM(CASE WHEN Validar = 'RE' THEN 1 ELSE 0 END) AS RE,
    SUM(CASE WHEN Validar IS NULL OR LTRIM(RTRIM(Validar)) = '' THEN 1 ELSE 0 END) AS SinEstado,
    SUM(CASE WHEN Usuario_de_recepcion IS NULL OR LTRIM(RTRIM(Usuario_de_recepcion)) = '' THEN 1 ELSE 0 END) AS PendientesCxC
FROM (
    SELECT DISTINCT c.Factura, c.Validar, c.Usuario_de_recepcion
    FROM custinvoicejour c
    LEFT JOIN (SELECT DISTINCT invoiceid, inventlocationid FROM Facturas_lineas) fl ON c.Factura = fl.invoiceid
    WHERE c.Fecha BETWEEN @desde AND @hasta 
    AND c.Transportista NOT LIKE '%Contado%'
    AND c.Usuario = @usuario
) AS FacturasUnicas;
