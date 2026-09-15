-- =============================================================================
-- 006 — Compras y pagos
-- Tablas: payments, webhook_events
--
-- Regla de oro: el acceso a un curso de pago se activa ÚNICAMENTE cuando llega
-- una confirmación de pago verificada desde la pasarela. La página de retorno
-- del navegador sólo informa; nunca concede acceso.
--
-- `webhook_events` tiene índice único por (provider, external_event_id), de modo
-- que un reenvío repetido de la pasarela se reconoce y no duplica nada.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Órdenes de compra.
--   status : pending | paid | failed | refunded | cancelled
-- `reference` es la referencia propia que se le muestra al estudiante y que se
-- envía a la pasarela.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference        VARCHAR(64) NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    course_id        BIGINT UNSIGNED NOT NULL,
    gateway          VARCHAR(20) NOT NULL,             -- wompi | payu | stripe | paypal | manual
    external_id      VARCHAR(190) NULL,                -- identificador de la transacción en la pasarela
    amount           DECIMAL(12,2) NOT NULL,
    currency         CHAR(3) NOT NULL DEFAULT 'COP',
    discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    coupon_code      VARCHAR(60) NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'pending',
    status_message   VARCHAR(255) NULL,
    paid_at          DATETIME NULL,
    refunded_at      DATETIME NULL,
    refunded_by      BIGINT UNSIGNED NULL,
    refund_reason    VARCHAR(255) NULL,
    gateway_payload  JSON NULL,
    ip               VARCHAR(45) NULL,
    user_agent       VARCHAR(255) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_reference (reference),
    UNIQUE KEY uq_payments_gateway_external (gateway, external_id),
    KEY idx_payments_user (user_id, created_at),
    KEY idx_payments_course (course_id),
    KEY idx_payments_status (status, created_at),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_refunded_by FOREIGN KEY (refunded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Ahora que `payments` existe se cierra la referencia desde `enrollments`.
-- -----------------------------------------------------------------------------
ALTER TABLE enrollments
    ADD CONSTRAINT fk_enrollments_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- Confirmaciones recibidas de la pasarela.
--
-- `signature_valid` deja constancia de si la autenticidad pudo comprobarse.
-- Un evento inválido se guarda igualmente (para poder auditarlo) pero nunca
-- concede acceso.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_events (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider          VARCHAR(20)  NOT NULL,
    external_event_id VARCHAR(190) NOT NULL,
    event_type        VARCHAR(80)  NULL,
    payment_reference VARCHAR(64)  NULL,
    signature_valid   TINYINT(1)   NOT NULL DEFAULT 0,
    processed         TINYINT(1)   NOT NULL DEFAULT 0,
    processed_at      DATETIME     NULL,
    result            VARCHAR(255) NULL,               -- qué hizo el sistema con el evento
    payload           JSON NULL,
    ip                VARCHAR(45)  NULL,
    attempt_count     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_events (provider, external_event_id),
    KEY idx_webhook_events_reference (payment_reference),
    KEY idx_webhook_events_processed (processed, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
