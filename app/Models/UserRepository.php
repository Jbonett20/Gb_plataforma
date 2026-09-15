<?php

declare(strict_types=1);

namespace GB\Models;

use GB\Support\Model;

/**
 * Cuentas de acceso: superadministradores y estudiantes.
 */
final class UserRepository extends Model
{
    protected string $table = 'users';

    /**
     * Columnas de la cuenta junto con su rol.
     */
    private const SELECT_WITH_ROLE = 'SELECT u.id, u.role_id, u.name, u.last_name, u.email, u.phone,
               u.avatar_media_id, u.status, u.email_verified_at, u.must_change_password,
               u.failed_login_attempts, u.locked_until, u.last_login_at, u.accepted_terms_at,
               u.created_at, r.key_name AS role_key, r.label AS role_label
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id';

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->selectOne(self::SELECT_WITH_ROLE . ' WHERE u.id = :id AND u.deleted_at IS NULL', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->selectOne(
            self::SELECT_WITH_ROLE . ' WHERE u.email = :email AND u.deleted_at IS NULL',
            ['email' => mb_strtolower($email)]
        );
    }

    /**
     * Busca una cuenta por cualquier identificador admitido.
     *
     * Cada aparición usa un marcador propio: la conexión trabaja con sentencias
     * preparadas nativas y ahí un mismo marcador no puede repetirse.
     *
     * @return array<string, mixed>|null
     */
    public function findByLoginIdentifier(string $identifier): ?array
    {
        $raw = trim($identifier);
        $value = mb_strtolower($raw);

        return $this->selectOne(
            self::SELECT_WITH_ROLE . ' WHERE u.deleted_at IS NULL AND (
                    u.email = :identifier_raw
                    OR LOWER(u.email) = :identifier_email
                    OR u.phone = :identifier_phone_raw
                    OR LOWER(u.phone) = :identifier_phone
                    OR LOWER(u.name) = :identifier_name
                    OR LOWER(u.last_name) = :identifier_last_name
                    OR LOWER(TRIM(CONCAT(u.name, " ", u.last_name))) = :identifier_full_name
                )
             ORDER BY u.id ASC
             LIMIT 1',
            $this->identifierBindings($raw, $value)
        );
    }

    /**
     * Valores para las consultas de identificación. El nombre de cada clave
     * coincide con el marcador que usa la consulta.
     *
     * @return array<string, string>
     */
    private function identifierBindings(string $raw, string $value): array
    {
        return [
            'identifier_raw' => $raw,
            'identifier_email' => $value,
            'identifier_phone_raw' => $raw,
            'identifier_phone' => $value,
            'identifier_name' => $value,
            'identifier_last_name' => $value,
            'identifier_full_name' => $value,
        ];
    }

    /**
     * Busca una cuenta para el inicio de sesión aceptando el correo y, en modo
     * transitorio, también nombres y teléfonos que se han usado como identidad.
     *
     * @return array<string, mixed>|null
     */
    public function findForAuthentication(string $identifier): ?array
    {
        $raw = trim($identifier);
        $value = mb_strtolower($raw);

        return $this->selectOne(
            'SELECT u.id, u.name, u.email, u.password_hash, u.status, u.failed_login_attempts,
                    u.locked_until, u.must_change_password, r.key_name AS role_key
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.deleted_at IS NULL
               AND (
                    u.email = :identifier_raw
                    OR LOWER(u.email) = :identifier_email
                    OR u.phone = :identifier_phone_raw
                    OR LOWER(u.phone) = :identifier_phone
                    OR LOWER(u.name) = :identifier_name
                    OR LOWER(u.last_name) = :identifier_last_name
                    OR LOWER(TRIM(CONCAT(u.name, " ", u.last_name))) = :identifier_full_name
               )
             ORDER BY u.id ASC
             LIMIT 1',
            $this->identifierBindings($raw, $value)
        );
    }

    public function emailExists(string $email): bool
    {
        return $this->exists(
            'SELECT 1 FROM users WHERE email = :email AND deleted_at IS NULL',
            ['email' => mb_strtolower($email)]
        );
    }

    public function roleId(string $keyName): ?int
    {
        $id = $this->scalar('SELECT id FROM roles WHERE key_name = :key', ['key' => $keyName]);

        return $id === null ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data['email'] = mb_strtolower((string) $data['email']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($data);
    }

    public function recordSuccessfulLogin(int $userId, string $ip): void
    {
        $this->run(
            'UPDATE users
             SET last_login_at = NOW(), last_login_ip = :ip, failed_login_attempts = 0, locked_until = NULL
             WHERE id = :id',
            ['id' => $userId, 'ip' => $ip]
        );
    }

    /**
     * Suma un intento fallido y bloquea la cuenta al alcanzar el límite.
     */
    public function recordFailedLogin(int $userId, int $lockAfter, int $lockMinutes): void
    {
        $this->run(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id',
            ['id' => $userId]
        );

        $attempts = (int) $this->scalar('SELECT failed_login_attempts FROM users WHERE id = :id', ['id' => $userId]);

        if ($attempts < $lockAfter) {
            return;
        }

        $this->run(
            'UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE) WHERE id = :id',
            ['id' => $userId, 'minutes' => $lockMinutes]
        );
    }

    public function isLocked(int $userId): bool
    {
        return $this->exists(
            'SELECT 1 FROM users WHERE id = :id AND locked_until IS NOT NULL AND locked_until > NOW()',
            ['id' => $userId]
        );
    }

    public function lockedUntil(int $userId): ?string
    {
        $value = $this->scalar('SELECT locked_until FROM users WHERE id = :id AND locked_until > NOW()', ['id' => $userId]);

        return is_string($value) ? $value : null;
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $this->run(
            'UPDATE users SET password_hash = :hash, must_change_password = 0, failed_login_attempts = 0, locked_until = NULL
             WHERE id = :id',
            ['id' => $userId, 'hash' => $hash]
        );
    }

    /**
     * Datos personales que puede editar la propia persona.
     *
     * El correo electrónico no está en esta lista a propósito: es el
     * identificador de la cuenta, y cambiarlo exige comprobar que la nueva
     * dirección es suya. Eso se resuelve desde el panel (tarea 11.11).
     *
     * @param array<string, mixed> $data
     */
    public function updateProfile(int $userId, array $data): void
    {
        $this->run(
            'UPDATE users SET name = :name, last_name = :last_name, phone = :phone WHERE id = :id',
            [
                'id' => $userId,
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]
        );
    }

    /**
     * Contraseña encriptada de una cuenta.
     *
     * Sólo lo usan las comprobaciones de seguridad, para confirmar que lo
     * guardado no es texto legible. Ninguna pantalla lo muestra.
     */
    public function passwordHashOf(int $userId): ?string
    {
        $hash = $this->scalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);

        return is_string($hash) ? $hash : null;
    }

    /**
     * Marca una contraseña puesta por administración y obliga a cambiarla en el
     * primer ingreso.
     */
    public function setInitialPassword(int $userId, string $hash): void
    {
        $this->run(
            'UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id',
            ['id' => $userId, 'hash' => $hash]
        );
    }

    public function countByRole(string $roleKey): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE r.key_name = :role AND u.status = :status AND u.deleted_at IS NULL',
            ['role' => $roleKey, 'status' => 'active']
        );
    }

    /**
     * Listado de estudiantes para el panel, con el número de cursos vigentes.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function studentsPage(string $search = '', int $page = 1, int $perPage = 25): array
    {
        $where = ["r.key_name = 'Student'", 'u.deleted_at IS NULL'];
        $bindings = [];

        $search = trim($search);

        if ($search !== '') {
            // Un marcador por aparición: las consultas preparadas nativas no admiten repetir nombres.
            $where[] = '(LOWER(u.name) LIKE :search_name OR LOWER(u.last_name) LIKE :search_last_name
                OR LOWER(u.email) LIKE :search_email OR u.phone LIKE :search_phone)';
            $term = '%' . mb_strtolower($search) . '%';
            $bindings['search_name'] = $term;
            $bindings['search_last_name'] = $term;
            $bindings['search_email'] = $term;
            $bindings['search_phone'] = '%' . $search . '%';
        }

        $sql = 'SELECT u.id, u.name, u.last_name, u.email, u.phone, u.status, u.created_at,
                       (SELECT COUNT(*) FROM enrollments e
                         WHERE e.user_id = u.id AND e.status = :enroll_status) AS active_courses
                FROM users u INNER JOIN roles r ON r.id = u.role_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY u.created_at DESC, u.id DESC';

        return $this->paginate($sql, $bindings + ['enroll_status' => 'active'], $perPage, $page);
    }

    /** @return array<string, mixed>|null */
    public function findStudent(int $id): ?array
    {
        return $this->selectOne(
            "SELECT u.id, u.name, u.last_name, u.email, u.phone, u.status, u.created_at, u.last_login_at
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND r.key_name = 'Student' AND u.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    /**
     * Cuentas de acceso para el panel, con su rol.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function accountsPage(string $role = '', string $search = '', int $page = 1, int $perPage = 25): array
    {
        $where = ['u.deleted_at IS NULL'];
        $bindings = [];

        if (in_array($role, ['SuperAdmin', 'Student'], true)) {
            $where[] = 'r.key_name = :role';
            $bindings['role'] = $role;
        }

        $search = trim($search);

        if ($search !== '') {
            // Un marcador por cada aparición: las consultas preparadas nativas no
            // permiten repetir el mismo nombre de parámetro.
            $where[] = '(LOWER(u.name) LIKE :search_name OR LOWER(u.email) LIKE :search_email)';
            $bindings['search_name'] = '%' . mb_strtolower($search) . '%';
            $bindings['search_email'] = '%' . mb_strtolower($search) . '%';
        }

        $sql = 'SELECT u.id, u.name, u.last_name, u.email, u.status, u.last_login_at, u.created_at,
                       r.id AS role_id, r.key_name AS role_key, r.label AS role_label
                FROM users u INNER JOIN roles r ON r.id = u.role_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.id ASC, u.created_at DESC, u.id DESC';

        return $this->paginate($sql, $bindings, $perPage, $page);
    }

    /** @return array<int, array{id: int, key_name: string, label: string}> */
    public function roles(): array
    {
        return $this->select('SELECT id, key_name, label FROM roles ORDER BY id');
    }

    /**
     * Administradores activos. Es la cifra que protege el acceso al panel: si
     * llega a cero, nadie puede volver a entrar.
     */
    public function countActiveAdmins(): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE r.key_name = 'SuperAdmin' AND u.status = 'active' AND u.deleted_at IS NULL"
        );
    }

    public function setRole(int $userId, int $roleId): int
    {
        return $this->run(
            'UPDATE users SET role_id = :role, updated_at = NOW() WHERE id = :id',
            ['id' => $userId, 'role' => $roleId]
        );
    }

    public function setStatus(int $userId, string $status): int
    {
        return $this->run(
            'UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $userId, 'status' => $status]
        );
    }

    /**
     * Si a esta cuenta todavía hay que mostrarle el recorrido guiado del primer
     * ingreso (tarea 11.5).
     */
    public function needsOnboarding(int $userId): bool
    {
        return $this->scalar(
            'SELECT onboarding_completed_at FROM users WHERE id = :id AND deleted_at IS NULL',
            ['id' => $userId]
        ) === null;
    }

    /**
     * Cierra el recorrido guiado. No se vuelve a mostrar: la marca se guarda en
     * la cuenta, no en la sesión, para que no reaparezca en cada ingreso.
     */
    public function completeOnboarding(int $userId): void
    {
        $this->run(
            'UPDATE users SET onboarding_completed_at = NOW() WHERE id = :id AND onboarding_completed_at IS NULL',
            ['id' => $userId]
        );
    }
}
