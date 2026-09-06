<?php

declare(strict_types=1);

/**
 * Garanties du stockage objet (§3.3, THREAT_MODEL.md M-12).
 *
 * Ces tests portent sur la CONFIGURATION, pas sur le service : ils empêchent
 * qu'une modification ramène silencieusement le disque local ou une URL
 * publique, qui annuleraient tous les autres contrôles sur les images.
 */
it('n’utilise jamais le disque local pour les fichiers', function (): void {
    // Le système de fichiers du conteneur n'est pas persistant, et une image
    // de document n'a rien à y faire (§3.1, §3.3).
    expect(config('filesystems.default'))->not->toBe('local')
        ->and(config('filesystems.default'))->not->toBe('public');
});

it('n’expose aucune URL publique sur le disque des documents', function (): void {
    // Une URL publique ou devinable annulerait tous les autres contrôles.
    expect(config('filesystems.disks.s3.url'))->toBeEmpty();
});

it('lit ses identifiants sous un nom propre au projet', function (): void {
    // Les variables AWS_* sont couramment définies au niveau du système et
    // priment sur .env : la configuration du projet se retrouvait remplacée
    // par des identifiants étrangers, avec une erreur incompréhensible.
    $contenu = file_get_contents(config_path('filesystems.php'));

    expect($contenu)->toContain('DOCUTRACK_S3_KEY')
        ->and($contenu)->not->toContain("env('AWS_ACCESS_KEY_ID')");
});

it('déclare le disque des documents en privé, sans visibilité publique', function (): void {
    $disque = config('filesystems.disks.s3');

    expect($disque['visibility'] ?? 'private')->toBe('private');
});
