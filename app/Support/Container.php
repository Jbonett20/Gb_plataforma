<?php

declare(strict_types=1);

namespace GB\Support;

use RuntimeException;

/**
 * Contenedor de dependencias mínimo.
 *
 * No pretende ser un contenedor de inyección completo: basta para registrar
 * servicios compartidos (conexión, vistas, servicios de dominio) y para que el
 * enrutador construya controladores con sus dependencias.
 */
final class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /** @param callable(self): mixed $factory */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    /** @param callable(self): mixed $factory */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = static function (self $container) use ($factory): mixed {
            return $factory($container);
        };
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $this->instances[$id] = ($this->bindings[$id])($this);

            return $this->instances[$id];
        }

        if (class_exists($id)) {
            // Se construye resolviendo sus dependencias: nunca con `new` a
            // secas, porque las clases de datos y los controladores reciben
            // servicios por el constructor.
            return $this->build($id);
        }

        throw new RuntimeException(sprintf('El contenedor no sabe construir "%s".', $id));
    }

    /**
     * Construye una clase resolviendo recursivamente sus dependencias tipadas
     * del constructor. Es lo que permite escribir controladores con parámetros
     * como `public function __construct(private View $view)`.
     */
    public function build(string $class): object
    {
        if (array_key_exists($class, $this->instances)) {
            return $this->instances[$class];
        }

        // Si hay una definición registrada, se respeta: construirla por
        // reflexión perdería sus parámetros de configuración (por ejemplo el
        // entorno de la aplicación o los límites de intentos).
        if (isset($this->bindings[$class])) {
            $resolved = $this->get($class);

            if (is_object($resolved)) {
                return $resolved;
            }
        }

        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('No existe la clase "%s".', $class));
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $instance = new $class();
            $this->instances[$class] = $instance;

            return $instance;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();

                    continue;
                }

                throw new RuntimeException(sprintf(
                    'No se puede construir "%s": el parámetro "$%s" no tiene un tipo resoluble.',
                    $class,
                    $parameter->getName()
                ));
            }

            $arguments[] = $this->get($type->getName());
        }

        $instance = $reflection->newInstanceArgs($arguments);
        $this->instances[$class] = $instance;

        return $instance;
    }
}
