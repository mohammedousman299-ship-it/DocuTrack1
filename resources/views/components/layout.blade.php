{{-- Gabarit de base. Interface en français, i18n prête pour l'anglais. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'DocuTrack' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-slate-900 antialiased">
    {{-- Lien d'évitement : navigation clavier complète (§9.3) --}}
    <a href="#contenu"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50
              focus:rounded focus:bg-trust-700 focus:px-4 focus:py-2 focus:text-white">
        Aller au contenu principal
    </a>

    <main id="contenu" class="mx-auto max-w-5xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
