<x-layout title="Revue des déclarations — DocuTrack">
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-2 text-xl font-bold">Déclarations à vérifier</h1>

        <p class="mb-6 text-sm text-slate-600">
            Ces déclarations portent un nom différent de celui du compte. Elles
            sont en attente&nbsp;: aucun rapprochement n'a été fait et aucune
            notification n'a été envoyée. La question à trancher est
            unique&nbsp;: <strong>ce compte déclare-t-il plausiblement pour un
            proche&nbsp;?</strong>
        </p>

        @if (session('status'))
            <x-alert tone="recover" class="mb-6">{{ session('status') }}</x-alert>
        @endif

        <x-card class="mb-6">
            <dl class="grid grid-cols-2 gap-2 text-sm">
                <dt class="text-slate-600">En attente</dt>
                <dd class="font-medium">{{ $pendingCount }}</dd>
                <dt class="text-slate-600">Plus ancienne</dt>
                <dd class="font-medium">
                    {{ $oldestPendingHours === null ? '—' : $oldestPendingHours.' h' }}
                </dd>
            </dl>
            {{-- Q-27 : la soutenabilité de la file est une question ouverte.
                 Un délai qui s'allonge est le signal qu'elle ne l'est plus. --}}
        </x-card>

        @if ($items->isEmpty())
            <x-empty-state title="Rien à vérifier">
                <p>Aucune déclaration n'attend de décision.</p>
            </x-empty-state>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($items as $item)
                    <x-card>
                        <dl class="grid grid-cols-3 gap-2 text-sm">
                            <dt class="text-slate-600">Type de document</dt>
                            <dd class="col-span-2 font-medium">{{ $item->documentTypeLabel }}</dd>

                            <dt class="text-slate-600">Titulaire du compte</dt>
                            <dd class="col-span-2 font-medium">{{ $item->accountName }}</dd>

                            <dt class="text-slate-600">Nom déclaré</dt>
                            <dd class="col-span-2 font-medium">{{ $item->declaredName }}</dd>

                            <dt class="text-slate-600">Déclarée le</dt>
                            <dd class="col-span-2 font-medium">{{ $item->submittedOn }}</dd>

                            <dt class="text-slate-600">Explication</dt>
                            <dd class="col-span-2">
                                @if ($item->explanation === null)
                                    <span class="text-slate-500">Aucune explication fournie.</span>
                                @else
                                    <span class="whitespace-pre-line">{{ $item->explanation }}</span>
                                @endif
                            </dd>
                        </dl>

                        <p class="mt-3 rounded bg-slate-50 p-3 text-xs text-slate-600">
                            Le numéro du document et les correspondances éventuelles
                            ne sont pas affichés&nbsp;: ils ne servent pas cette
                            décision, et savoir qu'une déclaration «&nbsp;tombe
                            juste&nbsp;» ferait décider en connaissant l'enjeu.
                        </p>

                        <form method="POST"
                              action="{{ route('admin.review.update', $item->id) }}"
                              class="mt-4 flex flex-col gap-3">
                            @csrf

                            {{-- x-field porte un <input> ; un motif se rédige,
                                 d'où le <textarea> écrit ici en clair. --}}
                            <div class="flex flex-col gap-1.5">
                                <label for="reason-{{ $item->id }}"
                                       class="text-sm font-medium text-slate-900">
                                    Motif de la décision
                                    <span class="text-danger-700" aria-hidden="true">*</span>
                                    <span class="sr-only">(obligatoire)</span>
                                </label>
                                <p id="reason-hint-{{ $item->id }}" class="text-xs text-slate-600">
                                    Obligatoire pour l'approbation comme pour le refus,
                                    et conservé au journal d'audit, qui ne peut plus être
                                    modifié ensuite.
                                </p>
                                <textarea id="reason-{{ $item->id }}"
                                          name="reason"
                                          rows="2"
                                          minlength="{{ $minimumReasonLength }}"
                                          maxlength="1000"
                                          required
                                          aria-describedby="reason-hint-{{ $item->id }}"
                                          class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                @error('reason')
                                    <p class="text-xs font-medium text-danger-700">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex gap-3">
                                <x-button type="submit" name="decision" value="approve" variant="primary">
                                    Approuver
                                </x-button>
                                <x-button type="submit" name="decision" value="reject" variant="secondary">
                                    Refuser
                                </x-button>
                            </div>
                        </form>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layout>
