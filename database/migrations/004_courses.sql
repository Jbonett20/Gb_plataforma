-- =============================================================================
-- 004 — Cursos, lecciones e inscripciones
-- Tablas: courses, course_modules, lessons, enrollments, lesson_progress
--
-- El acceso se concede SIEMPRE mediante un registro en `enrollments`. Ninguna
-- pantalla decide por su cuenta si alguien puede ver un curso.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Cursos gratuitos y de pago.
--   access_type: free | paid
--   status     : draft | published | hidden | archived | deleted
-- Un curso sólo se publica si tiene título, descripción, portada, tipo de acceso
-- y al menos una lección (lo valida la aplicación, tarea 8.3).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug              VARCHAR(190) NOT NULL,
    title             VARCHAR(200) NOT NULL,
    subtitle          VARCHAR(255) NULL,
    summary           VARCHAR(500) NULL,               -- texto corto de las tarjetas del catálogo
    description       MEDIUMTEXT NULL,
    requirements      TEXT NULL,
    cover_media_id    BIGINT UNSIGNED NULL,
    instructor_name   VARCHAR(150) NULL,
    instructor_bio    TEXT NULL,
    instructor_media_id BIGINT UNSIGNED NULL,
    access_type       VARCHAR(10)  NOT NULL DEFAULT 'free',
    price             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency          CHAR(3)      NOT NULL DEFAULT 'COP',
    level             VARCHAR(20)  NULL,               -- basico | intermedio | avanzado
    category          VARCHAR(80)  NULL,
    duration_minutes  INT UNSIGNED NULL,
    lessons_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- se recalcula al guardar el temario
    status            VARCHAR(20)  NOT NULL DEFAULT 'draft',
    sort_order        SMALLINT     NOT NULL DEFAULT 0,
    meta_title        VARCHAR(190) NULL,
    meta_description  VARCHAR(320) NULL,
    published_at      DATETIME     NULL,
    created_by        BIGINT UNSIGNED NULL,
    updated_by        BIGINT UNSIGNED NULL,
    deleted_at        DATETIME     NULL,
    purge_after       DATETIME     NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_slug (slug),
    KEY idx_courses_status (status, sort_order),
    KEY idx_courses_access (access_type, status),
    KEY idx_courses_category (category),
    CONSTRAINT fk_courses_cover FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_courses_instructor_media FOREIGN KEY (instructor_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_courses_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_courses_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Módulos del curso: agrupan lecciones y definen su orden.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS course_modules (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id   BIGINT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    summary     VARCHAR(500) NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    status      VARCHAR(20) NOT NULL DEFAULT 'active',
    deleted_at  DATETIME NULL,
    purge_after DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_course_modules_course (course_id, sort_order),
    CONSTRAINT fk_course_modules_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Lecciones.
--   content_type  : video | document | text | link
--   protected_path: ruta dentro de storage/protected (material de pago).
--                   Nunca se expone al navegador: se entrega por controlador.
--   is_preview    : lección de muestra visible sin haber comprado el curso.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lessons (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_module_id  BIGINT UNSIGNED NOT NULL,
    title             VARCHAR(200) NOT NULL,
    summary           VARCHAR(500) NULL,
    content_type      VARCHAR(20)  NOT NULL DEFAULT 'video',
    body              MEDIUMTEXT NULL,                 -- contenido cuando content_type = text
    media_id          BIGINT UNSIGNED NULL,            -- recurso público (imagen o adjunto libre)
    protected_path    VARCHAR(500) NULL,               -- recurso de pago, dentro de storage/protected
    original_file_name VARCHAR(255) NULL,
    file_size_bytes   BIGINT UNSIGNED NULL,
    external_url      VARCHAR(500) NULL,
    duration_minutes  SMALLINT UNSIGNED NULL,
    is_preview        TINYINT(1) NOT NULL DEFAULT 0,
    sort_order        SMALLINT   NOT NULL DEFAULT 0,
    status            VARCHAR(20) NOT NULL DEFAULT 'active',
    deleted_at        DATETIME NULL,
    purge_after       DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lessons_module (course_module_id, sort_order),
    KEY idx_lessons_protected (protected_path),
    CONSTRAINT fk_lessons_module FOREIGN KEY (course_module_id) REFERENCES course_modules (id) ON DELETE CASCADE,
    CONSTRAINT fk_lessons_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Inscripciones: la única fuente de verdad sobre quién puede ver qué curso.
--   source     : free (curso gratuito) | purchase (compra) | granted (concedido por el SuperAdmin)
--   status     : active | revoked | expired
-- La llave foránea hacia `payments` se añade en la migración 006.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    course_id     BIGINT UNSIGNED NOT NULL,
    source        VARCHAR(20) NOT NULL DEFAULT 'free',
    payment_id    BIGINT UNSIGNED NULL,
    granted_by    BIGINT UNSIGNED NULL,
    granted_at    DATETIME NULL,
    expires_at    DATETIME NULL,
    revoked_at    DATETIME NULL,
    revoked_by    BIGINT UNSIGNED NULL,
    revoke_reason VARCHAR(255) NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'active',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_access_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enrollments_user_course (user_id, course_id),
    KEY idx_enrollments_status (status),
    KEY idx_enrollments_course (course_id),
    KEY idx_enrollments_payment (payment_id),
    CONSTRAINT fk_enrollments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_granted_by FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_enrollments_revoked_by FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Avance del estudiante por lección.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lesson_progress (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id               BIGINT UNSIGNED NOT NULL,
    enrollment_id         BIGINT UNSIGNED NOT NULL,
    lesson_id             BIGINT UNSIGNED NOT NULL,
    completed_at          DATETIME NULL,
    last_position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    seconds_watched       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lesson_progress (user_id, lesson_id),
    KEY idx_lesson_progress_enrollment (enrollment_id),
    CONSTRAINT fk_lesson_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_progress_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
