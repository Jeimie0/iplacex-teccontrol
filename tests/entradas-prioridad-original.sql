-- Exportar con el cliente mysql en modo batch y con encabezados.
SELECT s.id_solicitud, s.fecha_ingreso, u.nombre AS urgencia, s.fecha_comprometida,
       CURDATE() AS hoy, er.nombre AS estado
FROM solicitudes AS s
LEFT JOIN urgencias AS u ON u.id_urgencia = s.id_urgencia
JOIN historial_estados AS h ON h.id_historial = (
    SELECT id_historial FROM historial_estados
    WHERE id_solicitud = s.id_solicitud ORDER BY fecha_cambio DESC, id_historial DESC LIMIT 1
)
JOIN estados_reparacion AS er ON er.id_estado = h.id_estado
ORDER BY s.id_solicitud;
