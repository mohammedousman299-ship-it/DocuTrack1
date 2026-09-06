<?php

declare(strict_types=1);

use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function typeParcours(string $sensitivity = 'standard'): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'parcours_'.$sensitivity],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => $sensitivity, 'retention_days' => 180]
    );
}

it('refuse le parcours à un compte sans téléphone vérifié', function (): void {
    // Sans numéro vérifié, les plafonds par compte seraient décoratifs et
    // l'index deviendrait un canal d'extraction (D-013, M-06).
    $this->actingAs(User::factory()->unverifiedPhone()->create())
        ->get('/signalement')
        ->assertRedirect(route('phone.verify.show'));
});

it('refuse le parcours à un visiteur anonyme', function (): void {
    $this->get('/signalement')->assertRedirect(route('login'));
});

it('affiche le parcours à un compte vérifié', function (): void {
    $this->actingAs(User::factory()->create())->get('/signalement')->assertOk();
});

it('enregistre un signalement en trois étapes', function (): void {
    $type = typeParcours();
    $finder = User::factory()->create();

    Livewire::actingAs($finder)
        ->test('found-report-form')
        ->set('documentTypeId', $type->id)
        ->set('foundRegion', 'Centre')
        ->set('foundCity', 'Ville')
        ->call('next')
        ->assertSet('step', 2)
        ->set('ownerName', 'Miro Olanda')
        ->set('documentNumber', 'AB123456')
        ->call('next')
        ->assertSet('step', 3)
        ->call('submit')
        ->assertSet('submitted', true);

    expect(FoundReport::count())->toBe(1)
        ->and(FoundReport::first()->owner_name_normalized)->toBe('miro olanda');
});

it('refuse d’avancer sans les champs obligatoires de l’étape', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test('found-report-form')
        ->call('next')
        ->assertHasErrors(['documentTypeId', 'foundRegion', 'foundCity'])
        ->assertSet('step', 1);
});

it('conserve un brouillon entre deux visites', function (): void {
    // Perdre une saisie sur un réseau instable est l'une des façons les plus
    // sûres de perdre un signalement (§9.4).
    $finder = User::factory()->create();
    $type = typeParcours();

    Livewire::actingAs($finder)
        ->test('found-report-form')
        ->set('documentTypeId', $type->id)
        ->set('foundCity', 'Ville reprise');

    Livewire::actingAs($finder)
        ->test('found-report-form')
        ->assertSet('foundCity', 'Ville reprise');
});

it('efface le brouillon après envoi', function (): void {
    // Le brouillon contient le nom et le numéro du document d'un tiers : il
    // n'a pas à subsister après l'envoi.
    $type = typeParcours();

    Livewire::actingAs(User::factory()->create())
        ->test('found-report-form')
        ->set('documentTypeId', $type->id)
        ->set('foundRegion', 'Centre')
        ->set('foundCity', 'Ville')
        ->set('ownerName', 'Miro Olanda')
        ->call('submit');

    expect(session('found_report_draft'))->toBeNull();
});

it('accepte un signalement sans photo sur un type ordinaire', function (): void {
    $type = typeParcours();

    Livewire::actingAs(User::factory()->create())
        ->test('found-report-form')
        ->set('documentTypeId', $type->id)
        ->set('foundRegion', 'Centre')
        ->set('foundCity', 'Ville')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(FoundReport::first()->status->value)->toBe('active');
});

it('accepte mais met en revue un type sensible sans photo', function (): void {
    // On ne bloque pas le Trouveur : chaque signalement perdu est un document
    // non restitué. Mais la restitution passera par un administrateur.
    Storage::fake('s3');
    $type = typeParcours('high');

    Livewire::actingAs(User::factory()->create())
        ->test('found-report-form')
        ->set('documentTypeId', $type->id)
        ->set('foundRegion', 'Centre')
        ->set('foundCity', 'Ville')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(FoundReport::first()->status->value)->toBe('pending_review');
});

it('n’annonce jamais au Trouveur le sort de son signalement', function (): void {
    // Un retour de rapprochement transformerait les faux signalements en canal
    // d'extraction (M-06).
    $type = typeParcours();

    $rendu = Livewire::actingAs(User::factory()->create())
        ->test('found-report-form')
        ->set('documentTypeId', $type->id)
        ->set('foundRegion', 'Centre')
        ->set('foundCity', 'Ville')
        ->call('submit')
        ->html();

    expect($rendu)->toContain('Vous ne serez pas informé de la suite')
        ->and(mb_strtolower($rendu))->not->toContain('correspondance trouvée');
});

it('délivre une autorisation d’envoi à un compte vérifié', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('reports.upload-ticket'));

    $response->assertOk()->assertJsonStructure(['object_key', 'upload_url', 'expires_in']);
    expect($response->json('object_key'))->toMatch('#^documents/[0-9a-f-]{36}\.jpg$#');
});

it('refuse une autorisation d’envoi sans téléphone vérifié', function (): void {
    $this->actingAs(User::factory()->unverifiedPhone()->create())
        ->postJson(route('reports.upload-ticket'))
        ->assertRedirect(route('phone.verify.show'));
});
