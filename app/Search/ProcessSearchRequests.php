<?php

declare(strict_types=1);

namespace App\Search;

use App\Internal\TaskBudget;
use App\Matching\RecordMatches;
use App\Models\LostDeclaration;
use App\Models\MatchingSetting;
use App\Models\User;
use App\Notifications\NotificationDispatcher;
use App\Notifications\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traitement différé des demandes de recherche (D-008).
 *
 * Exécuté par POST /internal/cron/match, jamais dans une requête HTTP : c'est
 * ce report qui supprime le canal temporel et la boucle d'énumération rapide
 * (M-05, M-07).
 */
final class ProcessSearchRequests
{
    public function __construct(
        private readonly SearchMatcher $matcher,
        private readonly RecordMatches $recorder,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** @return array{processed: int, details: array<string, int>} */
    public function handle(TaskBudget $budget, int $limit = 50): array
    {
        // Chargés UNE FOIS pour le lot : les seuils ne doivent pas changer
        // en cours de traitement, sans quoi deux demandes du même lot seraient
        // jugées selon des règles différentes.
        $settings = MatchingSetting::active();

        $pending = DB::table('search_requests')
            ->where('status', 'queued')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $processed = 0;
        $notified = 0;

        foreach ($pending as $row) {
            if ($budget->isExhausted()) {
                break;
            }

            $declaration = $row->lost_declaration_id === null
                ? null
                : LostDeclaration::find($row->lost_declaration_id);

            // Échec FERMÉ. Une demande sans déclaration identifiable ne peut
            // pas être vérifiée : on ne sait pas si elle relève d'une
            // déclaration en revue. Deviner la déclaration — par exemple la
            // plus récente du même type — désigne la mauvaise dès qu'un
            // utilisateur lance deux recherches, et contournerait la revue.
            if ($declaration === null || ! $declaration->status->isMatchable()) {
                DB::table('search_requests')->where('id', $row->id)->update([
                    'status' => $declaration === null ? 'failed' : 'held',
                    'updated_at' => now(),
                ]);

                $processed++;

                continue;
            }

            $candidates = $this->matcher->candidatesFor($declaration);
            $matches = $this->recorder->handle($declaration, $candidates, $settings);

            // ------------------------------------------------------------
            // SEULES les correspondances au-dessus du seuil de notification
            // sont montrées à l'utilisateur.
            //
            // Jusqu'ici, la page de résultats affichait du niveau N1 pour
            // CHAQUE candidat présélectionné — présélection dont le seuil de
            // similarité est 0,35, volontairement bas pour ne rien manquer.
            // Un couple scoré 0,07 divulguait donc les initiales, le mois et
            // la région d'un document sans rapport.
            //
            // Les correspondances en revue n'y figurent pas non plus : un
            // humain ne les a pas encore tranchées, exactement comme une
            // déclaration en revue ne produit rien (D-039).
            // ------------------------------------------------------------
            $shown = $matches->where('status', 'candidate');

            DB::transaction(function () use ($row, $shown, &$notified): void {
                foreach ($shown as $match) {
                    DB::table('search_results')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'search_request_id' => $row->id,
                        'found_report_id' => $match->found_report_id,
                        'score' => $match->score,
                        'created_at' => now(),
                    ]);
                }

                DB::table('search_requests')->where('id', $row->id)->update([
                    'status' => 'processed',
                    // Ordre de grandeur seulement : le nombre exact est un
                    // signal d'énumération (M-07).
                    'result_count_bucket' => match (true) {
                        $shown->isEmpty() => 'none',
                        $shown->count() === 1 => 'one',
                        default => 'several',
                    },
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

                $user = User::find($row->user_id);

                if ($user !== null) {
                    // Message IDENTIQUE quel que soit le résultat (D-034).
                    $sent = $this->dispatcher->queue(
                        recipient: $user,
                        template: NotificationTemplate::SearchCompleted,
                        channel: 'sms',
                        idempotencyKey: 'search:'.$row->id,
                    );

                    if ($sent) {
                        $notified++;
                    }
                }
            });

            $processed++;
        }

        return ['processed' => $processed, 'details' => ['notified' => $notified]];
    }
}
