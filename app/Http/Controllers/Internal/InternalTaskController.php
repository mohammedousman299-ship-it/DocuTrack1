<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Internal\InternalTaskRunner;
use App\Internal\TaskBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints internes remplaçant le worker et le scheduler absents (§3.1).
 *
 * DocuTrack est fondamentalement asynchrone : rapprochement, notifications,
 * réconciliation des paiements et purge sont des traitements de fond. Sur une
 * plateforme sans processus persistant, ils passent par ces endpoints,
 * déclenchés par un ordonnanceur externe.
 *
 * Chacun est protégé par secret d'en-tête, verrouillé contre les exécutions
 * concurrentes, borné en temps et idempotent.
 */
final class InternalTaskController
{
    public function __construct(private readonly InternalTaskRunner $runner) {}

    /** Draine la file d'attente : remplace `queue:work`, qui ne survit pas. */
    public function drainQueue(): JsonResponse
    {
        $result = $this->runner->run('queue.drain', function (TaskBudget $budget): array {
            $processed = 0;

            // --once traite un travail puis rend la main : on garde ainsi le
            // contrôle du budget entre deux travaux, ce que --stop-when-empty
            // seul ne permettrait pas.
            while ($budget->hasTimeLeft()) {
                $before = DB::table('jobs')->count();

                if ($before === 0) {
                    break;
                }

                Artisan::call('queue:work', [
                    '--once' => true,
                    '--no-interaction' => true,
                ]);

                $processed++;
            }

            return ['processed' => $processed];
        });

        return response()->json($result->toArray());
    }

    /**
     * Rapprochement par lots.
     *
     * Le moteur lui-même arrive au jalon 5 ; l'endpoint et ses garanties
     * (verrou, budget, idempotence) sont validés dès maintenant, parce que
     * c'est l'architecture asynchrone qui est le risque, pas l'algorithme.
     */
    public function match(): JsonResponse
    {
        $result = $this->runner->run('cron.match', fn (TaskBudget $budget): array => [
            'processed' => 0,
            'details' => ['status' => 'not_implemented_until_milestone_5'],
        ]);

        return response()->json($result->toArray());
    }

    /** Envoi des notifications en attente (jalon 2 pour l'infrastructure). */
    public function notify(): JsonResponse
    {
        $result = $this->runner->run('cron.notify', fn (TaskBudget $budget): array => [
            'processed' => 0,
            'details' => ['status' => 'not_implemented_until_milestone_4'],
        ]);

        return response()->json($result->toArray());
    }

    /** Réconciliation des paiements restés en suspens — fréquent en mobile money. */
    public function reconcilePayments(): JsonResponse
    {
        $result = $this->runner->run('cron.reconcile-payments', fn (TaskBudget $budget): array => [
            'processed' => 0,
            'details' => ['status' => 'not_implemented_until_milestone_6'],
        ]);

        return response()->json($result->toArray());
    }

    /**
     * Purge de rétention (D-010).
     *
     * Suppression EFFECTIVE, pas logique : la rétention est traitée comme un
     * contrôle de sécurité de premier rang, puisqu'elle réduit d'un ordre de
     * grandeur ce qu'une fuite expose (THREAT_MODEL.md M-08).
     */
    public function purge(): JsonResponse
    {
        $result = $this->runner->run('cron.purge', function (TaskBudget $budget): array {
            $processed = 0;
            $details = [];

            // Les pièces jointes d'abord : ce sont les données les plus
            // sensibles et elles ont la durée la plus courte.
            foreach (['report_attachments', 'lost_declarations', 'found_reports'] as $table) {
                if ($budget->isExhausted()) {
                    break;
                }

                $deleted = DB::table($table)
                    ->where('expires_at', '<=', now())
                    ->limit(1000)
                    ->delete();

                $details[$table] = $deleted;
                $processed += $deleted;
            }

            if ($budget->hasTimeLeft()) {
                $cutoff = now()->subDays((int) config('docutrack.retention.search_request_days'));
                $deleted = DB::table('search_requests')->where('created_at', '<=', $cutoff)->limit(1000)->delete();
                $details['search_requests'] = $deleted;
                $processed += $deleted;
            }

            return ['processed' => $processed, 'details' => $details];
        });

        return response()->json($result->toArray());
    }
}
