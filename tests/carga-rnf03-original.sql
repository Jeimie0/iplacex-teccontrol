-- Parte 1 de la prueba de volumen del SQL original.
-- Ejecutar sobre las 20 solicitudes de ejemplo. El llamador debe terminar con ROLLBACK.
USE teccontrol;
SET NAMES utf8mb4;
START TRANSACTION;

SET @rol_cliente = (SELECT id_rol FROM roles WHERE nombre = 'Cliente');
SET @tecnico = (SELECT id_usuario FROM usuarios WHERE correo = 'admin@teccontrol.example');
SET @hash_demo = (SELECT password_hash FROM usuarios WHERE correo = 'cliente-a@teccontrol.example');
SET @hoy = CURDATE();

INSERT INTO usuarios (id_rol, nombre, correo, password_hash)
WITH RECURSIVE numeros (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM numeros WHERE n < 98)
SELECT @rol_cliente, CONCAT('Cliente de volumen ', n),
       CONCAT('volumen-', LPAD(n, 3, '0'), '@teccontrol.example'), @hash_demo
FROM numeros;

INSERT INTO equipos (id_usuario, tipo, marca, modelo)
SELECT id_usuario, 'Notebook', 'Carga ficticia', 'Escenario 500'
FROM usuarios WHERE correo LIKE 'volumen-%@teccontrol.example';

INSERT INTO solicitudes (id_equipo, descripcion_falla, fecha_ingreso, id_urgencia, fecha_comprometida)
WITH RECURSIVE numeros (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM numeros WHERE n < 480)
SELECT e.id_equipo, CONCAT('Solicitud ficticia de volumen ', numeros.n),
       DATE_SUB(TIMESTAMP(@hoy), INTERVAL MOD(numeros.n, 22) DAY),
       CASE WHEN MOD(numeros.n, 4) = 0 THEN NULL ELSE MOD(numeros.n, 4) END,
       CASE WHEN MOD(numeros.n, 7) = 0 THEN NULL ELSE DATE_ADD(@hoy, INTERVAL (MOD(numeros.n, 14) - 4) DAY) END
FROM numeros
JOIN usuarios AS u ON u.correo = CONCAT('volumen-', LPAD(MOD(numeros.n - 1, 98) + 1, 3, '0'), '@teccontrol.example')
JOIN equipos AS e ON e.id_usuario = u.id_usuario;

INSERT INTO historial_estados (id_solicitud, id_estado, id_usuario, fecha_cambio)
SELECT s.id_solicitud, estado.id_estado,
       CASE WHEN estado.orden_visual = 1 THEN e.id_usuario ELSE @tecnico END,
       DATE_ADD(s.fecha_ingreso, INTERVAL estado.orden_visual MINUTE)
FROM solicitudes AS s
JOIN equipos AS e ON e.id_equipo = s.id_equipo
JOIN usuarios AS u ON u.id_usuario = e.id_usuario
JOIN estados_reparacion AS estado ON estado.orden_visual <= 1 + MOD(s.id_solicitud, 5)
WHERE u.correo LIKE 'volumen-%@teccontrol.example'
ORDER BY s.id_solicitud, estado.orden_visual;

SELECT
    (SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE r.nombre = 'Cliente') AS clientes,
    (SELECT COUNT(*) FROM solicitudes) AS solicitudes;
