<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Cómo se guarda el intento de una operación con formulario.
 *
 * Todas las pantallas de cuenta siguen el mismo patrón: validar, actuar y
 * volver a la vista con los datos escritos (para no obligar a teclearlo todo de
 * nuevo) y con los errores ya en español.
 *
 * Este objeto evita repetir esa mecánica en cada controlador y, sobre todo,
 * impide que una contraseña llegue a la vista: `values()` nunca la incluye.
 *
 * Cuando una operación sí tiene que enseñar un dato sensible una sola vez —por
 * ejemplo la contraseña temporal de una cuenta recién creada—, se entrega por
 * `secret()`, que hay que pedir a propósito. Así no se cuela por descuido al
 * repintar un formulario.
 */
final class FormResult
{
    /** @var array<string, mixed> */
    private array $values;

    /** @var array<string, string> datos que sólo debe verse una vez */
    private array $secrets;

    /** @var array<string, array<int, string>> */
    private array $errors;

    private string $message;

    private bool $succeeded;

    /**
     * @param array<string, mixed> $values datos para repintar el formulario
     * @param array<string, array<int, string>> $errors
     * @param array<string, string> $secrets datos sensibles de un solo uso
     */
    public function __construct(
        bool $succeeded,
        array $values = [],
        array $errors = [],
        string $message = '',
        array $secrets = [],
    ) {
        $this->succeeded = $succeeded;
        $this->errors = $errors;
        $this->message = $message;
        $this->values = self::withoutSecrets($values);
        $this->secrets = $secrets;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $secrets
     */
    public static function ok(array $values = [], string $message = '', array $secrets = []): self
    {
        return new self(true, $values, [], $message, $secrets);
    }

    /**
     * @param array<string, array<int, string>> $errors
     * @param array<string, mixed> $values
     */
    public static function failed(array $errors, array $values = [], string $message = ''): self
    {
        return new self(false, $values, $errors, $message);
    }

    public function succeeded(): bool
    {
        return $this->succeeded;
    }

    public function hasFailed(): bool
    {
        return !$this->succeeded;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }

        return null;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Dato sensible de un solo uso, si la operación dejó alguno.
     *
     * Hay que pedirlo por su nombre: así no aparece nunca en el repintado de un
     * formulario ni en un volcado de datos por descuido.
     */
    public function secret(string $key): ?string
    {
        $value = $this->secrets[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function withoutSecrets(array $values): array
    {
        foreach (['password', 'password_confirmation', 'current_password', 'new_password', 'token'] as $secret) {
            unset($values[$secret]);
        }

        return $values;
    }
}
