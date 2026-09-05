# Niveaux de divulgation

**Version :** 1.0 · **Date :** 2026-09-05

> **Règle architecturale non négociable.** La recherche ne se comporte jamais
> comme un moteur de recherche ouvert. Toute donnée issue de `found_reports` ou
> de `lost_declarations` traverse obligatoirement l'un des niveaux définis ici.

---

## 1. Les quatre niveaux

| Niveau | Contenu | Condition d'accès |
|---|---|---|
| **N0** | **Rien.** Aucun endpoint ne renvoie quoi que ce soit issu des tables de documents. | Utilisateur non authentifié |
| **N1 — Existence** | « Un document de type X, trouvé en *mois année* dans la région de Y, correspond peut-être à votre recherche. » | Compte authentifié **et vérifié par SMS**, ayant soumis une recherche satisfaisant la combinaison minimale de critères (§3) |
| **N2 — Confirmation** | Éléments permettant au Propriétaire de confirmer qu'il s'agit bien de son document, **sans lui révéler ce qu'il ne saurait pas déjà** | Vérification d'identité réussie (`PERMISSIONS.md`, preuve par la connaissance) |
| **N3 — Récupération** | Lieu et modalités de retrait auprès du dépositaire | Vérification d'identité réussie **et** paiement validé serveur-à-serveur — ou vérification seule si le module de paiement est désactivé (D-015) |

**Principe directeur de N2 :** l'échange est inversé. Ce n'est pas la
plateforme qui révèle des éléments au demandeur, c'est **le demandeur qui
prouve ce qu'il sait**. Le système se contente de confirmer ou d'infirmer, en
bloc (`THREAT_MODEL.md` M-03).

---

## 2. Table champ par champ

Source : un `found_report` (signalement de découverte) rapproché d'une
`lost_declaration` du demandeur.

| Champ | N1 | N2 | N3 | Mécanisme de masquage |
|---|---|---|---|---|
| Type de document | Libellé de catégorie | Idem | Idem | Aucun — donnée non identifiante |
| Nom porté sur le document | **Initiales seules** — `J. M.` | Prénom + initiale du nom — `Jean M.` | Complet | Calcul **serveur** sur les tokens du nom normalisé |
| **Numéro du document** | **∅** | 4 derniers caractères, **en confirmation d'une saisie de l'utilisateur** | Complet | Déchiffrement serveur, jamais transmis au-delà du niveau |
| Date de découverte | **Mois + année** | Date exacte | Date exacte | Troncature serveur à `YYYY-MM` |
| Lieu de découverte | **Région seule** | Ville | Point de retrait complet | Remontée serveur dans la hiérarchie géographique |
| Date de la perte déclarée | Non applicable — donnée du demandeur | — | — | — |
| Modalités et horaires de retrait | ∅ | ∅ | Complet | Absent du DTO |
| Référence de dépôt | ∅ | ∅ | Complet | Absent du DTO |
| Informations libres du Trouveur | ∅ | ∅ | Complet, **après revue** | Absent du DTO ; en N3, filtrage de motifs (M-11) |
| Identité du Trouveur | ∅ | ∅ | **∅ — jamais** | N'entre dans aucun DTO utilisateur |
| Coordonnées du Trouveur | ∅ | ∅ | **∅ — jamais** | N'entre dans aucun DTO utilisateur |
| **Image du document** | ∅ | ∅ | **∅ — jamais** | Réservée à l'Administrateur (D-006) |
| Score de correspondance | ∅ | ∅ | ∅ | N'entre dans aucun DTO utilisateur |
| Version de l'algorithme | ∅ | ∅ | ∅ | Idem |
| Champs ayant contribué au score | ∅ | ∅ | ∅ | Idem |
| Nombre de correspondances | « une » / « aucune » | — | — | **Jamais de compte > 1** |
| Identifiant technique du signalement | ∅ | Jeton opaque | Jeton opaque | Identifiant non séquentiel, non énumérable |

### 2.1 Trois écarts assumés par rapport au master prompt

**Écart 1 — Numéro totalement absent en N1** (D-005). La §4.2 autorisait les
derniers caractères. La combinaison « type + région + 4 derniers caractères »
constitue déjà un quasi-identifiant pour qui détient un fichier partiel. En N2,
les 4 derniers caractères ne sont affichés qu'**en confirmation de ce que
l'utilisateur vient lui-même de saisir** : on ne lui apprend donc rien.

**Écart 2 — Image jamais visible, même en N3** (D-006). La §4.2 posait la
question ; elle est tranchée dans le sens le plus strict. L'image est fournie
par un tiers sur une personne qui n'a pas consenti, elle contient plus que le
nécessaire, et sa seule fonction utile est la vérification humaine par
l'Administrateur. Le Propriétaire n'a besoin que de savoir **où aller**.

**Écart 3 — Aucun compte de résultats.** Un compteur de correspondances est un
signal d'énumération offert gratuitement (`THREAT_MODEL.md` M-07). En présence
de plusieurs candidats, seul le meilleur est présenté.

### 2.2 Pourquoi le masquage du nom a un sens

Objection légitime : si l'utilisateur a cherché « Jean Manga » et que le
système répond « J. M. », il n'apprend rien de nouveau — le masquage semble
inutile.

C'est précisément le comportement recherché. Le masquage ne protège pas contre
la recherche **légitime et ciblée**, qui porte sur un nom déjà connu du
demandeur. Il protège contre la recherche **large ou approximative** : un
attaquant qui balaye des noms voisins ne récupère pas les noms exacts portés
par les documents de la base. La règle générale : **une réponse ne doit jamais
apprendre au demandeur autre chose que la validité de ce qu'il a déjà avancé.**

