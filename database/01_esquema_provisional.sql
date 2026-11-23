-- TecControl. Esquema provisional pendiente de cotejo con el SQL original.
-- Ejecutar sobre una base vacía seleccionada por el cliente mysql.

SET NAMES utf8mb4;

CREATE TABLE clientes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    correo_contacto VARCHAR(254),
    telefono VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT UNSIGNED,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('administrador', 'cliente') NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_usuarios_correo UNIQUE (correo),
    CONSTRAINT ck_usuarios_activo CHECK (activo IN (0, 1)),
    CONSTRAINT ck_usuarios_rol_cliente CHECK (
        (rol = 'administrador' AND cliente_id IS NULL)
        OR (rol = 'cliente' AND cliente_id IS NOT NULL)
    ),
    CONSTRAINT fk_usuarios_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE equipos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    marca VARCHAR(80) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    numero_serie VARCHAR(100),
    CONSTRAINT fk_equipos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE estados_orden (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    CONSTRAINT uq_estados_orden_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ordenes_servicio (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id BIGINT UNSIGNED NOT NULL,
    folio VARCHAR(30) NOT NULL,
    falla_reportada TEXT NOT NULL,
    diagnostico TEXT,
    creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_ordenes_servicio_folio UNIQUE (folio),
    CONSTRAINT fk_ordenes_servicio_equipo FOREIGN KEY (equipo_id) REFERENCES equipos (id),
    KEY idx_ordenes_servicio_fecha (creada_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE historial_estados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_id BIGINT UNSIGNED NOT NULL,
    estado_id SMALLINT UNSIGNED NOT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_orden FOREIGN KEY (orden_id) REFERENCES ordenes_servicio (id),
    CONSTRAINT fk_historial_estado FOREIGN KEY (estado_id) REFERENCES estados_orden (id),
    CONSTRAINT fk_historial_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id),
    KEY idx_historial_orden_evento (orden_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La titularidad se deriva del equipo. Estos vínculos son fijos en el modelo provisional.
DELIMITER $$

CREATE TRIGGER equipos_conservar_cliente
BEFORE UPDATE ON equipos
FOR EACH ROW
BEGIN
    IF NEW.cliente_id <> OLD.cliente_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cliente vinculado al equipo es fijo';
    END IF;
END$$

CREATE TRIGGER ordenes_conservar_equipo
BEFORE UPDATE ON ordenes_servicio
FOR EACH ROW
BEGIN
    IF NEW.equipo_id <> OLD.equipo_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El equipo vinculado a la orden es fijo';
    END IF;
END$$

DELIMITER ;

INSERT INTO estados_orden (codigo, nombre) VALUES
    ('recibida', 'Recibida'),
    ('diagnostico', 'En diagnóstico'),
    ('reparacion', 'En reparación'),
    ('lista', 'Lista para retiro'),
    ('entregada', 'Entregada');
