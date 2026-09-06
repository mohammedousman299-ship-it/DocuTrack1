<?php

declare(strict_types=1);

namespace App\Search\Abuse;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Réponse graduée à un schéma d'énumération (§4.2, D-037).
 *
 * Sans CAPTCHA : il reste ~2,6 ko de budget JavaScript, et un CAPTCHA tiers
 * transmettrait des données de navigation à un service externe — sur une
 * plateforme dont l'exigence n°1 est la protection des données. Le vrai coût
 * d'entrée reste la vérification SMS (D-013).
 *
 * La réponse est GRADUÉE et non binaire : un utilisateur légitime qui cherche
 * pour plusieurs proches ne doit pas être traité comme un attaquant dès le
 * premier signal. Chaque palier est journalisé et alerte l'administrateur.
 */
final class ApplyEnumerationResponse
{
    /** Durées de blocage successives, en minutes. */
    private const ESCALATION_MINUTES = [15, 60, 720];

    public function __construct(private readonly EnumerationDetector $detector) {}

    /** @return array{blocked: bool, until: ?string, signals: array<string, int|float>} */
    public function handle(User $user): array
    {
        $signals = $this->detector->signalsFor($user);

        if (! $this->detector->isEnumerating($signals)) {
            return ['blocked' => false, 'until' => null, 'signals' => $signals->toArray()];
        }

        $previous = DB::table('audit_logs')
            ->where('actor_user_id', $user->id)
            ->where('action', 'search.enumeration_blocked')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $minutes = self::ESCALATION_MINUTES[min($previous, count(self::ESCALATION_MINUTES) - 1)];
        $until = now()->addMinutes($minutes);

        $user->forceFill(['blocked_until' => $until])->save();

        // Journal append-only : la trace ne peut être ni modifiée ni effacée,
        // y compris par un administrateur (§4.4).
        DB::table('audit_logs')->insert([
            'actor_user_id' => $user->id,
            'actor_role' => 'user',
            'action' => 'search.enumeration_blocked',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            // Les signaux, jamais les critères eux-mêmes : le journal ne doit
            // pas devenir un second entrepôt de données de recherche.
            'reason' => json_encode($signals->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return [
            'blocked' => true,
            'until' => $until->toIso8601String(),
            'signals' => $signals->toArray(),
        ];
    }
}
