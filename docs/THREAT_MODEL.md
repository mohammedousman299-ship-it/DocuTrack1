# Modèle de menaces

**Version :** 1.0 · **Date :** 2026-09-05 · **Statut :** vivant — à mettre à
jour à chaque jalon et avant toute livraison de fonctionnalité.

> **Règle d'usage.** Aucune fonctionnalité n'est livrée sans avoir été évaluée
> contre ce document. Une fonctionnalité qui crée une menace non listée ici
> impose d'abord une mise à jour de ce fichier.

---

## 1. Énoncé du risque

DocuTrack constitue, par construction, une **base de données de documents
d'identité officiels associant des noms et des numéros**. Mal conçu, ce système
devient un outil d'usurpation d'identité plus efficace que le vol de documents
lui-même : le voleur obtient une pièce, la plateforme mal protégée offre un
index consultable.

### 1.1 Reclassement du risque principal

Le master prompt identifiait deux menaces structurelles : l'usurpation
d'identité et l'extraction massive. L'analyse conduit à un **reclassement** :

> **Le risque n°1 n'est pas l'extraction de données. C'est la revendication
> frauduleuse assistée par la plateforme (M-01, amorcée par M-02 et M-03).**

Justification : l'extraction livre des données, exploitables ailleurs et plus
tard. La revendication frauduleuse livre **le document physique**, et donc
l'usurpation complète, immédiate, avec le concours involontaire de la
plateforme — qui aura authentifié l'attaquant, notifié l'attaquant, encaissé un
paiement de l'attaquant et orienté l'attaquant vers le point de retrait.

L'extraction massive reste une menace majeure (M-07), mais elle est
subordonnée : c'est souvent l'étape de collecte qui alimente M-01.

## 2. Biens à protéger

Classés par gravité de compromission décroissante.

| # | Bien | Pourquoi |
|---|---|---|
| B-1 | **Images de documents** | Donnée la plus riche. Fournie par un tiers sur une personne **qui n'a pas consenti**. Contient nom, numéro, date et lieu de naissance, photographie, signature. |
| B-2 | **Couple (nom complet, numéro de document)** | Le matériau direct de l'usurpation. |
| B-3 | **Le fait qu'une personne a perdu un document donné** | Information de vulnérabilité : elle désigne une cible en situation de faiblesse administrative. |
| B-4 | **Identité et coordonnées du Trouveur** | Un bénévole, exposable à des représailles ou à une extorsion. |
| B-5 | **Éléments de preuve de connaissance** (date de délivrance, lieu de naissance) | Leur fuite détruit le mécanisme de vérification d'identité (§4.3). |
| B-6 | **Journal d'audit** | Sa falsification supprime la capacité de constater un abus. |
| B-7 | **Coordonnées de contact** (téléphone, e-mail) | Alimentent l'ingénierie sociale (M-13). |

## 3. Acteurs et attaquants

| Acteur | Capacités | Motivation typique |
|---|---|---|
| A-1 Anonyme externe | HTTP non authentifié | Reconnaissance, extraction |
| A-2 **Utilisateur authentifié malveillant** | Tout ce qu'un compte vérifié permet | **Le plus important du modèle** : usurpation, extraction |
| A-3 Trouveur malveillant | Peut injecter des signalements | Appât du gain, nuisance, sondage de l'index |
| A-4 Administrateur malveillant ou compromis | Accès aux images et aux données complètes | Revente, curiosité, contrainte externe |
| A-5 Opérateur d'infrastructure | Accès physique aux données et aux sauvegardes | Hors de notre contrôle |
| A-6 Tiers avec pouvoir de contrainte | Réquisition, saisie | Hors de notre contrôle |
| A-7 Attaquant hors plateforme | Aucun accès au système | Ingénierie sociale, SIM swap |

**Hypothèse de travail :** A-2 est un attaquant qui **paie le prix d'entrée**
(compte vérifié par SMS). Toute défense qui suppose l'attaquant non authentifié
est insuffisante.

---

## 4. Menaces

> Les noms figurant en exemple dans cette documentation sont **synthétiques**,
> produits par combinaison de syllabes inventées. Aucun nom de personne réelle
> n'est utilisé, y compris à titre d'illustration (§12 du master prompt).


### M-01 — Revendication frauduleuse assistée par la plateforme ⚠️ RISQUE N°1

