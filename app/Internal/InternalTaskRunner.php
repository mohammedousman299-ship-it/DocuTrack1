<?php

declare(strict_types=1);

namespace App\Internal;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Exécute une tâche de fond sous verrou et sous budget de temps.
 *
 * Trois propriétés sont exigées par §3.1 pour tout endpoint interne :
 *
 * 1. VERROU — deux déclenchements simultanés ne doivent pas traiter le même
 *    travail deux fois. Vercel Cron peut redéclencher avant la fin du passage
 *    précédent, et rien ne garantit l'unicité côté plateforme.
 * 2. BUDGET DE TEMPS — la requête doit rendre la main proprement avant la
 *    limite d'exécution de la plateforme. Un lot interrompu au milieu
 *    laisserait un travail dans un état indéterminé.
 * 3. IDEMPOTENCE — rejouer un passage ne doit produire aucun effet de bord.
 *    Elle est portée par les tâches elles-mêmes (clés uniques), pas ici.
 */
final class InternalTaskRunner
{
    public function __construct(
        private readonly int $lockSeconds = 60,
        private readonly int $budgetSeconds = 20,
    ) {}

    /**
     * @param  Closure(TaskBudget): array{processed: int, details?: array<string, int|string|bool>}  $work
     */
    public function run(string $task, Closure $work): InternalTaskResult
    {
        $lock = Cache::lock("internal-task:{$task}", $this->lockSeconds);

        // Acquisition non bloquante : si un lot tourne déjà, on repart
        // immédiatement plutôt que de consommer le budget à attendre.
        if (! $lock->get()) {
            return new InternalTaskResult(task: $task, ran: false);
        }

        try {
            $budget = new TaskBudget($this->budgetSeconds);
            $outcome = $work($budget);

            return new InternalTaskResult(
                task: $task,
                ran: true,
                processed: $outcome['processed'],
                budgetExhausted: $budget->isExhausted(),
                details: $outcome['details'] ?? [],
            );
        } finally {
            $lock->release();
        }
    }
}
