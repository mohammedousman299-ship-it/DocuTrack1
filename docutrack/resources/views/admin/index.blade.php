@extends('layouts.app')
@section('content')
@php($tabs = ['documents' => __('tabDocHub'), 'users' => __('tabUsers'), 'categories' => __('tabCategories'), 'feedback' => __('tabFeedback')])
<h3 class="mb-4">{{ __('adminPortalTitle') }}</h3>
<div class="row">
    <div class="col-md-3 mb-3">
        <div class="list-group shadow-sm">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.index', ['tab' => $key]) }}" class="list-group-item list-group-item-action @if ($tab === $key) active @endif">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="col-md-9"><div class="card shadow-sm p-3">
    @switch($tab)
    @case('users')
        <h5>{{ __('tabUsers') }}</h5>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>ID</th><th>{{ __('tblName') }}</th><th>{{ __('lblEmail') }}</th><th>{{ __('tblRole') }}</th><th>{{ __('tblActions') }}</th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr class="{{ $user->is_active ? '' : 'table-secondary' }}">
                    <td>{{ $user->id }}</td><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ ucfirst($user->role) }}</td>
                    <td>@unless ($user->is(auth()->user()))
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}">@csrf @method('PATCH')
                            <button class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $user->is_active ? __('btnDeactivate') : __('btnActivate') }}</button>
                        </form>
                    @endunless</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @break
    @case('categories')
        <h5>{{ __('tabCategories') }}</h5>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="d-flex gap-2 mb-3">@csrf
            <input type="text" name="name" class="form-control form-control-sm" required>
            <button class="btn btn-sm btn-teal text-nowrap">{{ __('btnAddCategory') }}</button>
        </form>
        <ul class="list-group">
            @foreach ($categories as $category)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ $category->name }} <span class="badge bg-secondary">{{ $category->found_documents_count }}</span></span>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">{{ __('btnDelete') }}</button></form>
                </li>
            @endforeach
        </ul>
        @break
    @case('feedback')
        <h5><i class="fa-solid fa-comments text-teal me-2"></i>{{ __('manageFeedbackTitle') }}</h5>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>{{ __('lblSubject') }}</th><th>{{ __('lblRating') }}</th><th>{{ __('tblStatus') }}</th><th>{{ __('tblActions') }}</th></tr></thead>
            <tbody>
            @foreach ($feedback as $fb)
                <tr>
                    <td>{{ $fb->subject }}<br><small class="text-muted">{{ $fb->user?->email }}</small></td>
                    <td>{{ $fb->rating }}/5</td>
                    <td><span class="badge {{ $fb->status === 'Resolved' ? 'bg-success' : 'bg-secondary' }}">{{ $fb->status }}</span></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#fb{{ $fb->id }}">{{ __('btnProcess') }}</button>
                        <form method="POST" action="{{ route('admin.feedback.destroy', $fb) }}" class="d-inline">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('btnRemove') }}</button></form>
                    </td>
                </tr>
                <tr class="collapse" id="fb{{ $fb->id }}"><td colspan="4">
                    <p class="bg-light p-2 rounded">{{ $fb->message }}</p>
                    <form method="POST" action="{{ route('admin.feedback.update', $fb) }}">@csrf @method('PATCH')
                        <label class="form-label fw-bold">{{ __('lblUpdateStatus') }}</label>
                        <select name="status" class="form-select form-select-sm mb-2">
                            @foreach (config('docutrack.feedback_statuses') as $s)<option @selected($fb->status === $s)>{{ $s }}</option>@endforeach
                        </select>
                        <label class="form-label fw-bold">{{ __('lblAdminNotes') }}</label>
                        <textarea name="admin_notes" class="form-control form-control-sm mb-2" rows="2">{{ $fb->admin_notes }}</textarea>
                        <button class="btn btn-teal btn-sm">{{ __('btnSaveUpdate') }}</button>
                    </form>
                </td></tr>
            @endforeach
            </tbody>
        </table></div>
        @break
    @default
        <h5><i class="fa-solid fa-sliders me-2 text-teal"></i>{{ __('subMonFound') }}</h5>
        <form method="GET" class="d-flex gap-2 mb-3"><input type="hidden" name="tab" value="documents">
            <input type="text" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Doc # / {{ __('tblOwner') }}">
            <select class="form-select form-select-sm w-auto" name="category"><option value="">—</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-navy">{{ __('btnSearch') }}</button>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>{{ __('tblType') }}</th><th>{{ __('tblOwner') }}</th><th>{{ __('tblDocNum') }}</th><th>{{ __('tblDepLoc') }}</th><th>{{ __('tblStatus') }}</th><th>{{ __('tblActions') }}</th></tr></thead>
            <tbody>
            @foreach ($found as $doc)
                <tr><td>{{ $doc->category->name }}</td><td>{{ $doc->owner_name }}</td><td>{{ $doc->doc_number ?? '—' }}</td><td>{{ $doc->deposit_point }}</td><td>{{ $doc->status }}</td>
                    <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show', $doc) }}"><i class="fa-solid fa-eye"></i></a>
                        <form method="POST" action="{{ route('admin.found.destroy', $doc) }}" class="d-inline">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('btnDelete') }}</button></form></td></tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $found->links() }}

        <h5 class="mt-4"><i class="fa-solid fa-clipboard-check me-2 text-teal"></i>{{ __('subMonDecl') }}</h5>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>{{ __('tblType') }}</th><th>{{ __('tblClaimedOwner') }}</th><th>{{ __('tblDocNum') }}</th><th>{{ __('tblLastKnownLoc') }}</th><th>{{ __('tblStatus') }}</th><th>{{ __('tblActions') }}</th></tr></thead>
            <tbody>
            @foreach ($declarations as $d)
                <tr><td>{{ $d->category->name }}</td><td>{{ $d->full_name }}</td><td>{{ $d->doc_number ?? '—' }}</td><td>{{ $d->last_location }}</td><td>{{ $d->status }}</td>
                    <td><form method="POST" action="{{ route('admin.declarations.destroy', $d) }}">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">{{ __('btnDelete') }}</button></form></td></tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $declarations->links() }}
    @endswitch
    </div></div>
</div>
@endsection
