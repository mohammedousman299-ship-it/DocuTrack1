# Moteur de rapprochement

**Version du document :** 1.0 · **Date :** 2026-09-05
**Version de l'algorithme décrite :** `v1`
**Mesures :** premières sondes en §3.6 — le protocole complet du §6 reste à exécuter

> **Avertissement.** Les seuils et pondérations de ce document sont des
> **valeurs de départ argumentées, pas des valeurs validées**. Un moteur de
> rapprochement sans métriques est un moteur non testé. La §6 définit le
> protocole de mesure ; les résultats réels seront ajoutés à ce fichier au
> jalon 5, et les seuils ajustés en conséquence.

---

## 1. Principe et vocabulaire

Le moteur compare les déclarations de perte (`lost_declarations`) et les
signalements de découverte (`found_reports`), et produit des **correspondances
possibles**.

> **Un rapprochement n'est jamais une certitude.** L'interface ne dit jamais
> « votre document a été trouvé », mais « une correspondance possible a été
> identifiée ». Ce n'est pas une nuance de rédaction : c'est une exigence de
> conception, qui découle du fait mathématique qu'avec des homonymes et des
> fautes de saisie, les faux positifs sont inévitables.

**Deux déclencheurs**, tous deux via la file d'attente, jamais en synchrone
dans une requête HTTP :
1. enregistrement d'un nouveau signalement → confrontation aux déclarations
   actives ;
2. enregistrement d'une nouvelle déclaration → confrontation aux signalements
   actifs.

---

## 2. Normalisation

La normalisation est implémentée **deux fois** : une fonction PostgreSQL
`IMMUTABLE` (pour les index et les requêtes) et un miroir PHP (pour la
validation et les tests). Les deux implémentations sont couvertes par un test
d'équivalence sur un jeu de cas partagé — une divergence entre elles produirait
des correspondances irreproductibles.

### 2.1 Noms

> Les noms figurant en exemple dans cette documentation sont **synthétiques**,
> produits par combinaison de syllabes inventées. Aucun nom de personne réelle
> n'est utilisé, y compris à titre d'illustration (§12 du master prompt).


```
unaccent              « Ölanda »        → « Olanda »
minuscules            « OLANDA »        → « olanda »
apostrophes SUPPRIMÉES« Ol'anda »       → « olanda »      (un seul token)
autres signes SÉPARENT « Miro-Ayo »     → « ayo miro »    (deux tokens)
espaces               « miro  olanda »  → « miro olanda »
TRI DES TOKENS        « olanda miro »   → « miro olanda »
```

**Apostrophe et tiret ne sont pas traités de la même façon**, et la distinction
est délibérée :

- l'**apostrophe est supprimée sans découper** — elle est interne au nom
  (`Ol'anda` est un seul token, pas deux) ; sont concernées l'apostrophe
  droite, l'apostrophe courbe `U+2019`, la lettre modificative `U+02BC` et
  l'accent grave, tous rencontrés selon les claviers ;
- le **tiret sépare**, comme tout autre signe : un nom composé doit ainsi se
  rapprocher de sa graphie sans tiret, `Miro-Ayo Olanda` et `Miro Ayo Olanda`
  donnant le même résultat.

L'ordre nom/prénom est fréquemment inversé au Cameroun, selon que l'on remplit
un formulaire administratif ou qu'on se présente oralement.

> **Correction apportée après mesure (2026-09-05).** La version initiale de ce
> document présentait le tri des tokens comme « le point clé » de la
> neutralisation de l'inversion. **C'est faux pour le chemin trigramme :**
> `pg_trgm` découpe la chaîne en mots et compare des *ensembles* de trigrammes,
> l'ordre des mots ne l'affecte donc pas. Mesuré :
> `similarity('miro olanda', 'olanda miro') = 1` — sans aucun tri.

Le tri des tokens reste néanmoins nécessaire, mais pour d'autres usages, où la
comparaison se fait par **égalité** et non par similarité :

- l'empreinte de doublon `duplicate_fingerprint` (`DATA_MODEL.md` §4) ;
- le contrôle de cohérence entre le nom du compte et le nom déclaré
  (`THREAT_MODEL.md` M-02) ;
- la reproductibilité du miroir PHP, qui n'a pas de `pg_trgm`.

