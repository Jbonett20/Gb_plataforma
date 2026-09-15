<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\CourseRepository;
use GB\Models\ContentAccessRepository;
use GB\Services\EnrollmentService;
use GB\Services\ProtectedTokenService;
use GB\Services\WatermarkedGuideService;
use GB\Support\Auth;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;

final class ProtectedContentController extends Controller
{
    public function __construct(
        View $view,
        private CourseRepository $courses,
        private EnrollmentService $enrollments,
        private ProtectedTokenService $tokens,
        private Auth $auth,
        private ContentAccessRepository $accessLog,
        private WatermarkedGuideService $guides,
    ) {
        parent::__construct($view);
    }

    public function lesson(Request $request): Response
    {
        $user = $this->auth->user();
        $lessonId = (int) $request->routeParam('lesson');
        $lesson = $this->courses->protectedLesson($lessonId);
        $token = $request->string('t');

        if ($user === null || $lesson === null || $token === '') {
            $this->accessLog->record($user === null ? null : (int) $user['id'], $lesson === null ? null : (int) $lesson['course_id'], $lessonId, 'view', false, 'session_or_token', $request->ip(), $request->userAgent());
            return Response::html('Contenido no disponible.', 404);
        }

        $courseId = (int) $lesson['course_id'];
        if (!$this->enrollments->hasAccess((int) $user['id'], $courseId)
            || $this->tokens->validate($token, (int) $user['id'], 'lesson', $lessonId, $request->ip()) === null) {
            $this->accessLog->record((int) $user['id'], $courseId, $lessonId, 'view', false, 'access_denied', $request->ip(), $request->userAgent());
            return Response::html('No tienes permiso para ver este contenido.', 403);
        }

        $root = realpath(GB_STORAGE_PATH . '/protected');
        $file = realpath(GB_STORAGE_PATH . '/protected/' . ltrim((string) ($lesson['protected_path'] ?? ''), '/\\'));
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            $this->accessLog->record((int) $user['id'], $courseId, $lessonId, 'view', false, 'file_missing', $request->ip(), $request->userAgent());
            return Response::html('Contenido no disponible.', 404);
        }

        $size = filesize($file);
        if ($size === false || $size < 1) {
            return Response::html('Contenido no disponible.', 404);
        }

        $range = $this->byteRange($request->header('Range'), $size);
        if ($range === null && $request->header('Range') !== null) {
            return new Response('', 416, [
                'Content-Range' => 'bytes */' . $size,
                'Accept-Ranges' => 'bytes',
            ]);
        }

        $start = $range[0] ?? 0;
        $end = $range[1] ?? $size - 1;
        $length = $end - $start + 1;
        $handle = fopen($file, 'rb');
        if ($handle === false || fseek($handle, $start) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return Response::html('Contenido no disponible.', 404);
        }

        if ((string) $lesson['content_type'] === 'document' && strtolower((string) mime_content_type($file)) === 'application/pdf') {
            fclose($handle);
            try {
                $content = $this->guides->render($file, trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')));
            } catch (\RuntimeException) {
                $this->accessLog->record((int) $user['id'], $courseId, $lessonId, 'view', false, 'watermark_unavailable', $request->ip(), $request->userAgent());
                return Response::html('La guía no está disponible temporalmente.', 503);
            }
            $status = 200;
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($content),
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ];
            $this->accessLog->record((int) $user['id'], $courseId, $lessonId, 'view', true, null, $request->ip(), $request->userAgent());
            return new Response($content, $status, $headers);
        }

        $content = fread($handle, $length);
        fclose($handle);
        if ($content === false) {
            $this->accessLog->record((int) $user['id'], $courseId, $lessonId, 'view', false, 'read_failed', $request->ip(), $request->userAgent());
            return Response::html('Contenido no disponible.', 404);
        }

        $this->accessLog->record((int) $user['id'], $courseId, $lessonId, 'view', true, null, $request->ip(), $request->userAgent());

        $status = $range === null ? 200 : 206;
        $headers = [
            'Content-Type' => mime_content_type($file) ?: 'application/octet-stream',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($range !== null) {
            $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $size;
        }

        return new Response($content, $status, $headers);
    }

    public function player(Request $request): Response
    {
        $user = $this->auth->user();
        $lessonId = (int) $request->routeParam('lesson');
        $lesson = $this->courses->protectedLesson($lessonId);
        $token = $request->string('t');

        if ($user === null || $lesson === null || (string) $lesson['content_type'] !== 'video'
            || $token === '' || !$this->enrollments->hasAccess((int) $user['id'], (int) $lesson['course_id'])
            || $this->tokens->validate($token, (int) $user['id'], 'lesson', $lessonId, $request->ip()) === null) {
            return Response::html('Contenido no disponible.', 403);
        }

        return $this->respond('protected.player', [
            'title' => (string) $lesson['title'] . ' | Aula',
            'lesson' => $lesson,
            'source' => url('contenido/lecciones/' . $lessonId . '?t=' . rawurlencode($token)),
        ], 'layouts.public');
    }

    /** @return array{0: int, 1: int}|null */
    private function byteRange(?string $header, int $size): ?array
    {
        if ($header === null || !str_starts_with(strtolower($header), 'bytes=')) {
            return $header === null ? null : null;
        }

        $value = trim(substr($header, 6));
        if (str_contains($value, ',')) {
            return null;
        }

        [$start, $end] = array_pad(explode('-', $value, 2), 2, '');
        if ($start === '' && $end === '') {
            return null;
        }

        if ($start === '') {
            $suffix = (int) $end;
            return $suffix > 0 ? [max(0, $size - $suffix), $size - 1] : null;
        }

        if (!ctype_digit($start)) {
            return null;
        }

        $first = (int) $start;
        if ($first >= $size) {
            return null;
        }

        $last = $end === '' ? $size - 1 : (ctype_digit($end) ? (int) $end : -1);
        if ($last < $first) {
            return null;
        }

        return [$first, min($last, $size - 1)];
    }
}