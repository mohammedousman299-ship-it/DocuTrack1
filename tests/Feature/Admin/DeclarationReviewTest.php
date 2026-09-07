<?php

declare(strict_types=1);

use App\Enums\AdminRole;
use App\Enums\LostDeclarationStatus;
use App\Internal\TaskBudget;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\LostDeclaration;
use App\Models\User;
use App\Notifications\NotificationTemplate;
use App\Review\DecideDeclarationReview;
use App\Review\ReviewDecision;
use App\Search\ProcessSearchRequests;
use App\Search\SubmitSearch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function reviewType(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'review'],
        ['label_fr' => 'Carte', 'label_en' => 'Card', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

/** Une recherche faite par un compte AU NOM D'UN TIERS. */
function declarerPourUnTiers(User $user, string $nomDeclare = 'Yolena Bassim'): LostDeclaration
{
    return app(SubmitSearch::class)->handle($user, [
        'document_type_id' => reviewType()->id,
        'owner_name' => $nomDeclare,
        'lost_region' => 'Centre',
        'extra_info' => 'Je déclare pour ma mère, qui ne se sert pas d\'internet.',
    ])['declaration'];
}

/** @return array{processed: int, details: array<string, int>} */
function traiterLaFileDeRecherche(): array
{
    return app(ProcessSearchRequests::class)->handle(new TaskBudget(10));
}

it('met en revue une déclaration dont le nom ne correspond pas au compte', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    $declaration = declarerPourUnTiers($user);

    expect($declaration->status)->toBe(LostDeclarationStatus::PendingReview);

    $request = DB::table('search_requests')->where('user_id', $user->id)->first();

    expect($request->status)->toBe('held')
        ->and($request->lost_declaration_id)->toBe($declaration->id);
});

it('ne rapproche ni ne notifie tant que la revue n\'a pas tranché', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    // Un signalement qui correspondrait, s'il était rapproché.
    FoundReport::factory()->create([
        'document_type_id' => reviewType()->id,
        'owner_name' => 'Yolena Bassim',
        'status' => 'active',
    ]);

    declarerPourUnTiers($user);
    traiterLaFileDeRecherche();

    $request = DB::table('search_requests')->where('user_id', $user->id)->first();

    expect($request->status)->toBe('held')
        ->and($request->result_count_bucket)->toBeNull()
        ->and(DB::table('search_results')->where('search_request_id', $request->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('user_id', $user->id)->count())->toBe(0);
});

it('relance la recherche après approbation, sans la rejouer avant', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();

    FoundReport::factory()->create([
        'document_type_id' => reviewType()->id,
        'owner_name' => 'Yolena Bassim',
        'status' => 'active',
    ]);

    $declaration = declarerPourUnTiers($user);
    traiterLaFileDeRecherche();

    app(DecideDeclarationReview::class)->handle(
        reviewer: $reviewer,
        declaration: $declaration,
        decision: ReviewDecision::Approve,
        reason: 'Explication cohérente, même patronyme, compte ancien.',
    );

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::Active)
        ->and(DB::table('search_requests')->where('user_id', $user->id)->value('status'))->toBe('queued');

    traiterLaFileDeRecherche();

    $request = DB::table('search_requests')->where('user_id', $user->id)->first();

    expect($request->status)->toBe('processed')
        ->and(DB::table('search_results')->where('search_request_id', $request->id)->count())->toBe(1);
});

it('refuse et prévient l\'auteur, sans jamais relancer la recherche', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();

    $declaration = declarerPourUnTiers($user);

    app(DecideDeclarationReview::class)->handle(
        reviewer: $reviewer,
        declaration: $declaration,
        decision: ReviewDecision::Reject,
        reason: 'Aucun lien plausible, explication contradictoire.',
    );

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::Rejected)
        ->and(DB::table('search_requests')->where('user_id', $user->id)->value('status'))->toBe('failed');

    // L'auteur est prévenu : le silence le laisserait attendre indéfiniment.
    $notification = DB::table('notifications')->where('user_id', $user->id)->first();

    expect($notification)->not->toBeNull()
        ->and($notification->template)->toBe(NotificationTemplate::DeclarationRejected->value);

    traiterLaFileDeRecherche();

    expect(DB::table('search_results')->count())->toBe(0);
});

it('exige un motif, pour l\'approbation comme pour le refus', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $declaration = declarerPourUnTiers($user);

    foreach ([ReviewDecision::Approve, ReviewDecision::Reject] as $decision) {
        expect(fn () => app(DecideDeclarationReview::class)->handle(
            reviewer: $reviewer,
            declaration: $declaration,
            decision: $decision,
            reason: 'ok',
        ))->toThrow(InvalidArgumentException::class);
    }

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::PendingReview);
});

it('refuse une seconde décision sur la même déclaration', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $declaration = declarerPourUnTiers($user);

    app(DecideDeclarationReview::class)->handle(
        $reviewer, $declaration, ReviewDecision::Reject, 'Aucun lien plausible entre les deux noms.'
    );

    expect(fn () => app(DecideDeclarationReview::class)->handle(
        $reviewer->fresh(), $declaration->fresh(), ReviewDecision::Approve, 'Je change la décision précédente.'
    ))->toThrow(InvalidArgumentException::class);

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::Rejected);
});

