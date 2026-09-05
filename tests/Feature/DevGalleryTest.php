<?php

declare(strict_types=1);

it('affiche la galerie de composants hors production', function (): void {
    $response = $this->get('/dev/ui');

    $response->assertOk()
        ->assertSee('Galerie de composants')
        ->assertSee('Niveau 1')
        ->assertSee('Niveau 3');
});

it('signale explicitement que le logo est provisoire', function (): void {
    // Le logo est un jalon technique, pas un travail de graphiste : il faut que
    // ce soit dit, pas supposé compris.
    $this->get('/dev/ui')->assertSee('jalon technique', escape: false);
});

it('masque la galerie en production', function (): void {
    // La restriction est dans le code, pas dans une convention de déploiement.
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/dev/ui')->assertNotFound();
});

it('rend le niveau de divulgation visible de l’utilisateur', function (): void {
    // L'utilisateur doit comprendre qu'il ne voit pas tout, et pourquoi.
    $this->get('/dev/ui')
        ->assertSee('Informations volontairement limitées')
        ->assertSee('Le masquage est fait sur le serveur', escape: false);
});
