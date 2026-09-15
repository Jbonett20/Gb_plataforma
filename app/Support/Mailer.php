<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Envío de correo.
 *
 * El correo de este sitio es informativo: enlaces para restablecer la
 * contraseña, comprobantes de compra y confirmaciones de suscripción. Ninguna
 * contraseña viaja por aquí.
 *
 * Tres formas de trabajar, elegidas por la configuración del entorno:
 *
 *   - `smtp`: hay servidor de correo (MAIL_HOST). Usa PHPMailer si está
 *     instalado; si no, cae a `mail`.
 *   - `mail`: sin servidor propio, delega en el servicio del servidor web.
 *   - `log`: no hay nada configurado. El mensaje se guarda completo en
 *     `storage/logs/mail/` para poder comprobarlo, y `configured()` devuelve
 *     false para que el panel pueda avisar de que falta configurarlo.
 *
 * Nunca se lanza una excepción hacia la pantalla: el resultado se consulta con
 * `lastError()`. Un correo que no sale no debe romper la operación que la
 * persona estaba haciendo.
 */
final class Mailer
{
    public const DRIVER_SMTP = 'smtp';
    public const DRIVER_MAIL = 'mail';
    public const DRIVER_LOG = 'log';

    private ?string $lastError = null;

    /**
     * @param array<string, mixed> $config config('app.mail')
     */
    public function __construct(
        private array $config = [],
        private string $logPath = '',
    ) {
    }

    public function driver(): string
    {
        if (!$this->hasServer()) {
            return self::DRIVER_LOG;
        }

        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class) ? self::DRIVER_SMTP : self::DRIVER_MAIL;
    }

    /**
     * Si hay un modo REAL de enviar correo.
     *
     * No basta con que haya un servidor escrito: un servidor remoto necesita
     * una cuenta con la que autenticarse. Si falta, conviene guardar el mensaje
     * y avisar en el panel en lugar de intentar una conexión que va a fallar en
     * cada envío, sin que nadie se entere.
     */
    public function configured(): bool
    {
        return $this->hasServer();
    }

    private function hasServer(): bool
    {
        $host = trim((string) ($this->config['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        // Un servidor local suele aceptar envíos sin autenticación.
        if (in_array(mb_strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return trim((string) ($this->config['username'] ?? '')) !== '';
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param string $to destinatario
     * @param string $subject asunto, ya redactado
     * @param string $html cuerpo en HTML
     * @param string $replyTo correo de respuesta, opcional
     */
    public function send(string $to, string $subject, string $html, string $replyTo = ''): bool
    {
        $this->lastError = null;

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->lastError = 'La dirección de destino no es válida.';

            return false;
        }

        if (!$this->configured()) {
            // Sin servidor utilizable, el mensaje no se pierde: queda guardado y
            // `lastError()` explica por qué no salió.
            return $this->writeToLog($to, $subject, $html, $replyTo);
        }

        return match ($this->driver()) {
            self::DRIVER_SMTP => $this->sendWithSmtp($to, $subject, $html, $replyTo),
            self::DRIVER_MAIL => $this->sendWithMailFunction($to, $subject, $html, $replyTo),
            default => $this->writeToLog($to, $subject, $html, $replyTo),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $path = GB_APP_PATH . '/Views/emails/' . $template . '.php';

        if (!is_file($path)) {
            $this->lastError = sprintf('No existe la plantilla de correo "%s".', $template);

            return false;
        }

        $view = new View(GB_APP_PATH . '/Views');
        $html = $view->render('emails.' . $template, $data + ['siteName' => (string) Config::string('app.name')]);

        return $this->send($to, $subject, $html);
    }

    private function sendWithSmtp(string $to, string $subject, string $html, string $replyTo): bool
    {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) $this->config['host'];
            $mailer->Port = (int) ($this->config['port'] ?? 587);
            $mailer->SMTPAuth = (string) ($this->config['username'] ?? '') !== '';
            $mailer->Username = (string) ($this->config['username'] ?? '');
            $mailer->Password = (string) ($this->config['password'] ?? '');
            $mailer->SMTPSecure = (string) ($this->config['encryption'] ?? 'tls');
            $mailer->CharSet = 'UTF-8';

            $mailer->setFrom(
                (string) ($this->config['from_address'] ?? 'no-reply@localhost'),
                (string) ($this->config['from_name'] ?? '')
            );
            $mailer->addAddress($to);

            if ($replyTo !== '') {
                $mailer->addReplyTo($replyTo);
            }

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html;
            $mailer->AltBody = trim(strip_tags($html));

            $mailer->send();

            return true;
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return false;
        }
    }

    private function sendWithMailFunction(string $to, string $subject, string $html, string $replyTo): bool
    {
        if (!function_exists('mail')) {
            $this->lastError = 'El servidor no permite enviar correo.';

            return false;
        }

        $from = (string) ($this->config['from_address'] ?? 'no-reply@localhost');
        $fromName = (string) ($this->config['from_name'] ?? '');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $fromName, $from),
        ];

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $sent = @mail($to, $subject, $html, implode("\r\n", $headers));

        if (!$sent) {
            $this->lastError = 'El servidor rechazó el envío.';
        }

        return $sent;
    }

    /**
     * Guarda el mensaje completo para poder revisarlo. Sólo se usa cuando no hay
     * correo configurado; queda fuera del alcance del navegador, entre las
     * carpetas de datos del sitio.
     */
    private function writeToLog(string $to, string $subject, string $html, string $replyTo): bool
    {
        $directory = $this->logPath !== '' ? $this->logPath : storage_path('logs/mail');

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->lastError = 'No se pudo preparar la carpeta de correo.';

            return false;
        }

        $file = $directory . '/' . date('Y-m-d_His') . '-' . bin2hex(random_bytes(3)) . '.html';

        $document = sprintf(
            "<!DOCTYPE html>\n<meta charset=\"utf-8\">\n<pre>\nPara: %s\nAsunto: %s\nResponder a: %s\nFecha: %s\n</pre>\n<hr>\n%s\n",
            htmlspecialchars($to, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($replyTo === '' ? '(no indicado)' : $replyTo, ENT_QUOTES, 'UTF-8'),
            date('Y-m-d H:i:s'),
            $html
        );

        if (@file_put_contents($file, $document) === false) {
            $this->lastError = 'No se pudo guardar el mensaje.';

            return false;
        }

        $this->lastError = sprintf(
            'No hay servidor de correo configurado: el mensaje se guardó en %s',
            str_replace(storage_path(), 'storage', $file)
        );

        return true;
    }
}
