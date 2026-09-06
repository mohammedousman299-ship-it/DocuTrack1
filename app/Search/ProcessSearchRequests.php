<?php

declare(strict_types=1);

namespace App\Search;

use App\Internal\TaskBudget;
use App\Models\LostDeclaration;
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
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** @return array{processed: int, details: array<string, int>} */
    public function handle(TaskBudget $budget, int $limit = 50): array
    {
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

            $declaration = LostDeclaration::query()
                ->where('user_id', $row->user_id)
                ->where('document_type_id', $row->document_type_id)
                ->latest('created_at')
                ->first();

            $candidates = $declaration === null
                ? collect()
                : $this->matcher->candidatesFor($declaration);

            DB::transaction(function () use ($row, $candidates, &$notified): void {
                foreach ($candidates as $candidate) {
                    DB::table('search_results')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'search_request_id' => $row->id,
                        'found_report_id' => $candidate->id,
                        // Le score fin est du jalon 5 ; à ce stade la
                        // présélection ne classe pas, elle retient.
                        'score' => 0.500,
                        'created_at' => now(),
                    ]);
                }

                DB::table('search_requests')->where('id', $row->id)->update([
                    'status' => 'processed',
                    // Ordre de grandeur seulement : le nombre exact est un
                    // signal d'énumération (M-07).
                    'result_count_bucket' => match (true) {
                        $candidates->isEmpty() => 'none',
                        $candidates->count() === 1 => 'one',
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
