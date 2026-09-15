<?php

declare(strict_types=1);

namespace GB\Controllers\Admin;

use GB\Controllers\Controller;
use GB\Models\ModuleRepository;
use GB\Services\ModuleAccessService;
use GB\Services\TrashService;
use GB\Services\ViewCache;
use GB\Support\Audit;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\View;
use Throwable;

/**
 * Visibilidad de los módulos del sitio.
 *
 * Un módulo no se borra para dejar de mostrarse: cambia de estado. AsÃ el
 * contenido se conserva y el sitio nunca queda con enlaces rotos ni huecos en
 * la maquetaciÃ³n.
 *
 * Borrar de verdad es otra cosa y tiene su propio camino en dos pasos (tarea
 * 11.6): primero el mÃ³dulo va a la papelera y solo desde ahÃ se puede borrar
 * definitivamente, avisando de lo que se pierde y sin vuelta atrÃ¡s.
 */
final class ModuleController extends Controller
{
    /** Estados que se pueden aplicar desde esta pantalla. */
    private const STATES = ['active', 'inactive', 'hidden', 'deleted'];

    public function __construct(
        View $view,
        private ModuleRepository $modules,
        private ViewCache $cache,
        private Audit $audit,
        private ModuleAccessService $access,
        private TrashService $trash,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->respond('admin.modules', $this->pageData([
            'title' => 'Módulos del sitio | Panel',
            // Se incluyen los que están en la papelera. Si desaparecieran de la
            // lista no habría forma de recuperarlos ni de borrarlos del todo, y
            // la promesa de «se puede recuperar durante 30 días» quedaría en nada.
            'modules' => $this->modules->all(true),
            'states' => (array) config('admin_labels.statuses', []),
            'message' => '',
        ]), 'layouts.admin');
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $module = $this->modules->findById($id);
        $status = (string) $request->string('status');

        if ($module === null || !in_array($status, self::STATES, true)) {
            return $this->redirectWith('/admin/modulos', 'Ese módulo ya no existe.', 'warning');
        }

        $days = (int) config('app.trash_retention_days', 30);

        if ($status === 'deleted') {
            $this->modules->moveToTrash($id, $days);
        } else {
            // Volver a mostrarlo tiene que sacarlo de la papelera: si no, el
            // módulo seguiría con fecha de borrado y desaparecería igual.
            if (($module['deleted_at'] ?? null) !== null) {
                $this->modules->restoreFromTrash($id);
            }

            $this->modules->setStatus($id, $status);
        }

        // El menú del sitio se genera a partir de estos estados: la caché de
        // páginas públicas deja de ser válida en cuanto uno cambia.
        $this->cache->flush();

        $this->record($module, $status);

        $label = (string) (config('admin_labels.statuses.' . $status . '.label') ?? $status);
        $help = (string) (config('admin_labels.statuses.' . $status . '.help') ?? '');

        return $this->redirectWith('/admin/modulos', sprintf(
            'Listo: «%s» ahora está en «%s».%s',
            (string) $module['label'],
            $label,
            $status === 'deleted'
                ? sprintf(' El contenido no se borra todavía: tienes %d días para recuperarlo.', $days)
                : ' ' . $help
        ), $status === 'deleted' ? 'warning' : 'success');
    }

    /**
     * Primer paso del borrado definitivo: la pantalla de advertencia.
     *
     * Sólo se llega aquí si el módulo ya está en la papelera: borrar de verdad
     * exige haber pasado antes por el estado «Eliminado», y eso son los dos pasos.
     */
    public function confirmDelete(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $module = $this->modules->findById($id);

        if ($module === null) {
            return $this->redirectWith('/admin/modulos', 'Ese módulo ya no existe.', 'warning');
        }

        if (($module['deleted_at'] ?? null) === null) {
            return $this->redirectWith(
                '/admin/modulos',
                'Para borrar definitivamente hay que dar dos pasos: primero marca el módulo como «Eliminado» y después confirma el borrado.',
                'warning'
            );
        }

        $impact = $this->access->impact((string) $module['key_name']);

        $token = $this->trash->requestConfirmation(
            'module',
            $id,
            (string) $module['label'],
            $impact['summary']
        );

        return $this->admin('admin.module-delete', [
            'title' => 'Borrar definitivamente | Panel',
            'module' => $module,
            'impact' => $impact,
            'token' => $token,
        ]);
    }

    /**
     * Segundo paso: se borra de verdad, y sólo con la confirmación pedida.
     */
    public function deletePermanently(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $module = $this->modules->findById($id);

        if ($module === null) {
            return $this->redirectWith('/admin/modulos', 'Ese módulo ya no existe.', 'warning');
        }

        if (!$request->bool('acknowledge')) {
            return $this->redirectWith(
                '/admin/modulos/' . $id . '/borrar',
                'Falta marcar la casilla que confirma que entiendes que la acción es irreversible.',
                'warning'
            );
        }

        $pending = $this->trash->consume((string) $request->string('token'));

        if ($pending === null || $pending['type'] !== 'module' || (int) $pending['id'] !== $id) {
            return $this->redirectWith(
                '/admin/modulos',
                'La confirmación no es válida o ya caducó. Vuelve a abrir la pantalla de borrado.',
                'danger'
            );
        }

        try {
            $this->access->purge((string) $module['key_name']);
        } catch (Throwable $exception) {
            return $this->redirectWith('/admin/modulos', $exception->getMessage(), 'danger');
        }

        $this->modules->deletePermanently($id);
        $this->cache->flush();

        $this->record($module, 'purged');

        return $this->redirectWith('/admin/modulos', sprintf(
            'El módulo «%s» y su contenido se borraron definitivamente. Esta acción no se puede deshacer.',
            (string) $module['label']
        ), 'warning');
    }

    /** @param array<string, mixed> $module */
    private function record(array $module, string $status): void
    {
        try {
            $this->audit->log(
                action: $status === 'purged' ? 'module_purged' : 'module_status_changed',
                entityType: 'module',
                entityId: (int) $module['id'],
                summary: $status === 'purged'
                    ? sprintf('Módulo «%s» y su contenido borrados definitivamente', (string) $module['label'])
                    : sprintf('Módulo «%s» pasa a %s', (string) $module['label'], $status)
            );
        } catch (Throwable) {
            // La auditoría es informativa: el cambio ya está aplicado.
        }
    }

    /** @param array<string, mixed> $data */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return $this->respond($view, $this->pageData($data), 'layouts.admin', $status);
    }
}
