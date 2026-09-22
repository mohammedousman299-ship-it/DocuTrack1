@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-6 text-center">
    <div class="card shadow-sm p-4">
        <i class="fa-solid fa-folder-open fa-4x text-muted mb-3"></i>
        <h4>{{ __('txtNoMatchTitle') }}</h4>
        <p class="text-muted">{{ __('txtNoMatchSub') }}</p>
        <div class="d-flex justify-content-center gap-2">
            <a class="btn btn-teal" href="{{ route('declare.create') }}">{{ __('btnDeclare') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('search') }}">{{ __('btnTrySearch') }}</a>
        </div>
    </div>
</div></div>
@endsection
