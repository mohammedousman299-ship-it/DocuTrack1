<?php

declare(strict_types=1);

namespace App\Internal;

/** Résultat d'une tâche interne, rendu tel quel dans la réponse HTTP. */
final readonly class InternalTaskResult
{
    /** @param array<string, int|string|bool> $details */
    public function __construct(
        public string $task,
        public bool $ran,
        public int $processed = 0,
        public bool $budgetExhausted = false,
        public array $details = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            // false => une autre exécution détenait le verrou. Ce n'est PAS une
            // erreur : le cron peut se déclencher pendant qu'un lot tourne.
            'ran' => $this->ran,
            'processed' => $this->processed,
            // true => il reste du travail. Le passage suivant reprendra.
            'budget_exhausted' => $this->budgetExhausted,
            'details' => $this->details,
        ];
    }
}
