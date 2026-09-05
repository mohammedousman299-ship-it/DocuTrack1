<x-layout title="Créer un compte — DocuTrack">
    <div class="mx-auto max-w-md">
        <div class="mb-6 flex items-center gap-3">
            <x-logo class="h-9 w-9" />
            <h1 class="text-xl font-bold">Créer un compte</h1>
        </div>

        <x-alert tone="trust" class="mb-6">
            Un compte suffit pour les deux usages : signaler un document trouvé
            <strong>et</strong> déclarer une perte. Vous n'avez pas à choisir.
        </x-alert>

        @if ($errors->any())
            <x-alert tone="danger" title="Le formulaire contient des erreurs" class="mb-6">
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4">
                @csrf

                <x-field label="Nom complet" name="full_name" required
                         value="{{ old('full_name') }}" autocomplete="name"
                         hint="Écrivez-le comme il figure sur vos documents officiels."
                         :error="$errors->first('full_name')" />

                <x-field label="Adresse e-mail" name="email" type="email" required
                         value="{{ old('email') }}" autocomplete="email"
                         :error="$errors->first('email')" />

                <x-field label="Numéro de téléphone" name="phone" type="tel" required
                         value="{{ old('phone') }}" autocomplete="tel"
                         hint="Nous vous enverrons un code par SMS pour vérifier ce numéro. Un numéro ne peut être utilisé que par un seul compte."
                         :error="$errors->first('phone_e164') ?: $errors->first('phone')" />

                <x-field label="Mot de passe" name="password" type="password" required
                         autocomplete="new-password"
                         hint="Au moins 12 caractères, avec des lettres et des chiffres."
                         :error="$errors->first('password')" />

                <x-field label="Confirmer le mot de passe" name="password_confirmation"
                         type="password" required autocomplete="new-password" />

                <x-button type="submit" variant="primary" class="w-full">Créer mon compte</x-button>
            </form>
        </x-card>

        <p class="mt-4 text-center text-sm text-slate-600">
            Vous avez déjà un compte ?
            <a href="{{ route('login') }}" class="font-medium text-trust-700 underline">Se connecter</a>
        </p>
    </div>
</x-layout>
