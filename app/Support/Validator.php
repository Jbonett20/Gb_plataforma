<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Validación de datos recibidos.
 *
 * Todos los mensajes están redactados en español y nombran el campo tal como lo
 * ve la persona en pantalla, no como se llama la columna en la base de datos.
 *
 * Uso:
 *     $validator = new Validator($request->all(), ['email' => 'Correo electrónico']);
 *     $validator->validate(['email' => 'required|email|max:190', 'password' => 'required|min:10']);
 *
 *     if ($validator->fails()) {
 *         // $validator->errors() => ['email' => ['El campo Correo electrónico es obligatorio.']]
 *     }
 *
 * Reglas disponibles: required, email, url, numeric, integer, boolean, date,
 * min (longitud), max (longitud), in, same, confirmed, regex, gte (cantidad
 * mínima), lte (cantidad máxima).
 *
 * `min`, `max` y `gte`, `lte` no son lo mismo y no deben confundirse:
 *   - `min:10` / `max:190` miden la LONGITUD del texto. Un identificador
 *     numérico de nueve cifras cumple `max:120`.
 *   - `gte:0` / `lte:100` comparan la CANTIDAD. Un precio de 1500 no cumple
 *     `lte:100`.
 */
final class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $labels nombre visible de cada campo
     */
    public function __construct(
        private array $data,
        private array $labels = [],
    ) {
    }

    /**
     * @param array<string, string> $rules campo => reglas separadas por "|"
     */
    public function validate(array $rules): self
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            // Un campo vacío y no obligatorio no se valida más.
            if (!$this->hasRule($ruleSet, 'required') && ($value === null || $value === '')) {
                $this->validated[$field] = $value === '' ? null : $value;

                continue;
            }

            foreach (explode('|', $ruleSet) as $rule) {
                if ($rule === '') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->rulePasses($name, $value, $parameter, $field)) {
                    $this->errors[$field][] = $this->message($name, $field, $parameter);
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Lista plana de mensajes, para mostrar junto al formulario.
     *
     * @return array<int, string>
     */
    public function messages(): array
    {
        return array_merge(...array_values(array_map(
            static fn (array $messages): array => $messages,
            $this->errors
        ))) ?: [];
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    private function hasRule(string $ruleSet, string $rule): bool
    {
        foreach (explode('|', $ruleSet) as $candidate) {
            if ($candidate === $rule || str_starts_with($candidate, $rule . ':')) {
                return true;
            }
        }

        return false;
    }

    private function rulePasses(string $rule, mixed $value, ?string $parameter, string $field): bool
    {
        return match ($rule) {
            'required' => !($value === null || $value === '' || $value === []),
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var((string) $value, FILTER_VALIDATE_URL) !== false,
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true),
            'date' => is_string($value) && strtotime($value) !== false,
            'min' => $this->length($value) >= (int) $parameter,
            'max' => $this->length($value) <= (int) $parameter,
            'gte' => $this->amount($value) >= (float) $parameter,
            'lte' => $this->amount($value) <= (float) $parameter,
            'in' => in_array((string) $value, explode(',', (string) $parameter), true),
            'same' => ($this->data[$parameter ?? ''] ?? null) === $value,
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
            'regex' => is_string($value) && preg_match((string) $parameter, $value) === 1,
            default => true,
        };
    }

    /**
     * Longitud de un texto, o número de elementos de una lista.
     *
     * Se mide siempre como texto: un identificador numérico de nueve cifras
     * mide nueve, no nueve millones. Para comparar cantidades están `gte` y `lte`.
     */
    private function length(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        return mb_strlen((string) $value);
    }

    /** Cantidad numérica de un valor, para las reglas gte y lte. */
    private function amount(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function message(string $rule, string $field, ?string $parameter): string
    {
        $label = $this->label($field);

        return match ($rule) {
            'required' => sprintf('El campo %s es obligatorio.', $label),
            'email' => sprintf('%s no tiene un formato de correo válido.', $label),
            'url' => sprintf('%s no tiene un formato de dirección web válido.', $label),
            'numeric' => sprintf('%s debe ser un número.', $label),
            'integer' => sprintf('%s debe ser un número entero.', $label),
            'boolean' => sprintf('%s debe ser un valor de sí o no.', $label),
            'date' => sprintf('%s no es una fecha válida.', $label),
            'min' => sprintf('%s debe tener al menos %s caracteres.', $label, (string) $parameter),
            'max' => sprintf('%s no puede tener más de %s caracteres.', $label, (string) $parameter),
            'gte' => sprintf('%s debe ser %s o más.', $label, (string) $parameter),
            'lte' => sprintf('%s debe ser %s o menos.', $label, (string) $parameter),
            'in' => sprintf('El valor elegido en %s no es una opción válida.', $label),
            'same' => sprintf('%s no coincide con el campo relacionado.', $label),
            'confirmed' => sprintf('La confirmación de %s no coincide.', $label),
            'regex' => sprintf('%s no tiene el formato esperado.', $label),
            default => sprintf('%s no es válido.', $label),
        };
    }

    private function label(string $field): string
    {
        $label = $this->labels[$field] ?? null;

        if (is_string($label) && $label !== '') {
            return $label;
        }

        // Respaldo legible: "last_name" -> "Last name".
        return mb_strtolower(str_replace('_', ' ', preg_replace('/_id$/', '', $field) ?? $field));
    }
}
