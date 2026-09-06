<?php

use Livewire\Component;

/** Sonde : quelles expressions Alpine survivent au mode compatible CSP ? */
new class extends Component {};
?>

<div>
    <div x-data="{ open: false }" data-testid="alpine-inline">
        <button type="button" x-on:click="open = !open" data-testid="alpine-toggle">Basculer</button>
        <span data-testid="alpine-state" x-text="open ? 'ouvert' : 'ferme'">ferme</span>
    </div>
</div>
