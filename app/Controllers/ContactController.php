<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\AppointmentRepository;
use GB\Support\Audit;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;
use Throwable;

/**
 * Formulario de contacto del sitio público.
 *
 * La ruta lleva los middleware `csrf` y `throttle:contact`, así que aquí sólo
 * queda validar, guardar y responder. Las respuestas son siempre en español y
 * pensadas para mostrarse junto al formulario.
 */
final class ContactController extends Controller
{
    public function __construct(
        View $view,
        private AppointmentRepository $appointments,
        private Audit $audit,
    ) {
        parent::__construct($view);
    }

    /**
     * El formulario de contacto es una sección de la portada, no una página
     * aparte. Quien escriba la dirección a mano —o llegue desde un enlace
     * antiguo— va hasta él en lugar de encontrarse una página de error.
     */
    public function show(Request $request): Response
    {
        return $this->redirect('/#contact', 301);
    }

    public function send(Request $request): Response
    {
        $validator = new Validator($request->all(), [            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'subject' => 'Asunto',
            'message' => 'Mensaje',
        ]);

        $validator->validate([
            'name' => 'required|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'max:40',
            'subject' => 'required|max:200',
            'message' => 'required|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->respondJson([
                'error' => true,
                'title' => 'Revisa los datos',
                'message' => $validator->firstError() ?? 'Revisa los datos del formulario.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $id = $this->appointments->create([
            'name' => (string) $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
            'status' => 'new',
            'source' => 'web',
            'ip' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->audit->log(
                action: 'contact_request',
                entityType: 'appointment',
                entityId: $id,
                summary: sprintf('Nueva solicitud de cita de %s', $data['name'])
            );
        } catch (Throwable) {
            // El registro es informativo: el mensaje ya quedó guardado.
        }

        return $this->respondJson([
            'error' => false,
            'message' => 'Recibimos tu mensaje. Te contactaremos muy pronto.',
        ]);
    }
}
