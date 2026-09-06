<?php

declare(strict_types=1);

namespace App\Models;

use App\Documents\DocumentNumber;
use App\Documents\DuplicateFingerprint;
use App\Enums\FoundReportStatus;
use App\Matching\NameNormalizer;
use Database\Factories\FoundReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Signalement de découverte d'un document.
 *
 * ------------------------------------------------------------------------
 * CE MODÈLE NE PORTE VOLONTAIREMENT AUCUN RETOUR DE RAPPROCHEMENT.
 *
 * Pas de compteur de correspondances, pas de statut de rapprochement lisible
 * par le Trouveur. Un tel retour transformerait l'envoi de faux signalements en
 * canal d'extraction : l'attaquant déposerait des signalements calibrés et
 * apprendrait, par le retour, qu'une personne donnée a déclaré la perte d'un
 * document. Cette attaque contourne intégralement les quotas de recherche
 * (docs/THREAT_MODEL.md M-06).
 * ------------------------------------------------------------------------
 *
 * @property string $id
 * @property string $finder_user_id
 * @property int $document_type_id
 * @property string|null $owner_name
 * @property string|null $owner_name_normalized
 * @property string|null $number_hmac
 * @property string $duplicate_fingerprint
 * @property FoundReportStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $found_on
 * @property string|null $number_encrypted
 * @property string|null $number_last4_encrypted
 * @property string|null $deposit_reference_encrypted
 */
class FoundReport extends Model
{
    /** @use HasFactory<FoundReportFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'finder_user_id', 'document_type_id', 'owner_name',
        'found_on', 'found_region', 'found_city',
        'deposit_point_id', 'deposit_free_text', 'extra_info',
    ];

    /**
     * Ces colonnes ne sont jamais assignables en masse ni sérialisées : elles
     * sont dérivées, et deux d'entre elles portent des données de niveau N3.
     *
     * @var list<string>
     */
    protected $hidden = [
        'number_encrypted', 'number_last4_encrypted',
        'deposit_reference_encrypted', 'duplicate_fingerprint', 'number_hmac',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'found_on' => 'date',
            'expires_at' => 'datetime',
            'status' => FoundReportStatus::class,
            'number_encrypted' => 'encrypted',
            'number_last4_encrypted' => 'encrypted',
            'deposit_reference_encrypted' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // Le nom normalisé et l'empreinte de doublon sont DÉRIVÉS, jamais
        // saisis : une dérive entre eux et les champs sources fausserait le
        // rapprochement et le contrôle de doublon sans que rien ne le signale.
        static::saving(function (self $report): void {
            $report->owner_name_normalized = NameNormalizer::normalize($report->owner_name);

            $report->duplicate_fingerprint = DuplicateFingerprint::compute(
                $report->document_type_id,
                $report->numberInClear(),
                $report->owner_name,
            );
        });
    }

    /** Enregistre un numéro sous ses trois formes (D-007). */
    public function setDocumentNumber(?string $input): void
    {
        $number = DocumentNumber::fromInput($input);

        // Les colonnes portent un cast `encrypted` : on leur confie la valeur
        // normalisée en clair, Eloquent se charge du chiffrement.
        $this->number_encrypted = $number->normalized;
        $this->number_hmac = $number->hmac;
        $this->number_last4_encrypted = $number->last4;
    }

    /**
     * Numéro déchiffré. Réservé au niveau N3 et à la revue administrateur :
     * tout appel doit être justifié par une Policy et journalisé.
     */
    public function numberInClear(): ?string
    {
        return $this->number_encrypted;
    }

    /** @return BelongsTo<User, $this> */
    public function finder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finder_user_id');
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return HasMany<ReportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    /**
     * Signalements entrant dans l'index de rapprochement.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeMatchable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            FoundReportStatus::Active->value,
            FoundReportStatus::Matched->value,
        ]);
    }
}
