<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extensions PostgreSQL requises par le moteur de rapprochement.
 *
 * Les trois premières sont bloquantes : sans elles, la conception décrite dans
 * docs/MATCHING.md ne tient pas. Créées ici de façon idempotente pour que le
 * projet fonctionne aussi sur une base qui n'aurait pas été initialisée par
 * docker/postgres/init-extensions.sql.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const REQUIRED = ['unaccent', 'pg_trgm', 'fuzzystrmatch', 'pgcrypto'];

    public function up(): void
    {
        foreach (self::REQUIRED as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }

        $installed = collect(DB::select('select extname from pg_extension'))
            ->pluck('extname')
            ->all();

        $missing = array_diff(self::REQUIRED, $installed);

        if ($missing !== []) {
            throw new RuntimeException(
                'Extensions PostgreSQL manquantes : '.implode(', ', $missing).'. '
                .'Le moteur de rapprochement en dépend (docs/MATCHING.md §7).'
            );
        }
    }

    public function down(): void
    {
        // Les extensions ne sont pas supprimées : elles peuvent être partagées
        // avec d'autres schémas de la même base.
    }
};
