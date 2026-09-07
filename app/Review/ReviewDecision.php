<?php

declare(strict_types=1);

namespace App\Review;

/**
 * Issue d'une revue de déclaration à nom incohérent (D-036).
 *
 * Deux issues seulement. Il n'y a pas de « à revoir plus tard » : une file
 * dont on peut repousser les éléments cesse d'être une file, et la déclaration
 * resterait indéfiniment sans notification, sans que personne n'ait décidé.
 */
enum ReviewDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
}
