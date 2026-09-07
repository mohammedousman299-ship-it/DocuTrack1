# Performance et accessibilité — mesures

**Version :** 1.2 · **Date :** 2026-09-06 · **Jalons :** 1 à 4

> Budgets à **mesurer, pas à supposer** (§9.4). Ce document ne contient que des
> valeurs relevées. Ce qui n'a pas été mesuré est marqué comme tel.

---

## 1. Poids des ressources

### Jalon 1 — socle sans Livewire

| Budget (§9.4) | Cible | Mesuré | Marge |
|---|---|---|---|
| JS initial compressé | < 100 ko | 0,02 ko | 99,98 % |
| CSS compressé | < 50 ko | 10,25 ko | 79 % |

### Jalon 3 — avec Livewire ⚠️ **la marge a disparu**

| Budget (§9.4) | Cible | **Mesuré** | Marge |
|---|---|---|---|
| CSS compressé | < 50 ko | **4,6 ko** | 91 % |
| **JS initial compressé** | **< 100 ko** | **≈ 97,4 ko** | **≈ 2,6 %** |

Détail du JS, mesuré sur les fichiers réellement servis :

| Fichier | Brut | **Compressé** |
|---|---:|---:|
| `livewire.csp.min.js` (production) | 298,7 ko | **96,4 ko** |
| `app.js` (notre code) | 1,8 ko | **1,0 ko** |
| **Total** | 300,5 ko | **≈ 97,4 ko** |

> **Le budget JavaScript est désormais consommé presque entièrement par
> Livewire.** Il reste environ 2,6 ko compressés de marge. Toute bibliothèque
> JavaScript supplémentaire le ferait dépasser.

**Ce que coûte la CSP stricte, chiffré.** La variante compatible CSP est plus
lourde que la variante standard : **96,4 ko contre 82,9 ko compressés**, soit
**+13,5 ko** — environ 13 % du budget total. C'est le prix de D-026, et il
était jusqu'ici inconnu.

**Piège de mesure à connaître.** En développement (`APP_DEBUG=true`), Livewire
sert une variante **non minifiée de 695 ko**. Une mesure prise dans cet état
laisse croire à un dépassement massif du budget alors que la production sert
298 ko, compressés par Caddy. La distinction est faite par `config('app.debug')`
dans Livewire, et la mesure ci-dessus a été prise `APP_DEBUG=false`.

### Le CSS a baissé, et ce n'est pas une régression

10,25 → 4,6 ko compressés, parce que la page `welcome` de Laravel, qui portait
des centaines de classes utilitaires inutilisées, a été remplacée par la vraie
page d'accueil.

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

## 2. Premier affichage utile — MESURÉ au jalon 2, **RE-MESURÉ ET CORRIGÉ au jalon 4**

**Protocole.** Chromium piloté par Playwright, fenêtre de **360 px** (le point
de départ de la conception mobile-first), bridage réseau et processeur par le
protocole DevTools, cache vidé, processeur bridé d'un facteur 4 pour
représenter un mobile modeste. Le script est **versionné** :
[`bin/measure-fcp.mjs`](../bin/measure-fcp.mjs).

Les préréglages sont donnés par leurs **valeurs**, et non par leur nom :

| Préréglage | Débit descendant | Latence aller-retour |
|---|---:|---:|
| 3G lente | 400 kbit/s | **2 000 ms** |
| 3G rapide | 1,6 Mbit/s | **562,5 ms** |

### 2.1 Correction d'une mesure antérieure

> **Les chiffres du jalon 2 étaient mal étiquetés.** Ils annonçaient 1 344 ms
> pour l'accueil en « 3G lente ». La même page, re-mesurée au jalon 4, donne
> **4 580 ms** en 3G lente et **1 336 ms** en 3G rapide. L'écart de 8 ms avec
> l'ancienne valeur ne laisse guère de doute : la mesure du jalon 2 a été prise
> sous le préréglage **3G rapide**, sous une étiquette « 3G lente ».
>
> **Cause : le script de mesure n'avait pas été versionné.** Ses conditions
> exactes n'ont pas pu être reconstituées, seulement déduites. C'est la raison
> pour laquelle `bin/measure-fcp.mjs` fait désormais partie du dépôt. Une
> mesure non reproductible n'est pas une mesure.

