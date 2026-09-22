@props(['href' => url()->previous()])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn btn-sm btn-outline-secondary mb-3']) }}><i class="fa-solid fa-arrow-left me-1"></i> {{ __('btnBack') }}</a>
