@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-5">
    <x-back :href="route('home')" />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('authTitle') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('login') }}">@csrf
                <div class="mb-3"><label class="form-label">{{ __('lblEmail') }}</label>
                    <input type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus></div>
                <div class="mb-3"><label class="form-label">{{ __('lblPassword') }}</label>
                    <input type="password" class="form-control" name="password" required></div>
                <button type="submit" class="btn btn-navy w-100">{{ __('btnAuthenticate') }}</button>
            </form>
            @if (app()->environment('local'))
                <hr><small class="text-muted">{{ __('demoAccounts') }}<br>
                - <b>john@example.com</b> / password<br>- <b>admin@docutrack.cm</b> / password</small>
            @endif
        </div>
    </div>
</div></div>
@endsection
