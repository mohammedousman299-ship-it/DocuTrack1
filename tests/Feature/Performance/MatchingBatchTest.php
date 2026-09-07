<?php

declare(strict_types=1);

use App\Internal\TaskBudget;
use App\Matching\Synthetic\SyntheticName;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use App\Search\ProcessSearchRequests;
use App\Search\SubmitSearch;
use Illuminate\Support\Facades\DB;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Temps d'un lot de rapprochement (MATCHING.md §6.4).
 *
 * Sert à dimensionner la fréquence du cron : sans ce chiffre, l'intervalle est
 * choisi au hasard, et on découvre en production qu'un lot déborde son budget.
 */
it('traite un lot de 50 demandes dans le budget de la tâche', function (): void {
    $type = DocumentType::firstOrCreate(
        ['code' => 'lot'],
        ['label_fr' => 'Carte', 'label_en' => 'Card', 'sensitivity' => 'standard', 'retention_days' => 180]
    );

    $names = new SyntheticName(new Randomizer(new Xoshiro256StarStar(7)));

    // Un index de signalements réaliste : la présélection doit avoir de quoi
    // travailler, sans quoi le lot mesuré ne rapproche rien et le chiffre ne
    // vaut rien.
    foreach (range(1, 200) as $i) {
        FoundReport::factory()->create([
            'document_type_id' => $type->id,
            'owner_name' => $names->fullName(),
            'found_region' => 'Centre',
            'found_on' => now()->subDays($i % 30)->toDateString(),
        ]);
    }

    config(['docutrack.limits.searches_per_day' => 100]);

    foreach (range(1, 50) as $i) {
        $user = User::factory()->create(['full_name' => $names->fullName()]);
        app(SubmitSearch::class)->handle($user, [
            'document_type_id' => $type->id,
            'owner_name' => $user->full_name,
            'lost_region' => 'Centre',
        ]);
    }

    $startedAt = microtime(true);
    $result = app(ProcessSearchRequests::class)->handle(new TaskBudget(30), limit: 50);
    $elapsed = microtime(true) - $startedAt;

    fwrite(STDERR, sprintf(
        "\nMESURE lot de %d demandes contre 200 signalements : %d ms (%.1f ms/demande)\n",
        $result['processed'], (int) round($elapsed * 1000), $elapsed * 1000 / max($result['processed'], 1)
    ));

    expect($result['processed'])->toBe(50)
        ->and(DB::table('search_requests')->where('status', 'queued')->count())->toBe(0);
});

it('s’arrête proprement quand le budget est épuisé, sans perdre de demandes', function (): void {
    // Un lot qui déborde doit laisser le reste en file, pas le traiter à
    // moitié : le passage suivant reprend où celui-ci s'est arrêté.
    $type = DocumentType::firstOrCreate(
        ['code' => 'lot'],
        ['label_fr' => 'Carte', 'label_en' => 'Card', 'sensitivity' => 'standard', 'retention_days' => 180]
    );

    config(['docutrack.limits.searches_per_day' => 100]);

    foreach (range(1, 5) as $i) {
        $user = User::factory()->create(['full_name' => 'Miro Olanda '.$i]);
        app(SubmitSearch::class)->handle($user, [
            'document_type_id' => $type->id,
            'owner_name' => $user->full_name,
            'lost_region' => 'Centre',
        ]);
    }

    // Budget nul : rien ne doit être traité, et rien ne doit être perdu.
    $result = app(ProcessSearchRequests::class)->handle(new TaskBudget(0), limit: 50);

    expect($result['processed'])->toBe(0)
        ->and(DB::table('search_requests')->where('status', 'queued')->count())->toBe(5);
});
