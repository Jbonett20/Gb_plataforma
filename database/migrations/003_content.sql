-- =============================================================================
-- 003 — Contenido del sitio público
-- Tablas: modules, blocks, block_items, block_versions, settings
--
-- El sitio público no tiene texto fijo en el código: todo lo que se ve proviene
-- de estas tablas y se edita desde el panel.
--
-- Estados posibles de módulos y bloques (`status`):
--   active   : visible y navegable en el sitio público.
--   inactive : oculto al público; el contenido se conserva íntegro en el panel.
--   hidden   : no aparece en listados, pero su enlace directo sigue vigente.
--   deleted  : borrado lógico; se conserva hasta `purge_after` (por defecto 30 días).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Módulos: agrupan un tipo de contenido y permiten ocultarlo entero de una vez.
-- Ejemplos: courses (Cursos), templates (Plantillas), videos (Videos), news (Noticias).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS modules (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name     VARCHAR(60)  NOT NULL,
    label        VARCHAR(120) NOT NULL,              -- "Cursos": lo que ve el SuperAdmin
    menu_label   VARCHAR(120) NULL,                  -- texto del enlace en el menú público
    description  VARCHAR(255) NULL,                  -- explicación en lenguaje sencillo para el panel
    status       VARCHAR(20)  NOT NULL DEFAULT 'active',
    sort_order   SMALLINT     NOT NULL DEFAULT 0,
    show_in_menu TINYINT(1)   NOT NULL DEFAULT 1,
    show_on_home TINYINT(1)   NOT NULL DEFAULT 1,
    deleted_at   DATETIME     NULL,
    purge_after  DATETIME     NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_modules_key_name (key_name),
    KEY idx_modules_status_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Bloques: cada sección editable de la página (inicio, visión, servicios...).
--
-- `type` decide qué parcial de vista lo dibuja. Valores previstos:
--   hero         : carrusel de diapositivas
--   text_image   : título, texto e imagen
--   card_grid    : cuadrícula de tarjetas
--   list         : lista simple de elementos
--   cta          : bloque de llamada a la acción con imagen de fondo
--   faq          : acordeón de preguntas frecuentes
--   contact_form : datos de contacto y formulario
--   testimonial  : opiniones o clientes
--   stats        : cifras destacadas
--
-- `settings` guarda las opciones del bloque en JSON (intervalo del carrusel,
-- número de columnas, fondo claro u oscuro, etc.).
-- `published_version_id` apunta a la versión publicada: es lo que permite
-- "Deshacer" y volver a la anterior.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocks (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_id            SMALLINT UNSIGNED NULL,      -- NULL = bloque institucional del sitio
    key_name             VARCHAR(60)  NOT NULL,
    label                VARCHAR(150) NOT NULL,       -- "Bloque de inicio"
    help_text            VARCHAR(500) NULL,           -- "Esta es la primera imagen que ve el visitante"
    type                 VARCHAR(40)  NOT NULL,
    template             VARCHAR(60)  NULL,           -- parcial alternativo dentro de app/Views/blocks
    sort_order           SMALLINT     NOT NULL DEFAULT 0,
    status               VARCHAR(20)  NOT NULL DEFAULT 'active',
    settings             JSON         NULL,
    published_version_id BIGINT UNSIGNED NULL,
    published_at         DATETIME     NULL,
    published_by         BIGINT UNSIGNED NULL,
    deleted_at           DATETIME     NULL,
    purge_after          DATETIME     NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blocks_key_name (key_name),
    KEY idx_blocks_status_order (status, sort_order),
    KEY idx_blocks_module (module_id),
    CONSTRAINT fk_blocks_module FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE SET NULL,
    CONSTRAINT fk_blocks_published_by FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Elementos repetibles de un bloque: diapositivas del inicio, servicios,
-- integrantes del equipo, clientes, casos, preguntas frecuentes, enlaces del pie.
--
-- Las columnas con nombre (title, body, media_id...) cubren los casos comunes de
-- modo que el panel pueda mostrarlas con una etiqueta clara; `data` queda para
-- campos propios de cada tipo de bloque.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS block_items (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    block_id    INT UNSIGNED NOT NULL,
    media_id    BIGINT UNSIGNED NULL,
    title       VARCHAR(255) NULL,
    subtitle    VARCHAR(255) NULL,
    body        TEXT NULL,
    link_url    VARCHAR(500) NULL,
    link_label  VARCHAR(120) NULL,
    icon        VARCHAR(60)  NULL,                    -- clase de Bootstrap Icons, por ejemplo "bi bi-lightbulb-fill"
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    status      VARCHAR(20)  NOT NULL DEFAULT 'active',
    data        JSON         NULL,
    deleted_at  DATETIME     NULL,
    purge_after DATETIME     NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_block_items_block (block_id, sort_order),
    KEY idx_block_items_status (status),
    CONSTRAINT fk_block_items_block FOREIGN KEY (block_id) REFERENCES blocks (id) ON DELETE CASCADE,
    CONSTRAINT fk_block_items_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Historial de versiones publicadas.
-- Cada publicación guarda una copia completa del bloque y sus elementos, para
-- poder restaurar la versión anterior desde el panel.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS block_versions (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    block_id       INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot       JSON NOT NULL,
    note           VARCHAR(255) NULL,
    published_by   BIGINT UNSIGNED NULL,
    published_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_block_versions (block_id, version_number),
    CONSTRAINT fk_block_versions_block FOREIGN KEY (block_id) REFERENCES blocks (id) ON DELETE CASCADE,
    CONSTRAINT fk_block_versions_user FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Ajustes del sitio editables desde el panel: nombre, correo, teléfonos, redes,
-- moneda, aviso de cookies, etc. `label` y `help_text` son los textos que ve el
-- SuperAdmin, para que nunca tenga que interpretar un nombre técnico.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name    VARCHAR(80)  NOT NULL,
    value       TEXT         NULL,
    type        VARCHAR(20)  NOT NULL DEFAULT 'string',  -- string | int | bool | json | html | media
    group_name  VARCHAR(60)  NOT NULL DEFAULT 'general',
    label       VARCHAR(150) NOT NULL,
    help_text   VARCHAR(500) NULL,
    is_public   TINYINT(1)   NOT NULL DEFAULT 0,         -- 1 = puede mostrarse en el sitio público
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    updated_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key_name (key_name),
    KEY idx_settings_group (group_name, sort_order),
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