Alternative écartée : deviner quel token est le patronyme (heuristique fragile,
qui échoue sur les noms composés et sur les prénoms qui sont aussi des
patronymes).

Effet de bord accepté : deux personnes portant les mêmes tokens dans un ordre
différent deviennent indistinguables par empreinte. C'est le comportement
voulu — ce sont très probablement la même personne.

### 2.2 Numéros

```
1. retrait de tout caractère non [A-Za-z0-9]
2. PUIS mise en majuscules
3. homoglyphes : O → 0   et   I → 1
```

**L'ordre des deux premières étapes est important**, et l'inverse était un
défaut de la conception initiale. `upper()` dépend de la locale PostgreSQL :
selon la collation, `upper('ß')` vaut `'ß'` ou `'SS'`. En filtrant d'abord, il
ne reste que de l'ASCII au moment de changer la casse, et le résultat devient
indépendant de la locale — donc reproductible entre SQL et PHP, et entre deux
installations.

> **Ce défaut a été trouvé par le test d'équivalence**, pas par relecture : la
> chaîne `ßeta9` donnait `ETA9` en SQL et `SSETA9` en PHP. C'est la
> justification concrète de la double implémentation décrite ci-dessus.

**Seulement ces deux substitutions.** `S↔5` et `B↔8` ont été écartés : ils
génèrent des collisions réelles entre numéros distincts. `O/0` et `I/1` sont
les confusions dominantes en saisie manuelle et en lecture de document usé.

Le résultat n'est **jamais stocké en clair** : il alimente le calcul de
`number_hmac` puis est écarté de la mémoire (D-007).

> **Incertitude signalée.** Les formats réels des numéros de CNI, passeport et
> permis de conduire camerounais ne sont pas connus avec certitude. Aucune
> expression régulière de validation n'est inventée. La validation reste
> permissive (longueur et alphabet) jusqu'à confirmation — `OPEN_QUESTIONS.md`
> Q-07.

---

## 3. Score

### 3.1 Champs comparables

Trois composantes, chacune **comparable** seulement si les deux côtés
renseignent le champ :

| Composante | Poids | Valeur |
|---|---:|---|
| `N` — numéro | 0,60 | `1` si `number_hmac` identiques, `0` sinon |
| `P` — nom | 0,35 | **Score composite** — voir §3.1.1 |
| `G` — géographie | 0,05 | `1` si même région, `0` sinon |

#### 3.1.1 Le score de nom est composite, pas purement trigramme

La conception initiale retenait `similarity()` seul. **Les mesures du jalon 1
montrent que c'est insuffisant** (§3.6).

```
P = max(
      similarity(a, b),                              -- ensembles de trigrammes
      1 - levenshtein(a, b) / greatest(len(a), len(b))  -- distance d'édition
    )
```

Justification : le trigramme sous-évalue lourdement la faute de frappe d'un
seul caractère, qui est **l'erreur la plus fréquente**. La distance d'édition
la capte exactement. Prendre le maximum des deux conserve les qualités de
chacun : le trigramme reste insensible à l'ordre des mots et robuste aux
tokens manquants, la distance d'édition rattrape les fautes courtes.

`levenshtein()` provient de `fuzzystrmatch`, dont la disponibilité devient donc
**requise** — voir §7, où cette extension était initialement classée
facultative.

**Risque connu, à mesurer au jalon 5 :** sur des noms courts, la distance
d'édition normalisée est généreuse — deux noms brefs réellement différents
peuvent obtenir un score élevé. Une longueur minimale, ou une pondération par
la longueur, sera peut-être nécessaire.

#### 3.1.2 Troisième terme : inclusion de tokens — AJOUTÉ après mesure au jalon 5

La formule à deux termes classait un **inconnu au-dessus d'une vraie
correspondance**. Mesuré : la famille `compound_name` — même personne, un côté
omettant un prénom — obtenait 0,750 de score moyen, tandis que
`near_name_different_person` — deux personnes différentes aux noms proches —
obtenait 0,821.

La cause est mécanique : la disparition d'un token entier coûte beaucoup au
trigramme comme à la distance d'édition, bien plus que deux ou trois caractères
modifiés. Or omettre un prénom est banal, et désigne la même personne.

