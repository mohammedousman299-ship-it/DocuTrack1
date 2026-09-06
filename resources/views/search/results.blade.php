<x-layout title="Vos recherches — DocuTrack">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-xl font-bold">{{ __('search.results_title') }}</h1>

        @if ($searches === [])
            <x-empty-state title="Aucune recherche pour l'instant">
                <p>Décrivez le document que vous avez perdu pour lancer une recherche.</p>
                <x-slot:action>
                    <a href="{{ route('search.create') }}" class="dt-target">
                        <x-button variant="primary">Rechercher un document</x-button>
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($searches as $search)
                    <x-card>
                        @if ($search['pending'])
                            <div class="flex flex-col gap-2">
                                <x-badge tone="neutral">{{ __('search.pending') }}</x-badge>
                                <p class="text-sm text-slate-600">
                                    Le résultat vous parviendra par notification.
                                </p>
                                <x-skeleton :lines="2" />
                            </div>
                        @elseif ($search['view'] === null)
                            <div class="flex flex-col gap-3">
                                <x-badge tone="neutral">{{ __('search.no_result') }}</x-badge>
                                <p class="text-sm text-slate-600">
                                    Personne n'a encore signalé ce document. Votre déclaration
                                    reste active&nbsp;: vous serez prévenu si quelqu'un le
                                    signale plus tard.
                                </p>
                            </div>
                        @else
                            {{-- Niveau N1 : le masquage est fait côté serveur, ces
                                 champs sont les SEULS que la vue transporte. --}}
                            <div class="flex flex-col gap-3">
                                <x-disclosure-level :level="1" />

                                <dl class="grid grid-cols-2 gap-2 text-sm">
                                    <dt class="text-slate-600">Type de document</dt>
                                    <dd class="font-medium">{{ $search['view']->documentTypeLabel }}</dd>
                                    <dt class="text-slate-600">Nom sur le document</dt>
                                    <dd class="font-medium">{{ $search['view']->ownerInitials }}</dd>
                                    <dt class="text-slate-600">Trouvé en</dt>
                                    <dd class="font-medium">{{ $search['view']->foundMonth }}</dd>
                                    <dt class="text-slate-600">Région</dt>
                                    <dd class="font-medium">{{ $search['view']->foundRegion }}</dd>
                                </dl>

                                <p class="rounded bg-slate-50 p-3 text-xs text-slate-600">
                                    Il s'agit d'une <strong>correspondance possible</strong>,
                                    pas d'une certitude. Le numéro du document, le lieu précis
                                    et la photo ne sont pas affichés à ce stade.
                                </p>

                                <div>
                                    <x-button variant="primary">C'est peut-être mon document</x-button>
                                </div>
                            </div>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layout>
