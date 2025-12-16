-- =====================================================
-- Script para crear usuario con acceso a Gestión de Imágenes
-- MACO Logística - Pantalla 13
-- =====================================================

-- EJEMPLO: Crear un usuario con acceso solo a Gestión de Imágenes
-- NOTA: La contraseña debe estar hasheada con password_hash() en PHP

-- OPCIÓN 1: Crear usuario desde SQL Server
-- (Luego deberás hashear la contraseña desde PHP)
INSERT INTO usuarios (usuario, password, pantalla)
VALUES ('usuario_imagenes', 'PASSWORD_HASH_AQUI', 13);

-- =====================================================
-- Para hashear la contraseña, usa el siguiente script PHP:
-- =====================================================
/*
<?php
// crear_hash.php
$password = 'tu_contraseña_aqui';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash: " . $hash;
?>
*/

-- =====================================================
-- OPCIÓN 2: Actualizar usuario existente para darle acceso a Gestión de Imágenes
-- =====================================================
-- UPDATE usuarios SET pantalla = 13 WHERE usuario = 'nombre_usuario';

-- =====================================================
-- NIVELES DE ACCESO DISPONIBLES:
-- =====================================================
-- 0  = Administrador (acceso total)
-- 1  = Gestión
-- 2  = Facturas
-- 3  = CXC
-- 4  = Reportes
-- 5  = Panel Admin
-- 6  = BI
-- 8  = Etiquetas
-- 9  = Dashboard
-- 10 = Inventario Listo
-- 11 = Códigos de Barras
-- 12 = Códigos de Referencia
-- 13 = Gestión de Imágenes (NUEVO)

-- =====================================================
-- VERIFICAR USUARIOS EXISTENTES
-- =====================================================
-- SELECT usuario, pantalla FROM usuarios ORDER BY pantalla;
