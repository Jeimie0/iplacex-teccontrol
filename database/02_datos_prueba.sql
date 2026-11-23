-- Datos ficticios para el entorno local. Requiere el esquema provisional.
-- Cargar con el cliente mysql en modo batch sin la opción --force.

SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO clientes (nombre, correo_contacto, telefono)
VALUES ('Cliente de prueba A', 'contacto-a@teccontrol.example', NULL);
SET @demo_cliente_a = LAST_INSERT_ID();

INSERT INTO clientes (nombre, correo_contacto, telefono)
VALUES ('Cliente de prueba B', 'contacto-b@teccontrol.example', NULL);
SET @demo_cliente_b = LAST_INSERT_ID();

-- Las contraseñas ficticias se indican en el README. Se usó password_hash de PHP.
INSERT INTO usuarios (nombre, correo, password_hash, rol, cliente_id) VALUES
    ('Administración de prueba', 'admin@teccontrol.example', '$2y$10$2lbkUA3LiV7oRWPBq5wrDOim6iYp6koWieo23064TqHOLIjnronda', 'administrador', NULL),
    ('Acceso cliente A', 'cliente-a@teccontrol.example', '$2y$10$E3foCQWjzT.4ghtyA86l6uJSQRh/AmLon6DND.eNstI7CMU41P1Ke', 'cliente', @demo_cliente_a),
    ('Acceso cliente B', 'cliente-b@teccontrol.example', '$2y$10$IMpnGhX.mCUiiYP09TOyN.kDpAQqDuuw0AgDt5VEwfbrvW6sGOWIa', 'cliente', @demo_cliente_b);

SELECT id INTO @demo_administrador
FROM usuarios
WHERE correo = 'admin@teccontrol.example';

INSERT INTO equipos (cliente_id, tipo, marca, modelo, numero_serie)
VALUES (@demo_cliente_a, 'Notebook', 'Equipo Demo', 'N14', 'DEMO-A-N14');
SET @demo_notebook = LAST_INSERT_ID();

INSERT INTO equipos (cliente_id, tipo, marca, modelo, numero_serie)
VALUES (@demo_cliente_a, 'Impresora', 'Equipo Demo', 'L200', 'DEMO-A-L200');
SET @demo_impresora = LAST_INSERT_ID();

INSERT INTO equipos (cliente_id, tipo, marca, modelo, numero_serie)
VALUES (@demo_cliente_b, 'Computador de escritorio', 'Equipo Demo', 'T500', 'DEMO-B-T500');
SET @demo_computador = LAST_INSERT_ID();

INSERT INTO equipos (cliente_id, tipo, marca, modelo, numero_serie)
VALUES (@demo_cliente_b, 'Monitor', 'Equipo Demo', 'M24', 'DEMO-B-M24');
SET @demo_monitor = LAST_INSERT_ID();

INSERT INTO ordenes_servicio (equipo_id, folio, falla_reportada, creada_en) VALUES
    (@demo_notebook, 'DEMO-001', 'Batería se descarga rápidamente', '2026-09-01 09:00:00'),
    (@demo_notebook, 'DEMO-002', 'Ventilador con ruido irregular', '2026-09-03 10:15:00'),
    (@demo_impresora, 'DEMO-003', 'Atasco frecuente de papel', '2026-09-04 15:20:00'),
    (@demo_impresora, 'DEMO-004', 'Imprime con líneas verticales', '2026-09-08 11:30:00'),
    (@demo_notebook, 'DEMO-005', 'Puerto de carga intermitente', '2026-09-10 08:45:00'),
    (@demo_impresora, 'DEMO-006', 'Conexión de red intermitente', '2026-09-14 13:10:00'),
    (@demo_notebook, 'DEMO-007', 'Teclado con teclas sin respuesta', '2026-09-16 10:00:00'),
    (@demo_impresora, 'DEMO-008', 'Aviso de bandeja vacía con papel', '2026-09-21 12:10:00'),
    (@demo_notebook, 'DEMO-009', 'Pantalla parpadea al abrir la tapa', '2026-09-24 14:30:00'),
    (@demo_impresora, 'DEMO-010', 'Papel sale arrugado de la bandeja', '2026-09-29 16:00:00'),
    (@demo_computador, 'DEMO-011', 'No inicia después de encender', '2026-09-02 09:30:00'),
    (@demo_monitor, 'DEMO-012', 'Imagen se pierde con la entrada HDMI', '2026-09-05 10:45:00'),
    (@demo_computador, 'DEMO-013', 'Reinicios inesperados bajo carga', '2026-09-07 15:00:00'),
    (@demo_monitor, 'DEMO-014', 'Brillo irregular en la parte inferior', '2026-09-09 11:15:00'),
    (@demo_computador, 'DEMO-015', 'Disco secundario no aparece', '2026-09-11 08:30:00'),
    (@demo_monitor, 'DEMO-016', 'Botón de encendido con desgaste', '2026-09-15 13:45:00'),
    (@demo_computador, 'DEMO-017', 'Puerto de red sin conexión', '2026-09-17 10:20:00'),
    (@demo_monitor, 'DEMO-018', 'Base inestable tras un ajuste', '2026-09-22 12:40:00'),
    (@demo_computador, 'DEMO-019', 'Arranque más lento de lo habitual', '2026-09-25 14:10:00'),
    (@demo_monitor, 'DEMO-020', 'Aparecen líneas horizontales', '2026-09-30 16:30:00');

INSERT INTO historial_estados (orden_id, estado_id, registrado_por, registrado_en)
SELECT orden.id, estado.id, @demo_administrador, TIMESTAMPADD(DAY, etapa.dias, orden.creada_en)
FROM ordenes_servicio AS orden
JOIN (
    SELECT 'recibida' AS codigo, 0 AS dias, 1 AS primer_folio
    UNION ALL SELECT 'diagnostico', 1, 5
    UNION ALL SELECT 'reparacion', 2, 9
    UNION ALL SELECT 'lista', 3, 13
    UNION ALL SELECT 'entregada', 4, 17
) AS etapa ON CAST(RIGHT(orden.folio, 3) AS UNSIGNED) >= etapa.primer_folio
JOIN estados_orden AS estado ON estado.codigo = etapa.codigo
WHERE orden.equipo_id IN (@demo_notebook, @demo_impresora, @demo_computador, @demo_monitor)
ORDER BY orden.id, etapa.dias;

COMMIT;
