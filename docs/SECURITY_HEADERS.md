# En-têtes de sécurité et CSP

**Version :** 1.0 · **Date :** 2026-09-06
**Mesuré sur :** Chromium (Playwright), Livewire 4.4.3, Laravel 13.30.1

> Le §4.6 du cahier des charges demande une CSP stricte **et** la documentation
> de la configuration compatible Livewire/Alpine. Ce document contient une
> **mesure**, pas une supposition.

---

## 1. Pourquoi au jalon 2 et non au jalon 8

Le master prompt place le durcissement au jalon 8. Nous l'avons avancé (D-025).

Une CSP compatible Livewire s'établit bien plus facilement sur trois écrans que
sur trente : chaque violation est immédiatement rattachable au composant qui l'a
causée. Ajoutée tardivement, elle casserait des composants écrits entre-temps,
et la pression serait alors d'**assouplir la politique** plutôt que de corriger
le code.

Ce raisonnement s'est vérifié : la première mesure a révélé une incompatibilité
de fond en quelques minutes, sur un composant unique.

## 2. La politique appliquée

| Directive | Valeur | Remarque |
|---|---|---|
| `default-src` | `'self'` | |
| `script-src` | `'self' 'nonce-<aléatoire>'` | **Ni `unsafe-eval`, ni `unsafe-inline`** |
| `style-src` | `'self' 'unsafe-inline'` | Assouplissement réel — voir §5 |
| `img-src` | `'self' data: blob:` | `blob:` pour la prévisualisation avant envoi |
| `font-src` | `'self'` | Aucune police externe (D-020) |
| `connect-src` | `'self'` | L'origine du stockage objet devra s'y ajouter au jalon 3 |
| `media-src` | `'none'` | |
| `object-src` | `'none'` | |
| `base-uri` | `'self'` | |
| `form-action` | `'self'` | |
| `frame-ancestors` | `'none'` | |
| `upgrade-insecure-requests` | — | |

Le nonce est régénéré à chaque réponse — vérifié par test.

Autres en-têtes : `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`,
`Permissions-Policy: camera=(self), geolocation=(), microphone=(), payment=(), usb=()`.

`Strict-Transport-Security` **n'est émis que sur HTTPS** : l'émettre en clair
n'apporte rien et rendrait un environnement local inaccessible.

## 3. La mesure — Livewire 4 sous CSP stricte

**Protocole.** Un composant Livewire réel (`⚡dev-counter`) exposé dans
`/dev/ui`, chargé par Chromium via Playwright, avec relevé des violations de CSP
en console et vérification que l'interaction fonctionne réellement.

### 3.1 Sans réglage particulier — ÉCHEC

```
compteur avant : 0
compteur apres : 0        <- l'incrément ne se produit pas
Livewire fonctionne : NON
violations CSP : 2
  ! Livewire Expression Error: Refused to evaluate a string as JavaScript
    because 'unsafe-eval' is not an allowed source of script...
```

Le composant s'affiche — le rendu initial est fait côté serveur — mais **toute
interaction est morte**. Livewire évalue ses expressions dynamiquement, ce que
la CSP interdit.

C'est exactement le risque signalé en D-025, et il était réel.

### 3.2 Avec `livewire.csp_safe = true` — SUCCÈS

```
compteur avant : 0
compteur apres : 1        <- l'incrément fonctionne
Livewire fonctionne : OUI
violations CSP : 0
```

Livewire 4 embarque une variante d'Alpine compatible CSP, activée par ce seul
réglage de configuration.

### 3.3 Expressions Alpine inline — SUCCÈS

La crainte était que le mode compatible CSP impose une syntaxe d'expressions
restreinte, obligeant à réécrire tous les composants. Mesure sur une expression
inline ordinaire (`x-data`, `x-on:click`, `x-text` avec un ternaire) :

```
avant : ferme | apres : ouvert
expression inline fonctionne : OUI
messages : 0
```

**Aucune contrainte de syntaxe constatée sur ce cas.** Cela ne prouve pas que
toutes les constructions Alpine passent : la vérification doit être refaite si
un composant futur se comporte anormalement. Les deux composants de mesure
restent dans `/dev/ui` comme test de non-régression.

## 4. Conclusion opérationnelle

**Une CSP stricte, sans `unsafe-eval` ni `unsafe-inline` sur les scripts, est
compatible avec Livewire 4 — à la condition que `livewire.csp_safe` soit à
`true`.**

Ce réglage n'est pas cosmétique : à `false`, l'interface entière cesse de
répondre sous notre politique. Un test le verrouille, et un autre interdit
l'apparition de `unsafe-eval` dans `script-src` — pour qu'on ne soit pas tenté
d'assouplir la politique le jour où un composant résistera.

## 5. L'assouplissement assumé : `style-src 'unsafe-inline'`

C'est le seul écart à une politique pleinement stricte.

Blade et Tailwind produisent des attributs `style` ponctuels — barres de
progression, largeurs de squelettes de chargement. Le durcir exigerait de
supprimer tout attribut `style` du projet ou de leur appliquer un nonce
individuel.

**Portée du risque :** `unsafe-inline` sur les styles n'autorise pas
l'exécution de code. Il permet des attaques d'exfiltration par CSS, réelles
mais nettement moins graves qu'une exécution de script. L'écart est consigné
pour être réévalué au jalon 8, pas oublié.

## 6. À faire aux jalons suivants

- **Jalon 3 :** ajouter l'origine du stockage objet à `connect-src` et
  `img-src`, et **remesurer** — l'envoi direct depuis le navigateur est
  précisément le genre de fonctionnalité qu'une CSP casse.
- **Jalon 8 :** réévaluer `style-src`, et rejouer la mesure sur l'ensemble des
  écrans plutôt que sur `/dev/ui` seul.
