<x-layout title="Vérifier votre adresse — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-xl font-bold">Confirmez votre adresse e-mail</h1>
        <p class="mb-6 text-sm text-slate-600">
            Un lien vous a été envoyé. Cette adresse nous sert à vous prévenir et
            à vous permettre de reprendre la main sur votre compte.
        </p>

        @if (session('status') === 'verification-link-sent')
            <x-alert tone="recover" class="mb-6">
                Un nouveau lien vient de vous être envoyé.
            </x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">
                    Renvoyer le lien
                </x-button>
            </form>
        </x-card>
    </div>
</x-layout>
