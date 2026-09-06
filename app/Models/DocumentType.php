<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie de document, gérée par l'Administrateur (§1.10).
 *
 * @property int $id
 * @property string $code
 * @property string $sensitivity
 * @property int $retention_days
 */
class DocumentType extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code', 'label_fr', 'label_en', 'sensitivity',
        'number_min_length', 'number_max_length', 'number_alphabet',
        'retention_days', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'retention_days' => 'integer'];
    }

    /**
     * Les types sensibles imposent une revue humaine avant restitution (M-01)
     * ET une photo obligatoire au signalement (D-030) : sans image,
     * l'administrateur n'aurait rien à vérifier et le contrôle serait décoratif.
     */
    public function isSensitive(): bool
    {
        return $this->sensitivity === 'high';
    }

    public function requiresAttachment(): bool
    {
        return $this->isSensitive();
    }

    public function label(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en'
            ? $this->label_en
            : $this->label_fr;
    }

    /** @return HasMany<FoundReport, $this> */
    public function foundReports(): HasMany
    {
        return $this->hasMany(FoundReport::class);
    }
}
