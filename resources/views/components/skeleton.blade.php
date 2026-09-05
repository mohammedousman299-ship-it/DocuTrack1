{{-- Squelette de chargement : jamais de page blanche (§9.4). --}}
@props(['lines' => 3])

<div {{ $attributes->merge(['class' => 'space-y-2']) }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div class="h-3 animate-pulse rounded bg-slate-200" style="width: {{ 100 - ($i * 12) }}%"></div>
    @endfor
    <span class="sr-only">Chargement en cours</span>
</div>
