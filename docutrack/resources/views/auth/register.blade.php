@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-6">
    <x-back :href="route('home')" />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('regTitle') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('register') }}">@csrf
                <div class="mb-3"><label class="form-label">{{ __('lblFullName') }}</label>
                    <input type="text" class="form-control" name="name" value="{{ old('name') }}" required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblEmail') }}</label>
                    <input type="email" class="form-control" name="email" value="{{ old('email') }}" required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblPhone') }}</label>
                    <input type="tel" class="form-control" name="phone" value="{{ old('phone') }}" placeholder="+237..." required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblPassword') }}</label>
                    <input type="password" class="form-control" name="password" required minlength="6"></div>
                <button type="submit" class="btn btn-teal w-100">{{ __('btnCreateAccount') }}</button>
            </form>
        </div>
    </div>
</div></div>
@endsection
