<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Matching\NameNormalizer;
use App\Matching\Synthetic\SyntheticNameGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Banc d'essai de la présélection du moteur de rapprochement.
 *
 * Critère de validation du jalon 1 (docs/MATCHING.md §4.1) : la présélection
 * doit produire un INDEX SCAN sur les deux chemins. Un SEQ SCAN invaliderait
 * la conception et imposerait de la revoir avant d'écrire le moteur.
 *
 * Les données générées sont ENTIÈREMENT FICTIVES (§12 du master prompt).
 */
final class BenchmarkMatchingCommand extends Command
{
    protected $signature = 'docutrack:benchmark-matching
        {--rows=100000 : Nombre de signalements synthétiques à générer}
        {--keep : Conserver les données après la mesure}';

    protected $description = 'Mesure le plan d\'exécution de la présélection sur un volume réaliste';

    public function handle(): int
    {
        $rows = (int) $this->option('rows');

        $typeId = DB::table('document_types')->where('code', 'national_id')->value('id');

        if ($typeId === null) {
            $this->error('Lancez d\'abord php artisan db:seed.');

            return self::FAILURE;
        }

        $userId = $this->ensureBenchmarkUser();

        $this->info("Génération de {$rows} signalements synthétiques…");
        $this->generateFoundReports($rows, (int) $typeId, $userId);

        DB::statement('ANALYZE found_reports');

        $total = DB::table('found_reports')->count();
        $this->line("Total en base : {$total} lignes");
        $this->newLine();

        $sample = new SyntheticNameGenerator(1234);
        $probeName = NameNormalizer::normalize($sample->fullName());

        $results = [
            'chemin nom (trigramme)' => $this->explain(
                'SELECT id FROM found_reports
                 WHERE document_type_id = ? AND status = ?
                   AND owner_name_normalized % ?
                 LIMIT 50',
                [$typeId, 'active', $probeName]
            ),
            'chemin numéro (HMAC)' => $this->explain(
                'SELECT id FROM found_reports
                 WHERE document_type_id = ? AND status = ?
                   AND number_hmac = ?
                 LIMIT 50',
                [$typeId, 'active', hash('sha256', 'sonde')]
            ),
            'présélection complète (OR)' => $this->explain(
                'SELECT id FROM found_reports
                 WHERE document_type_id = ? AND status = ?
                   AND (number_hmac = ? OR owner_name_normalized % ?)
                 LIMIT 50',
                [$typeId, 'active', hash('sha256', 'sonde'), $probeName]
            ),
        ];

        $failed = false;

        foreach ($results as $label => $plan) {
            $usesIndex = $plan['uses_index'];
            $verdict = $usesIndex ? 'INDEX SCAN' : 'SEQ SCAN';
            $failed = $failed || ! $usesIndex;

            $this->line(sprintf(
                '%-30s %-12s %8.2f ms',
                $label,
                $verdict,
                $plan['duration_ms']
            ));

            foreach ($plan['nodes'] as $node) {
                $this->line('    '.$node);
            }
        }

        $this->newLine();

        if (! $this->option('keep')) {
            DB::table('found_reports')->where('finder_user_id', $userId)->delete();
            $this->line('Données de mesure supprimées (--keep pour les conserver).');
        }

        if ($failed) {
            $this->error('ÉCHEC : au moins un chemin fait un parcours séquentiel.');
            $this->error('La conception de docs/MATCHING.md §4.1 est invalidée.');

            return self::FAILURE;
        }

        $this->info('Tous les chemins de présélection utilisent un index.');

        return self::SUCCESS;
    }

    private function ensureBenchmarkUser(): string
    {
        $existing = DB::table('users')->where('email', 'benchmark@docutrack.invalid')->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $id = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $id,
            'full_name' => 'Compte de mesure',
            'full_name_normalized' => NameNormalizer::normalize('Compte de mesure'),
            'email' => 'benchmark@docutrack.invalid',
            'password' => bcrypt(Str::random(40)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function generateFoundReports(int $rows, int $typeId, string $userId): void
    {
        $generator = new SyntheticNameGenerator;
        $bar = $this->output->createProgressBar($rows);
        $chunk = [];
        $now = now();
        $expires = now()->addDays(180);

        for ($i = 0; $i < $rows; $i++) {
            $name = $generator->fullName();
            $number = $generator->documentNumber();

            $chunk[] = [
                'id' => (string) Str::uuid(),
                'finder_user_id' => $userId,
                'document_type_id' => $typeId,
                'owner_name' => $name,
                'owner_name_normalized' => NameNormalizer::normalize($name),
                'number_hmac' => hash('sha256', $number),
                'found_on' => $now->toDateString(),
                'found_region' => 'Centre',
                'found_city' => 'Ville '.($i % 50),
                'duplicate_fingerprint' => hash('sha256', $number.$name),
                'status' => 'active',
                'expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) === 2000) {
                DB::table('found_reports')->insert($chunk);
                $chunk = [];
                $bar->advance(2000);
            }
        }

        if ($chunk !== []) {
            DB::table('found_reports')->insert($chunk);
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine(2);
    }

    /**
     * @param  list<mixed>  $bindings
     * @return array{uses_index: bool, duration_ms: float, nodes: list<string>}
     */
    private function explain(string $sql, array $bindings): array
    {
        $output = DB::select('EXPLAIN (ANALYZE, BUFFERS) '.$sql, $bindings);

        $lines = array_map(
            static fn (object $row): string => trim((string) reset($row)),
            $output
        );

        $plan = implode("\n", $lines);

        // Un parcours séquentiel sur found_reports invalide la conception.
        // Attention : « Seq Scan » peut apparaître légitimement sur une petite
        // table jointe ; on ne teste donc que la table mesurée.
        $usesIndex = preg_match('/Seq Scan on found_reports/i', $plan) !== 1;

        preg_match('/Execution Time: ([\d.]+) ms/', $plan, $matches);

        $nodes = array_values(array_filter(
            $lines,
            static fn (string $line): bool => preg_match('/(Scan|Bitmap|Recheck)/i', $line) === 1
        ));

        return [
            'uses_index' => $usesIndex,
            'duration_ms' => (float) ($matches[1] ?? 0),
            'nodes' => $nodes,
        ];
    }
}
