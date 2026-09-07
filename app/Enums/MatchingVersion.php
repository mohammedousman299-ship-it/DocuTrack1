<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Versions du moteur de rapprochement.
 *
 * Une version n'est pas un numéro décoratif : elle est inscrite dans chaque
 * ligne de `matches` et fait partie de sa clé d'unicité. Changer les seuils
 * sans changer la version rendrait les scores anciens et nouveaux
 * indiscernables, et le rejeu produirait des doublons silencieux.
 */
enum MatchingVersion: string
{
    /** Seuils proposés, non mesurés. Conservée pour lire les scores anciens. */
    case V1 = 'v1-proposed';

    /**
     * Seuils calibrés par le balayage du jalon 5 (D-041) et score de nom à
     * trois termes (D-040). Version distincte, et non modification de v1 :
     * un score porte sa version, sinon il devient inexplicable dès que les
     * réglages changent.
     */
    case V2 = 'v2-calibrated';
}
