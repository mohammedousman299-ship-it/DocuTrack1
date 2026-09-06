<?php

declare(strict_types=1);

it('pose une CSP stricte sur les réponses web', function (): void {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("form-action 'self'");
});

it('n’autorise jamais unsafe-eval ni unsafe-inline sur les scripts', function (): void {
    // Mesuré au navigateur : Livewire 4 exige unsafe-eval SAUF si le mode
    // compatible CSP est actif. Ce test empêche qu'on l'assouplisse pour
    // faire passer un composant récalcitrant (D-025, D-026).
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    preg_match('/script-src ([^;]+)/', $csp, $matches);
    $scriptSrc = $matches[1] ?? '';

    expect($scriptSrc)->not->toContain('unsafe-eval')
        ->and($scriptSrc)->not->toContain('unsafe-inline')
        ->and($scriptSrc)->toContain('nonce-');
});

it('garde Livewire en mode compatible CSP', function (): void {
    // Sans ce réglage, l'interface entière cesse de répondre sous notre CSP.
    // Mesuré : 2 violations et un composant inerte quand il vaut false.
    expect(config('livewire.csp_safe'))->toBeTrue();
});

it('régénère le nonce à chaque réponse', function (): void {
    $premier = (string) $this->get('/')->headers->get('Content-Security-Policy');
    $second = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($premier)->not->toBe($second);
});

it('pose les autres en-têtes de sécurité', function (): void {
    $response = $this->get('/');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and((string) $response->headers->get('Permissions-Policy'))->toContain('geolocation=()');
});

it('n’émet HSTS que sur une connexion sécurisée', function (): void {
    // L'émettre en clair n'apporte rien et pourrait rendre un environnement
    // local inaccessible.
    expect($this->get('/')->headers->get('Strict-Transport-Security'))->toBeNull()
        ->and($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=31536000');
});

it('n’expose pas la pile technique', function (): void {
    expect($this->get('/')->headers->get('X-Powered-By'))->toBeNull();
});
