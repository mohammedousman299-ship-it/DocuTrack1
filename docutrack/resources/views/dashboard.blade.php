@extends('layouts.app')
@section('content')
<div class="text-center mb-4">
    <h2>{{ __('txtWelcome') }}, {{ auth()->user()->name }}!</h2>
    <p class="text-muted">{{ __('txtRoleSub') }}</p>
</div>
<div class="row justify-content-center g-4">
    <div class="col-md-5"><div class="card h-100 text-center shadow-sm p-4">
        <i class="fa-solid fa-hand-holding-hand fa-4x mb-3" style="color: var(--secondary-teal);"></i>
        <h4>{{ __('txtFoundDocQ') }}</h4><p class="text-muted">{{ __('txtFoundDocSub') }}</p>
        <a class="btn btn-teal mt-auto" href="{{ route('finder') }}">{{ __('btnContFinder') }}</a>
    </div></div>
    <div class="col-md-5"><div class="card h-100 text-center shadow-sm p-4">
        <i class="fa-solid fa-id-card-clip fa-4x mb-3" style="color: var(--primary-navy);"></i>
        <h4>{{ __('txtLostDocQ') }}</h4><p class="text-muted">{{ __('txtLostDocSub') }}</p>
        <a class="btn btn-navy mt-auto" href="{{ route('owner') }}">{{ __('btnContOwner') }}</a>
    </div></div>
</div>
@endsection
