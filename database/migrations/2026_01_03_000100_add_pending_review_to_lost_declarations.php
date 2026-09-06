<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le statut `pending_review` aux déclarations de perte.
 *
 * D-036 est postérieure au schéma du jalon 0 : une déclaration dont le nom ne
 * correspond pas au compte est acceptée mais part en revue, sans notification
 * automatique (M-02). Le statut n'existait pas dans la contrainte d'origine.
 *
 * La contrainte est reconstruite plutôt qu'élargie : PostgreSQL ne sait pas
 * modifier un CHECK en place.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'lost_declarations_status_check';

    /** @var list<string> */
    private const STATUSES = [
        'pending_review', 'active', 'matched', 'resolved', 'expired', 'withdrawn',
    ];

    /** @var list<string> */
    private const PREVIOUS_STATUSES = [
        'active', 'matched', 'resolved', 'expired', 'withdrawn',
    ];

    public function up(): void
    {
        $this->replaceConstraint(self::STATUSES);
    }

    public function down(): void
    {
        // Les lignes en revue n'ont plus de statut valide dans l'ancienne
        // contrainte : on les ramène en 'active' plutôt que de faire échouer
        // le retour arrière. Elles perdent leur mise en revue — c'est la
        // conséquence assumée d'un retour en arrière sur cette migration.
        DB::table('lost_declarations')
            ->where('status', 'pending_review')
            ->update(['status' => 'active']);

        $this->replaceConstraint(self::PREVIOUS_STATUSES);
    }

    /** @param list<string> $statuses */
    private function replaceConstraint(array $statuses): void
    {
        $values = implode(', ', array_map(
            static fn (string $s): string => "'".$s."'",
            $statuses
        ));

        DB::statement('ALTER TABLE lost_declarations DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE lost_declarations ADD CONSTRAINT '.self::CONSTRAINT
            .' CHECK (status::text = ANY (ARRAY['.$values.']::text[]))'
        );
    }
};
