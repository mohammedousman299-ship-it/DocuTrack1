@props(['title' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    @if ($title)
        <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @endif
    <div @class(['mt-3' => $title]) >{{ $slot }}</div>
</div>
