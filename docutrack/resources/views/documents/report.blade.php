@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-8">
    <x-back :href="route('finder')" />
    <div class="card shadow-sm">
        <div class="card-header btn-navy text-white fw-bold">{{ __('btnReportFound') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('found.store') }}">@csrf
                <div class="extension-card">
                    <h6 class="text-navy fw-bold mb-3"><i class="fa-solid fa-id-card text-teal me-2"></i>{{ __('cardDocDetails') }}</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3"><x-category-select :categories="$categories" /></div>
                        <div class="col-md-6 mb-3"><label class="form-label">{{ __('lblOwnerName') }}</label>
                            <input type="text" class="form-control" name="owner_name" value="{{ old('owner_name') }}" placeholder="e.g. Samuel Eto'o" required></div>
                    </div>
                    <label class="form-label">{{ __('lblDocNum') }} <span class="text-muted fw-normal">{{ __('lblOptional') }}</span></label>
                    <input type="text" class="form-control" name="doc_number" value="{{ old('doc_number') }}">
                </div>
                <div class="extension-card">
                    <h6 class="text-navy fw-bold mb-3"><i class="fa-solid fa-location-dot text-teal me-2"></i>{{ __('cardFoundLoc') }}</h6>
                    <label class="form-label">{{ __('lblLocCity') }}</label>
                    <input type="text" class="form-control" name="location" value="{{ old('location') }}" placeholder="e.g. Yaoundé Central Station" required>
                </div>
                <div class="extension-card">
                    <h6 class="text-navy fw-bold mb-3"><i class="fa-solid fa-building-columns text-teal me-2"></i>{{ __('cardCollectPt') }}</h6>
                    <label class="form-label">{{ __('lblDropOff') }}</label>
                    <input type="text" class="form-control" name="deposit_point" value="{{ old('deposit_point') }}" placeholder="e.g. Police Station 1st District, Yaoundé" required>
                </div>
                <div class="extension-card">
                    <h6 class="text-navy fw-bold mb-3"><i class="fa-solid fa-note-sticky text-teal me-2"></i>{{ __('cardCtxDetails') }}</h6>
                    <label class="form-label">{{ __('lblAddNotes') }}</label>
                    <textarea class="form-control" name="context" rows="2">{{ old('context') }}</textarea>
                </div>
                <div class="extension-card text-center">
                    <h6 class="text-navy fw-bold mb-3 text-start"><i class="fa-solid fa-camera text-teal me-2"></i>{{ __('cardCapImg') }}</h6>
                    <p class="small text-muted mb-2">{{ __('txtCamNote') }}</p>
                    <video id="cameraVideo" autoplay playsinline class="d-none mb-2"></video>
                    <canvas id="capturedCanvas" class="d-none mb-2"></canvas>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="startCamera()"><i class="fa-solid fa-camera me-1"></i> {{ __('btnStartCam') }}</button>
                        <button type="button" class="btn btn-teal btn-sm d-none" id="snapBtn" onclick="captureSnapshot()"><i class="fa-solid fa-circle-dot me-1"></i> {{ __('btnCapImg') }}</button>
                    </div>
                    <input type="hidden" name="image" id="cameraImageData">
                </div>
                <button type="submit" class="btn btn-navy w-100 py-2 fs-6 mt-2">{{ __('btnSubmit') }}</button>
            </form>
        </div>
    </div>
</div></div>
@endsection
@push('scripts')
<script>
    let videoStream;
    async function startCamera() {
        try {
            const video = document.getElementById('cameraVideo');
            videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = videoStream;
            video.classList.remove('d-none');
            document.getElementById('capturedCanvas').classList.add('d-none');
            document.getElementById('snapBtn').classList.remove('d-none');
        } catch (err) {
            alert('Camera access denied or unavailable: ' + err.message);
        }
    }
    function captureSnapshot() {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('capturedCanvas');
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.classList.remove('d-none');
        video.classList.add('d-none');
        if (videoStream) videoStream.getTracks().forEach(track => track.stop());
        document.getElementById('cameraImageData').value = canvas.toDataURL('image/jpeg', 0.8);
    }
</script>
@endpush
