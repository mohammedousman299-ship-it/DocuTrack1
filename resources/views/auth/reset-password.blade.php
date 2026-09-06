<x-layout title="Nouveau mot de passe — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-6 text-xl font-bold">Choisir un nouveau mot de passe</h1>

        <x-card>
            <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <x-field label="Adresse e-mail" name="email" type="email" required
                         value="{{ old('email', $request->email) }}" autocomplete="email"
                         :error="$errors->first('email')" />
                <x-field label="Nouveau mot de passe" name="password" type="password" required
                         autocomplete="new-password"
                         hint="Au moins 12 caractères, avec des lettres et des chiffres."
                         :error="$errors->first('password')" />
                <x-field label="Confirmer le mot de passe" name="password_confirmation"
                         type="password" required autocomplete="new-password" />

                <x-button type="submit" variant="primary" class="w-full">
                    Enregistrer le nouveau mot de passe
                </x-button>
            </form>
        </x-card>
    </div>
</x-layout>
