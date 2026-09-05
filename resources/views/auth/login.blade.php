<x-layout title="Se connecter — DocuTrack">
    <div class="mx-auto max-w-md">
        <div class="mb-6 flex items-center gap-3">
            <x-logo class="h-9 w-9" />
            <h1 class="text-xl font-bold">Se connecter</h1>
        </div>

        @if (session('status'))
            <x-alert tone="recover" class="mb-6">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            {{-- Message uniforme : ne jamais distinguer « compte inconnu » de
                 « mot de passe erroné » (THREAT_MODEL.md M-05). --}}
            <x-alert tone="danger" title="Connexion impossible" class="mb-6">
                {{ $errors->first() }}
            </x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                @csrf

                <x-field label="Adresse e-mail" name="email" type="email" required
                         value="{{ old('email') }}" autocomplete="email" />

                <x-field label="Mot de passe" name="password" type="password" required
                         autocomplete="current-password" />

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" class="rounded border-slate-300">
                    Rester connecté
                </label>

                <x-button type="submit" variant="primary" class="w-full">Se connecter</x-button>
            </form>
        </x-card>

        <div class="mt-4 flex flex-col items-center gap-2 text-sm text-slate-600">
            <a href="{{ route('password.request') }}" class="text-trust-700 underline">Mot de passe oublié ?</a>
            <p>Pas encore de compte ?
                <a href="{{ route('register') }}" class="font-medium text-trust-700 underline">Créer un compte</a>
            </p>
        </div>
    </div>
</x-layout>
