<?php

declare(strict_types=1);

namespace GB\Controllers;

use GB\Models\SubscriberRepository;
use GB\Support\Request;
use GB\Support\Response;
use GB\Support\Validator;
use GB\Support\View;

final class SubscriberController extends Controller
{
    public function __construct(View $view, private SubscriberRepository $subscribers)
    {
        parent::__construct($view);
    }

    /**
     * El formulario de suscripción es una sección del pie de página. Quien
     * escriba la dirección a mano va hasta él en vez de encontrarse un error.
     */
    public function show(Request $request): Response
    {
        return $this->redirect('/#footer', 301);
    }

    public function store(Request $request): Response
    {
        $input = $request->all();
        $validator = new Validator($input, [
            'name' => 'Nombre', 'last_name' => 'Apellidos', 'phone' => 'Teléfono', 'email' => 'Correo electrónico',
        ]);
        $validator->validate([
            'name' => 'required|min:2|max:150', 'last_name' => 'max:150', 'phone' => 'max:40',
            'email' => 'required|email|max:190',
        ]);

        if ($validator->fails()) {
            return $this->respondJson(['ok' => false, 'message' => $validator->firstError() ?? 'Revisa los datos.'], 422);
        }

        $data = $validator->validated();
        $email = mb_strtolower(trim((string) $data['email']));
        if ($this->subscribers->emailExists($email)) {
            return $this->respondJson(['ok' => false, 'message' => 'Este correo ya está suscrito a nuestras novedades.'], 409);
        }

        $this->subscribers->createFromWeb([
            'name' => (string) $data['name'], 'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null, 'email' => $email, 'ip' => $request->ip(),
        ]);

        return $this->respondJson(['ok' => true, 'message' => 'Te has suscrito correctamente a nuestras novedades.']);
    }
}