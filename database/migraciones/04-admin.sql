-- Ejecutar una vez en la base existente. No es necesario en instalaciones nuevas.
ALTER TABLE vendedores MODIFY rol ENUM('usuario','supremo','admin') NOT NULL DEFAULT 'usuario';
UPDATE vendedores SET rol='admin' WHERE rol='supremo';
UPDATE vendedores SET nombre='Administrador' WHERE nombre='Administrador supremo';
ALTER TABLE vendedores MODIFY rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario';

-- Renombrar el usuario anterior solo si no existe ya una cuenta llamada admin.
UPDATE vendedores anterior
LEFT JOIN vendedores existente ON existente.usuario='admin'
SET anterior.usuario='admin'
WHERE anterior.usuario='supremo' AND existente.id IS NULL;

-- Un hash no puede convertirse de vuelta a la contraseña original.
-- Para la tarea, asignar contraseñas simples solo a las cuentas con hashes.
UPDATE vendedores
SET clave=CASE WHEN rol='admin' THEN 'admin123' ELSE 'usuario123' END
WHERE LEFT(clave,4) IN ('$2y$','$2a$','$2b$') OR LEFT(clave,7)='$argon2';
