<x-layout title="Vérifier votre numéro — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-xl font-bold">Vérifier votre numéro</h1>
        <p class="mb-6 text-sm text-slate-600">
            Cette étape protège tout le monde : elle empêche la création de comptes
            en masse, qui servirait à collecter des informations sur les documents
            déclarés.
        </p>

        @if (session('status'))
            <x-alert tone="recover" class="mb-6">{{ session('status') }}</x-alert>
        @endif

        @error('code')
            <x-alert tone="danger" title="Vérification impossible" class="mb-6">{{ $message }}</x-alert>
        @enderror

        <x-card>
            <form method="POST" action="{{ route('phone.verify.send') }}" class="mb-5">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">
                    Recevoir un code par SMS
                </x-button>
            </form>

            <form method="POST" action="{{ route('phone.verify') }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Code reçu par SMS" name="code" required
                         inputmode="numeric" autocomplete="one-time-code"
                         maxlength="6" placeholder="123456"
                         hint="6 chiffres, valables 10 minutes."
                         :error="$errors->first('code')" />

                <x-button type="submit" variant="primary" class="w-full">Vérifier mon numéro</x-button>
            </form>
        </x-card>
    </div>
</x-layout>
