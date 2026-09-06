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

## D-019 — MinIO en développement, pas de disque local

**Date :** 2026-09-05 · **Statut :** Actée

Le stockage objet de développement est **MinIO**, compatible S3, lancé par
`docker-compose`. `FILESYSTEM_DISK=local` n'existe nulle part, développement
compris.

**Justification :** le code exerce le driver `s3` et les URL signées dès le
développement, exactement comme en production. Le disque local entraînerait le
code au mauvais motif et reporterait tous les problèmes au branchement du
stockage réel.

L'arbitrage Supabase Storage / Vercel Blob reste **non tranché**, faute
d'accès (`STORAGE.md` §2). Aucune de ses cases ne sera remplie de mémoire.

---

## D-020 — Aucune police téléchargée, pile système

**Date :** 2026-09-05 · **Statut :** Provisoire — à reconsidérer avec l'identité visuelle

La police web du squelette Laravel a été retirée au profit d'une pile système.

**Justification :** sur un réseau 3G, chaque graisse embarquée retarde le
premier affichage utile de plusieurs centaines de millisecondes. Le cahier des
charges demande « un caractère clair et moderne » ; une pile système récente y
répond, et l'arbitrage penche vers l'exigence n°3.

**À reconsidérer** si une identité visuelle formelle impose une police propre.
Le changement ne toucherait qu'un jeton dans `resources/css/app.css`.

---

## D-021 — Empreintes stockées en hexadécimal, pas en `bytea`

**Date :** 2026-09-05 · **Statut :** Actée · **Amende :** D-007

`number_hmac`, `duplicate_fingerprint`, `content_hash` et
`submitted_fields_hash` sont des `varchar(64)` hexadécimaux.

**Origine :** défaut rencontré à l'exécution. PDO refuse d'insérer des octets
bruts dans une colonne `bytea` sans liaison LOB explicite, et l'erreur produite
est illisible. L'hexadécimal s'indexe et se compare aussi bien, pour 64 octets
au lieu de 32.

`number_encrypted` est en `text` : le cast chiffré de Laravel produit du base64.

---

## D-022 — Laravel Fortify comme base d'authentification

**Date :** 2026-09-05 · **Statut :** Actée · **Jalon :** 2

Fortify, sans interface : il fournit la logique (inscription, connexion,
réinitialisation, 2FA TOTP), les vues sont écrites avec nos composants Blade.

