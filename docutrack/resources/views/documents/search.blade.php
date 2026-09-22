@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-8">
    <x-back :href="route('owner')" />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('searchCardHeader') }}</div>
        <div class="card-body">
            <form method="GET" action="{{ route('search') }}">
                <div class="mb-3"><x-category-select :categories="$categories" :selected="request('category_id')" /></div>
                <div class="mb-3"><label class="form-label">{{ __('lblOwnerName') }}</label>
                    <input type="text" class="form-control" name="owner_name" value="{{ request('owner_name') }}" placeholder="e.g. Samuel Etoo" required></div>
                <div class="mb-3"><label class="form-label">{{ __('lblDocNum') }} <span class="text-muted fw-normal">{{ __('lblOptional') }}</span></label>
                    <input type="text" class="form-control" name="doc_number" value="{{ request('doc_number') }}"></div>
                <button type="submit" class="btn btn-teal w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> {{ __('btnSearch') }}</button>
            </form>
        </div>
    </div>
</div></div>
@endsection
