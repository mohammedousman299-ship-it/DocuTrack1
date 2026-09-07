<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Correspondance entre une déclaration de perte et un signalement.
 *
 * Nommée `DocumentMatch` et non `Match` : `match` est un mot réservé de PHP 8
 * depuis l'expression du même nom, et la classe serait impossible à déclarer.
 * La table, elle, reste `matches`.
 *
 * `score_breakdown` n'est pas décoratif : sans lui, régler un seuil relève de
 * la divination et expliquer un faux positif après coup est impossible (§5).
 *
 * @property string $id
 * @property string $lost_declaration_id
 * @property string $found_report_id
 * @property string $algorithm_version
 * @property string $score
 * @property array<string, mixed> $score_breakdown
 * @property bool $possible_number_typo
 * @property string $status
 * @property Carbon|null $notified_at
 */
class DocumentMatch extends Model
{
    use HasUuids;

    protected $table = 'matches';

    /** @var list<string> */
    protected $fillable = [
        'lost_declaration_id', 'found_report_id',
        'algorithm_version', 'score', 'score_breakdown',
        'possible_number_typo', 'status', 'notified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score_breakdown' => 'array',
            'possible_number_typo' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LostDeclaration, $this> */
    public function lostDeclaration(): BelongsTo
    {
        return $this->belongsTo(LostDeclaration::class);
    }

    /** @return BelongsTo<FoundReport, $this> */
    public function foundReport(): BelongsTo
    {
        return $this->belongsTo(FoundReport::class);
    }
}
