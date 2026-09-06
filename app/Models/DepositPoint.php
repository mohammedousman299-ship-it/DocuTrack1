<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DepositPointFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Point de dépôt et de retrait — contenu privilégié de l'information N3
 * (D-009, D-028).
 *
 * Le dépôt auprès d'un tiers reste le mode PRIVILÉGIÉ : il évite par
 * construction de mettre deux inconnus en relation. La mise en relation
 * médiatisée n'est qu'un repli, et l'interface doit le dire (M-14).
 *
 * ATTENTION : aucun partenaire réel n'existe à ce jour (C-03, Q-14). Tant que
 * cette table ne contient aucun point vérifié, seul le repli s'applique.
 *
 * @property string $id
 * @property string $name
 * @property string $region
 * @property string $city
 * @property bool $is_verified
 */
class DepositPoint extends Model
{
    /** @use HasFactory<DepositPointFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name', 'kind', 'region', 'city', 'address',
        'opening_hours', 'institutional_contact', 'is_verified',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }

    /**
     * Un point non vérifié n'entre jamais en N3 : orienter quelqu'un vers un
     * lieu non confirmé serait pire que ne rien lui dire.
     */
    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function label(): string
    {
        return $this->name.' — '.$this->city;
    }
}
