{{-- État vide. Ne se termine JAMAIS sans proposer une action (§9.1). --}}
@props(['title'])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center']) }}>
    <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
    <div class="mt-2 text-sm text-slate-600">{{ $slot }}</div>
    @isset($action)
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endisset
</div>
