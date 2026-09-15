-- =============================================================================
-- 002 — Recursos multimedia
-- Tabla: media
--
-- Cada imagen o archivo subido desde el panel queda registrado aquí, con sus
-- dimensiones, el perfil de proporción con el que se recortó y el punto focal
-- elegido. Los originales viven en storage/uploads/originals (fuera del
-- webroot); las variantes públicas, en public/uploads/<perfil>/.
-- =============================================================================

CREATE TABLE IF NOT EXISTS media (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    disk         VARCHAR(20)  NOT NULL DEFAULT 'local',
    path         VARCHAR(500) NOT NULL,               -- ruta relativa dentro de storage/uploads/originals
    file_name    VARCHAR(255) NOT NULL,
    mime_type    VARCHAR(120) NOT NULL,
    extension    VARCHAR(10)  NOT NULL,
    size_bytes   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    width        INT UNSIGNED NULL,
    height       INT UNSIGNED NULL,
    profile      VARCHAR(60)  NULL,                   -- perfil de config/images.php: hero_slide, course_cover...
    focal_x      DECIMAL(5,4) NOT NULL DEFAULT 0.5000,-- 0 = borde izquierdo, 1 = borde derecho
    focal_y      DECIMAL(5,4) NOT NULL DEFAULT 0.5000,-- 0 = borde superior, 1 = borde inferior
    alt_text     VARCHAR(255) NULL,                   -- texto alternativo: accesibilidad y buscadores
    title        VARCHAR(255) NULL,
    checksum     CHAR(64)     NULL,                   -- sha256: evita guardar el mismo archivo dos veces
    variants     JSON         NULL,                   -- mapa de variantes generadas y sus medidas
    is_protected TINYINT(1)   NOT NULL DEFAULT 0,     -- 1 = material de pago, nunca accesible por URL directa
    uploaded_by  BIGINT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_checksum (checksum),
    KEY idx_media_profile (profile),
    KEY idx_media_protected (is_protected),
    KEY idx_media_uploaded_by (uploaded_by),
    CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Ahora que `media` existe se cierra la referencia desde `users`.
-- -----------------------------------------------------------------------------
ALTER TABLE users
    ADD CONSTRAINT fk_users_avatar FOREIGN KEY (avatar_media_id) REFERENCES media (id) ON DELETE SET NULL;
