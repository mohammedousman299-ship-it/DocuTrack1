@extends('layouts.app')
@section('content')
<div class="hero-section text-center mb-4">
    <h1 class="display-4 fw-bold">{{ __('heroTitle') }}</h1>
    <p class="lead">{{ __('heroDesc') }}</p>
    <div class="mt-4">
        @guest
            <a class="btn btn-teal btn-lg me-2" href="{{ route('register') }}"><i class="fa-solid fa-user-plus me-1"></i> {{ __('btnRegister') }}</a>
            <a class="btn btn-outline-light btn-lg" href="{{ route('login') }}"><i class="fa-solid fa-right-to-bracket me-1"></i> {{ __('btnLogin') }}</a>
        @else
            <a class="btn btn-teal btn-lg" href="{{ route('dashboard') }}">{{ __('navDashboard') }}</a>
        @endguest
    </div>
</div>
<div class="row text-center mt-5">
    @foreach ([['fa-camera-retro', 'var(--secondary-teal)', 1], ['fa-magnifying-glass', 'var(--primary-navy)', 2], ['fa-hand-holding-hand', 'var(--accent-gold)', 3]] as [$icon, $color, $n])
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-0 shadow-sm p-3">
                <i class="fa-solid {{ $icon }} fa-3x mb-3" style="color: {{ $color }};"></i>
                <h5>{{ __("step{$n}Title") }}</h5>
                <p class="text-muted">{{ __("step{$n}Desc") }}</p>
            </div>
        </div>
    @endforeach
</div>
@endsection
