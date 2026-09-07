<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Réglages du moteur de rapprochement (§5 du master prompt).
 *
 * Les seuils et pondérations sont en base et NON dans le code : les régler
 * demande sinon un redéploiement, ce qui revient en pratique à ne jamais les
 * régler. Chaque jeu porte une `algorithm_version`, inscrite dans chaque ligne
 * de `matches` : sans elle, un score ancien devient inexplicable dès que les
 * réglages changent.
 *
 * @property string $algorithm_version
 * @property string $notify_threshold
 * @property string $review_threshold
 * @property string $weight_number
 * @property string $weight_name
 * @property string $weight_geography
 * @property string $number_typo_name_threshold
 * @property bool $is_active
 */
class MatchingSetting extends Model
{
    public $timestamps = true;

    /** @var list<string> */
    protected $fillable = [
        'algorithm_version',
        'notify_threshold', 'review_threshold',
        'weight_number', 'weight_name', 'weight_geography',
        'number_typo_name_threshold',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Le jeu actif, ou une erreur explicite.
     *
     * Retomber sur des valeurs par défaut codées en dur serait pire que
     * d'échouer : le moteur tournerait avec des seuils que personne n'a
     * choisis, et rien ne le signalerait.
     */
    public static function active(): self
    {
        $settings = self::query()->where('is_active', true)->first();

        if ($settings === null) {
            throw new RuntimeException(
                'Aucun jeu de réglages de rapprochement actif : le moteur ne '
                .'peut pas tourner sur des seuils implicites.'
            );
        }

        return $settings;
    }
}
