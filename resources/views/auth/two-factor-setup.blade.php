<x-layout title="Double authentification — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-xl font-bold">Double authentification</h1>
        <p class="mb-6 text-sm text-slate-600">
            Elle est <strong>obligatoire</strong> pour les comptes d'administration.
            Ce compte accède à des documents d'identité : un mot de passe seul ne suffit pas.
        </p>

        @if (session('status'))
            <x-alert tone="caution" class="mb-6">{{ session('status') }}</x-alert>
        @endif

        <x-card title="Application d'authentification">
            <p class="text-sm text-slate-600">
                DocuTrack utilise une application d'authentification, et non le SMS.
                Un SMS peut être détourné par le transfert frauduleux d'une carte SIM,
                ce qui rendrait le second facteur inutile précisément sur le compte
                le plus sensible.
            </p>
            <p class="mt-3 text-sm text-slate-600">Écran à compléter au cours du jalon 2.</p>
        </x-card>
    </div>
</x-layout>