### 2.2 Mesures du jalon 4

| Page | 3G lente (2 000 ms) | 3G rapide (562 ms) | Octets non compressés |
|---|---:|---:|---:|
| Accueil | 4 580 ms ❌ | 1 336 ms ✅ | 20,2 ko |
| Parcours Trouveur `/signalement` | 4 840 ms ❌ | 1 416 ms ✅ | 312 ko |
| **Parcours Propriétaire `/recherche`** | **4 812 ms** ❌ | **1 412 ms** ✅ | 312 ko |
| **Résultats `/recherche/resultats`** | **4 552 ms** ❌ | **1 308 ms** ✅ | 20,2 ko |

**Les pages du jalon 4 ne coûtent rien de plus que celles du jalon 3.** À
préréglage égal, l'écart entre le parcours Propriétaire et le parcours Trouveur
est de 28 ms — du bruit de mesure. Le jalon 4 n'a pas alourdi l'application.

### 2.3 Le budget de 2,5 s ne tient pas en 3G lente, et le poids n'y est pour rien

Le FCP suit la latence, pas les octets. Mesuré sur l'accueil (20,2 ko), à débit
constant de 400 kbit/s :

| Latence aller-retour | FCP mesuré |
|---:|---:|
| 2 000 ms | 4 576 ms |
| 1 000 ms | 2 584 ms |
| 200 ms | 1 000 ms |

Soit **FCP ≈ 2 × latence + 580 ms** : deux allers-retours bloquants avant la
première peinture — le document HTML, puis la feuille de style qu'il référence.

Conséquence arithmétique : sous le préréglage « 3G lente » de Chrome, un seul
aller-retour consomme déjà 2 000 ms des 2 500 ms du budget. **Aucune page ne
peut tenir**, quelle que soit son optimisation, tant que la feuille de style
est une ressource externe. Même en l'incorporant au document — ce qui
supprimerait le second aller-retour — il resterait environ 2 400 ms pour
l'accueil : tenu de justesse, et hors budget pour toute page plus lourde.

> **Ce n'est donc pas un problème de poids, et l'optimiser ne le résoudrait
> pas.** Les 312 ko du parcours Propriétaire coûtent 260 ms sur cette
> mesure — Livewire est chargé sans bloquer la peinture. Réduire le paquet
> améliorerait l'interactivité, pas le premier affichage.
>
> **Question ouverte Q-36 :** le §9.4 fixe « FCP < 2,5 s sur réseau contraint »
> sans dire quel réseau. Sous le préréglage 3G lente de Chrome, la cible est
> hors d'atteinte par construction ; sous 3G rapide, elle est tenue avec une
> marge confortable sur toutes les pages. Le budget a besoin d'une définition
> chiffrée du réseau de référence avant d'être déclaré tenu ou manqué.

### 2.4 Deux pièges de mesure, déjà payés

**Le cache.** Une première série donnait 100 ms de FCP en 3G lente : les
ressources venaient du cache, la connexion préalable ayant été faite sans
bridage. Toutes les valeurs ci-dessus sont prises **cache vidé**.

**Le mode débogage.** Avec `APP_DEBUG=true`, Livewire sert un paquet non
minifié de 695 ko : la mesure est fausse d'un facteur 7. Le script suppose un
serveur lancé avec `APP_DEBUG=false`.

> **Erreur corrigée dans la mesure elle-même.** La première version du script
> relevait un FCP nul et concluait pourtant « budget tenu » — en JavaScript,
> `null < 2500` vaut `true`. Un FCP absent est désormais traité comme un échec
> de mesure, jamais comme un succès. Une mesure qui ne peut pas échouer ne
> mesure rien.

