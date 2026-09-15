-- =============================================================================
-- 001 — Acceso y cuentas
-- Tablas: roles, users, password_resets
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Roles del sistema
--   SuperAdmin : acceso total al panel de administración
--   Student    : área personal, cursos y compras
-- La etiqueta (`label`) es la que ve el SuperAdmin en el panel.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name    VARCHAR(50)  NOT NULL,
    label       VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_key_name (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (key_name, label, description) VALUES
    ('SuperAdmin', 'Superadministrador', 'Puede administrar todo el sitio, los cursos, los usuarios y las ventas.'),
    ('Student', 'Estudiante', 'Accede a su área personal, a los cursos gratuitos y a los que haya comprado.')
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description);

-- -----------------------------------------------------------------------------
-- Cuentas de acceso.
--
-- `avatar_media_id` se declara aquí y su llave foránea se añade en la migración
-- 002, cuando la tabla `media` ya existe.
--
-- `password_hash` guarda el resultado de password_hash(); la contraseña original
-- nunca se almacena ni se muestra en ningún panel, correo o registro.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id               TINYINT UNSIGNED NOT NULL,
    name                  VARCHAR(120) NOT NULL,
    last_name             VARCHAR(120) NULL,
    email                 VARCHAR(190) NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    phone                 VARCHAR(40) NULL,
    avatar_media_id       BIGINT UNSIGNED NULL,
    document_type         VARCHAR(20) NULL,
    document_number       VARCHAR(40) NULL,
    status                VARCHAR(20) NOT NULL DEFAULT 'active',   -- active | inactive | blocked
    email_verified_at     DATETIME NULL,
    must_change_password  TINYINT(1) NOT NULL DEFAULT 0,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until          DATETIME NULL,
    last_login_at         DATETIME NULL,
    last_login_ip         VARCHAR(45) NULL,
    accepted_terms_at     DATETIME NULL,
    notes                 VARCHAR(500) NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at            DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_status (status),
    KEY idx_users_avatar (avatar_media_id),
    KEY idx_users_created (created_at),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Restablecimiento de contraseña.
-- Se guarda el hash del token, nunca el token en claro.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    email      VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    ip         VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_token (token_hash),
    KEY idx_password_resets_expires (expires_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
