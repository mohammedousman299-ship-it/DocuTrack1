<x-layout title="Rechercher un document — DocuTrack">
    <div class="mx-auto max-w-lg">
        <div class="mb-6 flex items-center gap-3">
            <x-logo class="h-9 w-9" />
            <div>
                <h1 class="text-xl font-bold">Rechercher mon document</h1>
                <p class="text-sm text-slate-600">Décrivez le document que vous avez perdu.</p>
            </div>
        </div>

        <livewire:lost-declaration-form />
    </div>
</x-layout>
