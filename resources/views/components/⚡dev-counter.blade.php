<?php

use Livewire\Component;

/**
 * Composant de mesure — hors production uniquement.
 *
 * Il n'a qu'un rôle : vérifier expérimentalement qu'une CSP stricte, sans
 * 'unsafe-eval' ni 'unsafe-inline' sur les scripts, laisse fonctionner
 * Livewire 4 et Alpine. C'était le risque signalé en D-025, et il devait être
 * mesuré et non supposé.
 */
new class extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
};
?>

<div class="flex items-center gap-3" data-testid="dev-counter">
    <span class="text-sm">Compteur : <strong data-testid="count">{{ $count }}</strong></span>
    <x-button wire:click="increment" variant="secondary" data-testid="increment">
        Incrémenter
    </x-button>
</div>
