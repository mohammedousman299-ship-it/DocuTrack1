# Journal de décisions

Format : une décision par entrée. Chaque entrée porte sa date, les alternatives
écartées et la justification. Une décision n'est jamais supprimée : si elle est
révisée, une nouvelle entrée la remplace et l'ancienne est marquée
« Remplacée par D-0xx ».

Statuts : **Actée** · **Provisoire** (à reconfirmer à un jalon donné) ·
**Ouverte** (voir `OPEN_QUESTIONS.md`) · **Remplacée**.

---

## D-001 — Le prototype existant n'est pas migré

**Date :** 2026-09-05 · **Statut :** Actée · **Jalon :** 1

Le prototype (`index.html`, `app.js`, `style.css`) est archivé dans
`legacy/prototype/` avec un README d'avertissement. Aucune ligne n'est reprise.
Le projet Laravel est initialisé à la racine.

**Alternatives écartées :** suppression pure (perte de la référence de parcours,
que l'historique git rend moins commode à consulter) ; conservation à la racine
en parallèle (risque de déploiement accidentel d'une maquette contenant des
données de démonstration non sécurisées).

**Justification :** voir `AUDIT_PROTOTYPE.md` §6. Le prototype n'a pas de couche
serveur, son socle front-end est incompatible avec la stack cible, et son modèle
de divulgation est l'inverse exact de celui qui est requis.

---

## D-002 — Livewire 4 plutôt que Livewire 3

**Date :** 2026-09-05 · **Statut :** Provisoire — à reconfirmer au jalon 1

Le master prompt spécifiait Livewire 3. La version stable au moment de la
décision est **v4.4.3** ; la branche 3 est figée à v3.8.7. On retient Livewire 4.

**Alternatives écartées :** Livewire 3 (conforme à la lettre du prompt, mais
démarre un projet neuf sur une branche qui n'est plus la principale, avec une
migration à prévoir).

**Justification :** c'est la branche qui recevra les correctifs sur la durée du
projet. Compatible Laravel 10 à 13.

**Réserve explicite :** les ruptures 3→4 et la maturité de l'écosystème autour
de Livewire 4 n'ont pas été vérifiées en détail. Le CHANGELOG doit être lu au
jalon 1 et tout blocage remonté avant que la couche d'interface ne soit
construite. Cette décision est donc **provisoire**.

---

## D-003 — PHP 8.4 et Pest 5

**Date :** 2026-09-05 · **Statut :** Actée

Le master prompt annonçait « PHP 8.3+ ». Or `pestphp/pest` v5.1.3 requiert
`php ^8.4`. On fixe **PHP 8.4** et **Pest 5**.

**Alternatives écartées :** PHP 8.3 + Pest 4 (aurait respecté la lettre du
prompt, mais rien n'impose 8.3) ; PHPUnit sans Pest (le prompt demande Pest, et
sa syntaxe sert la lisibilité des tests de refus d'accès, qui seront nombreux).

**Justification :** l'image Docker déployée est sous notre contrôle, donc la
contrainte de version n'a pas de coût externe.

**Versions relevées sur Packagist le 2026-09-05 :** `laravel/framework` v13.30.1,
`livewire/livewire` v4.4.3, `laravel/fortify` v1.39.0, `pestphp/pest` v5.1.3,
`larastan/larastan` v3.11.0, `laravel/pint` v1.30.5.

---

## D-004 — Rôles = capacités, pas types de comptes exclusifs

**Date :** 2026-09-05 · **Statut :** Actée

Il existe **un seul type de compte utilisateur**. « Propriétaire » et
« Trouveur » sont des capacités contextuelles, pas des types de comptes.
« Administrateur » reste un rôle distinct, avec 2FA obligatoire, et **ne cumule
pas** les capacités utilisateur sur son propre compte.

**Alternative écartée :** comptes exclusifs (conforme à la lettre de la
description fonctionnelle, mais oblige une personne qui trouve un document à
créer un second compte).

**Justification :**
1. Réalité d'usage : la même personne perd un document un jour et en trouve un
   autre.
2. **Sécurité** : multiplier les comptes affaiblit le contrôle anti-Sybil, qui
   repose sur la vérification SMS (D-013), et disperse la réputation du
   Trouveur.
3. L'autorisation réelle ne porte pas sur un rôle mais sur une **relation à une
   ressource** : « peut voir le N2 de ce signalement » = « a une revendication
   vérifiée sur ce signalement ». C'est une Policy par ressource.

**Règle dérivée, non négociable :** un même compte ne peut pas être à la fois
Trouveur et revendiquant sur le **même** signalement — sinon on s'auto-attribue
un document. Contrainte en base + Policy + test de refus.

---

## D-005 — Le numéro de document est totalement absent du niveau N1

**Date :** 2026-09-05 · **Statut :** Actée

Aucun fragment du numéro n'est exposé en N1 — pas même les derniers caractères.
En N2, les 4 derniers caractères ne sont affichés qu'en **confirmation** de ce
que l'utilisateur vient lui-même de saisir.

**Alternative écartée :** 4 derniers caractères en N1, comme le prévoyait la
§4.2 du master prompt. **Cette décision est plus stricte que la spécification
d'origine, et l'écart est assumé.**

**Justification :** la combinaison « type de document + région + 4 derniers
caractères » constitue déjà un quasi-identifiant pour qui détient un fichier
partiel. En N2, confirmer une saisie de l'utilisateur ne lui apprend rien qu'il
ne sache déjà — c'est le seul usage défendable d'un fragment de numéro.

---

## D-006 — L'image du document n'est jamais visible du Propriétaire

**Date :** 2026-09-05 · **Statut :** Actée

L'image jointe par le Trouveur n'est visible d'aucun utilisateur, à aucun
niveau de divulgation, **y compris N3**. Elle est réservée à l'Administrateur
pour vérification, en accès journalisé et motivé.

**Alternatives écartées :** visible en N3 (rassure le Propriétaire avant qu'il
se déplace, mais si la vérification d'identité est franchie par un usurpateur,
celui-ci repart avec une image exploitable) ; floutage partiel côté serveur
(un floutage automatique fiable sur des pièces camerounaises hétérogènes n'est
pas réalisable sans OCR spécialisé — nous ne pouvons pas en garantir la
fiabilité, donc nous ne nous appuierons pas dessus).

**Justification :** l'image est fournie par un tiers sur une personne qui n'a
pas consenti, elle contient plus d'informations que nécessaire, et le
Propriétaire n'a besoin que de savoir **où aller récupérer sa pièce**.

---

## D-007 — Numéros chiffrés + HMAC indexé ; le flou ne porte que sur le nom

**Date :** 2026-09-05 · **Statut :** Actée

Chaque numéro de document est stocké sous deux formes :
- `number_encrypted` — chiffrement applicatif Laravel, **jamais indexé** ;
- `number_hmac` — HMAC-SHA256 du numéro normalisé avec une clé serveur,
  **indexé**, permettant uniquement l'égalité exacte.

**Conséquence assumée :** la recherche approximative sur le numéro devient
impossible. Le rapprochement flou ne porte plus que sur le nom.

**Alternatives écartées :** numéro en clair normalisé avec distance d'édition
(meilleur rappel, mais une fuite de base ou de sauvegarde Supabase livrerait
l'intégralité des numéros de pièces d'identité — c'est le scénario catastrophe
du projet, cf. `THREAT_MODEL.md` M-08) ; arbitrage par type de document
(deux chemins de matching à maintenir et tester, pour un gain de rappel limité).

**Justification :** c'est le seul contrôle du projet qui protège contre une
fuite d'infrastructure, laquelle contourne par nature tous les contrôles
applicatifs. Le coût en rappel est partiellement rattrapé par la file de revue
administrateur.

---

## D-008 — La recherche est asynchrone différée

**Date :** 2026-09-05 · **Statut :** Actée

Une recherche ne rend pas son résultat à l'écran. Elle crée une **demande de
recherche** ; le résultat parvient au demandeur par notification quelques
minutes plus tard.

**Alternatives écartées :** synchrone avec quota très bas (3/jour) ; hybride
(première recherche du jour synchrone, suivantes différées).

**Justification :** détruit la boucle d'énumération rapide — un attaquant ne
peut plus itérer — et supprime le canal latéral temporel (`THREAT_MODEL.md`
M-05). C'est le choix le plus protecteur des trois.

**Coût assumé, explicitement :** c'est la décision la plus coûteuse du projet
en convivialité. Elle dégrade le parcours principal d'un utilisateur qui vient
de perdre une pièce d'identité et attend une réponse immédiate. Elle a été
retenue en connaissance de cause au titre de l'exigence n°1. **Si une seule
décision devait être réexaminée après les premiers essais utilisateurs, c'est
celle-ci.**

**Conséquences architecturales :** voir C-01 et C-02 ci-dessous.

---

## D-009 — N3 = point de retrait tiers, jamais le contact du Trouveur

**Date :** 2026-09-05 · **Statut :** Actée, mais **sans contenu disponible** (voir C-03)

L'information de niveau N3 est le **lieu et les modalités de retrait auprès
d'un dépositaire** (poste de police, mairie, structure partenaire). L'identité
et les coordonnées du Trouveur ne sont **jamais** communiquées, à aucun niveau.

**Alternatives écartées :** contact direct du Trouveur (aucune dépendance
opérationnelle, livrable immédiatement — écarté parce qu'on exposerait un
bénévole à un inconnu qui vient de payer pour obtenir ses coordonnées, ce qui
crée mécaniquement un marché de la rançon et laisse la plateforme sans maîtrise
de la suite) ; messagerie interne anonymisée (module de modération à construire,
et les numéros circuleraient en clair dans les messages).

**Justification :** le prototype avait déjà retenu ce modèle (champ
`depositPoint`). C'est le seul qui ne mette personne en danger.

---

## D-010 — Rétention : 180 / 180 / 90 jours, audit 3 ans

**Date :** 2026-09-05 · **Statut :** Provisoire — à valider juridiquement

| Donnée | Durée de vie |
|---|---|
| Déclaration de perte | 180 jours |
| Signalement de découverte | 180 jours |
| Image de document | **90 jours** |
| Journal d'audit | 3 ans |

Purge **effective** par cron (suppression réelle, pas suppression logique).

**Alternatives écartées :** plus court, 90/90/30 j (surface réduite d'un facteur
2 à 3, mais un document trouvé en janvier et déclaré perdu en mai ne se
rapprocherait plus — or les gens mettent parfois des mois à constater une perte
ou à découvrir la plateforme) ; plus long, 1 an/1 an/180 j (meilleur taux de
rapprochement, mais contredit le classement de la rétention en contrôle de
sécurité).

**Justification :** la rétention est traitée ici comme un **contrôle de sécurité
de premier rang**, pas comme une obligation de conformité. Le risque croît avec
le stock. L'image, donnée la plus dangereuse, a la durée la plus courte.

---

## D-011 — Stack confirmée, avec réserve sur Vercel

**Date :** 2026-09-05 · **Statut :** Actée, avec réserve consignée

La stack Laravel + Supabase (PostgreSQL) + Vercel (runtime container,
FrankenPHP) est retenue, pour capitaliser sur l'outillage et les compétences
du projet PHOENIX.

**Réserve, consignée pour que le choix soit conscient et non hérité :** Vercel
est le maillon le plus contraignant pour DocuTrack, davantage que pour PHOENIX.
Ce système est fondamentalement asynchrone (rapprochement, notifications,
réconciliation des paiements, purge) et Vercel impose de remplacer
`queue:work` et `schedule:run` par des endpoints cron idempotents et
verrouillés. C'est une complexité permanente sur le chemin critique du produit.
Un VPS classique donnerait l'asynchrone natif et, peut-être, une meilleure
latence vers l'Afrique centrale.

**Ce qui n'a pas pu être vérifié :** l'état actuel de l'offre Vercel « runtime
container » (régions disponibles, limites de durée d'exécution, tarif) n'a pas
été vérifié depuis l'environnement de développement. À tester au jalon 1.

---

## D-012 — Validation permissive des numéros de documents

**Date :** 2026-09-05 · **Statut :** Provisoire — jalon 3

Les numéros sont validés sur la longueur et l'alphabet autorisé uniquement.
Aucune expression régulière par type de document.

**Justification :** les formats réels des numéros de CNI, passeport et permis
de conduire camerounais **ne sont pas connus avec certitude** et ne seront pas
inventés. Une validation trop stricte rejetterait à tort des numéros valides,
créant une impasse pour l'utilisateur — ce que §9.1 interdit. La normalisation
et le HMAC fonctionnent sans connaître le format.

**À faire :** resserrer quand des spécimens ou une description fiable seront
disponibles (`OPEN_QUESTIONS.md` Q-07).

---

## D-013 — Vérification SMS obligatoire à l'inscription

**Date :** 2026-09-05 · **Statut :** Actée

Tout compte est adossé à un numéro de téléphone vérifié par SMS.

**Alternatives écartées :** SMS seulement aux actions sensibles (le coût ne
porterait que sur les utilisateurs actifs, mais le contrôle arriverait plus
tard dans le parcours) ; e-mail seul (aucun coût, mais un attaquant crée autant
de comptes qu'il veut avec des adresses jetables).

**Justification :** la limitation de débit par IP est **inopérante au
Cameroun** — le CGNAT des opérateurs mobiles fait partager une même adresse IP
par des milliers d'abonnés, ce qui la rend à la fois contournable par
l'attaquant et bloquante pour des utilisateurs légitimes
(`THREAT_MODEL.md` M-04). Le SMS est donc le **seul** contrôle qui donne un
coût réel à la création de comptes en masse.

**Requalification :** le SMS n'est pas un canal de notification avec un budget
de confort. C'est le **contrôle anti-Sybil principal**, et son budget est un
budget de sécurité. Prestataire et coût unitaire : **À CONFIRMER**
(`OPEN_QUESTIONS.md` Q-15).

---

## D-014 — Deux rôles d'administration séparés, 2 à 3 administrateurs

**Date :** 2026-09-05 · **Statut :** Actée

- **Administration fonctionnelle** : utilisateurs, catégories de documents,
  retours d'expérience, tableaux de bord.
- **Revue sensible** : images de documents, revendications sur passeport et CNI.

Un compte peut porter les deux rôles, mais tout accès sensible exige la **saisie
d'un motif** et est journalisé séparément. 2FA obligatoire dans les deux cas.
Le nombre d'administrateurs est un chiffre nommé : **2 à 3**.

**Alternatives écartées :** rôle unique (plus simple, mais chaque admin voit
toutes les images à tout moment, et §4.4 demande une séparation) ; double
validation obligatoire sur passeport/CNI (contrôle le plus fort contre un admin
compromis, mais impraticable si une seule personne est disponible).

**Justification :** la journalisation seule est un contrôle **détectif**. La
séparation des rôles et le motif obligatoire ajoutent un contrôle **préventif**,
qui manquait.

---

## D-015 — Le paiement est un module désactivable par configuration

**Date :** 2026-09-05 · **Statut :** Actée

Le paiement est piloté par un drapeau de configuration. S'il est désactivé, le
parcours devient : correspondance → revendication → vérification d'identité →
N3, sans paiement et sans réécriture.

**Alternatives écartées :** paiement câblé et obligatoire (si la réponse
juridique est défavorable, tout le parcours Propriétaire est à reprendre au
jalon 6) ; gratuité d'emblée avec paiement reporté au jalon 9 (élimine le
risque juridique du chemin critique mais laisse la question du financement
entièrement ouverte).

**Justification :** la licéité du modèle payant n'est pas tranchée
(`PAYMENT_DECISION.md`). L'architecture doit survivre à une réponse
défavorable. Le coût de cette souplesse est quasi nul si elle est prévue dès
le modèle de données ; il est élevé si on l'ajoute après coup.

---

## D-016 — Séquence de facturation : vérification avant paiement

**Date :** 2026-09-05 · **Statut :** Actée

L'ordre est : correspondance → revendication → **vérification d'identité
réussie** → paiement → N3. Jamais l'inverse.

**Justification :** facturer avant vérification transformerait chaque faux
positif de rapprochement en litige, et chaque tentative d'usurpation en
recette. Un remboursement automatique et intégral est prévu si le retrait
n'aboutit pas, ou si la correspondance est infirmée.

---

## D-017 — Développement entièrement local ; Supabase et Vercel non testés

**Date :** 2026-09-05 · **Statut :** Actée · **Révise :** les questions Q-04 et Q-05

Le développement se fait intégralement sur un environnement local :
PostgreSQL 16 en local, adaptateurs factices pour le paiement, le SMS et
l'e-mail. **Aucun accès Supabase ni Vercel n'est fourni.**

**Alternative écartée :** fournir les accès pour valider le déploiement et le
pooling au jalon 1, comme le prescrit la §10 du master prompt.

**Conséquence à assumer, énoncée sans atténuation.** La §10 exige que le
déploiement Vercel et le mécanisme asynchrone soient validés **au jalon 1, pas
au dernier**, précisément parce qu'une architecture qui découvre les
contraintes de la plateforme au jalon 8 est à réécrire. **Cette exigence ne
peut pas être satisfaite.** Restent donc non validés :

- le mode de connexion Supabase (direct 5432 contre pooler Supavisor 6543, et
  la compatibilité de ce dernier avec les *prepared statements* de PDO) ;
- la disponibilité des extensions `unaccent`, `pg_trgm` et `fuzzystrmatch` sur
  Supabase — vérifiée en local uniquement (`MATCHING.md` §7) ;
- la latence Supabase ↔ Vercel depuis le Cameroun ;
- le runtime `container` de Vercel, ses régions, ses limites de durée
  d'exécution et son tarif ;
- Vercel Cron.

**Atténuation retenue.** Ce qui peut être validé localement le sera, et ne sera
pas repoussé :

1. Le `Dockerfile.vercel` et le `Caddyfile` sont écrits au jalon 1 et l'image
   FrankenPHP est **construite et exécutée en local**. Cela valide
   l'architecture du conteneur — caches construits à l'image, absence d'état
   sur le système de fichiers, `LOG_CHANNEL=stderr` — sans valider la
   plateforme Vercel.
2. Les endpoints internes (`/internal/queue/drain`, `/internal/cron/match`,
   etc.) sont écrits, protégés, idempotents et **exercés en local par appels
   HTTP répétés**, y compris en concurrence. Cela valide le mécanisme
   asynchrone lui-même, qui est le vrai risque architectural — le
   déclencheur Vercel Cron n'en est que l'ordonnanceur.
3. Toute contrainte de plateforme (§3.1 du master prompt) est respectée dès la
   première ligne : jamais `SESSION_DRIVER=file`, jamais
   `FILESYSTEM_DISK=local`, jamais de worker permanent supposé.

**Marquage.** Tout élément dépendant de Supabase ou de Vercel est marqué
**NON VALIDÉ** dans `docs/DATABASE.md` et `docs/ASYNC.md` au jalon 1, et le
reste jusqu'à ce que des accès soient fournis.

**Risque résiduel, à ne pas minimiser :** le pooler en mode transaction est le
point le plus susceptible de surprendre. S'il s'avère incompatible avec la
configuration retenue, l'impact portera sur la couche de connexion et les
migrations, pas sur le modèle de données ni sur le moteur — le coût d'une
découverte tardive est donc élevé mais circonscrit.

---

## D-018 — Le score de nom est composite, pas purement trigramme

**Date :** 2026-09-05 · **Statut :** Actée · **Amende :** D-007 et `MATCHING.md`

Le rapprochement sur le nom combine similarité trigramme **et** distance de
Levenshtein normalisée, en retenant le maximum des deux
(`MATCHING.md` §3.1.1).

**Origine :** les premières mesures réelles sur PostgreSQL 16
(`MATCHING.md` §3.6) ont montré qu'une faute d'**un seul caractère** — l'erreur
la plus fréquente — ne donne que 0,667 de similarité trigramme. Lorsque le nom
est le seul champ comparable, cas rendu fréquent par D-007 qui a supprimé le
flou sur les numéros, le score final tombait sous le seuil de notification :
**une correspondance valide n'aurait pas été notifiée.**

**Conséquence :** l'extension `fuzzystrmatch`, classée facultative au jalon 0,
devient **bloquante** au même titre que `pg_trgm` et `unaccent`.

**Deux corrections de documentation** consécutives à ces mesures :
- la justification du tri des tokens était **fausse** : `pg_trgm` neutralise
  déjà l'inversion nom/prénom (similarité = 1 sans aucun tri). Le tri reste
  utile pour les comparaisons par égalité, pas pour la similarité ;
- le seuil de 0,90 déclenchant `possible_number_typo` était inatteignable ;
  abaissé à 0,85 sur le score composite.

---

# Conséquences transverses

Ces entrées ne sont pas des décisions mais des **effets** des décisions
ci-dessus, consignés pour qu'ils ne surgissent pas par surprise en cours de
projet.

## C-01 — L'infrastructure de notification remonte du jalon 5 au jalon 2

Deux décisions l'imposent :
- **D-013** (SMS obligatoire à l'inscription) exige l'interface
  `NotificationChannel` et son adaptateur factice dès le **jalon 2**.
- **D-008** (recherche asynchrone) exige des notifications opérationnelles dès
  le **jalon 4**.

La notification n'est donc plus une brique de fin de projet : c'est un
prérequis du socle. Le plan des jalons est réordonné en conséquence.

## C-02 — L'enchaînement « recherche sans résultat → déclarer la perte » change de nature

La §1.3 du master prompt décrit un enchaînement d'écrans. Avec D-008, le
résultat n'est plus disponible à l'écran : la proposition de déclarer la perte
devient une **notification** (« votre recherche n'a rien donné — souhaitez-vous
déclarer la perte ? »), ou une invitation présentée par défaut au moment de la
recherche, avant même de connaître le résultat. À concevoir au jalon 4.

## C-03 — N3 n'a aucun contenu disponible aujourd'hui

D-009 fixe que N3 est un point de retrait tiers. **Aucun partenaire n'existe à
ce jour.** Le problème est plus profond qu'une table à alimenter : si le
Trouveur **conserve** le document au lieu de le déposer, il n'existe aucun
tiers, et N3 ne peut rien contenir d'autre que « le Trouveur le détient » — ce
que D-009 interdit précisément de révéler.

Deux issues, à trancher au **jalon 3** :
1. exiger le **dépôt effectif avant publication** du signalement (le Trouveur
   déclare où il a déposé, l'Administrateur valide) — cohérent avec D-009, mais
   allonge le parcours Trouveur, ce que §9.1 cherche à éviter ;
2. accepter une **mise en relation médiatisée** en repli lorsqu'aucun dépôt n'a
   eu lieu.

En attendant, `deposit_points` est modélisée comme entité administrable, et le
lieu de dépôt saisi par le Trouveur passe en **revue administrateur avant
d'entrer en N3** — c'est du texte libre, donc une fuite potentielle (le
Trouveur peut y recopier le numéro du document).

**Le jalon 6 reste bloqué tant qu'aucun point de retrait réel n'existe.** Ce
n'est pas un obstacle technique : c'est une dépendance opérationnelle, et c'est
à ce jour la question la plus structurante du projet.

## C-04 — Trois décisions dégradent délibérément l'expérience utilisateur

D-005 (numéro absent en N1), D-006 (image jamais visible) et surtout D-008
(recherche différée) rendent le parcours du Propriétaire nettement plus sec que
ce que décrit le cahier des charges. Prises ensemble, elles sont cohérentes
avec l'exigence n°1, qui prime explicitement sur l'exigence n°2. Elles sont
assumées, mais elles doivent être **réévaluées après les premiers essais
utilisateurs**, dans l'ordre de coût décroissant : D-008, puis D-005, puis
D-006.
