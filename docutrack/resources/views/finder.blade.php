@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">{{ __('finderPortalTitle') }}</h3>
    <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">{{ __('btnSwitchRole') }}</a>
</div>
<div class="card p-4 shadow-sm">
    <h5>{{ __('txtRepFoundHeader') }}</h5>
    <p class="text-muted">{{ __('txtRepFoundSub') }}</p>
    <div><a class="btn btn-teal" href="{{ route('found.create') }}"><i class="fa-solid fa-plus me-1"></i> {{ __('btnReportFound') }}</a></div>
</div>
@endsection
