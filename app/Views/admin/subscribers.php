<?php
declare(strict_types=1);
$listing = is_array($subscribers ?? null) ? $subscribers : [];
$items = is_array($listing['items'] ?? null) ? $listing['items'] : [];
$page = (int) ($listing['page'] ?? 1);
$pages = (int) ($listing['pages'] ?? 1);
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Suscriptores</h1><p class="text-muted mb-0">Personas inscritas a las novedades.</p></div><a class="btn btn-primary" href="<?= e(url('admin/suscriptores/exportar')) ?>"><i class="bi bi-download me-1"></i>Exportar CSV</a></div>
<div class="admin-card card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Estado</th><th>Alta</th></tr></thead><tbody><?php foreach ($items as $subscriber): ?><tr><td><?= e(trim((string) $subscriber['name'] . ' ' . (string) ($subscriber['last_name'] ?? ''))) ?></td><td><?= e((string) $subscriber['email']) ?></td><td><?= e((string) ($subscriber['phone'] ?? '')) ?></td><td><?= e((string) $subscriber['status']) ?></td><td><?= e((string) $subscriber['created_at']) ?></td></tr><?php endforeach; ?><?php if ($items === []): ?><tr><td colspan="5" class="text-center py-4">Todavía no hay suscriptores.</td></tr><?php endif; ?></tbody></table></div></div>
<?php if ($pages > 1): ?><nav class="mt-4" aria-label="Páginas de suscriptores"><ul class="pagination justify-content-center"><?php for ($number = 1; $number <= $pages; $number++): ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('admin/suscriptores?page=' . $number)) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>