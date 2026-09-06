<x-layout title="Confirmer votre mot de passe — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-xl font-bold">Confirmez votre mot de passe</h1>
        <p class="mb-6 text-sm text-slate-600">
            Cette action touche à la sécurité de votre compte. Merci de confirmer
            votre identité.
        </p>

        <x-card>
            <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Mot de passe" name="password" type="password" required
                         autocomplete="current-password" :error="$errors->first('password')" />
                <x-button type="submit" variant="primary" class="w-full">Confirmer</x-button>
            </form>
        </x-card>
    </div>
</x-layout>
