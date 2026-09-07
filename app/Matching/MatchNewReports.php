<?php

declare(strict_types=1);

namespace App\Matching;

use App\Internal\TaskBudget;
use App\Models\DocumentMatch;
use App\Models\FoundReport;
use App\Models\LostDeclaration;
use App\Models\MatchingSetting;
use App\Notifications\NotificationDispatcher;
use App\Notifications\NotificationTemplate;
use App\Search\SearchMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confronte les signalements NOUVEAUX aux déclarations déjà actives.
 *
 * C'est le second sens du rapprochement, celui qui tient la promesse faite à
 * l'écran : « votre déclaration reste active, vous serez prévenu si quelqu'un
 * le signale plus tard ». Sans lui, une déclaration ne trouvait que ce qui
 * existait déjà au moment de la recherche.
 *
 * Une déclaration en revue est ignorée, comme partout ailleurs : tant qu'un
 * humain n'a pas tranché, elle ne produit ni rapprochement ni notification
 * (D-039).
 */
final class MatchNewReports
{
    public function __construct(
        private readonly RecordMatches $recorder,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** @return array{processed: int, details: array<string, int>} */
    public function handle(TaskBudget $budget, int $limit = 25): array
    {
        $settings = MatchingSetting::active();

        $reports = FoundReport::query()
            ->with('documentType')
            ->whereNull('last_matched_at')
            ->matchable()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $processed = 0;
        $notified = 0;

        foreach ($reports as $report) {
            if ($budget->isExhausted()) {
                break;
            }

            $notified += $this->sweep($report, $settings);
            $processed++;
        }

        return ['processed' => $processed, 'details' => ['notified' => $notified]];
    }

    private function sweep(FoundReport $report, MatchingSetting $settings): int
    {
        $declarations = $this->candidateDeclarations($report);
        $notified = 0;

        DB::transaction(function () use ($report, $declarations, $settings, &$notified): void {
            foreach ($declarations as $declaration) {
                $matches = $this->recorder->handle($declaration, collect([$report]), $settings);

                foreach ($matches->where('status', 'candidate') as $match) {
                    $notified += $this->notify($match, $declaration) ? 1 : 0;
                }
            }

            // Marqué même sans correspondance : un signalement sans
            // déclaration correspondante a bien été examiné, et le rebalayer
            // à chaque passage ferait grossir le lot sans fin.
            $report->forceFill(['last_matched_at' => now()])->save();
        }, 3);

        return $notified;
    }

    /**
     * Déclarations plausibles pour ce signalement — le miroir de
     * SearchMatcher::candidatesFor, dans l'autre sens.
     *
     * @return Collection<int, LostDeclaration>
     */
    private function candidateDeclarations(FoundReport $report): Collection
    {
        $name = $report->owner_name_normalized;

        return LostDeclaration::query()
            ->with('user')
            ->where('document_type_id', $report->document_type_id)
            // ----------------------------------------------------------------
            // UNIQUEMENT les déclarations ANTÉRIEURES au signalement.
            //
            // Une déclaration postérieure a déjà rencontré ce signalement par
            // son propre chemin, celui de la demande de recherche. Sans cette
            // borne, le couple est traité DEUX fois et le propriétaire reçoit
            // deux notifications — « recherche terminée » puis « correspondance
            // possible » — là où celui qui n'a rien trouvé n'en reçoit qu'une.
            //
            // Le nombre de messages redevenait alors le signal binaire que la
            // recherche différée et le gabarit unique de D-034 avaient
            // précisément pour objet de supprimer : il suffisait de compter ses
            // SMS, sans même ouvrir le site.
            //
            // Avec cette borne, chaque couple est examiné exactement une fois.
            // ----------------------------------------------------------------
            ->where('lost_declarations.created_at', '<', $report->created_at)
            ->matchable()
            ->where(function ($query) use ($report, $name): void {
                if ($report->number_hmac !== null) {
                    $query->orWhere('number_hmac', $report->number_hmac);
                }

                if ($name !== null && $name !== '') {
                    $query->orWhereRaw(
                        'similarity(owner_name_normalized, ?) >= ?',
                        [$name, SearchMatcher::NAME_SIMILARITY_THRESHOLD]
                    );
                }
            })
            ->when(
                $report->found_on !== null,
                fn ($q) => $q->where(function ($inner) use ($report): void {
                    // Une découverte antérieure à la perte est impossible ;
                    // la tolérance couvre une date de perte approximative.
                    $inner->whereNull('lost_on')
                        ->orWhere('lost_on', '<=', $report->found_on->copy()->addDays(
                            TemporalFactor::TOLERANCE_DAYS
                        ));
                })
            )
            ->limit(50)
            ->get();
    }

    private function notify(DocumentMatch $match, LostDeclaration $declaration): bool
    {
        // Déjà notifiée : rejouer le cron ne doit pas renvoyer le message.
        if ($match->notified_at !== null || $declaration->user === null) {
            return false;
        }

        // Gabarit SANS aucun paramètre : il annonce l'existence d'une
        // correspondance possible et invite à se connecter. Rien du document,
        // rien du signalement — un SMS s'affiche sur un écran verrouillé.
        $sent = $this->dispatcher->queue(
            recipient: $declaration->user,
            template: NotificationTemplate::MatchFound,
            channel: 'sms',
            idempotencyKey: 'match:'.$match->id,
        );

        if ($sent) {
            $match->forceFill(['status' => 'notified', 'notified_at' => now()])->save();
        }

        return $sent;
    }
}
