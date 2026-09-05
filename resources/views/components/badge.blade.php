{{--
    Badge de statut.

    L'information n'est JAMAIS portée par la seule couleur (§9.3, WCAG 2.1) :
    chaque badge porte un libellé explicite, et un point coloré ne vient qu'en
    complément.
--}}
@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-slate-100 text-slate-700',
        'trust' => 'bg-trust-100 text-trust-700',
        'recover' => 'bg-recover-100 text-recover-700',
        'caution' => 'bg-caution-100 text-caution-700',
        'danger' => 'bg-danger-100 text-danger-700',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium '
        .($tones[$tone] ?? $tones['neutral']),
]) }}>
    {{ $slot }}
</span>
