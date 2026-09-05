{{--
    Indicateur du niveau de divulgation, visible par l'utilisateur.

    Rendre le niveau explicite est une exigence de conception : l'utilisateur
    doit comprendre qu'il ne voit pas tout, et pourquoi. Cela évite aussi de
    laisser croire qu'une correspondance est confirmée alors qu'elle ne l'est
    pas (docs/DISCLOSURE_LEVELS.md).
--}}
@props(['level' => 1])

@php
    $labels = [
        1 => ['Correspondance possible', 'trust', 'Informations volontairement limitées'],
        2 => ['Correspondance à confirmer', 'caution', 'Confirmez votre identité pour en voir plus'],
        3 => ['Informations de récupération', 'recover', 'Vous pouvez récupérer votre document'],
    ];
    [$label, $tone, $help] = $labels[$level] ?? $labels[1];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1']) }}>
    <x-badge :tone="$tone">Niveau {{ $level }} — {{ $label }}</x-badge>
    <p class="text-xs text-slate-600">{{ $help }}</p>
</div>
