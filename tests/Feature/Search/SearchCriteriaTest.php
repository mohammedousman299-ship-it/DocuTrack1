<?php

declare(strict_types=1);

use App\Search\SearchCriteria;

it('refuse une recherche par nom seul', function (): void {
    // Elle remonterait tous les documents d'un homonyme : vecteur d'attaque
    // direct (§4.2).
    expect(SearchCriteria::satisfiedBy(['owner_name' => 'Miro Olanda']))->toBeNull();
});

it('refuse une recherche sans type de document', function (): void {
    expect(SearchCriteria::satisfiedBy(['document_number' => 'AB123456']))->toBeNull()
        ->and(SearchCriteria::satisfiedBy(['owner_name' => 'Miro Olanda', 'lost_region' => 'Centre']))
        ->toBeNull();
});

it('refuse un type seul', function (): void {
    expect(SearchCriteria::satisfiedBy(['document_type_id' => 1]))->toBeNull();
});

it('refuse un type et un nom sans troisième critère', function (): void {
    expect(SearchCriteria::satisfiedBy([
        'document_type_id' => 1,
        'owner_name' => 'Miro Olanda',
    ]))->toBeNull();
});

it('accepte type et numéro (C1)', function (): void {
    expect(SearchCriteria::satisfiedBy([
        'document_type_id' => 1,
        'document_number' => 'AB123456',
    ]))->toBe(SearchCriteria::C1);
});

it('accepte type, nom et région (C2)', function (): void {
    expect(SearchCriteria::satisfiedBy([
        'document_type_id' => 1,
        'owner_name' => 'Miro Olanda',
        'lost_region' => 'Centre',
    ]))->toBe(SearchCriteria::C2);
});

it('accepte type, nom et date de perte (C2)', function (): void {
    expect(SearchCriteria::satisfiedBy([
        'document_type_id' => 1,
        'owner_name' => 'Miro Olanda',
        'lost_on' => '2026-08-01',
    ]))->toBe(SearchCriteria::C2);
});

it('ignore les valeurs vides', function (): void {
    expect(SearchCriteria::satisfiedBy([
        'document_type_id' => 1,
        'owner_name' => 'Miro Olanda',
        'lost_region' => '',
    ]))->toBeNull();
});