**Acteur :** A-2 · **Bien :** B-1, B-2, B-3

Un attaquant réclame un document qui n'est pas le sien, franchit la
vérification d'identité, paie, obtient le point de retrait et récupère la pièce.

**Ce qui rend l'attaque réaliste :** rien n'empêche a priori de réclamer le
document d'un tiers, et la plateforme fournit à l'attaquant tout
l'accompagnement dont il a besoin.

**Contrôles**
- Preuve par la connaissance, avec les protections de M-03.
- Cohérence du nom du compte avec le nom revendiqué ; toute incohérence part en
  revue administrateur, jamais en refus silencieux.
- **Revue humaine obligatoire** pour passeport et CNI : aucune restitution
  automatisée sur ces types (D-014).
- Journalisation de toute tentative de revendication, réussie ou non ; alerte
  administrateur au-delà d'un seuil.
- Interdiction stricte d'être Trouveur et revendiquant sur le même signalement
  (D-004).
- N3 est un point de retrait tiers (D-009) : le dépositaire constitue une
  **seconde barrière humaine**, hors plateforme, au moment du retrait.

**Limite assumée :** ces contrôles réduisent la probabilité, ils ne
l'annulent pas. Un attaquant qui connaît bien sa cible — un proche, un
collègue, un membre de la famille — franchira la preuve par la connaissance.
**Le dispositif n'est pas infaillible et il ne doit jamais être présenté comme
tel**, ni à l'utilisateur, ni dans la communication du projet.

---

### M-02 — Déclaration de perte au nom d'autrui

**Acteur :** A-2 · **Bien :** B-3 · **Amorce de :** M-01

> Menace **non couverte** par la §4 du master prompt, qui vérifie l'identité du
> revendiquant mais jamais celle du déclarant.

J'ouvre un compte, je déclare que « Miro Olanda a perdu sa CNI », et le système
me **notifie automatiquement** (§1.4, §1.8) le jour où un Trouveur dépose la
CNI de Miro Olanda. Je suis alors en position de la revendiquer **avant** le
véritable propriétaire, avec un temps d'avance structurel offert par la
plateforme.

**C'est le vecteur le moins coûteux du système** : une déclaration, gratuite,
sans preuve, et la plateforme devient un service d'alerte au bénéfice de
l'usurpateur.

**Contrôles**
- Appliquer à la déclaration de perte **le même contrôle de cohérence
  nom-du-compte** qu'à la revendication.
- Une déclaration dont le nom ne correspond pas au compte vérifié **ne déclenche
  aucune notification automatique** : elle part en file de revue.
- Plafond de déclarations actives par compte.
- Le motif « je déclare pour un proche » est un cas légitime réel : il est
  traité en revue humaine, pas par une case à cocher qui contournerait le
  contrôle.

---

### M-03 — L'oracle de confirmation N2

**Acteur :** A-2 · **Bien :** B-5

> Menace **non couverte** par la §4.3, qui décrit la preuve par la connaissance
> sans traiter le fait qu'un formulaire juste/faux est un oracle.

Un attaquant détenant déjà un nom et un numéro devine les éléments manquants
par essais successifs. Le lieu de naissance est particulièrement faible : sa
distribution réelle au Cameroun est très concentrée sur quelques agglomérations.

**Contrôles**
- **Tous les champs de preuve sont soumis en une seule fois**, jamais un par un.
- **Réponse binaire globale** : « les éléments fournis ne correspondent pas ».
  Ne jamais indiquer **quel** champ était faux, ni combien étaient corrects.
- **3 tentatives à vie** sur une revendication donnée, pas 3 par heure. Au-delà,
  la revendication est verrouillée et part en revue.
- Chaque tentative est journalisée avec les champs fournis (hachés, pas en
  clair) pour permettre la détection de balayage.
- Temps de réponse normalisé (cf. M-05).

---

### M-04 — Sybil : la limitation par IP est inopérante au Cameroun

**Acteur :** A-1, A-2 · **Bien :** tous

> Menace **non couverte** : la §4.2 prescrit une limitation « par compte et par
> IP » sans interroger le coût réel d'un compte ni la validité de l'adresse IP
> comme identifiant dans le contexte visé.

Deux défauts :
1. **Un compte ne coûte rien** si l'inscription ne demande qu'une adresse
   e-mail. Les quotas par compte deviennent alors décoratifs.
