# Frais de service — arbitrage

**Version :** 1.0 · **Date :** 2026-09-05
**Statut :** partiellement tranché. La question juridique reste **ouverte** et
bloque le jalon 6.

La §1.7 du cahier des charges prévoit que l'information complète de récupération
soit accessible après paiement de frais de service. La §6 du master prompt pose
trois questions à trancher avant d'écrire la moindre ligne de code de paiement.
Ce document y répond en séparant explicitement ce qui relève de la technique, ce
qui relève du porteur du projet, et ce qui relève d'un conseil juridique.

---

## 1. Question juridique — **NON TRANCHÉE**

> Est-il licite, au Cameroun, de conditionner à un paiement l'accès à
> l'information permettant de récupérer son propre document officiel ? Et la
> législation impose-t-elle par ailleurs qu'un document d'identité trouvé soit
> remis à une autorité ?

**Je ne connais pas ces textes avec certitude et je n'en inventerai pas la
référence.** Cette phrase n'est pas une précaution de style : c'est la réponse
elle-même. Rien dans ce dépôt ne doit laisser croire qu'une vérification
juridique a eu lieu.

Ce qui est **inconnu** et doit être vérifié auprès d'un conseil :

- l'existence et le contenu d'une obligation de remise d'un document d'identité
  trouvé à une autorité (police, mairie, service émetteur) ;
- la licéité de conditionner à un paiement l'accès à une information permettant
  de récupérer sa propre pièce d'identité ;
- le régime applicable à la protection des données personnelles, et notamment
  le traitement de données d'identité concernant une personne **qui n'a pas
  consenti** — le cas du Trouveur qui photographie la pièce d'un tiers ;
- les obligations de déclaration, de conservation et de sécurité qui en
  découlent ;
- le régime applicable à l'encaissement de frais par une plateforme en ligne.

Ce qui est **cru sans être confirmé**, et donc inutilisable en l'état : il
existe probablement une législation camerounaise relative à la cybersécurité et
à la cybercriminalité, souvent datée de 2010. **Ni son numéro, ni sa date, ni
son contenu ne peuvent être confirmés ici.** Cette mention figure uniquement
pour orienter la recherche du conseil, jamais comme référence utilisable.

Les questions sont formulées pour être posées telles quelles dans
`COMPLIANCE_OPEN_QUESTIONS.md`.

### 1.1 Appréciation non juridique, à titre d'avis de conception

Conditionner à un paiement l'accès à l'information permettant de récupérer **sa
propre pièce d'identité** est le point le plus fragile du projet, juridiquement
et en réputation. Si un article de presse doit nuire à DocuTrack, c'est
celui-là. Cette appréciation n'a aucune valeur juridique ; elle justifie
seulement la décision D-015 ci-dessous.

**Séquencement favorable :** cette question bloque le **jalon 6**, pas le jalon
1. Le socle technique, le signalement, la déclaration, la recherche et le
rapprochement se construisent sans qu'elle soit tranchée.

---

## 2. Question de conception — **TRANCHÉE** (D-009)

> Que contient exactement l'information de niveau N3 ?

**Décision : N3 est le lieu et les modalités de retrait auprès d'un dépositaire
tiers** — poste de police, mairie, structure partenaire. **L'identité et les
coordonnées du Trouveur ne sont jamais communiquées, à aucun niveau.**

**Alternative écartée : le contact direct du Trouveur.** Elle n'a aucune
dépendance opérationnelle et serait livrable immédiatement. Elle est écartée
parce qu'elle exposerait un bénévole à un inconnu qui vient de payer pour
obtenir ses coordonnées. Elle crée mécaniquement un marché de la rançon, place
la plateforme en position d'avoir organisé la rencontre, et la laisse sans
aucune maîtrise de ce qui s'y passe.

**Alternative écartée : la messagerie interne anonymisée.** Techniquement
séduisante et sans dépendance partenaire, mais elle exige un module de
modération complet, et rien n'empêche les deux parties d'échanger des numéros
en clair dans les messages — ce qui ramène au cas précédent en ayant coûté un
module.

**Observation.** Le prototype audité avait déjà retenu ce modèle : le champ
`depositPoint` contient un poste de police. La maquette avait tranché avant que
la spécification ne pose la question.

### 2.1 La dépendance opérationnelle, énoncée sans atténuation

Ce modèle exige des **points de dépôt partenaires réels**. **Il n'en existe
aucun à ce jour.**

Le problème est plus profond qu'une table à remplir. Si le Trouveur **conserve**
le document au lieu de le déposer quelque part, il n'existe aucun tiers, et N3
ne peut rien contenir d'autre que « le Trouveur le détient » — ce que la
décision interdit précisément de révéler. **Sans partenaire, N3 n'a pas de
contenu défendable, et le jalon 6 reste bloqué.**

Deux issues, à trancher au jalon 3 :

1. **Dépôt effectif exigé avant publication** du signalement : le Trouveur
   déclare où il a déposé le document, l'Administrateur valide. Cohérent avec la
   décision, mais allonge le parcours du Trouveur — ce que la §9.1 cherche
   précisément à éviter, puisque chaque champ supplémentaire réduit le nombre de
   documents signalés.
