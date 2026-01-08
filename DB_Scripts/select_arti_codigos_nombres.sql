-- ========================================
-- SELECT de nombres de Arti_codigos
-- ========================================

-- Opción 1: Ver todos los nombres (únicos)
SELECT DISTINCT nombre
FROM Arti_codigos
ORDER BY nombre;

-- Opción 2: Ver todos los nombres con conteo de cuántas veces aparece cada uno
SELECT nombre, COUNT(*) AS total
FROM Arti_codigos
GROUP BY nombre
ORDER BY nombre;

-- Opción 3: Ver todos los registros completos
SELECT *
FROM Arti_codigos
ORDER BY nombre;

-- Opción 4: Solo nombres que no sean NULL o vacíos
SELECT DISTINCT nombre
FROM Arti_codigos
WHERE nombre IS NOT NULL 
  AND LTRIM(RTRIM(nombre)) <> ''
ORDER BY nombre;

-- Opción 5: Exportar a formato para usar en código (separado por comas)
SELECT STRING_AGG(QUOTENAME(nombre, ''''), ', ') AS nombres_lista
FROM (
    SELECT DISTINCT nombre
    FROM Arti_codigos
    WHERE nombre IS NOT NULL 
      AND LTRIM(RTRIM(nombre)) <> ''
) AS nombres_unicos;
