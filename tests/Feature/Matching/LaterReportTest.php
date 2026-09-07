<?php

declare(strict_types=1);

use App\Enums\LostDeclarationStatus;
use App\Internal\TaskBudget;
use App\Matching\MatchNewReports;
use App\Models\DocumentMatch;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\LostDeclaration;
use App\Models\User;
use App\Notifications\NotificationTemplate;
use App\Search\ProcessSearchRequests;
use App\Search\SubmitSearch;
use Illuminate\Support\Facades\DB;

/**
 * Le second sens du rapprochement.
 *
 * L'écran promet : « votre déclaration reste active, vous serez prévenu si
 * quelqu'un le signale plus tard ». Avant le jalon 5, rien ne confrontait un
 * signalement nouveau aux déclarations existantes : la promesse était affichée
 * mais jamais tenue.
 */
function typeTardif(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'tardif'],
        ['label_fr' => 'Carte', 'label_en' => 'Card', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

/** @return array{0: User, 1: FoundReport} */
function declarerPuisSignaler(string $nomSignale, ?string $statutDeclaration = null): array
{
    $type = typeTardif();
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);

    app(SubmitSearch::class)->handle($owner, [
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'lost_region' => 'Centre',
        'lost_on' => now()->subDays(10)->toDateString(),
    ]);

    if ($statutDeclaration !== null) {
        LostDeclaration::query()->update(['status' => $statutDeclaration]);
    }

    // Le signalement arrive APRÈS la recherche — vraiment après. Créés dans
    // la même seconde, ils porteraient le même horodatage et le balayage les
    // écarterait, à juste titre : à égalité, c'est le chemin de la recherche
    // qui couvre le couple.
    test()->travel(1)->minutes();

    $report = FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => $nomSignale,
        'found_region' => 'Centre',
        'found_on' => now()->toDateString(),
    ]);

    return [$owner, $report];
}

it('rapproche un signalement postérieur et prévient le propriétaire', function (): void {
    [$owner, $report] = declarerPuisSignaler('Miro Olanda');

    $result = app(MatchNewReports::class)->handle(new TaskBudget(10));

    expect($result['processed'])->toBe(1)
        ->and($result['details']['notified'])->toBe(1)
        ->and(DocumentMatch::where('found_report_id', $report->id)->value('status'))->toBe('notified');

    $notification = DB::table('notifications')->where('user_id', $owner->id)
        ->where('template', NotificationTemplate::MatchFound->value)->first();

    expect($notification)->not->toBeNull();
});

it('n’écrit aucune donnée du document dans la ligne de notification', function (): void {
    // M-10 : un SMS s'affiche sur un écran verrouillé, dans un lieu public,
    // sur un téléphone parfois partagé. La ligne ne porte que le GABARIT et sa
    // clé d'idempotence — le texte est rendu au moment de l'envoi. Le message
    // ne peut donc pas fuir par la base, faute d'y être écrit.
    [$owner, $report] = declarerPuisSignaler('Miro Olanda');

    app(MatchNewReports::class)->handle(new TaskBudget(10));

    $ligne = DB::table('notifications')->where('user_id', $owner->id)
        ->where('template', NotificationTemplate::MatchFound->value)->first();

    $serialisee = json_encode($ligne, JSON_THROW_ON_ERROR);

    expect($serialisee)->not->toContain('Olanda')
        ->and($serialisee)->not->toContain('Centre')
        ->and($serialisee)->not->toContain((string) $report->id)
        // …et le gabarit lui-même n'accepte aucun paramètre.
        ->and(NotificationTemplate::MatchFound->allowedParameters())->toBe([]);
});

it('ne renotifie pas quand le cron rejoue', function (): void {
    [$owner] = declarerPuisSignaler('Miro Olanda');

    app(MatchNewReports::class)->handle(new TaskBudget(10));
    $second = app(MatchNewReports::class)->handle(new TaskBudget(10));

    // Le signalement est marqué balayé : il ne repasse pas dans le lot.
    expect($second['processed'])->toBe(0)
        ->and(DB::table('notifications')->where('user_id', $owner->id)
            ->where('template', NotificationTemplate::MatchFound->value)->count())->toBe(1);
});

it('ignore une déclaration en revue, comme partout ailleurs', function (): void {
    // D-039 : tant qu'un humain n'a pas tranché, elle ne produit ni
    // rapprochement, ni notification.
    [$owner] = declarerPuisSignaler('Miro Olanda', LostDeclarationStatus::PendingReview->value);

    $result = app(MatchNewReports::class)->handle(new TaskBudget(10));

    expect($result['details']['notified'])->toBe(0)
        ->and(DocumentMatch::count())->toBe(0)
        ->and(DB::table('notifications')->where('user_id', $owner->id)
            ->where('template', NotificationTemplate::MatchFound->value)->count())->toBe(0);
});

it('ne notifie pas un signalement qui ne fait que passer en revue', function (): void {
    // Score sous le seuil de notification : la correspondance existe pour
    // l'administrateur, pas pour l'utilisateur.
    [$owner] = declarerPuisSignaler('Miro Kwalar');

    $result = app(MatchNewReports::class)->handle(new TaskBudget(10));

    expect($result['details']['notified'])->toBe(0)
        ->and(DB::table('notifications')->where('user_id', $owner->id)
            ->where('template', NotificationTemplate::MatchFound->value)->count())->toBe(0);
});

it('marque un signalement sans correspondance, pour ne pas le rebalayer sans fin', function (): void {
    declarerPuisSignaler('Ngoya Bwadem Tsalir');

    app(MatchNewReports::class)->handle(new TaskBudget(10));

    expect(FoundReport::whereNull('last_matched_at')->count())->toBe(0);
});

it('n’envoie pas PLUS de messages à qui a un résultat qu’à qui n’en a pas', function (): void {
    /*
     | D-034 dit que le CONTENU du message ne révèle pas le résultat. Il faut
     | aussi que leur NOMBRE ne le révèle pas.
     |
     | Le second sens du rapprochement a cassé cette propriété sans que rien ne
     | le signale : un couple traité deux fois — une fois par la recherche, une
     | fois par le balayage — valait deux SMS à celui qui avait un résultat,
     | contre un seul aux autres. Compter ses messages suffisait, sans ouvrir
     | le site, hors de portée des quotas et de la journalisation.
     */
    $type = typeTardif();

    $report = FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'found_region' => 'Centre',
        'found_on' => now()->toDateString(),
    ]);

    test()->travel(1)->minutes();

    $avecResultat = User::factory()->create(['full_name' => 'Miro Olanda']);
    $sansResultat = User::factory()->create(['full_name' => 'Ngoya Bwadem']);

    foreach ([$avecResultat, $sansResultat] as $user) {
        app(SubmitSearch::class)->handle($user, [
            'document_type_id' => $type->id,
            'owner_name' => $user->full_name,
            'lost_region' => 'Centre',
        ]);
    }

    // Les DEUX sens, comme le cron réel les enchaîne.
    app(ProcessSearchRequests::class)->handle(new TaskBudget(10));
    app(MatchNewReports::class)->handle(new TaskBudget(10));

    $compter = fn (User $u): int => DB::table('notifications')->where('user_id', $u->id)->count();

    expect($compter($avecResultat))->toBe($compter($sansResultat))
        ->and($compter($avecResultat))->toBe(1);

    // …et c'est bien le même gabarit des deux côtés.
    $gabarits = DB::table('notifications')->distinct()->pluck('template')->all();

    expect($gabarits)->toBe([NotificationTemplate::SearchCompleted->value]);
});
