<?php

use App\Documents\SubmitFoundReport;
use App\Models\DocumentType;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Parcours du Trouveur (§1.5).
 *
 * ------------------------------------------------------------------------
 * C'EST LE PARCOURS LE PLUS COURT DU SITE, ET CE N'EST PAS NÉGOCIABLE.
 *
 * Le Trouveur est un bénévole, souvent debout dans la rue avec un document en
 * main. Chaque champ supplémentaire réduit le nombre de documents signalés,
 * donc l'utilité de la plateforme entière (§9.1). Trois écrans courts, un seul
 * champ obligatoire par écran quand c'est possible, et un brouillon conservé.
 * ------------------------------------------------------------------------
 *
 * Le brouillon est gardé EN SESSION, côté serveur, et non dans le navigateur :
 * il contient le nom et le numéro du document d'un tiers, et il n'a pas à
 * subsister sur l'appareil du Trouveur une fois le signalement envoyé.
 */
new class extends Component
{
    public int $step = 1;

    public ?int $documentTypeId = null;
    public string $foundOn = '';
    public string $foundRegion = '';
    public string $foundCity = '';

    public string $ownerName = '';
    public string $documentNumber = '';
    public string $extraInfo = '';

    public ?string $objectKey = null;
    public string $depositFreeText = '';

    public bool $submitted = false;

    public function mount(): void
    {
        $this->foundOn = now()->toDateString();

        // Reprise d'un brouillon interrompu : perdre une saisie sur un réseau
        // instable est l'une des façons les plus sûres de perdre un
        // signalement (§9.4).
        foreach (session('found_report_draft', []) as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function updated(): void
    {
        $this->saveDraft();
    }

    private function saveDraft(): void
    {
        session()->put('found_report_draft', [
            'step' => $this->step,
            'documentTypeId' => $this->documentTypeId,
            'foundOn' => $this->foundOn,
            'foundRegion' => $this->foundRegion,
            'foundCity' => $this->foundCity,
            'ownerName' => $this->ownerName,
            'documentNumber' => $this->documentNumber,
            'extraInfo' => $this->extraInfo,
            'objectKey' => $this->objectKey,
            'depositFreeText' => $this->depositFreeText,
        ]);
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step));
        $this->step++;
        $this->saveDraft();
    }

    public function previous(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->saveDraft();
    }

    /** @return array<string, mixed> */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'documentTypeId' => ['required', Rule::exists('document_types', 'id')->where('is_active', true)],
                'foundOn' => ['required', 'date', 'before_or_equal:today'],
                'foundRegion' => ['required', 'string', 'max:80'],
                'foundCity' => ['required', 'string', 'max:80'],
            ],
            2 => [
                'ownerName' => ['nullable', 'string', 'max:120'],
                // Volontairement permissif : les formats réels des numéros
                // camerounais ne sont pas connus (D-012). Rejeter à tort un
                // numéro valide créerait une impasse.
                'documentNumber' => ['nullable', 'string', 'max:40'],
                'extraInfo' => ['nullable', 'string', 'max:500'],
            ],
            default => [],
        };
    }

    public function requiresPhoto(): bool
    {
        $type = $this->documentTypeId === null ? null : DocumentType::find($this->documentTypeId);

        return $type?->requiresAttachment() ?? false;
    }

    public function submit(SubmitFoundReport $service): void
    {
        $this->validate($this->rulesForStep(1) + $this->rulesForStep(2) + [
            'depositFreeText' => ['nullable', 'string', 'max:300'],
        ]);

        $service->handle(auth()->user(), [
            'document_type_id' => $this->documentTypeId,
            'owner_name' => $this->ownerName !== '' ? $this->ownerName : null,
            'document_number' => $this->documentNumber !== '' ? $this->documentNumber : null,
            'found_on' => $this->foundOn,
            'found_region' => $this->foundRegion,
            'found_city' => $this->foundCity,
            'deposit_free_text' => $this->depositFreeText !== '' ? $this->depositFreeText : null,
            'extra_info' => $this->extraInfo !== '' ? $this->extraInfo : null,
            'object_key' => $this->objectKey,
        ]);

        // Le brouillon contient le nom et le numéro du document d'un tiers :
        // il ne doit pas survivre à l'envoi.
        session()->forget('found_report_draft');

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
            <div class="flex flex-col gap-4 text-center">
                <h2 class="text-lg font-semibold text-recover-700">Signalement enregistré</h2>
                <p class="text-sm text-slate-600">
                    Merci. Si ce document correspond à une déclaration de perte, son
                    propriétaire sera prévenu. Vous ne serez pas informé de la suite&nbsp;:
                    c'est volontaire, et cela protège les personnes concernées.
                </p>
                <div class="flex justify-center">
                    <a href="{{ route('home') }}" class="dt-target">
                        <x-button variant="secondary">Retour à l'accueil</x-button>
                    </a>
                </div>
            </div>
        </x-card>
    @else
        {{-- Indicateur de progression (§9.1) --}}
        <div class="mb-6" role="group" aria-label="Progression du signalement">
            <div class="mb-2 flex justify-between text-xs text-slate-600">
                <span>Étape {{ $step }} sur 3</span>
                <span>{{ ['Le document', 'Le propriétaire', 'La photo'][$step - 1] ?? '' }}</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-slate-200">
                <div class="h-1.5 rounded-full bg-trust-700 transition-all"
                     style="width: {{ (int) ($step / 3 * 100) }}%"></div>
            </div>
        </div>

        <x-card>
            @if ($step === 1)
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="documentTypeId" class="text-sm font-medium">Type de document <span class="text-danger-700">*</span></label>
                        <select id="documentTypeId" wire:model.live="documentTypeId"
                                class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Choisissez…</option>
                            @foreach ($this->documentTypes() as $type)
                                <option value="{{ $type->id }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('documentTypeId') <p class="text-xs text-danger-700">{{ $message }}</p> @enderror
                    </div>

                    <x-field label="Date de la découverte" name="foundOn" type="date" required
                             wire:model="foundOn" :error="$errors->first('foundOn')" />
                    <x-field label="Région" name="foundRegion" required
                             wire:model="foundRegion" placeholder="Centre, Littoral…"
                             :error="$errors->first('foundRegion')" />
                    <x-field label="Ville ou quartier" name="foundCity" required
                             wire:model="foundCity" :error="$errors->first('foundCity')" />

                    <x-button wire:click="next" variant="primary" class="w-full">Continuer</x-button>
                </div>
            @elseif ($step === 2)
                <div class="flex flex-col gap-4">
                    <x-field label="Nom figurant sur le document" name="ownerName"
                             wire:model="ownerName"
                             hint="Laissez vide si vous ne le voyez pas clairement."
                             :error="$errors->first('ownerName')" />

                    <x-field label="Numéro du document" name="documentNumber"
                             wire:model="documentNumber"
                             hint="Si vous n'êtes pas certain du numéro, laissez ce champ vide : un numéro erroné empêche de retrouver le propriétaire, un champ vide non."
                             :error="$errors->first('documentNumber')" />

                    <x-field label="Où se trouve le document maintenant ?" name="depositFreeText"
                             wire:model="depositFreeText"
                             hint="Si vous l'avez déposé quelque part — poste de police, mairie — indiquez-le : c'est la façon la plus sûre de le rendre à son propriétaire."
                             :error="$errors->first('depositFreeText')" />

                    <div class="flex gap-3">
                        <x-button wire:click="previous" variant="secondary">Retour</x-button>
                        <x-button wire:click="next" variant="primary" class="flex-1">Continuer</x-button>
                    </div>
                </div>
            @else
                <div class="flex flex-col gap-4">
                    @if ($this->requiresPhoto())
                        <x-alert tone="caution" title="Photo nécessaire pour ce document">
                            Ce type de document exige une vérification par un administrateur avant
                            toute restitution. Sans photo, cette vérification serait impossible.
                        </x-alert>
                    @else
                        <p class="text-sm text-slate-600">
                            La photo est facultative pour ce type de document.
                        </p>
                    @endif

                    {{-- wire:ignore : transmettre la clé au composant déclenche un
                         rendu, qui remplacerait ce bloc et effacerait le message
                         d'état au moment précis où il devient utile. --}}
                    <div data-upload-container wire:ignore class="flex flex-col gap-3">
                        <label class="text-sm font-medium" for="photo">Photo du document</label>
                        <input id="photo" type="file" accept="image/*" capture="environment"
                               data-upload-input data-upload-target="objectKey"
                               class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm">

                        <p class="text-xs text-slate-600">
                            La photo est réduite et ses métadonnées — dont la position
                            GPS — sont retirées sur votre appareil avant l'envoi.
                        </p>

                        {{-- Renseigné par resources/js/document-upload.js. Aucune
                             expression Alpine ici : l'évaluateur compatible CSP
                             n'accepte pas les expressions complexes. --}}
                        <p data-upload-status class="text-sm font-medium text-slate-700" role="status" aria-live="polite"></p>
                    </div>

                    @if ($this->requiresPhoto() && $objectKey === null)
                        <x-alert tone="trust">
                            Vous pouvez tout de même envoyer votre signalement&nbsp;: il sera
                            simplement vérifié par un administrateur avant d'être pris en compte.
                        </x-alert>
                    @endif

                    <div class="flex gap-3">
                        <x-button wire:click="previous" variant="secondary">Retour</x-button>
                        <x-button wire:click="submit" variant="recover" class="flex-1"
                                  wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submit">Envoyer le signalement</span>
                            <span wire:loading wire:target="submit">Envoi…</span>
                        </x-button>
                    </div>
                </div>
            @endif
        </x-card>
    @endif
</div>
