-- =============================================================================
-- 010 — Recorrido guiado del primer ingreso al panel
--
-- El recorrido de tareas básicas sólo debe aparecer la primera vez que una
-- cuenta entra al panel. Guardar la marca en la base de datos (y no en la
-- sesión) evita que vuelva a aparecer cada vez que la persona inicia sesión,
-- que es justo lo contrario de lo que se busca.
-- =============================================================================

ALTER TABLE users
    ADD COLUMN onboarding_completed_at DATETIME NULL DEFAULT NULL
        COMMENT 'Cuándo se completó u omitió el recorrido guiado del panel' AFTER notes;