```
P = max(
      similarity(a, b),
      1 - levenshtein(a, b) / greatest(len(a), len(b)),   -- si len ≥ 8
      containment(a, b)                                   -- 0 ou 1
    )

containment = 1 si TOUS les tokens du nom le plus court figurent exactement
              dans le plus long ET qu'ils sont au moins DEUX ; 0 sinon.
```

**Le seuil de deux tokens n'est pas décoratif.** Un seul token partagé est le
cas banal du patronyme commun — deux personnes différentes, précisément ce que
la famille `shared_token_different_person` éprouve. Deux tokens exactement
identiques sont une coïncidence bien plus improbable. Le seuil rejoint celui de
`NameConsistencyCheck`, qui tranche la même question sur le nom du compte.

**Effet mesuré** (2 000 paires, graine 20260907) : à seuil 0,90, le rappel
passe de 0,679 à 0,799 **sans un seul faux positif supplémentaire** — le nombre
de faux positifs est identique à tous les seuils balayés. `compound_name` passe
de 0/160 à 160/160 notifiées.

> **Cette absence de faux positifs supplémentaires tient au jeu de mesure**, où
> aucune famille négative ne partage deux tokens exactement identiques sans
> être déjà écartée par ailleurs. Une population réelle comportant beaucoup de
> paires « même patronyme ET même prénom, personnes différentes » remettrait ce
> résultat en cause. C'est une propriété mesurée, pas démontrée.

#### 3.1.3 Plancher de longueur pour la distance d'édition — MESURÉ

Le §3.1.1 signalait un risque sans le chiffrer : sur des noms courts, la
distance d'édition normalisée est généreuse. Sur un nom de 4 caractères, une
seule lettre d'écart donne 0,75.

