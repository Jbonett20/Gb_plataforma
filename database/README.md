# Esquema de base de datos

Comandos:

```bash
php database/migrate.php            # aplica las migraciones pendientes
php database/migrate.php --status   # muestra qué está aplicado y qué falta
php database/verify.php             # comprueba tablas, relaciones e índices
```

Cada archivo de `migrations/` se ejecuta **una sola vez** (queda registrado en la
tabla `migrations`), por lo que puede contener `ALTER TABLE` sin riesgo de
duplicados. Los datos iniciales se cargan aparte con `database/seed.php` y son
reejecutables.

## Archivos

| Archivo | Tablas |
|---|---|
| `001_auth.sql` | `roles`, `users`, `password_resets` |
| `002_media.sql` | `media` (+ llave foránea de `users.avatar_media_id`) |
| `003_content.sql` | `modules`, `blocks`, `block_items`, `block_versions`, `settings` |
| `004_courses.sql` | `courses`, `course_modules`, `lessons`, `enrollments`, `lesson_progress` |
| `005_resources.sql` | `templates`, `template_downloads`, `videos`, `video_views` |
| `006_payments.sql` | `payments`, `webhook_events` (+ llave foránea de `enrollments.payment_id`) |
| `007_engagement.sql` | `news`, `subscribers`, `appointments`, `content_access_log`, `audit_logs`, `throttle` |

## Qué tabla alimenta cada pantalla

Esta correspondencia es la que debe respetarse al construir el panel: el
SuperAdmin administra **pantallas**, no tablas.

| Pantalla del panel | Tablas que usa |
|---|---|
| Cambiar un texto o imagen de la web | `blocks`, `block_items`, `media`, `block_versions` |
| Mostrar u ocultar secciones | `modules`, `blocks` (`status`) |
| Enlaces del menú y del pie | `blocks` + `block_items` (tipo `list`) |
| Ajustes generales del sitio | `settings` |
| Cursos y su temario | `courses`, `course_modules`, `lessons` |
| Videos | `videos`, `video_views` |
| Plantillas gratuitas | `templates`, `template_downloads` |
| Noticias | `news` |
| Suscriptores del boletín | `subscribers` |
| Solicitudes de cita | `appointments` |
| Estudiantes | `users` (rol `Student`), `enrollments`, `lesson_progress` |
| Ventas y pagos | `payments`, `enrollments`, `webhook_events` |
| Quién vio qué material | `content_access_log` |
| Historial de cambios del panel | `audit_logs` |
| Seguridad: intentos abusivos | `throttle`, `users` (`failed_login_attempts`, `locked_until`) |

## Estados de contenido

`modules`, `blocks`, `block_items`, `courses`, `templates`, `videos` y `news`
comparten la misma columna `status`:

| Estado | Efecto en el sitio público |
|---|---|
| `active` | Visible y navegable. |
| `inactive` | Oculto al público; el contenido se conserva íntegro en el panel. |
| `hidden` | No aparece en listados, pero su enlace directo sigue funcionando. |
| `deleted` | Borrado lógico. Se conserva hasta `purge_after` (30 días por defecto). |

## Convenciones

- Motor InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` en todas las tablas.
- Claves primarias `id` autoincrementales; `created_at` / `updated_at` en todas.
- Borrado lógico mediante `deleted_at` + `purge_after`, nunca `DELETE` directo
  sobre contenido publicado.
- Estados guardados como `VARCHAR(20)` con la lista cerrada documentada en el
  propio archivo `.sql`: así se puede añadir un estado sin migrar el tipo.
- Reglas de integridad: `ON DELETE CASCADE` cuando el hijo no existe sin el
  padre (`block_items`, `lessons`, `enrollments`), `RESTRICT` cuando borrar el
  padre destruiría historial (`payments` → `users`, `courses`), y
  `SET NULL` para referencias opcionales (autor, imagen, usuario que concede un
  acceso).

## Reglas que la base de datos no puede imponer y valida la aplicación

- Un curso sólo se publica si tiene título, descripción, portada, tipo de acceso
  y al menos una lección.
- El acceso a un curso de pago se activa **únicamente** desde la confirmación de
  pago verificada (`webhook_events.signature_valid = 1`).
- Un evento de webhook repetido se reconoce por `(provider, external_event_id)` y
  no produce ningún efecto nuevo.
- El material de pago vive en `storage/protected/`, fuera del webroot, y
  `lessons.protected_path` nunca se envía al navegador.
