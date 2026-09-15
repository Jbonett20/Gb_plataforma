<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Requisitos mínimos de contraseña.
 *
 * Es la única fuente de verdad: el formulario muestra esta misma lista y la
 * validación la comprueba, así que lo que se explica en pantalla y lo que se
 * exige no pueden separarse.
 *
 * Nunca se guarda ni se muestra la contraseña: sólo se comprueba y se
 * encripta con password_hash().
 */
final class PasswordPolicy
{
    /**
     * @param int $minLength longitud mínima; por defecto la de config('app.security')
     */
    public function __construct(private int $minLength = 10)
    {
        $this->minLength = max(8, $this->minLength);
    }

    /**
     * Lista de requisitos con su texto, para imprimirla junto al formulario.
     *
     * @return array<int, array{key: string, text: string}>
     */
    public function requirements(): array
    {
        return [
            ['key' => 'length', 'text' => sprintf('Al menos %d caracteres.', $this->minLength)],
            ['key' => 'letter', 'text' => 'Al menos una letra.'],
            ['key' => 'number', 'text' => 'Al menos un número.'],
            ['key' => 'different', 'text' => 'Distinta de tu correo electrónico.'],
        ];
    }

    /**
     * Requisitos que NO cumple la contraseña. Lista vacía = contraseña válida.
     *
     * @return array<int, string> textos de los requisitos incumplidos
     */
    public function violations(string $password, string $email = ''): array
    {
        $failures = [];

        if (mb_strlen($password) < $this->minLength) {
            $failures[] = 'length';
        }

        if (preg_match('/\p{L}/u', $password) !== 1) {
            $failures[] = 'letter';
        }

        if (preg_match('/\d/', $password) !== 1) {
            $failures[] = 'number';
        }

        if ($email !== '' && mb_strtolower(trim($password)) === mb_strtolower(trim($email))) {
            $failures[] = 'different';
        }

        $texts = [];

        foreach ($this->requirements() as $requirement) {
            if (in_array($requirement['key'], $failures, true)) {
                $texts[] = $requirement['text'];
            }
        }

        return $texts;
    }

    public function passes(string $password, string $email = ''): bool
    {
        return $this->violations($password, $email) === [];
    }

    /**
     * Mensaje único y claro para mostrar junto al campo.
     */
    public function message(string $password, string $email = ''): string
    {
        $violations = $this->violations($password, $email);

        if ($violations === []) {
            return '';
        }

        return 'La contraseña todavía no cumple: ' . mb_strtolower(implode(' ', $violations));
    }

    /**
     * Encripta la contraseña. Es irreversible: no existe ninguna operación en el
     * sistema que devuelva el texto original.
     */
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