**Le premier affichage n'est pas l'interactivité.** Le parcours Propriétaire
peint tôt parce que le rendu initial est fait côté serveur, mais ses champs ne
répondent qu'une fois Livewire chargé. Le budget mesuré ici est celui de la
§9.4 ; le délai avant interactivité mériterait un budget propre, que le cahier
des charges ne fixe pas.

**Deux limites de cette mesure :**
- elle est prise contre le serveur de développement de Laravel, **qui ne
  compresse pas** : la colonne des octets est pessimiste d'environ un facteur 3
  par rapport à FrankenPHP. Le FCP, dominé par la latence, en dépend peu ;
- elle n'a pas été prise contre l'image FrankenPHP elle-même — jalon 8.

## 3. Requêtes SQL par page — MESURÉ aux jalons 2 et 4

Budget : **≤ 15 requêtes**. Mesuré avec les pilotes de **production** (session
et cache en base), et non avec ceux de test — qui les placent en mémoire et
ramèneraient artificiellement le compte à zéro.

| Page | Requêtes | Mesurée au |
|---|---:|---|
| Accueil | **3** | jalon 2 |
| Inscription | **3** | jalon 2 |
| Connexion | **3** | jalon 2 |
| Vérification du téléphone | **3** | jalon 2 |
| Administration | **3** | jalon 2 |
| Galerie `/dev/ui` | **2** | jalon 2 |
| **Formulaire de recherche** | **4** | **jalon 4** |
| **Résultats de recherche** (6 recherches, 6 signalements) | **7** | **jalon 4** |
| **File de revue** (6 déclarations) | **9** | **jalon 4** |

> **Un N+1 trouvé par ce test, pas en production.** La première version de la
> page de résultats faisait deux requêtes PAR recherche affichée : **22 requêtes
> pour 6 recherches**. Le test charge la page avec plusieurs recherches
> précisément pour cela — mesurée avec une seule, elle serait passée en laissant
> le défaut intact. Le regroupement en deux requêtes globales ramène la page à
> 7, et la rend indépendante du nombre de recherches.
>
> La file de revue en coûte 9, dont une écriture : la consultation de la file
> est elle-même journalisée, puisqu'elle affiche des noms complets.

> **Les pages du jalon 2 en comptent une de plus qu'annoncé alors** (3 au lieu
> de 2). Le chiffre ci-dessus est celui re-mesuré au jalon 4 ; l'écart vient
> des couches ajoutées depuis, pas d'une erreur de mesure. Toutes restent très
> en deçà du budget.

Un test automatisé verrouille le budget sur chacun de ces écrans : la dérive
vers des dizaines de requêtes est progressive et ne se remarque qu'une fois
installée.

## 4. Reste non mesuré

| Budget (§9.4) | Cible | État |
|---|---|---|
| Upload reprenable, résilience réseau | — | **NON IMPLÉMENTÉ** — jalon 3 |
| FCP contre l'image FrankenPHP | < 2,5 s | **NON MESURÉ** — jalon 8 |
| Écrans de recherche et de signalement | < 2,5 s | **MESURÉ** — §2.2 |
| Définition chiffrée du « réseau contraint » | — | **OUVERTE** — Q-36 |
| Délai avant interactivité | — | **SANS BUDGET** — le §9.4 n'en fixe pas |

## 5. Performance de la base

Voir `DATABASE.md` §2. En résumé, sur 100 000 lignes : présélection par
trigramme 18,97 ms, par HMAC 0,03 ms, requête combinée 17,96 ms, toutes en
*index scan*.

## 6. Accessibilité — contrastes mesurés

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

### 6.1 Autres dispositions d'accessibilité en place

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

## 7. Galerie de composants

`/dev/ui`, **hors production uniquement** — la restriction est appliquée dans
la route, pas par convention de déploiement, et un test vérifie qu'elle renvoie
404 en production.

Elle présente la palette, les boutons, les badges de statut, les champs, les
alertes, les états vides, les squelettes de chargement, et une carte de
correspondance de niveau N1 montrant explicitement ce qui est masqué et
pourquoi.