Mesuré sur la famille `short_name_different_person` (60 paires, personnes
différentes portant un nom court d'un seul token) :

| Plancher | Score moyen | Notifiées à 0,85 | Routées en revue |
|---|---:|---:|---:|
| Aucun | 0,722 | 1 | 58 / 60 |
| **8 caractères** | **0,397** | **0** | **10 / 60** |

**Le gain porte surtout sur la file de revue**, pas sur la précision, qui ne
bouge que de 0,9301 à 0,9309 à ce taux de base. Le plancher évite à 48 paires
sur 60 d'occuper inutilement un relecteur. Il est retenu pour cette raison, et
non pour une amélioration de précision qu'il n'apporte pas.

### 3.2 Formule

```
Comparables = { composantes dont les deux côtés sont renseignés }

Base  = Σ (poids_i × valeur_i)  /  Σ (poids_i)      pour i ∈ Comparables
Score = Base × T
```

La **renormalisation sur les seuls champs comparables** est indispensable :
sans elle, un couple sans numéro des deux côtés ne pourrait jamais dépasser
0,40 et ne serait jamais notifié, alors que l'absence de numéro est un cas
courant et légitime (le Trouveur ne l'a pas relevé, le Propriétaire ne le
connaît pas).

Un champ absent est donc **neutre**, jamais pénalisant.

### 3.3 Facteur temporel `T`

Multiplicatif, et non additif : une découverte **antérieure** à la perte
déclarée est physiquement impossible et doit **annuler** la correspondance, pas
la décoter.

| Situation | `T` |
|---|---:|
| `found_on < lost_on − 7 jours` | **0** — annulation |
| Écart dans la tolérance de 7 jours | 1,0 |
| 0 ≤ écart ≤ 180 jours | 1,0 |
| 180 < écart ≤ 365 jours | 0,9 |
| écart > 365 jours | 0,8 |
| Une des deux dates absente | 1,0 (neutre) |

La tolérance de 7 jours existe parce que la date de perte est souvent
approximative : on constate la disparition d'un document plusieurs jours après
l'avoir perdu.

### 3.4 Seuils initiaux

| Intervalle | Traitement |
|---|---|
| `Score ≥ 0,75` | **Notification** au Propriétaire |
| `0,55 ≤ Score < 0,75` | **File de revue administrateur** |
| `Score < 0,55` | Ignoré, aucune trace exposée |

Stockés dans `matching_settings`, **modifiables sans redéploiement** (§5 du
master prompt).

### 3.5 Conséquence directe de D-007, à connaître

Lorsque les deux côtés renseignent un numéro et que ces numéros **diffèrent**,
la composante `N` vaut 0 et le score plafonne mécaniquement à
`(0,35 + 0,05) / 1,00 = 0,40` — sous le seuil de revue.

C'est correct dans le cas général : deux numéros différents désignent deux
documents différents. Mais **comme la distance d'édition sur les numéros a été
abandonnée (D-007), une simple faute de frappe dans le numéro suffit désormais
à faire disparaître une correspondance parfaitement valide.**

Deux compensations :

1. **Signal de faute de frappe probable.** Si les numéros diffèrent mais que le
   score de nom `P` est ≥ 0,85 et que la cohérence temporelle est satisfaite,
   le couple est **quand même routé en file de revue administrateur**, avec
   l'indicateur `possible_number_typo`. Son score reste bas ; c'est le drapeau,
   pas le score, qui déclenche la revue.
   *(Seuil abaissé de 0,90 à 0,85 après mesure — voir §3.6.)*
2. **Conséquence d'interface, à porter au jalon 4.** Le formulaire doit inviter
   explicitement l'utilisateur à **laisser le champ numéro vide s'il n'en est
   pas certain** — un numéro absent est neutre, un numéro faux est
   éliminatoire. C'est contre-intuitif pour l'utilisateur et cela doit donc
   être écrit dans l'interface, pas supposé.

### 3.6 Premières mesures réelles — PostgreSQL 16 local, 2026-09-05

Premières valeurs **mesurées** du projet. Elles portent sur des noms
synthétiques et sur PostgreSQL 16 local, non sur Supabase.

| Cas | `similarity()` | `levenshtein` | `P` composite |
|---|---:|---:|---:|
| Identique | 1,000 | 0 | 1,000 |
| **Faute d'1 caractère** | **0,667** | 1 | **0,917** |
| Faute d'1 caractère (variante) | 0,714 | 1 | 0,917 |
| Faute de 2 caractères | 0,471 | 2 | 0,846 |
| Nom composé 3 tokens, 1 faute | 0,737 | 1 | 0,933 |
| **Tokens inversés** | **1,000** | — | **1,000** |
| Prénom seul contre nom complet | 0,417 | — | 0,417 |
| Personnes différentes | 0,000 | — | ≈ 0,17 |

**Trois enseignements, dont deux invalident la conception initiale :**

1. **L'inversion des tokens était déjà neutralisée** par `pg_trgm` seul
   (similarité = 1). La justification du tri était fausse — corrigée en §2.1.
2. **Le trigramme seul échoue sur la faute d'un caractère** : 0,667. Si le nom
   est le **seul** champ comparable — cas fréquent, aucun numéro des deux
   côtés — le score final vaut 0,667, **sous le seuil de notification de
   0,75**. Une correspondance parfaitement valide n'aurait pas été notifiée.
   D'où le score composite du §3.1.1, qui porte ce cas à 0,917.
3. **Le seuil de 0,90 pour `possible_number_typo` était inatteignable** avec le
   trigramme seul. Abaissé à 0,85 sur le score composite.

**Ce que ces mesures ne disent pas.** Elles portent sur une poignée de cas
choisis, pas sur un jeu étiqueté. Elles suffisent à **invalider** une
conception, jamais à en **valider** une : elles ne remplacent pas le protocole
du §6. Le point 2 en particulier suggère que les seuils 0,75 / 0,55 sont peut-
être trop élevés, mais cela reste à établir par le balayage complet.

---

### 3.7 Calibrage des seuils — MESURÉ au jalon 5

**Protocole.** 2 000 paires étiquetées, graine 20260907, générateur versionné
(`app/Matching/Synthetic/`), commande `php artisan matching:calibrate`. Les
numéros passent par le HMAC de D-007 comme en production : comparer ici des
numéros en clair mesurerait un moteur qui n'existe pas. Scoring : 199 ms.

| Seuil | Précision | Rappel | **Couverture** | F1 | Faux + | En revue |
|---|---:|---:|---:|---:|---:|---:|
| 0,55 | 0,7874 | 0,9139 | 0,9959 | 0,8459 | 301 | 300 |
| 0,70 | 0,8568 | 0,9074 | 0,9959 | 0,8814 | 185 | 424 |
| 0,75 | 0,8668 | 0,9066 | 0,9959 | 0,8863 | 170 | 440 |
| 0,80 | 0,8955 | 0,9057 | 0,9959 | 0,9006 | 129 | 482 |
| 0,85 | 0,9309 | 0,8828 | 0,9959 | **0,9062** | 80 | 559 |
| **0,90** | **0,9839** | 0,7992 | 0,9959 | 0,8820 | **16** | 725 |
| 0,95 | 1,0000 | 0,6893 | 0,9959 | 0,8161 | 0 | 875 |

### 3.7.1 Le rappel dramatise le choix ; la couverture le décrit

**La couverture vaut 0,9959 à tous les seuils de 0,55 à 0,95.** Sur 1 220
vraies correspondances, 5 seulement échappent à la fois à la notification et à
la file de revue — et ce nombre ne bouge pas quand le seuil monte.

Relever le seuil ne fait donc **pas perdre de correspondances** : il déplace du
travail de la notification automatique vers la revue humaine. Le compromis réel
n'est pas « précision contre correspondances manquées » mais **« précision
contre charge de revue »**.

Ce constat n'a de valeur que si la file est réellement traitée. Une file
ingérable transforme la couverture de 0,9959 en simple intention (Q-27).

### 3.7.2 D'où viennent les faux positifs

À 0,90, les **16 faux positifs proviennent tous** de
`near_name_different_person`. Toutes les autres familles négatives en
produisent **zéro** :

| Famille négative | Score moyen | Faux positifs à 0,90 |
|---|---:|---:|
| `temporal_inconsistency` | 0,000 | 0 |
| `neighbouring_numbers` | 0,069 | 0 |
| `strict_homonym` | 0,357 | 0 |
| `short_name_different_person` | 0,397 | 0 |
| `shared_token_different_person` | 0,562 | 0 |
| **`near_name_different_person`** | **0,820** | **16** |

> **Limite de la mesure, à connaître avant d'utiliser le chiffre de précision.**
> La précision dépend presque entièrement de la part de
> `near_name_different_person` dans le jeu — 9 %, une valeur que **j'ai
> choisie** et dont je n'ai aucun moyen de savoir si elle correspond à la
> population réelle. Deux personnes différentes aux noms proches à 2-3
> caractères près sont, dans les faits, indiscernables d'une faute de frappe.
> Si ce cas est plus rare en réalité, la précision est meilleure que mesurée ;
> s'il est plus fréquent, elle est pire. Le chiffre 0,9839 est **conditionnel à
> cette hypothèse**, et ne doit pas être cité sans elle.

### 3.7.3 Composition de la file de revue — la vraie facture de D-007

| Seuil | Routées par le **drapeau** `possible_number_typo` | Routées par le **score** |
|---|---:|---:|
| 0,85 | **300** | 259 |
| 0,90 | **300** | 425 |

**300 éléments, invariants avec le seuil, viennent du seul drapeau de faute de
frappe sur le numéro** — 54 % de la file à 0,85. Ce sont 200 homonymes stricts
(personnes différentes, même nom, numéros différents) et 100 vraies fautes de
frappe. **Le drapeau ne peut pas les distinguer** : sous HMAC, « même nom,
numéros différents » ne dit rien de plus.

Autrement dit : **pour rattraper une vraie faute de frappe, le relecteur en
examine deux qui n'en sont pas.** D-007 ne coûte donc pas seulement du rappel
(§3.5), il coûte de la capacité de revue humaine — un coût qui n'avait pas été
anticipé au jalon 0 et qui pèse directement sur Q-27.

## 4. Exécution

### 4.1 Présélection indexée

L'évaluation complète du score ne peut pas être faite sur toute la table. La
présélection réduit l'espace de recherche par index :

```
1. Filtre dur   : même document_type_id, statut actif      → index B-tree
2. Candidats    : number_hmac identique                    → index B-tree
                  OU owner_name_normalized % $1            → index GIN trgm
3. Score complet sur les candidats retenus uniquement
```

`pg_trgm.similarity_threshold` est réglé bas pour la présélection (rappel
large), le tri fin étant fait par le score.

> **Critère de validation au jalon 1 :** `EXPLAIN (ANALYZE, BUFFERS)` sur
> 100 000 lignes synthétiques doit montrer un *index scan* sur les deux
> chemins. Un *seq scan* invalide la conception et impose de la revoir avant
> de continuer.

### 4.2 Traitement par lots

Pas de worker permanent sur Vercel (§3.1). Le rapprochement s'exécute par
`POST /internal/cron/match` :

- verrou `Cache::lock` pour empêcher deux exécutions concurrentes ;
- curseur `matched_up_to` pour la reprise ;
- budget de temps borné (~20 s) : le lot s'arrête proprement et reprend au
  passage suivant ;
- idempotent : rejouable sans effet de bord.

### 4.3 Idempotence

`UNIQUE (lost_declaration_id, found_report_id, algorithm_version)` sur `matches`,
alimentée par un *upsert*. Un même couple ne produit qu'une correspondance,
quel que soit le nombre de passages du cron.

La notification est portée par une ligne `notifications` avec une clé
d'idempotence **dérivée du match**, jamais du passage de cron. Conséquence :
même en cas de rejeu, le Propriétaire n'est notifié qu'une fois.

### 4.4 Traçabilité

Chaque `match` enregistre `algorithm_version`, `score` et `score_breakdown`
(contribution de chaque composante). Sans ces trois éléments, il est impossible
de régler un seuil ou d'expliquer un faux positif après coup.

Un changement de pondération ou de normalisation **incrémente
`algorithm_version`**. Les correspondances produites par des versions
différentes coexistent sans se contredire.

---

## 5. Ce qui n'est jamais exposé

- Le **score** — c'est un oracle de matching : il informerait un attaquant sur
  la qualité de son approximation et lui permettrait de converger.
- Le `score_breakdown`, pour la même raison.
- La `algorithm_version`.
- Le nombre de correspondances au-delà de « une » ou « aucune ».
- **Tout retour de rapprochement au Trouveur** : ni statut, ni compteur, ni
  notification (`THREAT_MODEL.md` M-06).

---

## 6. Protocole de validation

### 6.1 Jeu de données synthétique

**Entièrement fictif.** Aucune donnée réelle de personne n'entre dans le
dépôt, les tests, les captures d'écran ou les jeux de démonstration (§12 du
master prompt).

