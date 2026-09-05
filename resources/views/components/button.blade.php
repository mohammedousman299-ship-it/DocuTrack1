@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 '
        .'text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        'primary' => 'bg-trust-700 text-white hover:bg-trust-800',
        'recover' => 'bg-recover-700 text-white hover:bg-recover-800',
        'secondary' => 'border border-trust-700 bg-white text-trust-700 hover:bg-trust-50',
        'ghost' => 'text-trust-700 hover:bg-trust-50',
        'danger' => 'bg-danger-700 text-white hover:brightness-90',
    ];
@endphp

<button type="{{ $type }}"
        {{ $attributes->merge(['class' => $base.' '.($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</button>
