{{--
    Logo de travail — JALON TECHNIQUE, PAS UN TRAVAIL DE GRAPHISTE.

    Il respecte la direction décrite au cahier des charges : un document
    officiel, une loupe évoquant la recherche et l'identification. Il est
    destiné à être remplacé par un fichier produit par un graphiste ; le
    remplacer ne demande que de modifier ce seul fichier.
--}}
@props(['class' => 'h-8 w-8'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 32 32" fill="none"
     role="img" aria-label="DocuTrack">
    {{-- Le document --}}
    <path d="M7 3h12l6 6v20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"
          fill="var(--color-canvas)" stroke="var(--color-trust-700)" stroke-width="2"
          stroke-linejoin="round"/>
    <path d="M19 3v6h6" stroke="var(--color-trust-700)" stroke-width="2" stroke-linejoin="round"/>
    <path d="M10 13h7M10 17h5" stroke="var(--color-trust-300)" stroke-width="2" stroke-linecap="round"/>
    {{-- La loupe : recherche et identification --}}
    <circle cx="19.5" cy="21.5" r="5" fill="none"
            stroke="var(--color-recover-600)" stroke-width="2"/>
    <path d="M23.5 25.5 27 29" stroke="var(--color-recover-600)"
          stroke-width="2" stroke-linecap="round"/>
</svg>
