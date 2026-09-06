<x-layout title="Double authentification — DocuTrack">
    <div class="mx-auto max-w-md">
        <h1 class="mb-2 text-xl font-bold">Double authentification</h1>
        <p class="mb-6 text-sm text-slate-600">
            Elle est <strong>obligatoire</strong> pour les comptes d'administration :
            ce sont les seuls à pouvoir consulter des images de documents d'identité.
        </p>

        @if (session('status') === 'two-factor-authentication-enabled')
            <x-alert tone="trust" title="Étape suivante" class="mb-6">
                Scannez le code ci-dessous dans votre application d'authentification,
                puis saisissez le code à 6 chiffres qu'elle affiche.
            </x-alert>
        @elseif (session('status'))
            <x-alert tone="caution" class="mb-6">{{ session('status') }}</x-alert>
        @endif

        @error('code')
            <x-alert tone="danger" title="Code refusé" class="mb-6">{{ $message }}</x-alert>
        @enderror

        @php $user = auth()->user(); @endphp

        @if ($user?->hasConfirmedTwoFactor())
            <x-card title="Double authentification active">
                <p class="text-sm text-slate-600">
                    Votre compte est protégé par un second facteur.
                </p>
                <div class="mt-4">
                    <a href="{{ route('home') }}" class="dt-target">
                        <x-button variant="secondary">Retour à l'accueil</x-button>
                    </a>
                </div>
            </x-card>
        @elseif ($user?->two_factor_secret)
            <x-card title="Confirmer l'activation">
                <div class="my-4 flex justify-center rounded-lg bg-white p-4">
                    {!! $user->twoFactorQrCodeSvg() !!}
                    {{-- {!! !!} est justifié ici : la sortie est un SVG produit par
                         Fortify à partir du secret du compte, jamais une saisie
                         utilisateur (§4.6). --}}
                </div>

                <p class="mb-4 break-all rounded bg-slate-50 p-3 text-center text-xs text-slate-600">
                    Saisie manuelle : <strong>{{ decrypt($user->two_factor_secret) }}</strong>
                </p>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field label="Code affiché par votre application" name="code" required
                             inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                             placeholder="123456" :error="$errors->first('code')" />
                    <x-button type="submit" variant="primary" class="w-full">Activer</x-button>
                </form>
            </x-card>
        @else
            <x-card title="Application d'authentification">
                <p class="text-sm text-slate-600">
                    DocuTrack utilise une application d'authentification, <strong>et non
                    le SMS</strong>. Un SMS peut être détourné par le transfert frauduleux
                    d'une carte SIM, ce qui rendrait le second facteur inutile précisément
                    sur le compte le plus sensible.
                </p>

                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                    @csrf
                    <x-button type="submit" variant="primary" class="w-full">
                        Activer la double authentification
                    </x-button>
                </form>
            </x-card>
        @endif

        @if ($user?->two_factor_recovery_codes)
            <x-card title="Codes de récupération" class="mt-6">
                <p class="text-sm text-slate-600">
                    Conservez-les hors de votre téléphone. Ils permettent de reprendre la
                    main si vous perdez votre application — c'est ce qui remplace un repli
                    par SMS, sans en rouvrir la faiblesse.
                </p>
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-xs">
                    @foreach ($user->two_factor_recovery_codes as $code)
                        <li class="rounded bg-slate-50 px-2 py-1">{{ $code }}</li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-layout>
