<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Comprobaciones del asistente de instalación.
 *
 * Devuelve una lista de verificaciones con estado para que el administrador
 * técnico sepa exactamente qué falta antes de publicar el sitio. Ninguna
 * verificación detiene la aplicación: sólo informan.
 */
final class HealthCheck
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public function __construct(
        private string $basePath,
        private Container $container,
    ) {
    }

    /**
     * @return array<int, array{label: string, status: string, detail: string, hint: string}>
     */
    public function run(): array
    {
        return [
            $this->checkPhpVersion(),
            $this->checkExtensions(),
            $this->checkEnvFile(),
            $this->checkAppKey(),
            $this->checkBaseUrl(),
            $this->checkWritableStorage(),
            $this->checkStorageOutsideWebroot(),
            $this->checkDatabase(),
            $this->checkMail(),
        ];
    }

    public function hasErrors(): bool
    {
        foreach ($this->run() as $check) {
            if ($check['status'] === self::ERROR) {
                return true;
            }
        }

        return false;
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkPhpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, '8.1.0', '>=');

        return [
            'label' => 'Versión de PHP',
            'status' => $ok ? self::OK : self::ERROR,
            'detail' => 'Se detectó PHP ' . PHP_VERSION . '.',
            'hint' => $ok ? '' : 'Se requiere PHP 8.1 o superior. Cambia la versión en el panel del hosting.',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'gd', 'fileinfo', 'mbstring', 'json'];
        $missing = array_values(array_filter($required, static fn (string $ext): bool => !extension_loaded($ext)));

        return [
            'label' => 'Extensiones de PHP',
            'status' => $missing === [] ? self::OK : self::ERROR,
            'detail' => $missing === []
                ? 'Todas las extensiones necesarias están activas.'
                : 'Faltan: ' . implode(', ', $missing) . '.',
            'hint' => $missing === []
                ? ''
                : 'Actívalas en php.ini (en XAMPP basta con quitar el ";" delante de cada extensión) y reinicia Apache.',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkEnvFile(): array
    {
        $exists = is_file($this->basePath . '/.env');

        return [
            'label' => 'Archivo de configuración .env',
            'status' => $exists ? self::OK : self::ERROR,
            'detail' => $exists ? 'El archivo .env existe.' : 'No se encontró el archivo .env.',
            'hint' => $exists ? '' : 'Copia .env.example como .env y completa las credenciales de la base de datos.',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkAppKey(): array
    {
        $key = (string) Env::get('APP_KEY', '');
        $placeholder = $key === '' || str_contains($key, 'cambia-esta-clave');
        $strong = strlen($key) >= 32;

        if ($placeholder) {
            return [
                'label' => 'Clave secreta de la aplicación',
                'status' => self::ERROR,
                'detail' => 'La clave APP_KEY no está configurada.',
                'hint' => 'Genera una con: php -r "echo bin2hex(random_bytes(32));" y pégala en APP_KEY.',
            ];
        }

        return [
            'label' => 'Clave secreta de la aplicación',
            'status' => $strong ? self::OK : self::WARNING,
            'detail' => $strong ? 'La clave está configurada.' : 'La clave es más corta de lo recomendado.',
            'hint' => $strong ? '' : 'Usa una clave de al menos 64 caracteres hexadecimales.',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkBaseUrl(): array
    {
        $configured = rtrim((string) Env::get('APP_URL', ''), '/');

        // En línea de comandos no hay petición HTTP con la que comparar.
        if (PHP_SAPI === 'cli' || $configured === '') {
            return [
                'label' => 'Dirección del sitio',
                'status' => $configured === '' ? self::WARNING : self::OK,
                'detail' => $configured === ''
                    ? 'APP_URL está vacía: el sistema deduce la dirección de cada petición.'
                    : 'APP_URL configurada como ' . $configured . '.',
                'hint' => $configured === ''
                    ? 'En producción conviene fijarla, por ejemplo https://www.gonzalezballesteros.com'
                    : '',
            ];
        }

        $current = rtrim(base_url(), '/');
        $matches = $configured === $current;

        return [
            'label' => 'Dirección del sitio',
            'status' => $matches ? self::OK : self::ERROR,
            'detail' => $matches
                ? 'APP_URL coincide con la dirección actual (' . $current . ').'
                : 'APP_URL apunta a ' . $configured . ' pero el sitio responde en ' . $current . '.',
            'hint' => $matches
                ? ''
                : 'Corrige APP_URL en el archivo .env. Con el valor actual, los estilos, las fuentes y las imágenes no cargan.',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkWritableStorage(): array
    {
        $directories = ['storage', 'storage/cache', 'storage/logs', 'storage/uploads', 'storage/protected', 'public/uploads'];
        $notWritable = [];

        foreach ($directories as $directory) {
            $path = $this->basePath . '/' . trim($directory, '/');

            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (!is_dir($path) || !is_writable($path)) {
                $notWritable[] = $directory;
            }
        }

        return [
            'label' => 'Permisos de escritura',
            'status' => $notWritable === [] ? self::OK : self::ERROR,
            'detail' => $notWritable === []
                ? 'La aplicación puede escribir en storage/ y public/uploads.'
                : 'Sin permiso de escritura en: ' . implode(', ', $notWritable) . '.',
            'hint' => $notWritable === []
                ? ''
                : 'Da permisos de escritura a esas carpetas (en Linux: chmod -R 775 storage public/uploads).',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkStorageOutsideWebroot(): array
    {
        $public = realpath($this->basePath . '/public');
        $storage = realpath($this->basePath . '/storage');

        if ($public === false || $storage === false) {
            return [
                'label' => 'Material de pago fuera del alcance público',
                'status' => self::ERROR,
                'detail' => 'No se pudieron resolver las rutas de public/ y storage/.',
                'hint' => 'Verifica que las carpetas public/ y storage/ existen en la raíz del proyecto.',
            ];
        }

        $public = str_replace('\\', '/', $public);
        $storage = str_replace('\\', '/', $storage);
        $inside = str_starts_with($storage . '/', $public . '/');

        return [
            'label' => 'Material de pago fuera del alcance público',
            'status' => $inside ? self::ERROR : self::OK,
            'detail' => $inside
                ? 'storage/ está dentro de public/ y sería accesible por URL.'
                : 'storage/ está fuera de public/: los archivos de pago no tienen URL directa.',
            'hint' => $inside
                ? 'Mueve storage/ fuera de public/. Si no es posible, bloquea el acceso con una regla del servidor web.'
                : '',
        ];
    }

    /** @return array{label: string, status: string, detail: string, hint: string} */
    private function checkDatabase(): array
    {
        if (!Env::has('DB_DATABASE')) {
            return [
                'label' => 'Conexión a la base de datos',
                'status' => self::WARNING,
                'detail' => 'No hay credenciales de base de datos configuradas todavía.',
                'hint' => 'Completa DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD en el archivo .env.',
            ];
        }

        try {
            $pdo = $this->container->get(\PDO::class);
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')?->fetchColumn();

            return [
                'label' => 'Conexión a la base de datos',
                'status' => self::OK,
                'detail' => sprintf('Conexión correcta. Tablas encontradas: %d.', $tables),
                'hint' => $tables === 0
                    ? 'Ejecuta `composer migrate` para crear las tablas y `composer seed` para cargar el contenido inicial.'
                    : '',
            ];
        } catch (\Throwable $exception) {
            return [
                'label' => 'Conexión a la base de datos',
                'status' => self::ERROR,
                'detail' => 'No se pudo conectar: ' . $exception->getMessage(),
                'hint' => 'Revisa las credenciales DB_* del archivo .env y que el servidor MySQL esté encendido.',
            ];
        }
    }

    /**
     * Estado del correo saliente.
     *
     * Queda como aviso, no como error: el sitio funciona sin correo. Mientras no
     * haya servidor configurado, los mensajes (por ejemplo el enlace para
     * restablecer una contraseña) se guardan completos en `storage/logs/mail/`
     * para poder entregarlos a mano.
     *
     * @return array{label: string, status: string, detail: string, hint: string}
     */
    private function checkMail(): array
    {
        $host = trim((string) Env::get('MAIL_HOST', ''));
        $from = (string) Env::get('MAIL_FROM_ADDRESS', '');

        if ($host === '') {
            return [
                'label' => 'Correo saliente',
                'status' => self::WARNING,
                'detail' => 'Todavía sin activar: no sale ningún correo. Los mensajes se guardan completos'
                    . ' en storage/logs/mail para poder revisarlos y entregarlos a mano.',
                'hint' => 'Para activarlo, completa MAIL_HOST, MAIL_PORT, MAIL_USERNAME y MAIL_PASSWORD'
                    . ' en el archivo .env con la cuenta de correo del despacho. No hay que cambiar nada más.',
            ];
        }

        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'label' => 'Correo saliente',
                'status' => self::WARNING,
                'detail' => 'Hay un servidor de correo configurado, pero la dirección del remitente no es válida.',
                'hint' => 'Corrige MAIL_FROM_ADDRESS en el archivo .env (por ejemplo'
                    . ' gerencia@gonzalezballesteros.com).',
            ];
        }

        return [
            'label' => 'Correo saliente',
            'status' => self::OK,
            'detail' => sprintf('Activado a través de %s, con remitente %s.', $host, $from),
            'hint' => 'Conviene enviar un correo de prueba para confirmar que llega y no cae en la carpeta'
                . ' de correo no deseado.',
        ];
    }
}
