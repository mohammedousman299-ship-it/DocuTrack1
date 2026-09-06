<x-layout title="Galerie de composants — DocuTrack">
    <div class="mb-8 flex items-center gap-3">
        <x-logo class="h-10 w-10" />
        <div>
            <h1 class="text-xl font-bold text-slate-900">Galerie de composants</h1>
            <p class="text-sm text-slate-600">Hors production uniquement.</p>
        </div>
    </div>

    <x-alert tone="caution" title="Logo provisoire" class="mb-8">
        Le logo est un <strong>jalon technique</strong>, pas un travail de graphiste.
        Il respecte la direction décrite au cahier des charges — un document officiel
        et une loupe — et doit être remplacé par un fichier professionnel.
        Le remplacement ne touche qu'un seul fichier&nbsp;:
        <code>resources/views/components/logo.blade.php</code>.
    </x-alert>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Livewire sous CSP stricte</h2>
        <p class="mb-3 text-sm text-slate-600">
            Composant de mesure : il vérifie qu'une CSP stricte, sans
            <code>unsafe-eval</code> ni <code>unsafe-inline</code> sur les scripts,
            laisse fonctionner Livewire et Alpine (D-025).
        </p>
        <x-card>
            <livewire:dev-counter />
            <div class="mt-4"><livewire:dev-alpine-probe /></div>
        </x-card>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Palette</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-card title="Bleu — confiance">
                <div class="flex gap-1">
                    <div class="h-10 flex-1 rounded bg-trust-100"></div>
                    <div class="h-10 flex-1 rounded bg-trust-400"></div>
                    <div class="h-10 flex-1 rounded bg-trust-700"></div>
                    <div class="h-10 flex-1 rounded bg-trust-900"></div>
                </div>
            </x-card>
            <x-card title="Vert — récupération">
                <div class="flex gap-1">
                    <div class="h-10 flex-1 rounded bg-recover-100"></div>
                    <div class="h-10 flex-1 rounded bg-recover-400"></div>
                    <div class="h-10 flex-1 rounded bg-recover-700"></div>
                    <div class="h-10 flex-1 rounded bg-recover-900"></div>
                </div>
            </x-card>
            <x-card title="Attention">
                <div class="flex gap-1">
                    <div class="h-10 flex-1 rounded bg-caution-100"></div>
                    <div class="h-10 flex-1 rounded bg-caution-700"></div>
                </div>
            </x-card>
            <x-card title="Erreur">
                <div class="flex gap-1">
                    <div class="h-10 flex-1 rounded bg-danger-100"></div>
                    <div class="h-10 flex-1 rounded bg-danger-700"></div>
                </div>
            </x-card>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Boutons</h2>
        <x-card>
            <div class="flex flex-wrap gap-3">
                <x-button variant="primary">Rechercher un document</x-button>
                <x-button variant="recover">Signaler un document trouvé</x-button>
                <x-button variant="secondary">Retour</x-button>
                <x-button variant="ghost">Annuler</x-button>
                <x-button variant="danger">Supprimer</x-button>
                <x-button variant="primary" disabled>Envoi en cours…</x-button>
            </div>
        </x-card>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Niveaux de divulgation</h2>
        <p class="mb-3 text-sm text-slate-600">
            Le niveau est <strong>visible de l'utilisateur</strong> : il doit comprendre
            qu'il ne voit pas tout, et pourquoi.
        </p>
        <div class="grid gap-3 sm:grid-cols-3">
            <x-card><x-disclosure-level :level="1" /></x-card>
            <x-card><x-disclosure-level :level="2" /></x-card>
            <x-card><x-disclosure-level :level="3" /></x-card>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Carte de correspondance (niveau 1)</h2>
        <x-card>
            <div class="flex flex-col gap-3">
                <x-disclosure-level :level="1" />
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-slate-600">Type de document</dt>
                    <dd class="font-medium">Carte nationale d'identité</dd>
                    <dt class="text-slate-600">Nom sur le document</dt>
                    <dd class="font-medium">M. O.</dd>
                    <dt class="text-slate-600">Trouvé en</dt>
                    <dd class="font-medium">août 2026</dd>
                    <dt class="text-slate-600">Région</dt>
                    <dd class="font-medium">Centre</dd>
                </dl>
                <p class="rounded bg-slate-50 p-3 text-xs text-slate-600">
                    Le numéro du document, le lieu précis et l'image ne sont pas affichés
                    à ce niveau. Le masquage est fait sur le serveur&nbsp;: ces données ne
                    sont pas envoyées au navigateur.
                </p>
                <div><x-button variant="primary">C'est peut-être mon document</x-button></div>
            </div>
        </x-card>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Badges de statut</h2>
        <x-card>
            <div class="flex flex-wrap gap-2">
                <x-badge tone="neutral">Déclaration active</x-badge>
                <x-badge tone="trust">Correspondance possible</x-badge>
                <x-badge tone="caution">En cours de vérification</x-badge>
                <x-badge tone="recover">Document récupéré</x-badge>
                <x-badge tone="danger">Revendication refusée</x-badge>
            </div>
        </x-card>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Champs de formulaire</h2>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Nom figurant sur le document" name="owner_name" required
                         placeholder="Tel qu'il est écrit sur la pièce" />
                <x-field label="Numéro du document" name="document_number"
                         hint="Si vous n'êtes pas certain du numéro, laissez ce champ vide : un numéro erroné empêche de retrouver votre document, un champ vide non."
                         placeholder="Facultatif" />
                <x-field label="Adresse e-mail" name="email" type="email"
                         error="Cette adresse est déjà associée à un compte." />
            </div>
        </x-card>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">Alertes</h2>
        <div class="grid gap-3">
            <x-alert tone="trust" title="Recherche enregistrée">
                Votre recherche est en cours de traitement. Vous recevrez une notification
                dès qu'elle sera terminée, dans quelques minutes.
            </x-alert>
            <x-alert tone="recover" title="Vérification réussie">
                Vous pouvez maintenant accéder aux informations de récupération.
            </x-alert>
            <x-alert tone="caution" title="Correspondance possible, pas certaine">
                Une correspondance possible a été identifiée. Cela ne signifie pas que
                votre document a été retrouvé.
            </x-alert>
            <x-alert tone="danger" title="Éléments non concordants">
                Les éléments fournis ne correspondent pas. Il vous reste 2 tentatives.
            </x-alert>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">États vides et chargement</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-empty-state title="Aucune correspondance pour l'instant">
                <p>Aucun document correspondant n'a encore été signalé.</p>
                <x-slot:action>
                    <x-button variant="recover">Déclarer la perte</x-button>
                </x-slot:action>
            </x-empty-state>
            <x-card title="Chargement">
                <x-skeleton :lines="4" />
            </x-card>
        </div>
    </section>
</x-layout>
