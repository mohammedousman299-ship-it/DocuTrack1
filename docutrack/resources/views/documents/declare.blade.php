@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-8">
    <x-back :href="route('owner')" />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('txtDeclLostTitle') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('declare.store') }}">@csrf
                <div class="row">
                    <div class="col-md-6 mb-3"><x-category-select :categories="$categories" /></div>
                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('lblDocNum') }} <span class="text-muted fw-normal">{{ __('lblOptional') }}</span></label>
                        <input type="text" class="form-control" name="doc_number" value="{{ old('doc_number') }}"></div>
                </div>
                <div class="mb-3"><label class="form-label">{{ __('lblFullNameOnDoc') }}</label>
                    <input type="text" class="form-control" name="full_name" value="{{ old('full_name') }}" required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblLastLoc') }}</label>
                    <input type="text" class="form-control" name="last_location" value="{{ old('last_location') }}" required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblAddDesc') }}</label>
                    <textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea></div>
                <button type="submit" class="btn btn-navy w-100 py-2 fs-6"><i class="fa-solid fa-bullhorn me-1"></i> {{ __('btnDeclare') }}</button>
            </form>
        </div>
    </div>
</div></div>
@endsection
