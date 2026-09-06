<x-layout title="Signaler un document trouvé — DocuTrack">
    <div class="mx-auto max-w-lg">
        <div class="mb-6 flex items-center gap-3">
            <x-logo class="h-9 w-9" />
            <div>
                <h1 class="text-xl font-bold">Signaler un document trouvé</h1>
                <p class="text-sm text-slate-600">Quelques champs suffisent.</p>
            </div>
        </div>

        <x-alert tone="recover" class="mb-6">
            Votre identité ne sera <strong>jamais</strong> communiquée au propriétaire
            du document.
        </x-alert>

        <livewire:found-report-form />
    </div>
</x-layout>
