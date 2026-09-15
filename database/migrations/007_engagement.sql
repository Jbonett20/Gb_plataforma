-- =============================================================================
-- 007 — Noticias, contacto y control
-- Tablas: news, subscribers, appointments, content_access_log, audit_logs, throttle
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Noticias y artículos del blog.
--   status: draft | scheduled | published | hidden | deleted
-- Cuando `scheduled_at` llega y el estado es `scheduled`, la noticia pasa a
-- publicada automáticamente.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS news (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug             VARCHAR(190) NOT NULL,
    title            VARCHAR(200) NOT NULL,
    summary          VARCHAR(500) NULL,
    body             MEDIUMTEXT NULL,
    cover_media_id   BIGINT UNSIGNED NULL,
    category         VARCHAR(80) NULL,
    tags             VARCHAR(255) NULL,               -- etiquetas separadas por coma
    author_id        BIGINT UNSIGNED NULL,
    author_name      VARCHAR(150) NULL,               -- se conserva aunque se borre la cuenta
    status           VARCHAR(20) NOT NULL DEFAULT 'draft',
    scheduled_at     DATETIME NULL,
    published_at     DATETIME NULL,
    views_count      INT UNSIGNED NOT NULL DEFAULT 0,
    is_featured      TINYINT(1) NOT NULL DEFAULT 0,
    meta_title       VARCHAR(190) NULL,
    meta_description VARCHAR(320) NULL,
    created_by       BIGINT UNSIGNED NULL,
    updated_by       BIGINT UNSIGNED NULL,
    deleted_at       DATETIME NULL,
    purge_after      DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_news_slug (slug),
    KEY idx_news_status (status, published_at),
    KEY idx_news_scheduled (status, scheduled_at),
    KEY idx_news_category (category),
    CONSTRAINT fk_news_cover FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_news_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_news_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Suscriptores del boletín de novedades.
--
-- `legacy_id` conserva el identificador del sitio anterior (tabla gb_suscriptores)
-- para que la migración pueda reejecutarse sin duplicar registros.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscribers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150) NOT NULL,
    last_name       VARCHAR(150) NULL,
    phone           VARCHAR(40) NULL,
    email           VARCHAR(190) NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',   -- active | unsubscribed
    source          VARCHAR(40) NOT NULL DEFAULT 'web',      -- web | migracion | panel
    legacy_id       VARCHAR(50) NULL,
    confirmed_at    DATETIME NULL,
    unsubscribed_at DATETIME NULL,
    ip              VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscribers_email (email),
    UNIQUE KEY uq_subscribers_legacy (legacy_id),
    KEY idx_subscribers_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Solicitudes de cita recibidas desde el formulario de contacto.
--
-- `legacy_id` conserva el identificador del sitio anterior (tabla gb_citas).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150) NOT NULL,
    last_name       VARCHAR(150) NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(40) NULL,
    subject         VARCHAR(200) NULL,
    message         TEXT NULL,
    preferred_date  DATE NULL,
    preferred_time  TIME NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'new',   -- new | contacted | scheduled | attended | cancelled
    assigned_to     BIGINT UNSIGNED NULL,
    internal_notes  TEXT NULL,
    source          VARCHAR(40) NOT NULL DEFAULT 'web',
    legacy_id       VARCHAR(50) NULL,
    ip              VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appointments_legacy (legacy_id),
    KEY idx_appointments_status (status, created_at),
    KEY idx_appointments_assigned (assigned_to),
    CONSTRAINT fk_appointments_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Registro de accesos a contenido protegido.
-- Permite responder "¿quién vio este material y cuándo?" y detectar cuentas
-- compartidas entre varias personas.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS content_access_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NULL,
    course_id  BIGINT UNSIGNED NULL,
    lesson_id  BIGINT UNSIGNED NULL,
    video_id   BIGINT UNSIGNED NULL,
    media_id   BIGINT UNSIGNED NULL,
    action     VARCHAR(40) NOT NULL,               -- play | download | view
    delivered  TINYINT(1) NOT NULL DEFAULT 0,
    reason     VARCHAR(120) NULL,                  -- motivo cuando la entrega fue denegada
    ip         VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_access_user (user_id, created_at),
    KEY idx_content_access_lesson (lesson_id, created_at),
    KEY idx_content_access_video (video_id, created_at),
    CONSTRAINT fk_content_access_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_content_access_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL,
    CONSTRAINT fk_content_access_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE SET NULL,
    CONSTRAINT fk_content_access_video FOREIGN KEY (video_id) REFERENCES videos (id) ON DELETE SET NULL,
    CONSTRAINT fk_content_access_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Auditoría de acciones administrativas: quién hizo qué, cuándo y desde dónde.
-- `summary` está redactado en lenguaje natural para poder mostrarlo en el panel.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    actor_label VARCHAR(190) NULL,                 -- se conserva aunque se borre la cuenta
    action      VARCHAR(80)  NOT NULL,             -- create | update | delete | publish | grant_access | refund...
    entity_type VARCHAR(60)  NULL,                 -- block | course | user | payment...
    entity_id   BIGINT UNSIGNED NULL,
    summary     VARCHAR(500) NULL,
    changes     JSON NULL,
    ip          VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_user (user_id, created_at),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    KEY idx_audit_logs_action (action, created_at),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Control de intentos abusivos por acción y origen.
-- Lo consulta el middleware Throttle antes de procesar un formulario público,
-- un inicio de sesión, un registro o una recuperación de contraseña.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS throttle (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action           VARCHAR(60)  NOT NULL,        -- login | register | password_reset | contact | newsletter
    identifier       VARCHAR(190) NOT NULL,        -- dirección IP, correo o combinación de ambos
    attempts         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until    DATETIME NULL,
    first_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_throttle_action_identifier (action, identifier),
    KEY idx_throttle_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
