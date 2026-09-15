<?php

declare(strict_types=1);

/**
 * Tipo de bloque sin plantilla.
 *
 * No debería ocurrir nunca: `config/admin_labels.php` declara un tipo por cada
 * bloque y cada tipo tiene su archivo. Si aparece, es que se añadió un tipo sin
 * su plantilla, y conviene que sea evidente para quien administra y no un hueco
 * silencioso en la página.
 *
 * @var array<string, mixed> $block
 */

error_log(sprintf(
    '[gbplataforma] El bloque "%s" usa el tipo "%s", que no tiene plantilla.',
    (string) ($block['key_name'] ?? '?'),
    (string) ($block['type'] ?? '?')
));
?>
<!-- Bloque "<?= e($block['key_name'] ?? '') ?>" sin plantilla: avisa al equipo técnico. -->
