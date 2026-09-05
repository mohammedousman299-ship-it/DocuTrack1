<?php

declare(strict_types=1);

namespace App\Auth\PhoneVerification;

use App\Models\User;
use App\Notifications\NotificationDispatcher;
use App\Notifications\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Émission et vérification des codes SMS.
 *
 * Trois propriétés portent la valeur du contrôle (D-013) :
 *
 * 1. le code est HACHÉ en base — une fuite ne permet pas de valider des
 *    numéros à la place des utilisateurs ;
 * 2. les tentatives sont BORNÉES — un code à 6 chiffres se devine sinon ;
 * 3. émettre un nouveau code INVALIDE les précédents — sans quoi un attaquant
 *    accumulerait des codes valides simultanés, multipliant ses chances.
 */
final class PhoneVerificationService
{
    public const MAX_ATTEMPTS = 5;

    public const LIFETIME_MINUTES = 10;

    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /** Émet un code et le fait partir par SMS. Renvoie le code en clair UNIQUEMENT hors production. */
    public function issue(User $user): ?string
    {
        if ($user->phone_e164 === null) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $code): void {
            // Invalide les codes précédents : plusieurs codes valides en même
            // temps multiplieraient les chances d'un attaquant.
            DB::table('phone_verification_codes')
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            DB::table('phone_verification_codes')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'phone_e164' => $user->phone_e164,
                'code_hash' => hash('sha256', $code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->dispatcher->queue(
            recipient: $user,
            template: NotificationTemplate::PhoneVerificationCode,
            channel: 'sms',
            // Clé dérivée de l'évènement, pas du passage de cron.
            idempotencyKey: 'phone-verification:'.$user->id.':'.hash('sha256', $code),
            parameters: ['code' => $code],
        );

        // Hors production seulement : sans prestataire SMS réel, il n'existe
        // aucun autre moyen de poursuivre le parcours en développement.
        return app()->environment('production') ? null : $code;
    }

    public function verify(User $user, string $submitted): VerificationOutcome
    {
        $row = DB::table('phone_verification_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return VerificationOutcome::NoActiveCode;
        }

        if (now()->greaterThan($row->expires_at)) {
            return VerificationOutcome::Expired;
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return VerificationOutcome::TooManyAttempts;
        }

        // Le numéro a changé depuis l'émission : le code ne doit pas valider
        // un numéro auquel il n'a jamais été envoyé.
        if ($row->phone_e164 !== $user->phone_e164) {
            return VerificationOutcome::NoActiveCode;
        }

        DB::table('phone_verification_codes')->where('id', $row->id)
            ->update(['attempts' => $row->attempts + 1, 'updated_at' => now()]);

        // Comparaison en temps constant : une comparaison naïve laisserait
        // fuir le code caractère par caractère par la mesure du temps.
        if (! hash_equals((string) $row->code_hash, hash('sha256', trim($submitted)))) {
            return VerificationOutcome::Invalid;
        }

        DB::transaction(function () use ($row, $user): void {
            DB::table('phone_verification_codes')->where('id', $row->id)
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            $user->forceFill(['phone_verified_at' => now()])->save();
        });

        return VerificationOutcome::Verified;
    }
}
