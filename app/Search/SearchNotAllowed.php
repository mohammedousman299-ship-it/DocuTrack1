<?php

declare(strict_types=1);

namespace App\Search;

use RuntimeException;

/**
 * Refus de recherche opposable à l'utilisateur.
 *
 * Le message est destiné à être AFFICHÉ : il dit ce qui bloque et quand
 * réessayer, sans révéler les seuils exacts — les connaître permettrait de
 * s'en tenir juste en dessous.
 *
 * Type distinct d'InvalidArgumentException, qui signale une erreur de
 * programmation : celle-ci est un refus attendu, que l'interface doit
 * présenter et non laisser remonter en 500.
 */
final class SearchNotAllowed extends RuntimeException {}
