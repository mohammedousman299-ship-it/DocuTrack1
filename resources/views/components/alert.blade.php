{{--
    Alerte. Chaque erreur explique ce qui s'est passé ET propose l'action
    suivante : « aucune impasse » (§9.1).
--}}
@props(['tone' => 'trust', 'title' => null])

@php
    $tones = [
        'trust' => 'border-trust-200 bg-trust-50 text-trust-900',
        'recover' => 'border-recover-200 bg-recover-50 text-recover-900',
        'caution' => 'border-caution-100 bg-caution-100 text-caution-700',
        'danger' => 'border-danger-100 bg-danger-100 text-danger-700',
    ];
    $role = in_array($tone, ['danger', 'caution'], true) ? 'alert' : 'status';
@endphp

<div role="{{ $role }}"
     {{ $attributes->merge(['class' => 'rounded-lg border p-4 '.($tones[$tone] ?? $tones['trust'])]) }}>
    @if ($title)
        <p class="text-sm font-semibold">{{ $title }}</p>
    @endif
    <div class="text-sm">{{ $slot }}</div>
</div>