2. **L'adresse IP n'identifie personne** sur les réseaux mobiles camerounais :
   le CGNAT des opérateurs fait partager une même adresse publique par un grand
   nombre d'abonnés. La limitation par IP est donc simultanément **contournable
   par l'attaquant** (qui change d'IP) et **bloquante pour des utilisateurs
   légitimes** (faux positifs de masse), c'est-à-dire le pire des deux mondes.

**Contrôles**
- **Vérification SMS obligatoire à l'inscription** (D-013). C'est le seul
  contrôle qui donne un coût unitaire réel à la création de comptes en masse.
- Un numéro de téléphone ne peut être rattaché qu'à un seul compte actif.
- La limitation par IP est **conservée mais rétrogradée** : signal de détection
  et de corrélation, jamais barrière unique, et ses seuils sont larges pour ne
  pas exclure une population entière derrière un même CGNAT.
- Détection de corrélation inter-comptes : comptes créés en rafale, mêmes
  schémas de recherche, même empreinte de navigateur.

**Requalification budgétaire :** le SMS n'est pas un canal de confort avec un
coût à optimiser. C'est le **contrôle anti-Sybil principal**, et sa dépense est
une dépense de sécurité.

---

### M-05 — Canal latéral temporel et différentiel de messages

**Acteur :** A-1, A-2 · **Bien :** B-2, B-3

> Menace **non couverte** par la §4.

Même sans afficher la moindre donnée, « aucune correspondance » contre « une
correspondance possible » est un bit d'information exploitable en itération. Et
une recherche qui aboutit déclenche des écritures (journal, correspondance)
qu'une recherche vide ne déclenche pas : la **latence** trahit le résultat même
si la réponse est identique.

**Contrôles**
- **La recherche est asynchrone différée** (D-008) : la requête HTTP ne porte
  plus le résultat, donc ni la latence ni le corps de la réponse ne le
  révèlent. C'est le contrôle décisif contre cette menace, et la raison
  principale du choix D-008.
- Temps de réponse normalisé sur tous les points d'entrée qui restent
  synchrones (connexion, revendication, preuve de connaissance).
- Messages d'erreur uniformes : l'authentification ne distingue jamais
  « compte inconnu » de « mot de passe erroné ».

---

### M-06 — Injection dans l'index de rapprochement

**Acteur :** A-3 · **Bien :** B-3

> Menace **non couverte** : la §4.5 traite les faux signalements comme du bruit
> ou de la fraude au gain, pas comme un canal d'extraction.

Un attaquant dépose de **faux signalements** calibrés. Le moteur les confronte
automatiquement aux déclarations de perte existantes. Tout retour d'information
sur le sort de ses signalements — un statut, un compteur, une notification —
lui apprend qu'une personne donnée a déclaré la perte d'un document donné.

**Cette attaque contourne intégralement les quotas de recherche de la §4.2**,
qui n'observent que le chemin « recherche ».

**Contrôles**
- **Le Trouveur ne reçoit jamais aucun retour sur le rapprochement de ses
  propres signalements** : ni statut détaillé, ni compteur de correspondances,
  ni notification. Son écran affiche uniquement « signalement enregistré ».
- Plafond de signalements par compte et par période.
- Revue administrateur au-delà d'un seuil de signalements.
- Score de fiabilité du Trouveur ; les comptes à faible score voient leurs
  signalements retenus en revue avant d'entrer dans l'index.
- Détection de doublons sur empreinte normalisée (§4.5).

---

### M-07 — Extraction massive par la fonction de recherche

**Acteur :** A-1, A-2 · **Bien :** B-2, B-3

Automatisation de la recherche pour constituer un fichier de noms associés à
des numéros de pièces.

**Contrôles**
- Recherche **réservée aux utilisateurs authentifiés** et vérifiés par SMS.
- **Combinaison minimale de critères exigée** : une recherche par nom seul est
  refusée. La combinaison retenue est documentée dans `DISCLOSURE_LEVELS.md`.
- Plafond quotidien de recherches par compte.
- **Recherche asynchrone** (D-008) : la boucle d'itération rapide est détruite.
- Détection de schémas d'énumération : **volume** de numéros distincts,
  balayage de noms voisins, cadence régulière.

