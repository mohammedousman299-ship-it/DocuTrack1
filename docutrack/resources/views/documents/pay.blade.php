@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-6">
    <x-back />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('payFeeHeader') }}</div>
        <div class="card-body">
            <div class="mb-3 p-3 bg-light rounded d-flex justify-content-between align-items-center">
                <span>{{ __('txtFeeLabel') }}</span>
                <span class="h4 mb-0 fw-bold text-navy">{{ number_format(config('docutrack.fee')) }} XAF</span>
            </div>
            <form method="POST" action="{{ route('documents.pay', $document) }}">@csrf
                <div class="mb-3"><label class="form-label">{{ __('lblPayMethod') }}</label>
                    <select class="form-select" name="method" required>
                        @foreach (config('docutrack.payment_methods') as $method)<option>{{ $method }}</option>@endforeach
                    </select></div>
                <div class="mb-3"><label class="form-label">{{ __('lblAccNum') }}</label>
                    <input type="text" class="form-control" name="account_number" placeholder="6XXXXXXXX" required></div>
                <button type="submit" class="btn btn-teal w-100"><i class="fa-solid fa-lock me-1"></i> {{ __('btnConfirmPay') }}</button>
            </form>
        </div>
    </div>
</div></div>
@endsection
