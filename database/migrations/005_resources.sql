-- =============================================================================
-- 005 — Recursos gratuitos: plantillas y videos
-- Tablas: templates, template_downloads, videos, video_views
--
-- Cada descarga y cada reproducción queda contada, para que el SuperAdmin vea
-- qué material es realmente útil.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Plantillas descargables.
--   requires_registration: 1 = hay que identificarse antes de descargar.
--                         El ajuste general TEMPLATE_DOWNLOAD_POLICY marca el
--                         valor por defecto, y cada plantilla puede sobrescribirlo.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS templates (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug                  VARCHAR(190) NOT NULL,
    title                 VARCHAR(200) NOT NULL,
    summary               VARCHAR(500) NULL,
    description           MEDIUMTEXT NULL,
    category              VARCHAR(80)  NULL,
    preview_media_id      BIGINT UNSIGNED NULL,
    file_path             VARCHAR(500) NOT NULL,      -- ruta dentro de storage/protected
    original_file_name    VARCHAR(255) NOT NULL,
    file_mime             VARCHAR(120) NULL,
    file_size_bytes       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    requires_registration TINYINT(1) NOT NULL DEFAULT 0,
    download_count        INT UNSIGNED NOT NULL DEFAULT 0,
    status                VARCHAR(20) NOT NULL DEFAULT 'active',
    sort_order            SMALLINT NOT NULL DEFAULT 0,
    published_at          DATETIME NULL,
    created_by            BIGINT UNSIGNED NULL,
    updated_by            BIGINT UNSIGNED NULL,
    deleted_at            DATETIME NULL,
    purge_after           DATETIME NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_templates_slug (slug),
    KEY idx_templates_status (status, sort_order),
    KEY idx_templates_category (category),
    CONSTRAINT fk_templates_preview FOREIGN KEY (preview_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_templates_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_templates_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Registro de descargas. Se guardan también los datos de quien descarga sin
-- cuenta, cuando la plantilla lo permite.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS template_downloads (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    name        VARCHAR(150) NULL,
    email       VARCHAR(190) NULL,
    ip          VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_template_downloads_template (template_id, created_at),
    KEY idx_template_downloads_user (user_id),
    CONSTRAINT fk_template_downloads_template FOREIGN KEY (template_id) REFERENCES templates (id) ON DELETE CASCADE,
    CONSTRAINT fk_template_downloads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Biblioteca de videos.
--   source     : upload (archivo propio) | youtube | vimeo
--   access_type: free | paid
-- Cuando el video es de pago, `file_path` apunta a storage/protected y se
-- entrega por controlador con enlace firmado y caducidad corta.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS videos (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug             VARCHAR(190) NOT NULL,
    title            VARCHAR(200) NOT NULL,
    summary          VARCHAR(500) NULL,
    description      TEXT NULL,
    category         VARCHAR(80)  NULL,
    cover_media_id   BIGINT UNSIGNED NULL,
    source           VARCHAR(20) NOT NULL DEFAULT 'upload',
    external_id      VARCHAR(120) NULL,               -- identificador del proveedor externo
    file_path        VARCHAR(500) NULL,               -- dentro de storage/protected
    mime_type        VARCHAR(120) NULL,
    file_size_bytes  BIGINT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    access_type      VARCHAR(10) NOT NULL DEFAULT 'free',
    course_id        BIGINT UNSIGNED NULL,            -- si el video pertenece a un curso
    view_count       INT UNSIGNED NOT NULL DEFAULT 0,
    status           VARCHAR(20) NOT NULL DEFAULT 'active',
    sort_order       SMALLINT NOT NULL DEFAULT 0,
    published_at     DATETIME NULL,
    created_by       BIGINT UNSIGNED NULL,
    updated_by       BIGINT UNSIGNED NULL,
    deleted_at       DATETIME NULL,
    purge_after      DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_videos_slug (slug),
    KEY idx_videos_status (status, sort_order),
    KEY idx_videos_access (access_type, status),
    KEY idx_videos_category (category),
    CONSTRAINT fk_videos_cover FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_videos_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL,
    CONSTRAINT fk_videos_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_videos_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reproducciones, para las métricas del panel.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video_views (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    video_id         BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NULL,
    ip               VARCHAR(45) NULL,
    seconds_watched  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_video_views_video (video_id, created_at),
    KEY idx_video_views_user (user_id),
    CONSTRAINT fk_video_views_video FOREIGN KEY (video_id) REFERENCES videos (id) ON DELETE CASCADE,
    CONSTRAINT fk_video_views_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