> **Limite créée par notre propre conception, constatée au jalon 4.** Le §4.2
> demandait de détecter les **variations systématiques de numéros** — un
> attaquant essayant `AB123456`, `AB123457`, `AB123458`. C'est **impossible**
> avec D-007 : les numéros ne sont conservés que sous forme de HMAC, dont la
> propriété même est de détruire la similarité entre entrées voisines. Nous
> détectons le **volume** de numéros distincts, pas leur proximité.
>
> Un attaquant patient qui espace ses essais est donc plus difficile à repérer
> qu'il ne le serait si les numéros étaient comparables. C'est le prix assumé
> de D-007, qui protège en échange contre une fuite de base — un risque bien
> plus grave et bien moins réparable.
>
> Sur les **noms**, la similarité reste mesurable : ils sont conservés
> normalisés en clair pour le rapprochement. Le balayage de noms voisins est
> donc détectable, et c'est le signal le plus utile dont nous disposons.
- CAPTCHA au-delà d'un seuil ; blocage progressif ; alerte administrateur.
- **Aucun compte de résultats supérieur à 1 n'est jamais exposé** : un
  compteur est un signal d'énumération offert gratuitement.
- Journalisation de chaque recherche : auteur, critères, nombre de résultats,
  horodatage.
- **Aucun fragment de numéro en N1** (D-005).

---

### M-08 — Fuite d'infrastructure : sauvegardes et opérateur

**Acteur :** A-5, A-6 · **Bien :** B-1, B-2

Une fuite de sauvegarde Supabase, une mauvaise configuration de bucket, une
compromission de l'hébergeur ou une réquisition contournent **par nature**
l'intégralité des contrôles applicatifs : ni les Policies Laravel, ni les
quotas, ni la journalisation ne s'appliquent à une copie brute de la base.

**Contrôles**
- **Chiffrement applicatif des numéros de document** (D-007) : c'est le seul
  contrôle du projet qui protège contre cette menace, et c'est sa justification
  principale. Une copie brute de la base ne livre pas les numéros.
- Clé de chiffrement et clé HMAC **hors de la base**, en variables
  d'environnement, jamais dans le dépôt.
- Bucket privé, aucune URL publique ni devinable (§3.3).
- **Rétention courte** (D-010) : réduit le volume exposé par une fuite d'un
  ordre de grandeur. C'est le contrôle le plus rentable du projet.
- Minimisation : ce qui n'est pas collecté ne peut pas fuir.

**Limite assumée :** le nom du propriétaire reste en clair normalisé, parce que
le rapprochement approximatif en dépend (D-007). Une fuite de base livre donc
des noms associés à des types de documents et à des lieux, sans les numéros.

---

### M-09 — Administrateur malveillant ou compromis

**Acteur :** A-4 · **Bien :** B-1, B-2, B-5, B-6

L'administrateur est le point de collecte ultime : il voit les images, donc le
bien le plus sensible du système.

**Contrôles**
- **Séparation en deux rôles** (D-014) : administration fonctionnelle et revue
  sensible.
- **Contrôle préventif, pas seulement détectif** : floutage par défaut dans les
  files de revue ; révélation à l'unité, avec **saisie obligatoire d'un motif**.
- Journalisation de tout accès à une donnée sensible, au même titre que pour
  les autres acteurs, avec génération d'URL signée tracée individuellement.
- **Journal d'audit append-only** : ni mise à jour ni suppression, y compris
  par un administrateur, appliqué par révocation des droits SQL correspondants
  sur le rôle applicatif.
- Nombre d'administrateurs limité et nommé (2 à 3).
- 2FA obligatoire.
- L'Administrateur ne cumule pas les capacités utilisateur sur son compte
  (D-004).

**Limite assumée :** un administrateur déterminé peut consulter et recopier
manuellement les données auxquelles son rôle lui donne accès. La journalisation
permet de le **constater**, pas de l'empêcher. La seule vraie réduction de ce
risque est le nombre restreint d'administrateurs et la rétention courte.

---

### M-10 — Fuite par les notifications

**Acteur :** A-7, ou toute personne à portée de vue · **Bien :** B-2, B-3

Un SMS s'affiche sur un écran verrouillé, dans un lieu public, sur un téléphone
parfois partagé. Un e-mail transite par un fournisseur tiers.

