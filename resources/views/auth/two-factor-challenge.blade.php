<x-layout title="Double authentification — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-6 text-xl font-bold">Code de vérification</h1>

        @if ($errors->any())
            <x-alert tone="danger" title="Code refusé" class="mb-6">{{ $errors->first() }}</x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Code affiché par votre application" name="code"
                         inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                         placeholder="123456" />
                <x-button type="submit" variant="primary" class="w-full">Valider</x-button>
            </form>
        </x-card>

        <x-card title="Vous n'avez plus votre application ?" class="mt-6">
            <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Code de récupération" name="recovery_code"
                         autocomplete="one-time-code" />
                <x-button type="submit" variant="secondary" class="w-full">
                    Utiliser un code de récupération
                </x-button>
            </form>
        </x-card>
    </div>
</x-layout>
