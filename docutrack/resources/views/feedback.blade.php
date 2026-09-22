@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-6">
    <x-back />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('fbSubmitHeader') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('feedback') }}">@csrf
                <div class="mb-3"><label class="form-label">{{ __('lblSubject') }}</label>
                    <input type="text" class="form-control" name="subject" value="{{ old('subject') }}" required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblRating') }}</label>
                    <select class="form-select" name="rating">
                        @foreach ([5 => 'Excellent', 4 => 'Very Good', 3 => 'Average', 2 => 'Poor', 1 => 'Very Poor'] as $v => $l)
                            <option value="{{ $v }}">{{ $v }} - {{ $l }}</option>
                        @endforeach
                    </select></div>
                <div class="mb-3"><label class="form-label">{{ __('lblMessage') }}</label>
                    <textarea class="form-control" name="message" rows="4" required>{{ old('message') }}</textarea></div>
                <button type="submit" class="btn btn-teal w-100">{{ __('btnSubmitFeedback') }}</button>
            </form>
        </div>
    </div>
</div></div>
@endsection
