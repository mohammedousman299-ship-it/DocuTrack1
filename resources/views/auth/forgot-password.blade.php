<x-layout title="Mot de passe oublié — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-xl font-bold">Mot de passe oublié</h1>
        <p class="mb-6 text-sm text-slate-600">
            Indiquez votre adresse e-mail. Si un compte y est associé, vous recevrez
            un lien de réinitialisation.
        </p>

        @if (session('status'))
            <x-alert tone="recover" class="mb-6">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Adresse e-mail" name="email" type="email" required
                         value="{{ old('email') }}" autocomplete="email"
                         :error="$errors->first('email')" />
                <x-button type="submit" variant="primary" class="w-full">
                    Envoyer le lien
                </x-button>
            </form>
        </x-card>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('login') }}" class="text-trust-700 underline">Retour à la connexion</a>
        </p>
    </div>
</x-layout>