Les noms sont produits par **combinaison algorithmique de syllabes inventées**,
avec une structure morphologique plausible pour la région, et une graine
déterministe pour la reproductibilité. **Aucun nom de personnalité publique,
même à titre d'exemple** — c'est précisément l'erreur relevée dans le prototype
(`AUDIT_PROTOTYPE.md` V1). Les numéros sont générés dans l'espace des formats
plausibles, sans prétendre reproduire un format officiel réel.

Cible : **~2 000 paires étiquetées** (`match` / `no_match`).

> **Le tableau ci-dessous est celui du jalon 0. Il a été RÉVISÉ au jalon 5** —
> voir `app/Matching/Synthetic/DatasetGenerator.php`, dont l'en-tête porte la
> répartition en vigueur. Ses trois familles négatives portaient toutes un
> numéro **des deux côtés**, ce qui plafonne le score à 0,40 par arithmétique
> (§3.5) : aucun faux positif n'était possible et la précision mesurée valait
> 1,0000 sans rien mesurer. Trois familles négatives **sans numéro** ont été
> ajoutées — `shared_token_different_person`, `near_name_different_person`,
> `short_name_different_person` — car c'est le seul cas où le nom porte seul la
> décision, et donc le seul où le moteur peut réellement se tromper.

| Famille | Part visée | Ce qu'elle teste |
|---|---:|---|
| Correspondance franche (numéro + nom exacts) | 15 % | Non-régression |
| Faute de frappe sur le nom (1-2 caractères) | 20 % | Seuil trigramme |
| Nom inversé (prénom/patronyme permutés) | 10 % | Tri des tokens |
| Nom composé, 3-4 tokens | 10 % | Robustesse du tri |
| **Homonymes stricts, documents distincts** | 15 % | **Faux positifs** |
| Numéro absent d'un côté | 10 % | Renormalisation §3.2 |
| Numéro absent des deux côtés | 5 % | Cas limite : le nom porte tout |
| **Numéros voisins à un chiffre près** | 5 % | **Ne doivent PAS se rapprocher** |
| Faute de frappe sur le numéro + nom identique | 5 % | Coût réel de D-007 (§3.5) |
| Incohérence temporelle | 3 % | Facteur `T` = 0 |
| Doublons de signalement | 2 % | Empreinte de doublon |

