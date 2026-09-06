<?php

declare(strict_types=1);

namespace App\Models;

use App\Documents\DocumentNumber;
use App\Enums\LostDeclarationStatus;
use App\Enums\NameConsistency;
use App\Matching\NameNormalizer;
use Database\Factories\LostDeclarationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Déclaration de perte.
 *
 * Elle joue deux rôles à la fois (D-035) : elle est confrontée immédiatement
 * aux signalements existants — ce que l'utilisateur perçoit comme une
 * recherche — et elle reste active pour être confrontée aux signalements
 * futurs.
 *
 * @property string $id
 * @property string $user_id
 * @property int $document_type_id
 * @property string $owner_name
 * @property string $owner_name_normalized
 * @property string|null $number_hmac
 * @property string|null $number_encrypted
 * @property string|null $number_last4_encrypted
 * @property NameConsistency $name_consistency
 * @property LostDeclarationStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $lost_on
 */
class LostDeclaration extends Model
{
    /** @use HasFactory<LostDeclarationFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'document_type_id', 'owner_name',
        'lost_on', 'lost_region', 'lost_city', 'extra_info',
    ];

    /** Dérivés ou de niveau N3 : ni assignables en masse, ni sérialisés. */
    protected $hidden = [
        'number_encrypted', 'number_hmac', 'number_last4_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lost_on' => 'date',
            'expires_at' => 'datetime',
            'status' => LostDeclarationStatus::class,
            'name_consistency' => NameConsistency::class,
            'number_encrypted' => 'encrypted',
            'number_last4_encrypted' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $declaration): void {
            $declaration->owner_name_normalized = NameNormalizer::normalize($declaration->owner_name);
        });
    }

    /** Enregistre le numéro sous ses trois formes (D-007). */
    public function setDocumentNumber(?string $input): void
    {
        $number = DocumentNumber::fromInput($input);

        $this->number_encrypted = $number->normalized;
        $this->number_hmac = $number->hmac;
        $this->number_last4_encrypted = $number->last4;
    }

    /** Réservé au niveau N3 et à la revue administrateur. */
    public function numberInClear(): ?string
    {
        return $this->number_encrypted;
    }

    /**
     * Une déclaration incohérente ne notifie jamais automatiquement (D-036).
     */
    public function allowsAutomaticNotification(): bool
    {
        return $this->name_consistency->allowsAutomaticNotification()
            && $this->status->isMatchable();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeMatchable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LostDeclarationStatus::Active->value,
            LostDeclarationStatus::Matched->value,
        ]);
    }
}
