<x-layout title="DocuTrack — retrouver un document officiel perdu au Cameroun">
    {{-- §9.1 : la page d'accueil pose UNE SEULE question. L'utilisateur arrive
         en situation de stress, il ne connaît pas la nomenclature du site. --}}

    <div class="mx-auto max-w-3xl">
        <header class="mb-10 flex items-center gap-3">
            <x-logo class="h-10 w-10" />
            <div>
                <p class="text-lg font-bold text-slate-900">DocuTrack</p>
                <p class="text-sm text-slate-600">Retrouver un document officiel perdu au Cameroun</p>
            </div>
        </header>

        <h1 class="mb-2 text-2xl font-bold text-slate-900 sm:text-3xl">
            Avez-vous perdu ou trouvé un document&nbsp;?
        </h1>
        <p class="mb-8 text-base text-slate-600">
            DocuTrack met en relation les personnes qui ont perdu un document officiel
            et celles qui en ont trouvé un, pour faciliter sa restitution.
        </p>

        {{-- Les deux services principaux, à parité visuelle. Le parcours du
             Trouveur n'est pas secondaire : sans signalements, la plateforme
             n'a aucune utilité (§9.1). --}}
        <div class="mb-10 grid gap-4 sm:grid-cols-2">
            <x-card class="flex flex-col">
                <h2 class="text-base font-semibold text-slate-900">J'ai perdu un document</h2>
                <p class="mt-2 flex-1 text-sm text-slate-600">
                    Recherchez parmi les documents signalés. Si rien ne correspond
                    aujourd'hui, déclarez la perte&nbsp;: vous serez prévenu si un
                    document correspondant est signalé plus tard.
                </p>
                <div class="mt-4">
                    <a href="{{ route('register') }}" class="dt-target">
                        <x-button variant="primary" class="w-full">Rechercher mon document</x-button>
                    </a>
                </div>
            </x-card>

            <x-card class="flex flex-col">
                <h2 class="text-base font-semibold text-slate-900">J'ai trouvé un document</h2>
                <p class="mt-2 flex-1 text-sm text-slate-600">
                    Quelques champs suffisent. Votre signalement permettra à son
                    propriétaire de le retrouver, sans que votre identité lui soit
                    jamais communiquée.
                </p>
                <div class="mt-4">
                    <a href="{{ route('register') }}" class="dt-target">
                        <x-button variant="recover" class="w-full">Signaler un document trouvé</x-button>
                    </a>
                </div>
            </x-card>
        </div>

        <section class="mb-10">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Comment cela fonctionne</h2>
            <ol class="flex flex-col gap-3">
                <li class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <span class="font-semibold">1. Vous créez un compte.</span>
                    Un seul compte suffit pour les deux usages. Nous vérifions votre
                    numéro de téléphone&nbsp;: cette étape empêche la création de comptes
                    en masse qui servirait à collecter des informations sur les documents
                    déclarés.
                </li>
                <li class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <span class="font-semibold">2. Vous recherchez ou vous signalez.</span>
                    {{-- D-008 : la recherche est différée. L'annoncer ici évite que
                         l'utilisateur croie à une panne (§9.1, aucune impasse). --}}
                    Le résultat d'une recherche n'est pas immédiat&nbsp;: il vous parvient
                    par notification en quelques minutes. Ce délai est volontaire — il
                    empêche qu'un tiers interroge la base en rafale pour en extraire des
                    informations.
                </li>
                <li class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <span class="font-semibold">3. Nous vous prévenons.</span>
                    Si une <strong>correspondance possible</strong> est identifiée, vous
                    êtes notifié. Une correspondance possible n'est pas une certitude&nbsp;:
                    vous devrez confirmer qu'il s'agit bien de votre document.
                </li>
                <li class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <span class="font-semibold">4. Vous récupérez votre document.</span>
                    Après vérification de votre identité, nous vous indiquons où et
                    comment le retirer.
                </li>
            </ol>
        </section>

        @if (config('docutrack.payment.enabled'))
            {{-- D-024 : ce bloc n'apparaît QUE si le paiement est actif. Le §9.1
                 exige que les frais soient annoncés avant tout engagement ; il
                 apparaît donc en même temps que les frais eux-mêmes. --}}
            <x-alert tone="caution" title="Frais de service" class="mb-10">
                L'accès aux informations de récupération donne lieu à des frais de
                service de
                {{ number_format(config('docutrack.payment.amount_minor') / 100, 0, ',', ' ') }}
                {{ config('docutrack.payment.currency') }}, annoncés et détaillés avant
                tout paiement. Ils vous sont intégralement remboursés si la
                correspondance se révèle erronée ou si le retrait n'aboutit pas.
            </x-alert>
        @endif

        <x-alert tone="trust" title="Ce que nous ne faisons pas" class="mb-10">
            <ul class="mt-1 list-disc pl-5">
                <li>Nous ne publions jamais le nom complet ni le numéro d'un document.</li>
                <li>Nous ne communiquons jamais l'identité de la personne qui a trouvé un document.</li>
                <li>Nous ne vous demanderons jamais vos identifiants ni un paiement par téléphone ou par message.</li>
            </ul>
        </x-alert>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('register') }}" class="dt-target flex-1">
                <x-button variant="primary" class="w-full">Créer un compte</x-button>
            </a>
            <a href="{{ route('login') }}" class="dt-target flex-1">
                <x-button variant="secondary" class="w-full">Se connecter</x-button>
            </a>
        </div>
    </div>
</x-layout>
