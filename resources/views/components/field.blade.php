{{--
    Champ de formulaire.

    Le libellé est associé au champ, et le message d'erreur lui est relié par
    aria-describedby (§9.3). L'aide est un élément à part entière : sur ce
    projet, elle porte des informations que l'utilisateur ne devinerait pas —
    par exemple qu'un numéro douteux vaut mieux vide que faux.
--}}
@props(['label', 'name', 'hint' => null, 'error' => null, 'required' => false])

@php
    $describedBy = collect([
        $hint ? $name.'-hint' : null,
        $error ? $name.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-sm font-medium text-slate-900">
        {{ $label }}
        @if ($required)
            <span class="text-danger-700" aria-hidden="true">*</span>
            <span class="sr-only">(obligatoire)</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $name }}-hint" class="text-xs text-slate-600">{{ $hint }}</p>
    @endif

    <input id="{{ $name }}" name="{{ $name }}"
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @if ($error) aria-invalid="true" @endif
           {{ $attributes->merge([
               'class' => 'min-h-11 rounded-lg border px-3 py-2 text-sm '
                   .($error ? 'border-danger-700' : 'border-slate-300'),
           ]) }}>

    @if ($error)
        <p id="{{ $name }}-error" class="text-xs font-medium text-danger-700">{{ $error }}</p>
    @endif
</div>
