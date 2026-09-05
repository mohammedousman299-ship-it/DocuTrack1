<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fonctions de normalisation du moteur de rapprochement (docs/MATCHING.md §2).
 *
 * Elles doivent être IMMUTABLE pour être utilisables dans un index. Or
 * unaccent() est déclarée STABLE (elle dépend d'un dictionnaire chargeable),
 * d'où l'enveloppe ci-dessous qui fixe explicitement le dictionnaire.
 *
 * Un miroir PHP existe dans app/Matching/. Les deux implémentations sont
 * couvertes par un test d'équivalence : une divergence produirait des
 * correspondances irreproductibles selon le chemin emprunté.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION docutrack_immutable_unaccent(text)
            RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS
            $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
        SQL);

        // Noms : accents repliés, minuscules, ponctuation supprimée, espaces
        // compressés, tokens TRIÉS.
        //
        // Le tri ne sert PAS à neutraliser l'inversion nom/prénom pour la
        // similarité : pg_trgm compare des ensembles de trigrammes et ignore
        // déjà l'ordre des mots (mesuré, docs/MATCHING.md §3.6). Il sert aux
        // comparaisons par ÉGALITÉ : empreinte de doublon, contrôle de
        // cohérence du nom du compte, et reproductibilité du miroir PHP.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION docutrack_normalize_name(input text)
            RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS
            $$
                SELECT coalesce(
                    (
                        SELECT string_agg(token, ' ' ORDER BY token)
                        FROM regexp_split_to_table(
                            trim(regexp_replace(
                                -- Les apostrophes sont SUPPRIMÉES, pas traitées
                                -- comme séparateurs : elles sont internes au nom
                                -- (« Ol'anda » est un seul token, pas deux).
                                translate(
                                    lower(docutrack_immutable_unaccent(input)),
                                    U&'\0027\2019\02BC\0060', ''
                                ),
                                -- Tout le reste sépare, tirets compris : un nom
                                -- composé doit se rapprocher de sa graphie sans
                                -- tiret.
                                '[^a-z0-9]+', ' ', 'g'
                            )),
                            '\s+'
                        ) AS token
                        WHERE token <> ''
                    ),
                    ''
                )
            $$
        SQL);

        // Numéros : majuscules, caractères non alphanumériques supprimés, et
        // SEULEMENT les homoglyphes O→0 et I→1.
        //
        // S↔5 et B↔8 sont volontairement écartés : ils créent des collisions
        // entre numéros réellement distincts. Ces confusions-là relèvent de la
        // distance d'édition, pas de la normalisation.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION docutrack_normalize_number(input text)
            RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS
            $$
                -- Le filtrage précède la mise en majuscules, et c'est
                -- délibéré : upper() dépend de la locale (upper('ß') vaut
                -- 'ß' ou 'SS' selon la collation). En ne gardant que de
                -- l'ASCII avant, le résultat devient indépendant de la
                -- locale, donc identique en SQL et en PHP.
                SELECT translate(
                    upper(regexp_replace(input, '[^A-Za-z0-9]', '', 'g')),
                    'OI', '01'
                )
            $$
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS docutrack_normalize_number(text)');
        DB::statement('DROP FUNCTION IF EXISTS docutrack_normalize_name(text)');
        DB::statement('DROP FUNCTION IF EXISTS docutrack_immutable_unaccent(text)');
    }
};