**Contrôles**
- **Contenu strictement minimal** : une notification annonce l'existence d'une
  correspondance et invite à se connecter. Elle ne contient **jamais** de
  donnée de niveau N2 ou N3 — ni numéro, même partiel, ni nom complet, ni lieu
  précis, ni image, ni lien porteur d'un jeton donnant accès à autre chose
  qu'une page de connexion.
- Regroupement et plafond par période, pour éviter le harcèlement et la fuite
  par accumulation.
- Préférences par canal, désabonnement respecté.

---

### M-11 — Fuite par les champs de texte libre

**Acteur :** A-3 (souvent sans intention) · **Bien :** B-2

Le Trouveur dispose de champs libres — « informations complémentaires », « lieu
de dépôt ». Rien ne l'empêche d'y recopier le numéro complet du document, ce
qui court-circuite entièrement le modèle de divulgation : la donnée fuiterait
par un champ que le système ne considère pas comme sensible.

**Contrôles**
- Les champs libres **ne sont jamais exposés en N1 ni en N2**.
- Avant exposition en N3, passage en **revue administrateur** ou, au minimum,
  filtrage de motifs (suites de caractères ressemblant à un numéro de
  document), avec mise en revue en cas de détection.
- Ces champs ne sont pas indexés pour la recherche.
- Formulation du libellé pour dissuader la saisie du numéro.

---

### M-12 — Fuite par l'image et ses URL d'accès

**Acteur :** A-1, A-2 · **Bien :** B-1

Une image de document accessible par une URL publique ou devinable annule tous
les autres contrôles. Une photographie de pièce d'identité embarque en outre
des métadonnées EXIF, dont les coordonnées GPS du lieu de prise de vue.

**Contrôles**
- Bucket **privé**, aucune URL publique, identifiants d'objets non devinables.
- URL signées de **très courte durée**, générées à la demande, **chaque
  génération journalisée** (acteur, objet, motif, horodatage).
- **L'image n'est jamais exposée à un utilisateur** (D-006) : seul
  l'Administrateur y accède, en révélation unitaire motivée.
- Suppression **serveur** des métadonnées EXIF, systématique, avant stockage.
  La compression côté client ne vaut pas contrôle.
- Validation stricte du type MIME réel, de la taille et des dimensions.
- Aucune image sur le système de fichiers du conteneur.
- Purge effective à 90 jours (D-010).

---

### M-13 — Ingénierie sociale hors plateforme et SIM swap

**Acteur :** A-7 · **Bien :** B-7, et par ricochet tous les autres

Deux scénarios hors du périmètre technique mais bien réels :
1. Un attaquant contacte la victime en se présentant comme DocuTrack et lui
   réclame le paiement des frais de service ou ses éléments de preuve.
2. Un **SIM swap** donne à l'attaquant le contrôle du numéro de téléphone, ce
   qui casse simultanément la vérification SMS (D-013) et le canal de
   notification.

**Contrôles**
- Page publique permanente et visible : « DocuTrack ne vous demandera jamais
  X » — énumérant précisément ce que la plateforme ne demande jamais par
  téléphone ni par message.
- Aucun paiement n'est jamais sollicité en dehors du parcours authentifié.
- Notification sur un second canal (e-mail) lors d'un changement de numéro de
  téléphone, et délai de latence avant que le nouveau numéro ne devienne
  utilisable pour une revendication.
- Ré-authentification par mot de passe exigée avant toute action sensible : le
  SMS seul ne suffit jamais à revendiquer.

---

### M-14 — La mise en relation médiatisée

**Acteur :** A-2, A-3 · **Bien :** B-3, B-4, B-7 · **Introduite par :** D-028

> Menace **créée par une décision de conception**, et non découverte : D-009
> écartait explicitement la messagerie interne. D-028 la réintroduit en repli
> du dépôt, ce qui rend ses objections d'origine actives.

Lorsqu'aucun dépôt n'a eu lieu, N3 devient une messagerie anonymisée entre le
Propriétaire et le Trouveur. Trois conséquences :

1. **Contournement de l'anonymat.** Rien n'empêche une partie d'écrire son
   numéro de téléphone dans un message. La médiation devient alors nominale et
   l'on retombe sur le contact direct que D-009 refusait.
2. **Exposition du Trouveur.** La plateforme met deux inconnus en relation.
   Le marché de la rançon n'est pas supprimé, il est rendu traçable.
