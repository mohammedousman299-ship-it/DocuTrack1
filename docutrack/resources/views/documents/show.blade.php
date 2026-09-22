@extends('layouts.app')
@section('content')
@php($payment = auth()->user()->payments()->where('found_document_id', $document->id)->first())
<div class="row justify-content-center"><div class="col-md-8">
    <div class="card shadow-sm">
        <div class="card-header bg-teal text-white fw-bold"><i class="fa-solid fa-unlock me-2"></i>{{ __('consultCardHeader') }}</div>
        <div class="card-body">
            <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>{{ __('txtPayVerified') }}</div>
            <div class="p-3 bg-light rounded mb-3">
                <p><strong>{{ __('lblDocType') }}:</strong> {{ $document->category->name }}</p>
                <p><strong>{{ __('tblOwner') }}:</strong> {{ $document->owner_name }}</p>
                @if ($document->doc_number)<p><strong>{{ __('lblDocNum') }}:</strong> {{ $document->doc_number }}</p>@endif
                <p><strong>{{ __('lblFoundLoc') }}:</strong> {{ $document->location }}</p>
                <p><strong>{{ __('lblDropOff') }}:</strong> {{ $document->deposit_point }}</p>
                @if ($document->context)<p><strong>{{ __('lblAddNotes') }}:</strong> {{ $document->context }}</p>@endif
                <p><strong>{{ __('lblFinder') }}:</strong> {{ $document->user->name }} ({{ $document->user->phone }})</p>
                @if ($payment)<p class="mb-0"><strong>{{ __('lblPayRef') }}:</strong> {{ $payment->reference }}</p>@endif
                @if ($document->image_path)<img src="{{ asset('storage/'.$document->image_path) }}" class="img-fluid rounded mt-3" style="max-width:400px" alt="">@endif
            </div>
            <a class="btn btn-navy" href="{{ route('owner') }}">{{ __('btnBackDash') }}</a>
        </div>
    </div>
</div></div>
@endsection
