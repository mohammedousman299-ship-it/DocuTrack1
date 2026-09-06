# Performance et accessibilité — mesures

**Version :** 1.0 · **Date :** 2026-09-05 · **Jalon :** 1

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

## 2. Premier affichage utile — MESURÉ au jalon 2

**Protocole.** Page d'accueil, Chromium piloté par Playwright, fenêtre de
**360 px** (le point de départ de la conception mobile-first), bridage réseau et
processeur par le protocole DevTools, préréglages Chrome. Le processeur est
bridé d'un facteur 4 pour représenter un mobile modeste.

| Page | Condition | **FCP mesuré** | Poids | Verdict |
|---|---|---:|---:|---|
| Accueil | 3G lente + CPU ÷ 4 | **1 344 ms** | 28,7 ko | ✅ **TENU** |
| Accueil | 3G rapide + CPU ÷ 4 | **824 ms** | 26,4 ko | ✅ **TENU** |
| **Parcours Trouveur** | 3G lente + CPU ÷ 4 | **1 592 ms** | (voir §1) | ✅ **TENU** |

> **Le premier affichage n'est pas l'interactivité.** Le parcours Trouveur
> peint tôt parce que le rendu initial est fait côté serveur, mais ses boutons
> ne répondent qu'une fois Livewire chargé. En 3G lente, 96 ko compressés
> représentent environ deux secondes supplémentaires. Le budget mesuré ici est
> celui de la §9.4 ; le délai avant interactivité mériterait un budget propre,
> que le cahier des charges ne fixe pas.

**Deuxième piège de mesure.** Une première série donnait 100 ms de FCP en 3G
lente : les ressources venaient du cache, la connexion préalable ayant été
faite sans bridage. Les valeurs ci-dessus sont prises **cache vidé**.

> **Erreur corrigée dans la mesure elle-même.** La première version du script
> relevait un FCP nul et concluait pourtant « budget tenu » — en JavaScript,
> `null < 2500` vaut `true`. Un FCP absent est désormais traité comme un échec
> de mesure, jamais comme un succès. Une mesure qui ne peut pas échouer ne
> mesure rien.

**Limite de cette mesure :** elle a été prise contre le serveur de
développement de Laravel, non contre l'image FrankenPHP. Sur un réseau 3G, le
premier affichage est dominé par le transfert du HTML et du CSS, pas par le
temps de traitement PHP, mais l'écart reste à confirmer au jalon 8 contre le
conteneur réel.

## 3. Requêtes SQL par page — MESURÉ au jalon 2

Budget : **≤ 15 requêtes**. Mesuré avec les pilotes de **production** (session
et cache en base), et non avec ceux de test — qui les placent en mémoire et
ramèneraient artificiellement le compte à zéro.

| Page | Requêtes |
|---|---:|
| Accueil | **3** |
| Inscription | **2** |
| Connexion | **2** |
| Vérification du téléphone | **2** |
| Administration | **2** |
| Galerie `/dev/ui` | **2** |

Un test automatisé verrouille le budget sur chacun de ces écrans : la dérive
vers des dizaines de requêtes est progressive et ne se remarque qu'une fois
installée.

## 4. Reste non mesuré

| Budget (§9.4) | Cible | État |
|---|---|---|
| Upload reprenable, résilience réseau | — | **NON IMPLÉMENTÉ** — jalon 3 |
| FCP contre l'image FrankenPHP | < 2,5 s | **NON MESURÉ** — jalon 8 |
| Mesures sur les écrans de recherche et de signalement | — | jalons 3 et 4 |

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
