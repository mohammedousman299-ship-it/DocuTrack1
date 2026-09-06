<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportAttachmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Référence d'une image de document. JAMAIS le binaire en base.
 *
 * L'image est la donnée la plus sensible du système : fournie par un tiers,
 * sur une personne qui n'a pas consenti et ignore que ce traitement existe.
 * Elle n'est visible d'AUCUN utilisateur, à aucun niveau de divulgation, y
 * compris N3 (D-006). Seul l'Administrateur y accède, avec motif saisi.
 *
 * @property string $id
 * @property string $object_key
 * @property Carbon|null $exif_stripped_at
 */
class ReportAttachment extends Model
{
    /** @use HasFactory<ReportAttachmentFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'found_report_id', 'object_key', 'content_hash',
        'mime_type', 'byte_size', 'width', 'height', 'expires_at',
    ];

    /** La clé d'objet ne doit jamais partir vers le client : elle est devinable une fois connue. */
    protected $hidden = ['object_key', 'content_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'exif_stripped_at' => 'datetime',
            'expires_at' => 'datetime',
            'byte_size' => 'integer',
        ];
    }

    /**
     * Une pièce jointe dont les métadonnées n'ont PAS été retirées côté serveur
     * n'est jamais servie.
     *
     * C'est la garantie qui rend acceptable l'envoi direct depuis le navigateur
     * (D-029) : le nettoyage client n'est pas une preuve, la vérification
     * serveur en est une. Une photo de pièce d'identité embarque des
     * coordonnées GPS (M-12).
     */
    public function isServable(): bool
    {
        return $this->exif_stripped_at !== null;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeServable(Builder $query): Builder
    {
        return $query->whereNotNull('exif_stripped_at');
    }

    /** @return BelongsTo<FoundReport, $this> */
    public function foundReport(): BelongsTo
    {
        return $this->belongsTo(FoundReport::class);
    }
}