---

## 3. Combinaison minimale de critères de recherche

Une recherche par nom seul est **refusée** : elle remonterait tous les
documents d'un homonyme, ce qui est un vecteur d'attaque direct (§4.2 du master
prompt, `THREAT_MODEL.md` M-07).

**Combinaisons acceptées** — au moins l'une des deux :

- **C1 :** type de document **+** numéro complet ;
- **C2 :** type de document **+** nom complet **+** un élément parmi : région
  de la perte, date approximative de la perte (mois), date de délivrance.

**Refusées :** nom seul ; type seul ; nom + type sans troisième critère ;
numéro sans type de document.

Cette combinaison est un paramètre de configuration, pas une constante de code,
afin d'être resserrée sans redéploiement si la détection d'abus le justifie.

---

## 4. Mécanique de masquage

### 4.1 Le masquage est un type de donnée, pas une transformation d'affichage

Le masquage **n'est pas** :
- une règle CSS ;
- un traitement JavaScript ;
- un `@if` dans un template Blade appliqué à un modèle complet ;
- un accesseur qui tronque une valeur déjà chargée dans un objet transmis au
  client.

Le masquage **est** : trois classes de transfert distinctes, construites côté
serveur, ne portant chacune que les champs de leur niveau.

```
app/Disclosure/
  FoundReportLevel1View.php   ← type, initiales, mois/année, région
  FoundReportLevel2View.php   ← + prénom + initiale, date exacte, ville
  FoundReportLevel3View.php   ← + nom complet, numéro, point de retrait
```

Chaque vue est produite par une **fabrique unique** qui prend en entrée
l'entité Eloquent et le niveau autorisé, ce dernier étant déterminé par une
Policy — jamais par un paramètre venu de la requête.

### 4.2 Le piège spécifique à Livewire

> **Toute propriété publique d'un composant Livewire est sérialisée et
> transmise au navigateur.** Un composant qui porte `public FoundReport $report`
> envoie au client **l'intégralité du modèle**, y compris les champs jamais
> rendus à l'écran.

C'est exactement l'erreur du prototype audité (`AUDIT_PROTOTYPE.md` V3), et
c'est le mode de fuite le plus probable de cette stack.

**Règles :**
1. Un composant Livewire ne porte **jamais** un modèle Eloquent en propriété
   publique. Il porte une vue de divulgation, ou des scalaires.
2. Toute propriété qui n'a pas à revenir du client est `#[Locked]`.
3. Aucune donnée sensible dans un attribut HTML, un `data-*`, un champ caché ou
   un commentaire de gabarit.
4. Les identifiants exposés sont des jetons opaques non séquentiels.

### 4.3 Journalisation

Chaque construction d'une vue de niveau N1, N2 ou N3 écrit une ligne dans
`disclosures` : acteur, ressource, niveau, horodatage, adresse IP, empreinte de
session, et la recherche ou revendication à l'origine. Cette table est la trace
de « qui a vu quoi, à quel niveau, quand ».

---

## 5. Stratégie de test — le test de non-fuite

C'est le test le plus important du projet. Il ne vérifie pas ce qui s'affiche,
il vérifie **ce qui ne sort pas**.

**Principe.** Pour chaque écran susceptible de rendre un niveau N1 ou N2, on
construit un fixture dont les valeurs sensibles sont des **sentinelles**
uniques et improbables. On exerce l'écran, puis on affirme que la réponse HTTP
**complète** — corps HTML rendu, charge utile Livewire, en-têtes, et toute
réponse JSON — ne contient **aucune** de ces sentinelles.

```
Fixture N1 :
  nom          = "ZZQXNAME-SENTINEL"
  numéro       = "ZZQXNUMBER-SENTINEL"
  ville        = "ZZQXCITY-SENTINEL"
  point retrait= "ZZQXPICKUP-SENTINEL"
  info libre   = "ZZQXFREE-SENTINEL"

Assertion : la réponse ne contient aucune de ces cinq chaînes.
Assertion : la réponse contient les initiales attendues.
```

**Ce test doit exister avant l'écran qu'il protège**, et un écran sans test de
non-fuite ne passe pas la revue.

Trois compléments :
- un test par Policy vérifiant explicitement **le refus** (`PERMISSIONS.md`) ;
- un test vérifiant qu'un jeton de niveau N2 ne donne pas accès à N3 en
  modifiant un paramètre de requête ;
- un test vérifiant que la charge utile Livewire sérialisée ne contient pas de
  modèle Eloquent complet.

---

## 6. Ce que voit l'Administrateur

L'Administrateur n'est pas un quatrième niveau de divulgation : il a un chemin
d'accès distinct, soumis à ses propres contrôles (D-014).

| Donnée | Administration fonctionnelle | Revue sensible |
|---|---|---|
| Utilisateurs, catégories, retours | Oui | Oui |
| Signalements et déclarations, champs non sensibles | Oui | Oui |
| Nom complet, numéro de document | Non | Oui, avec motif saisi |
| **Image du document** | Non | Oui, **floutée par défaut**, révélation unitaire avec motif saisi |
| Éléments de preuve fournis lors d'une revendication | Non | Oui, avec motif saisi |

Tout accès en colonne « revue sensible » écrit dans `disclosures` **et** dans
`audit_logs`, et toute génération d'URL signée vers une image est tracée
individuellement.
