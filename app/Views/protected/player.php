<?php
declare(strict_types=1);
$lesson = is_array($lesson ?? null) ? $lesson : [];
?>
<section class="section bg-light-section"><div class="container py-5"><div class="row justify-content-center"><div class="col-lg-10"><p class="eyebrow text-brand">Aula privada</p><h1><?= e((string) ($lesson['title'] ?? 'Lección')) ?></h1><div class="ratio ratio-16x9 bg-dark rounded overflow-hidden shadow-sm"><video controls controlsList="nodownload noplaybackrate" disablePictureInPicture playsinline preload="metadata" oncontextmenu="return false"><source src="<?= e($source) ?>" type="video/mp4">Tu navegador no puede reproducir este video.</video></div><p class="small text-muted mt-3">Contenido protegido para tu sesión.</p></div></div></div></section>