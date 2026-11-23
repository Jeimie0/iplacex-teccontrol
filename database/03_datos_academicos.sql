-- Conjunto ficticio para el esquema original de TecControl.
-- Ejecutar una vez con teccontrol seleccionada en un entorno de pruebas.
SET NAMES utf8mb4;
START TRANSACTION;

SET @hoy = CURDATE();
SET @rol_cliente = (SELECT id_rol FROM roles WHERE nombre = 'Cliente');
SET @rol_tecnico = (SELECT id_rol FROM roles WHERE nombre = 'Administrador/Técnico');

INSERT INTO usuarios (id_rol, nombre, correo, password_hash)
VALUES (@rol_tecnico, 'Administración de prueba', 'admin@teccontrol.example', '$2y$10$2lbkUA3LiV7oRWPBq5wrDOim6iYp6koWieo23064TqHOLIjnronda');
SET @tecnico = LAST_INSERT_ID();

INSERT INTO usuarios (id_rol, nombre, correo, password_hash)
VALUES (@rol_cliente, 'Cliente de prueba A', 'cliente-a@teccontrol.example', '$2y$10$E3foCQWjzT.4ghtyA86l6uJSQRh/AmLon6DND.eNstI7CMU41P1Ke');
SET @cliente_a = LAST_INSERT_ID();

INSERT INTO usuarios (id_rol, nombre, correo, password_hash)
VALUES (@rol_cliente, 'Cliente de prueba B', 'cliente-b@teccontrol.example', '$2y$10$IMpnGhX.mCUiiYP09TOyN.kDpAQqDuuw0AgDt5VEwfbrvW6sGOWIa');
SET @cliente_b = LAST_INSERT_ID();

INSERT INTO equipos (id_usuario, tipo, marca, modelo)
VALUES (@cliente_a, 'Notebook', 'Equipo Demo', 'N14');
SET @portatil_a = LAST_INSERT_ID();

INSERT INTO equipos (id_usuario, tipo, marca, modelo)
VALUES (@cliente_a, 'Escritorio', 'Equipo Demo', 'A20');
SET @escritorio_a = LAST_INSERT_ID();

INSERT INTO equipos (id_usuario, tipo, marca, modelo)
VALUES (@cliente_b, 'Notebook', 'Equipo Demo', 'N16');
SET @portatil_b = LAST_INSERT_ID();

INSERT INTO equipos (id_usuario, tipo, marca, modelo)
VALUES (@cliente_b, 'Escritorio', 'Equipo Demo', 'T500');
SET @escritorio_b = LAST_INSERT_ID();

INSERT INTO solicitudes (id_equipo, descripcion_falla, fecha_ingreso, id_urgencia, fecha_comprometida)
WITH casos (numero, equipo, falla, espera, urgencia, dias_entrega) AS (
    SELECT 1,  @portatil_a,    'La batería pierde carga rápidamente',        0, 3, NULL
    UNION ALL SELECT 2,  @portatil_a,    'Ventilador con ruido irregular',                1, 2,  2
    UNION ALL SELECT 3,  @escritorio_a, 'No enciende después de un apagón',              2, 1,  5
    UNION ALL SELECT 4,  @escritorio_a, 'El disco secundario no aparece',                3, NULL, NULL
    UNION ALL SELECT 5,  @portatil_a,    'Puerto de carga intermitente',                  4, 3, -1
    UNION ALL SELECT 6,  @escritorio_a, 'Reinicios al entrar en el sistema',             5, 2,  0
    UNION ALL SELECT 7,  @portatil_a,    'Teclado con teclas sin respuesta',              6, 1,  1
    UNION ALL SELECT 8,  @escritorio_a, 'Se pierde la conexión de red',                 8, NULL, 3
    UNION ALL SELECT 9,  @portatil_a,    'Pantalla parpadea al abrir la tapa',           10, 3,  6
    UNION ALL SELECT 10, @escritorio_a, 'Equipo lento después de iniciar',             11, 2, NULL
    UNION ALL SELECT 11, @portatil_b,    'El equipo no reconoce su adaptador',            0, 1,  0
    UNION ALL SELECT 12, @escritorio_b, 'Se apaga durante el arranque',                  1, 2,  2
    UNION ALL SELECT 13, @portatil_b,    'El audio se interrumpe al reproducir',          2, 3,  3
    UNION ALL SELECT 14, @escritorio_b, 'Aparece una advertencia al encender',           3, NULL, -2
    UNION ALL SELECT 15, @portatil_b,    'El panel táctil deja de responder',             4, 2,  5
    UNION ALL SELECT 16, @escritorio_b, 'Falla el ventilador del procesador',             5, 1,  7
    UNION ALL SELECT 17, @portatil_b,    'La imagen se pierde al mover la tapa',          6, 3,  2
    UNION ALL SELECT 18, @escritorio_b, 'La tarjeta de red no aparece',                  8, 2,  0
    UNION ALL SELECT 19, @portatil_b,    'El equipo tarda en salir del reposo',          10, 1,  NULL
    UNION ALL SELECT 20, @escritorio_b, 'El equipo emite pitidos antes de iniciar',     12, 3, -1
)
SELECT equipo, CONCAT(LPAD(numero, 2, '0'), '. ', falla),
       DATE_SUB(TIMESTAMP(@hoy), INTERVAL espera DAY), urgencia,
       DATE_ADD(@hoy, INTERVAL dias_entrega DAY)
FROM casos
ORDER BY numero;

INSERT INTO historial_estados (id_solicitud, id_estado, id_usuario, fecha_cambio)
SELECT s.id_solicitud, e.id_estado,
       CASE WHEN e.orden_visual = 1 THEN eq.id_usuario ELSE @tecnico END,
       DATE_ADD(s.fecha_ingreso, INTERVAL (e.orden_visual - 1) HOUR)
FROM solicitudes AS s
JOIN equipos AS eq ON eq.id_equipo = s.id_equipo
CROSS JOIN estados_reparacion AS e
WHERE s.id_equipo IN (@portatil_a, @escritorio_a, @portatil_b, @escritorio_b)
  AND e.orden_visual <= CASE CAST(LEFT(s.descripcion_falla, 2) AS UNSIGNED)
      WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 1 WHEN 5 THEN 4
      WHEN 6 THEN 2 WHEN 7 THEN 3 WHEN 8 THEN 1 WHEN 9 THEN 5 WHEN 10 THEN 2
      WHEN 11 THEN 1 WHEN 12 THEN 2 WHEN 13 THEN 3 WHEN 14 THEN 1 WHEN 15 THEN 4
      WHEN 16 THEN 5 WHEN 17 THEN 3 WHEN 18 THEN 4 WHEN 19 THEN 5 WHEN 20 THEN 3
  END
ORDER BY s.id_solicitud, e.orden_visual;

INSERT INTO diagnosticos (id_solicitud, id_tecnico, descripcion, observaciones)
SELECT s.id_solicitud, @tecnico, 'Revisión técnica de prueba registrada.',
       'Resultado de un caso ficticio para verificar el seguimiento.'
FROM solicitudes AS s
WHERE s.id_equipo IN (@portatil_a, @escritorio_a, @portatil_b, @escritorio_b)
  AND LEFT(s.descripcion_falla, 2) IN ('02', '03', '05', '06', '07', '09', '10', '12', '13', '15', '16', '17', '18', '19', '20');

COMMIT;
