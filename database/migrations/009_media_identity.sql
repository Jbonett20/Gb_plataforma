-- =============================================================================
-- 009 — Huella de recursos: evitar duplicados sin bloquear usos legítimos
--
-- El índice original era único sólo por `checksum`, de modo que subir el mismo
-- archivo para dos destinos distintos (por ejemplo la misma foto como portada
-- de curso y como imagen de noticia) fallaba, aunque sean recortes diferentes
-- que deben existir por separado.
--
-- La clave pasa a ser la combinación de archivo + destino + encuadre:
--   - el mismo archivo, en el mismo lugar y con el mismo encuadre → un solo
--     registro (evita guardar dos veces la misma imagen);
--   - cualquier diferencia → registros distintos, como corresponde.
-- =============================================================================

ALTER TABLE media
    DROP INDEX uq_media_checksum,
    ADD UNIQUE KEY uq_media_identity (checksum, profile, focal_x, focal_y);