it('refuse la décision à un administrateur fonctionnel', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Functional)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $declaration = declarerPourUnTiers($user);

    expect(fn () => app(DecideDeclarationReview::class)->handle(
        $reviewer, $declaration, ReviewDecision::Approve, 'Décision prise sans le rôle requis.'
    ))->toThrow(RuntimeException::class);

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::PendingReview);
});

it('journalise la décision de façon indélébile', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $declaration = declarerPourUnTiers($user);

    app(DecideDeclarationReview::class)->handle(
        $reviewer, $declaration, ReviewDecision::Approve, 'Explication cohérente, même patronyme.'
    );

    $entry = DB::table('audit_logs')
        ->where('action', 'declaration.review_approved')
        ->where('entity_id', $declaration->id)
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_user_id)->toBe($reviewer->id)
        ->and($entry->reason)->toContain('patronyme');

    // Append-only : même l'administrateur ne peut pas réécrire sa trace (§4.4).
    expect(fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['reason' => 'autre']))
        ->toThrow(QueryException::class);
});

/*
 |------------------------------------------------------------------------
 | Accès à l'interface
 |------------------------------------------------------------------------
 |
 | Le refus est un 404 et non un 403 (PERMISSIONS.md §3.3) : un 403
 | confirmerait à qui n'y a pas droit que la file existe.
 */

it('renvoie 404 à qui n\'est pas administrateur sensible', function (string $etat): void {
    $user = match ($etat) {
        'utilisateur' => User::factory()->create(),
        'fonctionnel' => User::factory()->admin(AdminRole::Functional)->create(),
        'sans 2FA' => User::factory()->adminWithoutTwoFactor(AdminRole::Sensitive)->create(),
        default => null,
    };

    $response = $user === null
        ? $this->get('/administration/revue-declarations')
        : $this->actingAs($user)->get('/administration/revue-declarations');

    // Un administrateur sans 2FA est redirigé vers son activation : ce n'est
    // pas un intrus, mais un compte incomplètement sécurisé (D-023).
    $etat === 'sans 2FA'
        ? $response->assertRedirect()
        : $response->assertNotFound();
})->with(['anonyme', 'utilisateur', 'fonctionnel', 'sans 2FA']);

it('affiche la file à un administrateur sensible, sans le numéro du document', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    $declaration = app(SubmitSearch::class)->handle($user, [
        'document_type_id' => reviewType()->id,
        'owner_name' => 'Yolena Bassim',
        'document_number' => 'ZZ9988776',
        'lost_region' => 'Centre',
        'lost_city' => 'Obala',
        'extra_info' => 'Je déclare pour ma mère.',
    ])['declaration'];

    $response = $this->actingAs($reviewer)->get('/administration/revue-declarations');

    $response->assertOk()
        ->assertSee('Miro Olanda')
        ->assertSee('Yolena Bassim')
        ->assertSee('Je déclare pour ma mère.');

    $body = $response->getContent();

    // Sentinelles : ce que la revue n'a pas besoin de voir n'est pas dans la
    // page — ni le numéro, ni la ville, ni l'identifiant du signalement.
    expect($body)->not->toContain('ZZ9988776')
        ->and($body)->not->toContain('Obala')
        ->and($body)->not->toContain($declaration->number_hmac ?? 'aucun-hmac');
});

it('journalise la consultation de la file sans nommer les personnes', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    declarerPourUnTiers($user);

    $this->actingAs($reviewer)->get('/administration/revue-declarations')->assertOk();

    $entry = DB::table('audit_logs')
        ->where('action', 'declaration.review_queue_viewed')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_user_id)->toBe($reviewer->id)
        ->and($entry->reason)->toBe('items=1')
        ->and($entry->reason)->not->toContain('Olanda');
});

it('rejette une décision sans motif suffisant, sans changer le statut', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $declaration = declarerPourUnTiers($user);

    $this->actingAs($reviewer)
        ->from('/administration/revue-declarations')
        ->post('/administration/revue-declarations/'.$declaration->id, [
            'decision' => 'approve',
            'reason' => 'ok',
        ])
        ->assertSessionHasErrors('reason');

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::PendingReview);
});

it('enregistre une décision soumise depuis l\'interface', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);
    $declaration = declarerPourUnTiers($user);

    $this->actingAs($reviewer)
        ->from('/administration/revue-declarations')
        ->post('/administration/revue-declarations/'.$declaration->id, [
            'decision' => 'approve',
            'reason' => 'Même patronyme et explication cohérente.',
        ])
        ->assertRedirect('/administration/revue-declarations');

    expect($declaration->fresh()->status)->toBe(LostDeclarationStatus::Active);
});

it('dit à l\'auteur que sa déclaration est en vérification, sans rien montrer', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    FoundReport::factory()->create([
        'document_type_id' => reviewType()->id,
        'owner_name' => 'Yolena Bassim',
        'found_city' => 'Obala',
        'status' => 'active',
    ]);

    declarerPourUnTiers($user);
    traiterLaFileDeRecherche();

    $response = $this->actingAs($user)->get('/recherche/resultats');

    $response->assertOk()->assertSee('En cours de vérification');

    $body = $response->getContent();

    // Le silence serait pire pour l'utilisateur, mais dire ne doit rien
    // divulguer : aucune trace du signalement correspondant (D-036).
    expect($body)->not->toContain('Yolena')
        ->and($body)->not->toContain('Obala')
        ->and($body)->not->toContain('Y. B.');
});
