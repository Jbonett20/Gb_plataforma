<?php

declare(strict_types=1);

namespace GB\Support;

/**
 * Respuesta HTTP.
 *
 * Los controladores devuelven objetos de esta clase; la salida se emite una
 * sola vez desde el front controller.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            $payload = '{"error":"No se pudo serializar la respuesta."}';
            $status = 500;
        }

        return new self($payload, $status, ['Content-Type' => 'application/json; charset=UTF-8'] + $headers);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (headers_sent()) {
            echo $this->content;

            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->content;
    }
}
