<x-layout title="Administration — DocuTrack">
    <h1 class="mb-6 text-xl font-bold">Administration</h1>
    @can('admin.sensitive')
        <x-card class="mb-4">
            <h2 class="mb-2 text-base font-semibold">Déclarations à vérifier</h2>
            <p class="mb-3 text-sm text-slate-600">
                Déclarations dont le nom diffère de celui du compte. Tant qu'elles
                ne sont pas tranchées, rien n'est rapproché ni notifié.
            </p>
            <a href="{{ route('admin.review.index') }}"
               class="dt-target inline-flex items-center justify-center rounded-lg bg-trust-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-trust-800">
                Ouvrir la file
            </a>
        </x-card>
    @endcan

    <x-card>
        <p class="text-sm text-slate-600">
            Interface complète au jalon 7. Les accès aux données sensibles y seront
            journalisés et exigeront la saisie d'un motif.
        </p>
    </x-card>
</x-layout>
