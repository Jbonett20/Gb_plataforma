-- =============================================================================
-- 008 — Campos de texto de los bloques y ruta pública de los recursos
--
-- Dos ajustes que surgieron al construir el editor de bloques:
--
-- 1. Los bloques necesitan sus propios textos (el rótulo pequeño, el título
--    grande, la introducción y el botón). Guardarlos en columnas con nombre
--    permite que el panel los muestre con una etiqueta clara, en lugar de
--    esconderlos dentro del JSON de opciones.
--
-- 2. `media.public_path` indica dónde está el archivo que ve el navegador.
--    Para las imágenes heredadas del sitio actual apunta a `assets/img/...`;
--    para las que se suban desde el panel lo calculará el pipeline de imágenes
--    (tarea 5.x). Sin esta columna no se podría mostrar una imagen antes de
--    que exista el pipeline.
-- =============================================================================

ALTER TABLE blocks
    ADD COLUMN eyebrow    VARCHAR(150) NULL AFTER help_text,
    ADD COLUMN heading    VARCHAR(255) NULL AFTER eyebrow,
    ADD COLUMN intro      TEXT NULL AFTER heading,
    ADD COLUMN media_id   BIGINT UNSIGNED NULL AFTER intro,
    ADD COLUMN cta_label  VARCHAR(120) NULL AFTER media_id,
    ADD COLUMN cta_url    VARCHAR(500) NULL AFTER cta_label,
    ADD KEY idx_blocks_media (media_id),
    ADD CONSTRAINT fk_blocks_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL;

ALTER TABLE media
    ADD COLUMN public_path VARCHAR(500) NULL AFTER path,
    ADD COLUMN is_legacy TINYINT(1) NOT NULL DEFAULT 0 AFTER is_protected;
