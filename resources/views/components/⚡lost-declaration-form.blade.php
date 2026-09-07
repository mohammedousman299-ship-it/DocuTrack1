<?php

use App\Models\DocumentType;
use App\Search\SearchCriteria;
use App\Search\SearchNotAllowed;
use App\Search\SubmitSearch;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Parcours du Propriétaire (§1.3, §1.4).
 *
 * ------------------------------------------------------------------------
 * L'UTILISATEUR CROIT CHERCHER ; LE SYSTÈME ENREGISTRE AUSSI UNE DÉCLARATION.
 *
 * C'est la décision D-035, et elle oblige à une honnêteté explicite : sans
 * elle, on créerait des déclarations que l'utilisateur ignore avoir faites.
 * L'écran l'annonce avant l'envoi, pas après.
 * ------------------------------------------------------------------------
 */
new class extends Component
{
    public ?int $documentTypeId = null;
    public string $ownerName = '';
    public string $documentNumber = '';
    public string $lostRegion = '';
    public string $lostCity = '';
    public string $lostOn = '';
    public string $extraInfo = '';

    public bool $submitted = false;

    public function mount(): void
    {
        // Pré-remplissage du nom du compte : la plupart des gens cherchent
        // leur propre document, et une saisie divergente partirait en revue
        // sans qu'ils comprennent pourquoi (M-02, D-036).
        $this->ownerName = (string) (auth()->user()?->full_name ?? '');
    }

    /** La combinaison de critères est-elle suffisante ? */
    public function criteriaSatisfied(): bool
    {
        return SearchCriteria::satisfiedBy($this->criteria()) !== null;
    }

    /** @return array<string, mixed> */
    private function criteria(): array
    {
        return [
            'document_type_id' => $this->documentTypeId,
            'owner_name' => $this->ownerName !== '' ? $this->ownerName : null,
            'document_number' => $this->documentNumber !== '' ? $this->documentNumber : null,
            'lost_region' => $this->lostRegion !== '' ? $this->lostRegion : null,
            'lost_on' => $this->lostOn !== '' ? $this->lostOn : null,
        ];
    }

    public function submit(SubmitSearch $service): void
    {
        $this->validate([
            'documentTypeId' => ['required', Rule::exists('document_types', 'id')->where('is_active', true)],
            'ownerName' => ['required', 'string', 'max:120'],
            'documentNumber' => ['nullable', 'string', 'max:40'],
            'lostRegion' => ['nullable', 'string', 'max:80'],
            'lostCity' => ['nullable', 'string', 'max:80'],
            'lostOn' => ['nullable', 'date', 'before_or_equal:today'],
            'extraInfo' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->criteriaSatisfied()) {
            // Une recherche par nom seul est refusée : elle remonterait tous
            // les documents d'un homonyme (§4.2).
            $this->addError('documentNumber', __('search.criteria_insufficient'));

            return;
        }

        try {
            $service->handle(auth()->user(), $this->criteria() + [
                'lost_city' => $this->lostCity !== '' ? $this->lostCity : null,
                'extra_info' => $this->extraInfo !== '' ? $this->extraInfo : null,
                'ip' => request()->ip(),
            ]);
        } catch (SearchNotAllowed $e) {
            // Quota atteint ou compte temporairement bloqué : c'est un refus
            // prévu, qui s'affiche. Le laisser remonter donnerait une page 500,
            // sans expliquer ni proposer de suite (« aucune impasse », §9.1).
            $this->addError('documentTypeId', $e->getMessage());

            return;
        }

        $this->submitted = true;
    }

    /** @return array<int, DocumentType> */
    public function documentTypes(): array
    {
        return DocumentType::query()->where('is_active', true)->orderBy('id')->get()->all();
    }
};
?>

<div>
    @if ($submitted)
        <x-card>
            <div class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-trust-800">Recherche enregistrée</h2>
                <p class="text-sm text-slate-600">
                    Le résultat ne s'affiche pas immédiatement. Vous recevrez une
                    notification dans quelques minutes, et vous pourrez alors le
                    consulter en vous connectant.
                </p>
                <p class="text-sm text-slate-600">
                    Ce délai est <strong>volontaire</strong>&nbsp;: il empêche qu'un tiers
                    interroge la base en rafale pour en extraire des informations sur
                    les documents déclarés.
                </p>
                <x-alert tone="recover" title="Votre recherche vaut aussi pour l'avenir">
                    Si personne n'a encore signalé ce document, votre déclaration reste
                    active. Vous serez prévenu si quelqu'un le signale plus tard&nbsp;:
                    vous n'avez rien d'autre à faire.
                </x-alert>
                <div>
                    <a href="{{ route('search.results') }}" class="dt-target">
                        <x-button variant="secondary">Voir mes recherches</x-button>
                    </a>
                </div>
            </div>
        </x-card>
    @else
        <x-card>
            <form wire:submit="submit" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="documentTypeId" class="text-sm font-medium">
                        Type de document <span class="text-danger-700">*</span>
                    </label>
                    <select id="documentTypeId" wire:model.live="documentTypeId"
                            class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Choisissez…</option>
                        @foreach ($this->documentTypes() as $type)
                            <option value="{{ $type->id }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('documentTypeId') <p class="text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>

                <x-field label="Nom figurant sur le document" name="ownerName" required
                         wire:model.live="ownerName"
                         hint="Si le document appartient à un proche, indiquez son nom : votre demande sera vérifiée par une personne avant toute suite."
                         :error="$errors->first('ownerName')" />

                <x-field label="Numéro du document" name="documentNumber"
                         wire:model.live="documentNumber"
                         hint="Si vous n'êtes pas certain du numéro, laissez ce champ vide : un numéro erroné empêche de retrouver votre document, un champ vide non."
                         :error="$errors->first('documentNumber')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Région de la perte" name="lostRegion"
                             wire:model.live="lostRegion" placeholder="Centre, Littoral…"
                             :error="$errors->first('lostRegion')" />
                    <x-field label="Date approximative" name="lostOn" type="date"
                             wire:model.live="lostOn" :error="$errors->first('lostOn')" />
                </div>

                @if (! $this->criteriaSatisfied())
                    <x-alert tone="caution" title="Il manque un élément">
                        Pour éviter que n'importe qui puisse consulter les documents d'un
                        homonyme, une recherche demande soit le <strong>numéro</strong> du
                        document, soit le <strong>nom accompagné de la région ou de la
                        date</strong> de la perte.
                    </x-alert>
                @endif

                <x-alert tone="trust" title="Ce qui va se passer">
                    Votre demande est enregistrée comme une <strong>déclaration de
                    perte</strong>&nbsp;: elle est comparée aux documents déjà signalés,
                    et elle reste active pour ceux qui le seront plus tard. Le résultat
                    vous parviendra par notification, pas immédiatement à l'écran.
                </x-alert>

                <x-button type="submit" variant="primary" class="w-full"
                          wire:loading.attr="disabled"
                          :disabled="! $this->criteriaSatisfied()">
                    <span wire:loading.remove wire:target="submit">Lancer la recherche</span>
                    <span wire:loading wire:target="submit">Enregistrement…</span>
                </x-button>
            </form>
        </x-card>
    @endif
</div>
