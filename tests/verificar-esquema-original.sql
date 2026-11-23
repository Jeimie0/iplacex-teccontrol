-- Comprobaciones de integridad para la copia de prueba del SQL original.
-- Ejecutar luego de cargar database/03_datos_academicos.sql.
USE teccontrol;
SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE verificar_integridad_teccontrol()
BEGIN
    DECLARE v_tecnico INT UNSIGNED;
    DECLARE v_rol INT UNSIGNED;
    DECLARE v_cliente INT UNSIGNED;
    DECLARE v_equipo INT UNSIGNED;
    DECLARE v_solicitud INT UNSIGNED;
    DECLARE v_diagnostico INT UNSIGNED;
    DECLARE v_error INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF @@SESSION.foreign_key_checks <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La prueba requiere claves foráneas activas';
    END IF;

    SELECT id_usuario, id_rol INTO v_tecnico, v_rol
    FROM usuarios WHERE correo = 'admin@teccontrol.example';
    SELECT id_solicitud INTO v_diagnostico FROM diagnosticos ORDER BY id_diagnostico LIMIT 1;

    START TRANSACTION;

    INSERT INTO usuarios (id_rol, nombre, correo, password_hash)
    SELECT id_rol, 'Cliente para intervención de integridad', 'integridad@teccontrol.example', password_hash
    FROM usuarios WHERE correo = 'cliente-a@teccontrol.example';
    SET v_cliente = LAST_INSERT_ID();
    INSERT INTO equipos (id_usuario, tipo, marca, modelo)
    VALUES (v_cliente, 'Notebook', 'Equipo de prueba', 'TX-1');
    SET v_equipo = LAST_INSERT_ID();
    INSERT INTO solicitudes (id_equipo, descripcion_falla)
    VALUES (v_equipo, 'Solicitud para comprobar reversión');
    SET v_solicitud = LAST_INSERT_ID();
    INSERT INTO historial_estados (id_solicitud, id_estado, id_usuario)
    VALUES (v_solicitud, 1, v_cliente);
    IF (SELECT COUNT(*) FROM historial_estados WHERE id_solicitud = v_solicitud) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se creó el estado inicial';
    END IF;
    SELECT 'OK inserciones válidas y evento inicial relacionado' AS comprobacion;

    BEGIN
        DECLARE CONTINUE HANDLER FOR 1062 SET v_error = 1062;
        INSERT INTO usuarios (id_rol, nombre, correo, password_hash)
        SELECT id_rol, 'Duplicado', correo, password_hash
        FROM usuarios WHERE id_usuario = v_tecnico;
    END;
    IF v_error <> 1062 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se rechazó el correo duplicado';
    END IF;
    SELECT 'OK UNIQUE correo' AS comprobacion, v_error AS error_mysql;

    SET v_error = 0;
    BEGIN
        DECLARE CONTINUE HANDLER FOR 1062 SET v_error = 1062;
        INSERT INTO diagnosticos (id_solicitud, id_tecnico, descripcion)
        VALUES (v_diagnostico, v_tecnico, 'No debe quedar un segundo diagnóstico');
    END;
    IF v_error <> 1062 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se rechazó el diagnóstico duplicado';
    END IF;
    SELECT 'OK UNIQUE diagnóstico principal' AS comprobacion, v_error AS error_mysql;

    SET v_error = 0;
    BEGIN
        DECLARE CONTINUE HANDLER FOR 1452 SET v_error = 1452;
        INSERT INTO equipos (id_usuario, tipo, marca, modelo)
        VALUES (0, 'Notebook', 'Equipo de prueba', 'FK-1');
    END;
    IF v_error <> 1452 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se rechazó el titular inexistente';
    END IF;
    SELECT 'OK FK titular inexistente' AS comprobacion, v_error AS error_mysql;

    SET v_error = 0;
    BEGIN
        DECLARE CONTINUE HANDLER FOR 1451 SET v_error = 1451;
        DELETE FROM roles WHERE id_rol = v_rol;
    END;
    IF v_error <> 1451 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se rechazó borrar un rol referenciado';
    END IF;
    SELECT 'OK restricción al eliminar el registro padre' AS comprobacion, v_error AS error_mysql;

    SET v_error = 0;
    BEGIN
        DECLARE CONTINUE HANDLER FOR 1048 SET v_error = 1048;
        INSERT INTO solicitudes (id_equipo, descripcion_falla) VALUES (v_equipo, NULL);
    END;
    IF v_error <> 1048 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se rechazó la falla obligatoria nula';
    END IF;
    SELECT 'OK NOT NULL descripción de falla' AS comprobacion, v_error AS error_mysql;

    SET v_error = 0;
    BEGIN
        DECLARE CONTINUE HANDLER FOR 1452 SET v_error = 1452;
        INSERT INTO historial_estados (id_solicitud, id_estado, id_usuario)
        VALUES (v_solicitud, 0, v_cliente);
    END;
    IF v_error <> 1452 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se rechazó el estado inexistente';
    END IF;
    SELECT 'OK FK estado inexistente' AS comprobacion, v_error AS error_mysql;
    ROLLBACK;

    IF EXISTS (SELECT 1 FROM solicitudes WHERE id_solicitud = v_solicitud)
       OR EXISTS (SELECT 1 FROM equipos WHERE id_equipo = v_equipo)
       OR EXISTS (SELECT 1 FROM usuarios WHERE id_usuario = v_cliente)
       OR EXISTS (SELECT 1 FROM historial_estados WHERE id_solicitud = v_solicitud) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Persistieron cambios después de la reversión';
    END IF;
    SELECT 'OK reversión de solicitud, equipo, cuenta e historial' AS comprobacion;
END$$
DELIMITER ;

CALL verificar_integridad_teccontrol();
DROP PROCEDURE verificar_integridad_teccontrol;

SELECT u.nombre AS cliente, COUNT(*) AS solicitudes
FROM solicitudes AS s JOIN equipos AS e ON e.id_equipo = s.id_equipo
JOIN usuarios AS u ON u.id_usuario = e.id_usuario
GROUP BY u.id_usuario, u.nombre ORDER BY u.nombre;

SELECT s.id_solicitud, u.nombre AS cliente, s.descripcion_falla, er.nombre AS estado
FROM solicitudes AS s
JOIN equipos AS e ON e.id_equipo = s.id_equipo
JOIN usuarios AS u ON u.id_usuario = e.id_usuario
JOIN historial_estados AS h ON h.id_historial = (
    SELECT id_historial FROM historial_estados
    WHERE id_solicitud = s.id_solicitud ORDER BY fecha_cambio DESC, id_historial DESC LIMIT 1
)
JOIN estados_reparacion AS er ON er.id_estado = h.id_estado
ORDER BY s.id_solicitud;
