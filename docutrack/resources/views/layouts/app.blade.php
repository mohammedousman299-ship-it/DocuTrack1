<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocuTrack - Lost & Found Documents Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/docutrack.css') }}">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold p-0 d-flex align-items-center text-white" href="{{ route('home') }}">
                <img src="{{ asset('logo.jpg') }}" alt="DocuTrack" height="40" class="me-2 bg-white p-1 rounded">
            </a>
            <div class="d-flex align-items-center ms-auto me-3">
                <i class="fa-solid fa-globe text-white me-2"></i>
                <select class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto;" onchange="location.href='{{ url('lang') }}/' + this.value">
                    <option value="en" @selected(app()->getLocale() === 'en')>English</option>
                    <option value="fr" @selected(app()->getLocale() === 'fr')>Français</option>
                </select>
            </div>
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">{{ __('navHome') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('feedback') }}">{{ __('navFeedback') }}</a></li>
                    @auth
                        <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">{{ __('navDashboard') }}</a></li>
                        @php($unread = auth()->user()->alerts()->whereNull('read_at')->count())
                        <li class="nav-item"><a class="nav-link" href="{{ route('notifications') }}">{{ __('navNotifications') }}
                            @if ($unread)<span class="badge bg-danger badge-notification">{{ $unread }}</span>@endif</a></li>
                        @if (auth()->user()->isAdmin())
                            <li class="nav-item"><a class="nav-link" href="{{ route('admin.index') }}">{{ __('navAdmin') }}</a></li>
                        @endif
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="nav-link btn btn-link">{{ __('navLogout') }}</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">{{ __('navRegister') }}</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('navLogin') }}</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger shadow-sm"><ul class="mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif
        @yield('content')
    </main>

    <footer class="footer-custom text-center">
        <div class="container"><small>&copy; {{ date('Y') }} DocuTrack</small></div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
