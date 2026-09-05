<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminRole;
use App\Matching\NameNormalizer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Compte utilisateur.
 *
 * Il n'existe QU'UN SEUL type de compte (D-004). « Propriétaire » et
 * « Trouveur » sont des capacités contextuelles : elles ne sont pas stockées
 * ici, elles se déduisent de la relation à une ressource.
 *
 * Justification de chaque colonne : docs/DATA_MODEL.md §2.1. Les données
 * volontairement absentes y figurent aussi — ni date de naissance, ni adresse,
 * ni pièce d'identité du titulaire du compte.
 *
 * @property string $id
 * @property string $full_name
 * @property string $full_name_normalized
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $phone_e164
 * @property Carbon|null $phone_verified_at
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property AdminRole $admin_role
 * @property int $reporter_score
 * @property string $locale
 * @property array<string, mixed> $notification_prefs
 * @property Carbon|null $blocked_until
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'full_name',
        'email',
        'phone_e164',
        'password',
        'locale',
    ];

    /**
     * Jamais sérialisé.
     *
     * `two_factor_secret` et `two_factor_recovery_codes` sont chiffrés en base
     * ET masqués ici : le chiffrement protège du vol de base, le masquage
     * protège d'une exposition accidentelle par une réponse d'API.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected static function booted(): void
    {
        // Le nom normalisé est dérivé, jamais saisi : il doit rester cohérent
        // avec full_name quoi qu'il arrive, sinon le contrôle de cohérence du
        // nom (M-01, M-02) et l'empreinte de doublon deviennent faux.
        static::saving(function (self $user): void {
            if ($user->isDirty('full_name')) {
                $user->full_name_normalized = NameNormalizer::normalize($user->full_name);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'blocked_until' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'notification_prefs' => 'array',
            'admin_role' => AdminRole::class,
            'reporter_score' => 'integer',
        ];
    }

    /**
     * Le téléphone vérifié conditionne la recherche et la revendication.
     *
     * C'est le contrôle anti-Sybil principal (D-013) : sans coût d'entrée réel,
     * les quotas par compte seraient décoratifs, et la limitation par IP est
     * inopérante au Cameroun à cause du CGNAT des opérateurs (M-04).
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    public function isAdministrator(): bool
    {
        return $this->admin_role->isAdministrator();
    }

    /** La 2FA est obligatoire pour tout administrateur (D-014, D-023). */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function canAdministerFunctionally(): bool
    {
        return $this->admin_role->grantsFunctional() && $this->hasConfirmedTwoFactor();
    }

    public function canAccessSensitiveData(): bool
    {
        return $this->admin_role->grantsSensitive() && $this->hasConfirmedTwoFactor();
    }
}
