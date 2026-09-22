@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">{{ __('ownerPortalTitle') }}</h3>
    <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">{{ __('btnSwitchRole') }}</a>
</div>
<div class="row g-3">
    <div class="col-md-6"><div class="card p-4 shadow-sm h-100">
        <h5>{{ __('txtSearchLostTitle') }}</h5><p class="text-muted">{{ __('txtSearchLostSub') }}</p>
        <a class="btn btn-navy mt-auto" href="{{ route('search') }}"><i class="fa-solid fa-magnifying-glass me-1"></i> {{ __('btnSearch') }}</a>
    </div></div>
    <div class="col-md-6"><div class="card p-4 shadow-sm h-100">
        <h5>{{ __('txtDeclLostTitle') }}</h5><p class="text-muted">{{ __('txtDeclLostSub') }}</p>
        <a class="btn btn-teal mt-auto" href="{{ route('declare.create') }}"><i class="fa-solid fa-bullhorn me-1"></i> {{ __('btnDeclare') }}</a>
    </div></div>
</div>
@endsection
