-- TecControl: diseño para probar en MySQL 8.4.
CREATE DATABASE IF NOT EXISTS teccontrol
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teccontrol;
CREATE TABLE roles (
 id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE usuarios (
 id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_rol INT UNSIGNED NOT NULL,
 nombre VARCHAR(100) NOT NULL,
 correo VARCHAR(150) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
) ENGINE=InnoDB;
CREATE TABLE equipos (
 id_equipo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_usuario INT UNSIGNED NOT NULL,
 tipo ENUM('Escritorio','Notebook') NOT NULL,
 marca VARCHAR(80) NOT NULL, modelo VARCHAR(100) NOT NULL,
 fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
 INDEX idx_equipos_usuario (id_usuario)
) ENGINE=InnoDB;
CREATE TABLE urgencias (
 id_urgencia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(20) NOT NULL UNIQUE,
 peso_base TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE solicitudes (
 id_solicitud INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_equipo INT UNSIGNED NOT NULL,
 descripcion_falla TEXT NOT NULL,
 fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 id_urgencia INT UNSIGNED NULL,
 fecha_comprometida DATE NULL,
 FOREIGN KEY (id_equipo) REFERENCES equipos(id_equipo),
 FOREIGN KEY (id_urgencia) REFERENCES urgencias(id_urgencia),
 INDEX idx_sol_equipo (id_equipo),
 INDEX idx_sol_ingreso (fecha_ingreso)
) ENGINE=InnoDB;
CREATE TABLE diagnosticos (
 id_diagnostico INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_solicitud INT UNSIGNED NOT NULL UNIQUE,
 id_tecnico INT UNSIGNED NOT NULL,
 descripcion TEXT NOT NULL, observaciones TEXT NULL,
 fecha_diagnostico DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud),
 FOREIGN KEY (id_tecnico) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;
CREATE TABLE estados_reparacion (
 id_estado INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(40) NOT NULL UNIQUE,
 orden_visual TINYINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE historial_estados (
 id_historial BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_solicitud INT UNSIGNED NOT NULL,
 id_estado INT UNSIGNED NOT NULL,
 id_usuario INT UNSIGNED NOT NULL,
 fecha_cambio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud),
 FOREIGN KEY (id_estado) REFERENCES estados_reparacion(id_estado),
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
 INDEX idx_hist_sol_fecha (id_solicitud,fecha_cambio,id_historial)
) ENGINE=InnoDB;
INSERT IGNORE INTO roles(id_rol,nombre) VALUES
 (1,'Cliente'),(2,'Administrador/Técnico');
INSERT IGNORE INTO urgencias(id_urgencia,nombre,peso_base)
 VALUES (1,'Baja',10),(2,'Media',20),(3,'Alta',30);
INSERT IGNORE INTO estados_reparacion(id_estado,nombre,orden_visual)
 VALUES (1,'Recibida',1),(2,'En diagnóstico',2),
 (3,'En reparación',3),(4,'Lista para entrega',4),
 (5,'Entregada',5);

-- CONSULTA ORIENTATIVA RF-13
WITH ultimo_estado AS (
 SELECT h.id_solicitud, e.nombre AS estado,
 ROW_NUMBER() OVER (
  PARTITION BY h.id_solicitud
  ORDER BY h.fecha_cambio DESC, h.id_historial DESC
 ) AS rn
 FROM historial_estados h
 JOIN estados_reparacion e ON e.id_estado=h.id_estado
), puntajes AS (
 SELECT s.id_solicitud, s.fecha_ingreso,
 u.nombre AS urgencia, s.fecha_comprometida,
 (COALESCE(u.peso_base,0)
  + LEAST(GREATEST(DATEDIFF(CURDATE(),
     DATE(s.fecha_ingreso)),0)*2,20)
  + CASE
    WHEN s.fecha_comprometida IS NULL THEN 0
    WHEN DATEDIFF(s.fecha_comprometida,CURDATE())<=0 THEN 30
    WHEN DATEDIFF(s.fecha_comprometida,CURDATE())<=2 THEN 20
    WHEN DATEDIFF(s.fecha_comprometida,CURDATE())<=5 THEN 10
    ELSE 0 END) AS puntuacion
 FROM solicitudes s
 LEFT JOIN urgencias u ON u.id_urgencia=s.id_urgencia
 JOIN ultimo_estado ue ON ue.id_solicitud=s.id_solicitud
                     AND ue.rn=1
 WHERE ue.estado<>'Entregada'
)
SELECT * FROM puntajes
ORDER BY puntuacion DESC, fecha_ingreso ASC,
         id_solicitud ASC;