3. **Harcèlement et pression**, dans les deux sens.

**Contrôles**
- Accès à la messagerie **conditionné à une vérification d'identité réussie** :
  jamais un simple candidat au rapprochement.
- Détection de motifs ressemblant à un numéro de téléphone ou à une adresse,
  avec mise en revue plutôt que blocage silencieux — un faux positif ne doit
  pas empêcher une restitution.
- Journalisation intégrale des échanges, plafond de messages par période,
  signalement par les utilisateurs, clôture automatique à la restitution.
- **L'interface présente le dépôt comme le mode privilégié** et la messagerie
  comme un repli, pas comme une alternative équivalente.

**Limite assumée :** aucun de ces contrôles n'empêche deux personnes décidées
d'échanger leurs coordonnées. Ils rendent l'échange visible et sanctionnable,
pas impossible.

---

## 5. Contrôles transverses

| Contrôle | Menaces couvertes |
|---|---|
| Divulgation progressive N1/N2/N3, masquage **serveur** par DTO distincts | M-01, M-07 |
| Vérification SMS obligatoire | M-04, et par effet M-01, M-06, M-07 |
| Recherche asynchrone différée | M-05, M-07 |
| Chiffrement des numéros + HMAC indexé | M-08 |
| Rétention courte et purge effective | M-08, M-09, M-12 |
| Journal d'audit append-only | M-09 |
| Séparation des rôles d'administration + motif obligatoire | M-09 |
| Aucun retour de rapprochement au Trouveur | M-06 |
| Contenu minimal des notifications | M-10 |
| Revue humaine obligatoire sur passeport et CNI | M-01, M-02 |
| Minimisation des champs collectés | M-08, M-11, M-12 |

## 6. Limites assumées

Ce dispositif **n'est pas infaillible**, et le prétendre serait une faute. Les
limites connues, énoncées sans atténuation :

1. **La preuve par la connaissance échoue face à un proche.** Un membre de la
   famille, un colocataire ou un collègue connaît la date de naissance, le lieu
   de naissance et souvent le numéro de pièce de sa cible. C'est la limite
   fondamentale de M-01, et aucun contrôle applicatif ne la lève.
2. **La vérification SMS ne résiste pas au SIM swap** ni à l'achat de cartes
   SIM prépayées en volume. Elle élève le coût de l'attaque ; elle ne la rend
   pas impossible.
3. **Un administrateur malveillant peut recopier ce qu'il voit.** La
   journalisation constate, elle n'empêche pas.
4. **Le nom du propriétaire reste en clair en base**, contrainte imposée par le
   rapprochement approximatif (D-007).
5. **Aucun contrôle ne protège contre une réquisition légale.**
6. **Le rapprochement produira des faux positifs.** C'est mathématiquement
   inévitable avec des homonymes et des fautes de saisie ; le dispositif vise à
   les rendre rares et à ne jamais les présenter comme des certitudes.
7. **La plateforme ne peut rien garantir sur ce qui se passe au point de
   retrait**, qui est hors de son périmètre.

## 7. Hors modèle de menaces

Non traités ici, et à traiter ailleurs : sécurité physique des points de
retrait partenaires ; comportement des dépositaires ; disponibilité et
continuité de service ; fraude au paiement côté opérateur mobile money ;
conformité réglementaire, qui relève de `COMPLIANCE_OPEN_QUESTIONS.md`.

## 8. Correspondance avec le master prompt

| Menace | Couverte par la §4 du master prompt ? |
|---|---|
| M-01 | Partiellement (§4.3) — le reclassement en risque n°1 est nouveau |
| **M-02** | **Non** |
| **M-03** | **Non** — l'effet d'oracle n'était pas identifié |
| **M-04** | **Non** — la §4.2 supposait la limitation par IP efficace |
| **M-05** | **Non** |
| **M-06** | **Non** — la §4.5 traitait les faux signalements comme du bruit |
| M-07 | Oui (§4.2) |
| M-08 | Partiellement (§8, chiffrement) — la justification était incomplète |
| M-09 | Partiellement (§4.4) — contrôle détectif seulement |
| M-10 | Oui (§7) |
| **M-11** | **Non** |
| M-12 | Oui (§3.3) |
| **M-13** | **Non** — explicitement hors périmètre technique |
