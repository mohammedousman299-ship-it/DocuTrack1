@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-8">
    <x-back :href="route('search')" />
    <div class="card border-success shadow-sm">
        <div class="card-header bg-success text-white fw-bold"><i class="fa-solid fa-circle-check me-2"></i>{{ __('txtMatchTitle') }}</div>
        <div class="card-body">
            <div class="alert alert-info">{{ __('txtMatchInfo') }}</div>
            @foreach ($matches as $doc)
                <div class="extension-card d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <p class="mb-1"><strong>{{ __('lblDocType') }}:</strong> {{ $doc->category->name }}</p>
                        <p class="mb-1"><strong>{{ __('tblOwner') }}:</strong> {{ $doc->owner_name }}</p>
                        <p class="mb-0"><strong>{{ __('lblFoundLoc') }}:</strong> {{ $doc->location }}</p>
                    </div>
                    <a class="btn btn-teal" href="{{ route('documents.pay', $doc) }}"><i class="fa-solid fa-credit-card me-2"></i>{{ __('btnPayFee') }}</a>
                </div>
            @endforeach
        </div>
    </div>
</div></div>
@endsection