**Alternatives écartées :** Breeze (échafaude une interface qu'il faudrait
largement réécrire pour nos composants, et n'inclut pas la 2FA, obligatoire
pour l'administrateur) ; authentification maison (réécrire connexion,
réinitialisation, limitation de débit et TOTP sur un projet où la sécurité est
l'exigence n°1 ajoute du risque là où du code éprouvé existe).

**Justification :** nos parcours s'écartent nettement du standard —
vérification téléphone bloquante, 2FA administrateur obligatoire, recherche
différée. Fortify laisse cette liberté ; Breeze la contrarie.

---

## D-023 — 2FA administrateur par TOTP, jamais par SMS

**Date :** 2026-09-05 · **Statut :** Actée

Application d'authentification (code à 6 chiffres) et codes de récupération.

**Alternatives écartées :** SMS (plus familier, aucune application à installer
— mais vulnérable au **SIM swap**, qui est précisément la menace M-13, sur le
compte qui voit toutes les images de documents) ; TOTP avec repli SMS (le
repli ramène la vulnérabilité du canal le plus faible, qu'un attaquant
choisira toujours ; les codes de récupération répondent au même besoin sans cet
inconvénient).

**Justification :** le compte administrateur est le point de collecte ultime
(M-09). Sa 2FA ne doit pas dépendre d'un canal qu'un attaquant peut détourner
en prenant le contrôle d'un numéro de téléphone.

---

## D-024 — La page d'accueil n'annonce aucun frais tant que le paiement est désactivé

**Date :** 2026-09-05 · **Statut :** Actée · **Lié à :** D-015

Le texte relatif aux frais de service est **conditionné au drapeau
`PAYMENT_ENABLED`** : absent quand il vaut `false`, affiché automatiquement
quand il passe à `true`.

**Alternatives écartées :** annoncer les frais dès maintenant (conforme à la
lettre du §9.1, mais communiquerait publiquement sur un modèle payant dont la
licéité n'est pas établie et qui pourrait ne jamais exister) ; formulation
conditionnelle prudente (le flou peut inquiéter davantage qu'un montant clair
ou qu'un silence).

**Justification :** le §9.1 exige la transparence sur le coût **avant que
l'utilisateur ne s'engage**. Cette exigence est respectée dès lors que le texte
apparaît en même temps que les frais eux-mêmes. Annoncer des frais qui
n'existent pas découragerait des Trouveurs et des Propriétaires sans
contrepartie — et le Trouveur est déjà l'acteur dont il faut le plus ménager le
parcours (§9.1).

---

## D-025 — En-têtes de sécurité et CSP posés au jalon 2, pas au jalon 8

**Date :** 2026-09-05 · **Statut :** Actée · **S'écarte du découpage du master prompt**

CSP stricte, HSTS, `X-Content-Type-Options`, `Referrer-Policy` et
`Permissions-Policy` sont mis en place au jalon 2, alors que le §10 place le
durcissement au jalon 8.

**Justification :** une CSP compatible Livewire et Alpine s'établit bien plus
facilement sur trois écrans que sur trente — chaque violation est
immédiatement rattachable au composant qui l'a causée. Ajoutée au jalon 8, elle
casserait des composants écrits entre-temps, et la pression serait alors
d'assouplir la politique plutôt que de corriger les composants.

**Risque connu, à établir au début du travail :** une CSP réellement stricte,
sans `unsafe-eval`, impose des contraintes à Alpine, dont les expressions sont
évaluées dynamiquement. Une variante compatible CSP existe, au prix d'une
syntaxe d'expressions restreinte. **Je ne connais pas avec certitude son état
actuel ni son interaction avec Livewire 4 :** ce sera mesuré et rapporté avant
d'écrire les composants, et la configuration retenue sera documentée (§4.6).

---

## D-026 — Livewire en mode compatible CSP (`csp_safe`)

**Date :** 2026-09-06 · **Statut :** Actée · **Résout le risque ouvert de D-025**

`livewire.csp_safe` est réglé à `true`. Ce n'est pas un réglage cosmétique :
à `false`, l'interface entière cesse de répondre sous notre CSP.

**Origine — une mesure, pas une supposition.** D-025 signalait un risque non
évalué : une CSP sans `unsafe-eval` contraint Alpine, dont les expressions sont
évaluées dynamiquement. La mesure au navigateur (Chromium via Playwright, sur
un composant Livewire réel) a confirmé le risque puis l'a levé :

| Configuration | Interaction | Violations CSP |
|---|---|---|
| `csp_safe = false` | **morte** | **2** (`unsafe-eval` refusé) |
| `csp_safe = true` | fonctionnelle | **0** |

Le composant s'affichait dans les deux cas — le rendu initial est fait côté
serveur — mais sans ce réglage aucun clic ne produisait d'effet. Une CSP posée
sans cette mesure aurait donc livré une interface silencieusement inerte.

**Crainte levée :** les expressions Alpine inline ordinaires fonctionnent sous
ce mode ; aucune réécriture de composants n'est nécessaire. Cela vaut pour les
cas mesurés, pas pour toute construction Alpine imaginable.

**Verrouillage :** deux tests figent le résultat — l'un interdit l'apparition
de `unsafe-eval` dans `script-src`, l'autre vérifie que `csp_safe` reste actif.
Sans eux, la tentation le jour d'un composant récalcitrant serait d'assouplir
la politique plutôt que de corriger le code.

Détail complet : `docs/SECURITY_HEADERS.md`.

---

## D-027 — Livewire 4 utilise des composants monofichiers

**Date :** 2026-09-06 · **Statut :** Constat · **Confirme D-002**

`php artisan make:livewire` produit un **composant monofichier** dans
`resources/views/components/`, mêlant classe PHP et gabarit Blade, au lieu du
couple classe + vue de Livewire 3.

Ce n'est pas une décision mais un constat, consigné parce qu'il confirme que
D-002 (retenir Livewire 4) portait une rupture réelle : la structure des
fichiers de composants diffère de celle du master prompt et de la documentation
Livewire 3. Aucun blocage rencontré ; les composants d'interface existants,
qui sont des composants Blade et non Livewire, ne sont pas concernés.

---

## D-028 — Mise en relation médiatisée en repli du dépôt

**Date :** 2026-09-06 · **Statut :** Actée · **Amende D-009 · Résout C-03 et Q-14**

Le dépôt auprès d'un tiers reste le mode **privilégié** et mis en avant.
Lorsqu'aucun dépôt n'a eu lieu, N3 devient une **messagerie interne
anonymisée** entre le Propriétaire et le Trouveur : journalisée, sans échange
de coordonnées, avec possibilité de signalement.

**Alternatives écartées :** dépôt effectif exigé avant publication (cohérent
avec D-009 et seul modèle où N3 a toujours un contenu, mais il impose au
Trouveur un déplacement **avant tout résultat** — or chaque obstacle réduit le
nombre de signalements, donc l'utilité de la plateforme entière) ; statu quo
(ne tranche rien, laisse le jalon 6 bloqué et fait croître la charge de revue).

**Justification :** c'est la seule option qui fonctionne **dès aujourd'hui,
sans partenaire**, tout en gardant le parcours Trouveur court. Elle débloque
C-03 et le jalon 6.

### Ce que cette décision réintroduit, et qu'il faut regarder en face

D-009 écartait la messagerie interne pour deux raisons qui **restent valables**
et deviennent maintenant des risques à traiter, non des objections levées :

1. **Rien n'empêche les parties d'échanger des numéros en clair dans les
   messages.** La médiation devient alors nominale et l'on retombe sur le cas
   du contact direct, que D-009 refusait — mais en ayant coûté un module.
2. **La plateforme met deux inconnus en relation**, ce que le point de retrait
   tiers évitait par construction. Le marché de la rançon n'est pas supprimé,
   il est rendu traçable.

Contrôles retenus en conséquence (détaillés en M-14) : détection de motifs
ressemblant à un numéro de téléphone, journalisation intégrale, signalement par
les utilisateurs, plafond de messages, accès **conditionné à une vérification
d'identité réussie** — jamais un simple candidat au rapprochement.

**Le dépôt reste préférable et l'interface doit le dire.** La messagerie est un
repli, pas une alternative équivalente.

---

## D-029 — EXIF : le client nettoie, le serveur vérifie

**Date :** 2026-09-06 · **Statut :** Actée · **Résout une contradiction du cahier des charges**

Le §3.3 interdit que l'image transite par le conteneur PHP ; le modèle de
menaces (M-12) exige une suppression EXIF **côté serveur**. Les deux ne peuvent
pas être vrais simultanément : si le navigateur écrit directement dans le
stockage, le serveur ne voit jamais les octets avant enregistrement.

**Résolution retenue :**
1. le navigateur réencode l'image via un `<canvas>`, ce qui supprime les
   métadonnées, puis l'envoie **directement** au stockage par URL pré-signée ;
2. une **tâche de file** télécharge ensuite le fichier, vérifie l'absence
   d'EXIF, renettoie si nécessaire, puis renseigne `exif_stripped_at` ;
3. tant que ce champ est nul, la pièce jointe **n'est jamais servie** — la
   règle existe déjà dans le modèle de données.

**Alternatives écartées :** transit par le serveur PHP (garantie immédiate,
mais contredit frontalement le §3.3 et se heurtera aux limites d'exécution de
la plateforme sur des photos de téléphone récentes) ; confiance au client seul
(un attaquant détenant une URL pré-signée y dépose ce qu'il veut sans passer
par notre code, et le garde-fou deviendrait une fiction).

**Limite assumée :** un fichier non nettoyé peut exister brièvement dans le
stockage, entre l'envoi et le passage de la tâche. Il n'est jamais servi, mais
il existe. La fenêtre est bornée par la fréquence du cron.

---

## D-030 — Photo obligatoire pour les seuls documents sensibles

**Date :** 2026-09-06 · **Statut :** Actée

La photo est **facultative** en général, et **obligatoire** pour les types dont
`sensitivity = high` (carte nationale d'identité, passeport, permis de
conduire, attestation d'identité).

**Justification :** ces types imposent une revue humaine avant restitution
(M-01). Sans image, l'administrateur n'a rien à vérifier et le contrôle
deviendrait décoratif. Partout ailleurs, le parcours du Trouveur reste au plus
court, conformément au §9.1.

**Alternatives écartées :** toujours facultative (parcours le plus court, mais
la revue humaine perdrait son support principal et la vérification reposerait
entièrement sur la preuve par la connaissance — qui échoue face à un proche,
`THREAT_MODEL.md` §6.1) ; toujours obligatoire (c'est la violation V7 relevée
dans le prototype : elle bloque tout Trouveur sans caméra ni fichier, et chaque
signalement perdu est un document non restitué).

---

## D-031 — Les écrans d'authentification inachevés sont traités au jalon 3

**Date :** 2026-09-06 · **Statut :** Actée · **Résout Q-29 et Q-30**

L'écran d'activation de la 2FA et les vues Fortify secondaires (mot de passe
oublié, réinitialisation, confirmation, défi 2FA) sont complétés en fin de
jalon 3.

**Justification :** sans l'écran d'activation, aucun administrateur ne peut
activer sa 2FA, donc **l'espace d'administration est inaccessible en
pratique** — or le jalon 3 a besoin d'un administrateur opérationnel pour
valider les signalements suspects (M-06) et les points de dépôt.

---

## D-032 — Aucune logique dans les expressions Alpine

**Date :** 2026-09-06 · **Statut :** Actée · **Précise D-026**

Les attributs Alpine ne portent que des expressions **simples** : accès de
propriété, appel court, ternaire. Toute logique — chaîne de promesses, fonction
fléchée, bloc multi-instructions — vit dans un module JavaScript chargé comme
script externe et se branche par **délégation d'évènement**.

**Origine — une mesure, encore une fois.** D-026 notait que les expressions
Alpine inline fonctionnaient sous le mode compatible CSP, en précisant que cela
ne valait **que pour les cas mesurés**. Le premier composant réel l'a démenti :
le gestionnaire d'envoi de fichier, un bloc multi-instructions avec fonctions
fléchées, produisait `CSP Parser Error: Unexpected token` — et **l'échec était
silencieux pour l'utilisateur**. Le champ ne réagissait tout simplement pas.

C'est le risque qui justifiait de poser la CSP au jalon 2 plutôt qu'au jalon 8 :
découvert sur un composant, il aurait été découvert sur trente.

**Conséquence pratique :** un module externe pour la logique, des attributs
`data-*` pour la déclarer, et la délégation d'évènement pour survivre aux
rendus Livewire.

---

## D-033 — `upgrade-insecure-requests` seulement en HTTPS

**Date :** 2026-09-06 · **Statut :** Actée

La directive n'est émise que lorsque la page est elle-même servie en HTTPS.

**Origine :** elle réécrit en `https://` **toute** requête `http://` de la
page, y compris vers d'autres origines. Sur une installation locale servie en
clair, elle cassait l'envoi direct vers le stockage objet : le navigateur
tentait `https://` sur un service qui n'écoute qu'en `http`, sans message
d'erreur exploitable. En production, page et stockage sont en HTTPS et la
directive reprend tout son sens.

---

## D-034 — Un seul gabarit de notification de recherche

**Date :** 2026-09-06 · **Statut :** Actée · **Corrige un défaut du jalon 2**

La fin d'une recherche produit **un message unique**, identique que le système
ait trouvé quelque chose ou non : « votre recherche est terminée, connectez-vous
pour consulter le résultat ». Le gabarit `search_no_result` est supprimé.

**Origine — un défaut que j'avais introduit.** Le jalon 2 a créé deux gabarits
distincts, `search_completed` et `search_no_result`. Or un SMS s'affiche sur un
écran verrouillé : deux messages différents **révèlent le résultat sans
connexion**, et rendent à un attaquant le signal binaire que la recherche
différée (D-008) avait précisément pour but de supprimer. Il lui suffirait de
lire ses SMS au lieu d'itérer sur le site — et les quotas comme la
journalisation ne verraient rien passer.

Le contenu minimal exigé par M-10 portait sur les données ; il porte désormais
aussi sur **le fait même du résultat**.

**Coût assumé :** un utilisateur légitime doit se connecter pour apprendre
qu'il n'y a rien. C'est une friction réelle sur un parcours déjà différé.

**Écarté :** le délai d'envoi aléatoire, qui fermerait en plus le canal
temporel résiduel, mais allongerait l'attente de tout le monde. À reconsidérer
si une corrélation entre délai de traitement et résultat est constatée.

---

## D-035 — Un seul parcours : déclarer, la recherche en découle

**Date :** 2026-09-06 · **Statut :** Actée · **S'écarte de la §1.3 · Résout C-02 et Q-23**

Le Propriétaire décrit son document **une seule fois**. Le système confronte
immédiatement sa déclaration aux signalements existants **et** la conserve
active pour les signalements futurs.

**Alternatives écartées :** deux actions distinctes, conformes à la lettre de
la §1.3 (deux saisies des mêmes informations, un enchaînement à concevoir
malgré le différé, et une recherche qui échappe au contrôle de cohérence du
nom) ; déclaration optionnelle cochée par défaut (créerait des déclarations que
l'utilisateur ignore avoir faites).

**Justification :**
1. **Sécurité** — le contrôle de cohérence du nom (M-02) s'applique dès la
   première saisie. Avec deux actions séparées, la recherche seule y échappait,
   ce qui laissait un chemin non contrôlé vers l'information N1.
2. **C-02 disparaît** : il n'y a plus d'« enchaînement recherche sans résultat
   → déclaration » à concevoir, puisqu'il n'y a plus deux étapes.
3. Une seule saisie, sur un parcours déjà rendu plus sec par le différé.

**Écart assumé :** la §1.3 décrit deux fonctions distinctes et le réflexe de
chercher avant de déclarer. L'interface doit donc **présenter l'action comme une
recherche** — c'est ce que l'utilisateur croit faire — tout en expliquant
qu'elle vaut aussi pour l'avenir.

---

## D-036 — Une incohérence de nom part en revue, sans case à cocher

**Date :** 2026-09-06 · **Statut :** Actée · **Résout Q-25**

Une déclaration dont le nom ne correspond pas au compte est **acceptée**, mais
ne déclenche **aucune notification automatique** : elle part en file de revue
administrateur.

**Aucune case « je déclare pour un proche ».** Elle ne serait qu'un
contournement en un clic : un attaquant la coche comme n'importe qui, et le
contrôle deviendrait décoratif — or déclarer au nom d'autrui est le vecteur le
moins coûteux du système (M-02).

**Alternative écartée :** interdire toute déclaration pour autrui (contrôle
absolu, mais exclut des cas légitimes nombreux — parent âgé, enfant, conjoint,
personne peu à l'aise avec le numérique — dans un contexte où l'entraide
familiale est la norme).

**Coût assumé :** charge de revue supplémentaire, sur une file dont la
soutenabilité est déjà une question ouverte (Q-27).

---

## D-037 — Pas de CAPTCHA : blocage progressif et revue

**Date :** 2026-09-06 · **Statut :** Actée · **S'écarte de la §4.2**

Au-delà du seuil de détection d'énumération, le compte est ralenti puis bloqué
temporairement, avec alerte administrateur. Aucun CAPTCHA.

**Justification, chiffrée :**
1. Il reste **~2,6 ko** de budget JavaScript compressé (Q-32). Un CAPTCHA tiers
   le ferait dépasser.
2. Un CAPTCHA tiers transmet des données de navigation d'utilisateurs à un
   service externe — sur une plateforme dont l'exigence n°1 est la protection
   des données personnelles.
3. Le vrai coût d'entrée reste la **vérification SMS** (D-013), qui est déjà le
   contrôle anti-Sybil principal.

**Alternative écartée :** une épreuve maison sans JavaScript, qui respecterait
les deux contraintes mais serait bien plus facile à automatiser qu'un CAPTCHA
professionnel — elle gênerait surtout les utilisateurs légitimes.

**Écart assumé et réversible :** la §4.2 prévoit explicitement un CAPTCHA. Si
l'énumération est constatée en production malgré le blocage progressif, la
décision est à reprendre — et le coût en budget JavaScript devra alors être
payé sciemment.

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
