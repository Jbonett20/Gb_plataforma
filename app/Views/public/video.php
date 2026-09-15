<?php
declare(strict_types=1);
$video = is_array($video ?? null) ? $video : [];
$duration = (int) ($video['duration_seconds'] ?? 0);
$durationLabel = $duration > 0 ? floor($duration / 60) . ':' . str_pad((string) ($duration % 60), 2, '0', STR_PAD_LEFT) : '';
?>
<section class="section bg-light-section">
	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-lg-9">
				<p class="eyebrow text-brand"><?= e((string) ($video['category'] ?? 'Video')) ?></p>
				<h1><?= e((string) $video['title']) ?></h1>
				<?php if ($video['summary'] !== null): ?><p class="lead text-muted"><?= e((string) $video['summary']) ?></p><?php endif; ?>
				<div class="ratio ratio-16x9 shadow-sm rounded overflow-hidden mb-4" data-video-player
						 data-video-url="<?= e($embedUrl) ?>"
						 data-video-record-url="<?= e(url('videos/' . $video['slug'] . '/reproducir')) ?>">
					<div class="d-flex align-items-center justify-content-center bg-dark">
						<button type="button" class="btn btn-light" data-video-play>
							<i class="bi bi-play-fill me-1"></i>Reproducir video
						</button>
					</div>
				</div>
				<?php if (!empty($video['description'])): ?><div class="content-copy mb-3"><?= nl2br(e((string) $video['description'])) ?></div><?php endif; ?>
				<?php if ($durationLabel !== ''): ?><p class="small text-muted">Duración: <?= e($durationLabel) ?></p><?php endif; ?>
			</div>
		</div>
	</div>
</section>
<script>
document.querySelectorAll('[data-video-player]').forEach(function (container) {
	var button = container.querySelector('[data-video-play]');
	button.addEventListener('click', function () {
		button.disabled = true;
		fetch(container.dataset.videoRecordUrl, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
				'Accept': 'application/json'
			}
		}).finally(function () {
			container.innerHTML = '<iframe src="' + container.dataset.videoUrl + '" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
		});
	});
});
</script>