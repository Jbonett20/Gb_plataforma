<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Solicitudes de cita recibidas desde el formulario de contacto.
 */
final class AppointmentRepository extends Model
{
    protected string $table = 'appointments';

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($data);
    }

    public function countNew(): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM appointments WHERE status = 'new'");
    }
}
