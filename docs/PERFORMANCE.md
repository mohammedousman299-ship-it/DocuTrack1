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

## 2. Premier affichage utile — MESURÉ au jalon 2

**Protocole.** Page d'accueil, Chromium piloté par Playwright, fenêtre de
**360 px** (le point de départ de la conception mobile-first), bridage réseau et
processeur par le protocole DevTools, préréglages Chrome. Le processeur est
bridé d'un facteur 4 pour représenter un mobile modeste.

| Condition | **FCP mesuré** | Budget | Verdict |
|---|---:|---:|---|
| **3G lente** (400 kb/s, 400 ms de latence) + CPU ÷ 4 | **1 416 ms** | 2 500 ms | ✅ **TENU** |
| **3G rapide** (1,6 Mb/s, 300 ms de latence) + CPU ÷ 4 | **824 ms** | 2 500 ms | ✅ **TENU** |

Poids transféré : **26,4 ko** pour la page complète.

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