2. **Mise en relation médiatisée en repli**, uniquement lorsqu'aucun dépôt n'a
   eu lieu, avec les réserves du §2 ci-dessus.

**Cette dépendance est, à ce jour, la question la plus structurante du projet.**
Elle n'est pas technique.

---

## 3. Question de confiance — **TRANCHÉE** (D-016)

> Facturer avant confirmation de la correspondance transforme chaque faux
> positif en litige.

**Décision : la séquence est vérification d'abord, paiement ensuite.**

```
correspondance → revendication → VÉRIFICATION D'IDENTITÉ RÉUSSIE
              → paiement → N3
```

Jamais l'inverse. Facturer avant vérification transformerait chaque faux
positif du moteur de rapprochement en litige, et chaque tentative d'usurpation
en recette pour la plateforme — une incitation économique qu'il faut refuser
d'installer.

**Exigences minimales, quelle que soit la suite :**

| Exigence | Mise en œuvre |
|---|---|
| Correspondance confirmée avant paiement | La revendication doit être en `proof_passed` |
| Montant et contrepartie annoncés sans ambiguïté avant validation | Écran de confirmation dédié |
| **Transparence en amont** | Le principe des frais, leur montant et leur contrepartie sont annoncés **dès la page d'accueil**, pas découverts au moment de payer (§9.1) |
| Procédure de remboursement documentée **et implémentée** | Statut `refunded` sur `payments`, déclenché automatiquement si le retrait n'aboutit pas sous X jours ou si la correspondance est infirmée |

**Le montant de 1 000 XAF** relevé dans le prototype est traité comme une
hypothèse d'origine inconnue, **pas comme une décision validée**. Il reste à
arbitrer, notamment au regard du coût réel du SMS (D-013), qui est une dépense
de sécurité et non un coût variable optionnel.

---

## 4. Décision d'architecture — le paiement est désactivable (D-015)

Puisque la question juridique n'est pas tranchée, **l'architecture doit
survivre à une réponse défavorable.**

Le paiement est piloté par un drapeau de configuration. Désactivé, le parcours
devient : correspondance → revendication → vérification → N3, **sans aucune
réécriture**.

Le coût de cette souplesse est quasi nul si elle est prévue dès le modèle de
données ; il est élevé si on l'ajoute après coup. C'est la raison pour laquelle
elle est décidée au jalon 0, alors qu'aucune ligne de code de paiement n'existe.

---

## 5. Exigences techniques

Indépendantes du prestataire et du sort de la question juridique. Applicables
dès que le module sera écrit (jalon 6).

- **Interface `PaymentProvider`** dans `app/Contracts/`.
- **Adaptateur factice déterministe**, couvrant explicitement : succès, échec,
  expiration, **paiement partiel**, **webhook en double**. Ces deux derniers
  cas sont fréquents en mobile money et doivent être des états de première
  classe, pas des cas d'erreur.
- **Squelette d'adaptateur réel non implémenté**, sans aucun nom d'API, endpoint
  ni format de charge utile inventé.
- **Idempotence stricte** : référence de transaction unique, clé d'idempotence
  unique, aucun double débit possible, webhook rejouable sans effet de bord.
- **Aucune confiance dans le retour client.** Le passage en N3 est conditionné
  au seul champ `confirmed_server_side_at`, renseigné exclusivement par
  confirmation serveur-à-serveur. **Jamais par une redirection navigateur.**
  Un test de refus explicite couvre ce point (`PERMISSIONS.md`, jalon 6).
- **Vérification de la signature des webhooks**, traitée en **synchrone** à la
  réception (validation, enregistrement idempotent), le traitement lourd étant
  délégué à la file — c'est l'exception prévue par la §3.1 du master prompt.
- **Journalisation intégrale** des charges utiles reçues.
- **Cron de réconciliation** (`POST /internal/cron/reconcile-payments`) pour les
  transactions restées en suspens, situation fréquente en mobile money.
- **Aucune donnée de paiement sensible stockée.**

---

## 6. Prestataire — **À CONFIRMER**

Le prototype mentionne **MTN Mobile Money** et **Orange Money**, ce qui est
cohérent avec ce que l'on croit savoir du marché camerounais. Mais :

> **L'état actuel de leurs API, leurs conditions d'accès, et la nécessité
> éventuelle de passer par un agrégateur ne sont pas connus avec certitude.**
> Aucun nom d'API, aucun endpoint, aucun format de charge utile ne sera inventé.

Le développement se fait intégralement sur l'adaptateur factice jusqu'à
confirmation. Voir `OPEN_QUESTIONS.md` Q-16.

---

## 7. État des trois questions

| Question | Statut | Bloque |
|---|---|---|
| Juridique (§1) | **Ouverte** — conseil juridique requis | Jalon 6 |
| Conception, contenu de N3 (§2) | Tranchée (D-009) | — |
| Dépendance partenaires (§2.1) | **Ouverte** — dépendance opérationnelle | Jalon 6, et arbitrage au jalon 3 |
| Confiance, séquence de facturation (§3) | Tranchée (D-016) | — |
| Montant des frais (§3) | Ouverte | Jalon 6 |
| Prestataire (§6) | **À CONFIRMER** | Jalon 9 (adaptateur réel) |