### 6.2 Mesures

Balayage du seuil de notification de **0,40 à 0,95 par pas de 0,05**, en
produisant pour chaque valeur :

- précision, rappel, F1 ;
- taux de faux positifs et de faux négatifs ;
- courbe précision-rappel ;
- volume routé en revue administrateur (charge de travail humaine induite).

### 6.3 Critères d'acceptation proposés

| Métrique | Cible | Pourquoi |
|---|---|---|
| **Précision au seuil de notification** | **≥ 0,98** | Un faux positif coûte un litige de paiement, une divulgation N1 injustifiée et la confiance de l'utilisateur |
| Rappel | ≥ 0,85 | Rattrapé partiellement par la file de revue |
| Volume en revue | soutenable pour 2-3 administrateurs | Une file ingérable équivaut à une absence de revue |

> **Asymétrie assumée : on préfère rater une correspondance que d'en
> inventer une.** Un faux négatif laisse l'utilisateur dans la situation où il
> se trouvait déjà. Un faux positif l'expose, le fait payer pour rien et
> divulgue de l'information sur un tiers.

Ces critères sont **proposés** et doivent être confirmés au vu des premières
mesures : si la précision de 0,98 impose un rappel de 0,40, l'arbitrage est à
reprendre ensemble.

### 6.4 Ce qui reste à mesurer

