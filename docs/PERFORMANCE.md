# Performance et accessibilité — mesures

**Version :** 1.0 · **Date :** 2026-09-05 · **Jalon :** 1

> Budgets à **mesurer, pas à supposer** (§9.4). Ce document ne contient que des
> valeurs relevées. Ce qui n'a pas été mesuré est marqué comme tel.

---

## 1. Poids des ressources

| Budget (§9.4) | Cible | **Mesuré** | Marge |
|---|---|---|---|
| JS initial compressé | < 100 ko | **0,02 ko** | 99,98 % |
| CSS compressé | < 50 ko | **10,25 ko** | 79 % |

CSS non compressé : 42,10 ko. JS non compressé : ~0 ko.

Ces valeurs correspondent au socle du jalon 1 : elles augmenteront avec
Livewire et Alpine aux jalons suivants et devront être remesurées à chaque
jalon. La marge actuelle est confortable, elle n'est pas acquise.

### 1.1 Aucune police téléchargée — décision d'exigence n°3

Le squelette Laravel embarquait une police web (Instrument Sans, trois
graisses). Elle a été **retirée** au profit d'une pile système.

Justification : sur un réseau 3G, chaque graisse embarquée retarde le premier
affichage utile de plusieurs centaines de millisecondes, et le texte reste
invisible ou substitué pendant le téléchargement. La pile système est déjà
présente sur l'appareil, rendue instantanément, et lisible partout.

Le cahier des charges demande « un caractère clair et moderne, exprimant
professionnalisme, simplicité et fiabilité ». Une pile système récente y
répond, et l'arbitrage penche vers l'exigence n°3 tant qu'aucune contrainte de
marque n'impose une police propre. **À reconsidérer si une identité visuelle
formelle est définie** — le changement ne toucherait qu'un jeton dans
`resources/css/app.css`.

## 2. NON MESURÉ à ce jalon

| Budget (§9.4) | Cible | État |
|---|---|---|
| Premier affichage utile en 3G simulée, mobile bas de gamme | < 2,5 s | **NON MESURÉ** — nécessite des pages réelles (jalon 2) |
| Requêtes SQL par page | ≤ 15 | **NON MESURÉ** — nécessite des écrans réels (jalon 2) |
| Upload reprenable, résilience réseau | — | **NON IMPLÉMENTÉ** — jalon 3 |

Ces mesures n'ont pas de sens sur un socle sans parcours utilisateur. Elles
sont dues au jalon 2, sur la page d'accueil et le parcours de connexion.

## 3. Performance de la base

Voir `DATABASE.md` §2. En résumé, sur 100 000 lignes : présélection par
trigramme 18,97 ms, par HMAC 0,03 ms, requête combinée 17,96 ms, toutes en
*index scan*.

## 4. Accessibilité — contrastes mesurés

Cible : **WCAG 2.1 niveau AA**, soit un ratio ≥ 4,5:1 pour le texte.

Les **13 paires** texte/fond de la palette ont été calculées. **Toutes
atteignent AA**, la plus faible à 5,30:1.

| Paire | Ratio |
|---|---:|
| `trust-700` sur blanc | 8,72:1 |
| blanc sur `trust-700` | 8,72:1 |
| `recover-700` sur blanc | 7,68:1 |
| blanc sur `recover-700` | 7,68:1 |
| `slate-600` sur blanc | 7,58:1 |
| `trust-700` sur `trust-100` | 7,15:1 |
| `recover-700` sur `recover-100` | 6,78:1 |
| `danger-700` sur blanc | 6,47:1 |
| blanc sur `danger-700` | 6,47:1 |
| `caution-700` sur `caution-100` | 6,37:1 |
| `trust-600` sur blanc | 5,83:1 |
| `recover-600` sur blanc | 5,48:1 |
| `danger-700` sur `danger-100` | 5,30:1 |

> Les valeurs annoncées en commentaire dans `app.css` étaient d'abord des
> estimations, dont une fausse (8,0:1 annoncé pour `caution-700`, 6,37:1
> réel). Elles ont été remplacées par les valeurs calculées.

### 4.1 Autres dispositions d'accessibilité en place

| Disposition | État |
|---|---|
| Cibles tactiles ≥ 44 × 44 px | ✅ appliqué globalement aux boutons et champs |
| Focus visible | ✅ `:focus-visible` explicite, jamais supprimé |
| Lien d'évitement vers le contenu | ✅ dans le gabarit |
| Libellés associés aux champs | ✅ composant `field` |
| Erreurs liées par `aria-describedby` | ✅ composant `field` |
| Champ obligatoire annoncé aux lecteurs d'écran | ✅ `sr-only` en plus de l'astérisque |
| Aucune information portée par la seule couleur | ✅ chaque badge porte un libellé |
| `role="alert"` sur les messages d'erreur | ✅ composant `alert` |
| Respect de `prefers-reduced-motion` | ✅ |
| Régions live pour les mises à jour Livewire | **À FAIRE** — jalon 2 |
| Audit d'accessibilité complet | **À FAIRE** — jalon 8 |

## 5. Galerie de composants

`/dev/ui`, **hors production uniquement** — la restriction est appliquée dans
la route, pas par convention de déploiement, et un test vérifie qu'elle renvoie
404 en production.

Elle présente la palette, les boutons, les badges de statut, les champs, les
alertes, les états vides, les squelettes de chargement, et une carte de
correspondance de niveau N1 montrant explicitement ce qui est masqué et
pourquoi.
