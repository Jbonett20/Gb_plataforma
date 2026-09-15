<?php

declare(strict_types=1);

namespace GB\Services;

use GB\Models\UserRepository;
use GB\Support\AdminMenu;

/**
 * Recorrido guiado del primer ingreso (tarea 11.5).
 *
 * Decide si corresponde mostrar el recorrido y lo cierra cuando la persona
 * termina. La marca se guarda en la cuenta, de modo que aparece una sola vez:
 * repetirlo en cada ingreso convertiría una ayuda en un estorbo.
 */
final class OnboardingService
{
    /**
     * @param array{title?: string, intro?: string, steps?: array<int, array<string, string>>} $definition
     */
    public function __construct(
        private UserRepository $users,
        private AdminMenu $menu,
        private array $definition = [],
    ) {
    }

    /**
     * El recorrido listo para dibujar, o null si ya no corresponde mostrarlo.
     *
     * @return array{title: string, intro: string, steps: array<int, array{title: string, text: string, url: string, icon: string}>}|null
     */
    public function pending(?int $userId): ?array
    {
        if ($userId === null || !$this->users->needsOnboarding($userId)) {
            return null;
        }

        $tour = $this->menu->tour($this->definition);

        return $tour['steps'] === [] ? null : $tour;
    }

    public function complete(?int $userId): void
    {
        if ($userId !== null) {
            $this->users->completeOnboarding($userId);
        }
    }
}