Produit au jalon 5 :

- [x] Génération du jeu synthétique et vérification de sa distribution —
      `DatasetGeneratorTest`, 8 contrôles portant sur le jeu lui-même
- [x] `EXPLAIN` sur 100 000 lignes — fait au jalon 1, `DATABASE.md` §2
- [x] Balayage des seuils, tableau de résultats — §3.7
- [x] Décision finale sur les seuils — **D-041**, seuil porté à 0,90
- [x] Coût réel de D-007 en rappel : **0 correspondance notifiée sur 100** dans
      la famille « faute de frappe sur le numéro » ; les 100 partent en revue.
      Second coût découvert : **300 éléments de file de revue** pour 100 vraies
      fautes rattrapées (§3.7.3, D-042)
- [x] Temps d'exécution d'un lot : **289 ms pour 50 demandes** contre 200
      signalements, soit **5,8 ms par demande** (`MatchingBatchTest`)

Reste ouvert :

- [ ] Fréquence du cron : le temps par demande est mesuré, mais le volume
      attendu ne l'est pas — l'intervalle ne peut pas s'en déduire seul
- [ ] Réglage du seuil de **revue** (0,55), que le balayage n'a pas éprouvé :
      faute d'un critère pour juger de la BONNE quantité d'éléments en file, le
      régler demande d'abord de répondre à Q-27

---

## 7. Dépendances techniques à vérifier

Le moteur repose sur des extensions PostgreSQL dont la disponibilité sur
Supabase **n'a pas été vérifiée** :

| Extension | Usage | Criticité |
|---|---|---|
| `unaccent` | Normalisation des noms | **Bloquante** |
| `pg_trgm` | Similarité et index GIN | **Bloquante** |
| `fuzzystrmatch` | `levenshtein()` dans le score composite de nom (§3.1.1) | **Bloquante** — reclassée après mesure |

**Vérifié en local le 2026-09-05** sur PostgreSQL 16.13 : `unaccent`,
`pg_trgm`, `pgcrypto` et `fuzzystrmatch` s'installent et fonctionnent
(`CREATE EXTENSION` réussi, `unaccent()`, `similarity()` et `levenshtein()`
exécutés). **Cela ne préjuge en rien de leur disponibilité sur Supabase**, qui
reste non testée (`OPEN_QUESTIONS.md` Q-04).

**Si `pg_trgm` ou `unaccent` sont indisponibles, la conception de ce document
tombe** et doit être reprise : ce serait une remontée immédiate, pas un
contournement silencieux.
