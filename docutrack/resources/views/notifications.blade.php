@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-8">
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold"><i class="fa-solid fa-bell me-2"></i>{{ __('notifHeader') }}</div>
        <div class="card-body">
            @forelse ($alerts as $alert)
                <div class="extension-card d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        @unless ($alert->read_at)<span class="badge bg-danger me-1">{{ __('lblNew') }}</span>@endunless
                        {{ __('txtNotifMatch') }}
                        <strong>{{ $alert->foundDocument->category->name }}</strong> — {{ $alert->foundDocument->owner_name }}
                        <br><small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                    </div>
                    <a class="btn btn-sm btn-teal" href="{{ route('documents.pay', $alert->foundDocument) }}">{{ __('btnViewMatch') }}</a>
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('txtNoNotif') }}</p>
            @endforelse
        </div>
    </div>
</div></div>
@endsection
