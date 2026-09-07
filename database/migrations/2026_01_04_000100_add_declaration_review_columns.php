<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * File de revue des déclarations à nom incohérent (D-036).
 *
 * Trois manques constatés en construisant la file :
 *
 * 1. `lost_declarations` n'avait pas de statut de REFUS. Ramener une
 *    déclaration refusée sur 'withdrawn' l'aurait confondue avec un retrait
 *    par l'utilisateur lui-même — deux faits différents, dont l'un est une
 *    décision d'administrateur qui doit rester lisible.
 *
 * 2. `search_requests` n'avait pas de statut d'ATTENTE. Sans lui, une demande
 *    rattachée à une déclaration en revue était traitée comme les autres :
 *    résultats écrits, notification envoyée, niveau N1 affiché. La mise en
 *    revue de D-036 ne bloquait donc rien.
 *
 * 3. `search_requests` ne portait AUCUN lien vers sa déclaration. Le traitement
 *    la retrouvait par (utilisateur, type de document, la plus récente) — une
 *    heuristique qui désigne la mauvaise déclaration dès qu'un utilisateur
 *    lance deux recherches du même type. Le lien explicite supprime à la fois
 *    ce défaut et l'impossibilité de savoir si la demande relève d'une
 *    déclaration en revue.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const DECLARATION_STATUSES = [
        'pending_review', 'active', 'matched', 'resolved', 'expired', 'withdrawn', 'rejected',
    ];

    /** @var list<string> */
    private const PREVIOUS_DECLARATION_STATUSES = [
        'pending_review', 'active', 'matched', 'resolved', 'expired', 'withdrawn',
    ];

    /** @var list<string> */
    private const REQUEST_STATUSES = ['queued', 'held', 'processed', 'failed'];

    /** @var list<string> */
    private const PREVIOUS_REQUEST_STATUSES = ['queued', 'processed', 'failed'];

    public function up(): void
    {
        $this->replaceCheck(
            'lost_declarations',
            'lost_declarations_status_check',
            'status',
            self::DECLARATION_STATUSES
        );

        $this->replaceCheck(
            'search_requests',
            'search_requests_status_check',
            'status',
            self::REQUEST_STATUSES
        );

        Schema::table('search_requests', function (Blueprint $table): void {
            // Nullable pour rester applicable à des lignes existantes ; le
            // traitement, lui, REFUSE une demande sans déclaration plutôt que
            // de deviner laquelle (échec fermé, App\Search\ProcessSearchRequests).
            $table->foreignUuid('lost_declaration_id')->nullable()
                ->after('user_id')
                ->constrained('lost_declarations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('search_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lost_declaration_id');
        });

        // Une demande en attente de revue n'a plus de statut valide dans
        // l'ancienne contrainte. La ramener sur 'queued' la ferait traiter au
        // prochain passage, en contournant la revue : elle est marquée
        // 'failed', qui ne déclenche rien.
        DB::table('search_requests')->where('status', 'held')->update(['status' => 'failed']);
        DB::table('lost_declarations')->where('status', 'rejected')->update(['status' => 'withdrawn']);

        $this->replaceCheck(
            'search_requests',
            'search_requests_status_check',
            'status',
            self::PREVIOUS_REQUEST_STATUSES
        );
        $this->replaceCheck(
            'lost_declarations',
            'lost_declarations_status_check',
            'status',
            self::PREVIOUS_DECLARATION_STATUSES
        );
    }

    /** @param list<string> $values */
    private function replaceCheck(string $table, string $constraint, string $column, array $values): void
    {
        $list = implode(', ', array_map(static fn (string $v): string => "'".$v."'", $values));

        DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS '.$constraint);
        DB::statement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT '.$constraint
            .' CHECK ('.$column.'::text = ANY (ARRAY['.$list.']::text[]))'
        );
    }
};
