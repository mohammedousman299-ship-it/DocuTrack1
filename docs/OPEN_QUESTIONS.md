# Questions ouvertes

**Version :** 1.0 · **Date :** 2026-09-05

Suivi de toutes les questions posées au jalon 0. Une question résolue reste
listée, avec la décision qui la clôt. Les questions de conformité juridique
sont suivies séparément dans `COMPLIANCE_OPEN_QUESTIONS.md`.

---

## 1. Questions ouvertes bloquantes — jalon 1

> **Mise à jour du 2026-09-05 — D-017.** Le développement se fait entièrement
> en local. Les accès Supabase et Vercel **ne seront pas fournis**. Q-04 et
> Q-05 ne bloquent donc plus le démarrage du jalon 1 ; elles deviennent des
> **dettes de validation** qui restent ouvertes jusqu'à ce que des accès
> existent. La §10 du master prompt exigeait cette validation au jalon 1 :
> cette exigence n'est pas satisfaite, et c'est assumé (D-017).

### Q-04 · Accès Supabase ⚠️

**Statut :** ouverte, **dette de validation** (D-017) · **Ne bloque plus le démarrage**

Il faut la chaîne de connexion PostgreSQL, **dans ses deux modes** — direct
(`:5432`) et pooler Supavisor en mode transaction (`:6543`) — et la confirmation
que le rôle applicatif peut exécuter `CREATE EXTENSION`.

À tester une fois l'accès obtenu, et à consigner dans `docs/DATABASE.md` :

- [ ] les deux modes de connexion, avec migrations et transactions Eloquent
      réelles — le mode transaction s'accommode mal des *prepared statements*
      de PDO ;
- [ ] disponibilité et activation de `pg_trgm` et `unaccent` ;
- [ ] latence Supabase ↔ région Vercel, mesurée depuis le Cameroun.

> **Si `pg_trgm` ou `unaccent` sont indisponibles, la conception de
> `MATCHING.md` tombe** et doit être reprise avant d'écrire le moteur. Ce serait
> une remontée immédiate, pas un contournement.

**Précaution :** utiliser un **projet Supabase de développement dédié**, jamais
un projet de production. Aucun secret dans le dépôt — variables
d'environnement uniquement, `.env.example` documenté sans valeur.

### Q-05 · Accès Vercel ⚠️

**Statut :** ouverte, **dette de validation** (D-017) · **Ne bloque plus le démarrage**

Nom du projet, région, et un jeton **à portée limitée et révocable**.

À valider **au jalon 1, pas au dernier** (§10 du master prompt) :

- [ ] runtime `container` fonctionnel avec `Dockerfile.vercel`, `Caddyfile` et
      `vercel.json` ;
- [ ] page de santé accessible ;
- [ ] mécanisme cron + file d'attente opérationnel de bout en bout ;
- [ ] état réel de l'offre — régions, limites de durée d'exécution, tarif —
      **non vérifié à ce jour** (D-011).

**Repli retenu (D-017) :** développement sur PostgreSQL local, avec marquage
explicite **NON VALIDÉ** de tout ce qui dépend de Supabase et de Vercel, et
validation locale de ce qui peut l'être — image FrankenPHP construite et
exécutée localement, endpoints internes exercés par appels HTTP répétés et
concurrents. Ce repli **ne doit pas subsister jusqu'au jalon 8** : le pooler en
mode transaction est le point le plus susceptible de surprendre.

### Q-02b · Lecture du CHANGELOG Livewire 3 → 4

**Statut :** ouverte · **Bloque :** la couche d'interface du jalon 1

D-002 retient Livewire 4 (v4.4.3) mais la décision est **provisoire** : les
ruptures 3→4 et la maturité de l'écosystème n'ont pas été vérifiées. À lire
avant d'écrire le premier composant, et à confirmer ou infirmer dans
`DECISIONS.md`.

---

## 2. Questions ouvertes bloquantes — jalons ultérieurs

### Q-14 · Points de dépôt partenaires ⛔⛔

**Statut :** ouverte · **Bloque :** jalon 6 · **Arbitrage requis au jalon 3**

**Aucun partenaire n'existe.** D-009 fixe que N3 est un point de retrait tiers ;
sans partenaire, **N3 n'a aucun contenu défendable**.

Le problème dépasse l'alimentation d'une table : si le Trouveur conserve le
document au lieu de le déposer, il n'y a aucun tiers. Deux issues à trancher au
jalon 3 (voir `DECISIONS.md` C-03 et `PAYMENT_DECISION.md` §2.1) :

1. dépôt effectif exigé avant publication du signalement ;
2. mise en relation médiatisée en repli.

> **C'est à ce jour la question la plus structurante du projet, et elle n'est
> pas technique.**

### Q-13 · Licéité du modèle payant ⛔

**Statut :** ouverte · **Bloque :** jalon 6

Voir `COMPLIANCE_OPEN_QUESTIONS.md`, blocs A et B. Atténuée par D-015 : le
module de paiement est désactivable, donc l'architecture survit à une réponse
défavorable. Ne bloque pas les jalons 1 à 5.

### Q-15 · Prestataire et budget SMS ⛔

**Statut :** ouverte · **Bloque :** jalon 2 (adaptateur réel), pas le jalon 0

Le SMS est le **contrôle anti-Sybil principal** (D-013), pas un canal de
confort. Il faut : le prestataire (**À CONFIRMER** — aucun nom d'API ni format
ne sera inventé), le coût unitaire réel, et un volume estimé.

Le développement se fait sur adaptateur factice jusque-là. **Mais le passage en
production sans prestataire SMS est impossible** : aucun compte ne pourrait être
vérifié, donc aucune recherche ni revendication ne fonctionnerait.

### Q-16 · Prestataire mobile money

**Statut :** ouverte · **Bloque :** jalon 9

Voir `PAYMENT_DECISION.md` §6. Développement sur adaptateur factice.

### Q-07 · Formats des numéros de documents camerounais

**Statut :** ouverte · **Atténuée par D-012** · **Bloque :** resserrement au jalon 3

Les formats réels ne sont pas connus et ne seront pas inventés. La validation
reste permissive (longueur et alphabet), ce qui ne bloque ni la normalisation ni
le HMAC. À resserrer sur spécimens ou description fiable.

### Q-18 · Montant des frais de service

**Statut :** ouverte · **Bloque :** jalon 6

Les 1 000 XAF du prototype sont une hypothèse d'origine inconnue, pas une
décision. À arbitrer au regard du coût réel du SMS (Q-15).

---

## 3. Questions résolues au jalon 0

| # | Question | Décision |
|---|---|---|
| Q-01 | Sort du prototype existant | **D-001** — archivé en `legacy/`, aucun code migré |
| Q-02 | Livewire 3 ou 4 | **D-002** — Livewire 4, provisoire (voir Q-02b) |
| Q-03 | PHP 8.3 + Pest 4, ou 8.4 + Pest 5 | **D-003** — PHP 8.4 + Pest 5 |
| Q-06 | Rôles exclusifs ou capacités | **D-004** — capacités, admin distinct |
| Q-08 | Recherche synchrone ou différée | **D-008** — asynchrone différée |
| Q-09 | Numéro masqué ou absent en N1 | **D-005** — absent, plus strict que la spec |
| Q-10 | Image visible du Propriétaire | **D-006** — jamais, même en N3 |
| Q-11 | Chiffrement des numéros | **D-007** — chiffré + HMAC, perte du flou sur numéro |
| Q-12 | Durées de rétention | **D-010** — 180/180/90 j, audit 3 ans (à valider juridiquement) |
| Q-17 | Contenu de N3 | **D-009** — point de retrait tiers |
| Q-19 | Câblage du paiement | **D-015** — module désactivable |
| Q-20 | Séquence de facturation | **D-016** — vérification avant paiement |
| Q-21 | Administrateurs | **D-014** — 2 à 3, deux rôles séparés, motif obligatoire |
| Q-22 | Vérification SMS | **D-013** — obligatoire à l'inscription |

---

## 4. Questions apparues pendant le jalon 0

Ces questions n'étaient pas dans la liste initiale : elles sont nées de la
conception elle-même.

### Q-23 · Comment présenter la recherche différée à l'utilisateur

**Statut :** ouverte · **Jalon 4**

D-008 supprime le résultat à l'écran. La §1.3 du cahier des charges prévoyait
l'enchaînement « recherche sans résultat → proposer de déclarer la perte », qui
n'est plus un enchaînement d'écrans (`DECISIONS.md` C-02).

Piste à valider : proposer la déclaration de perte **par défaut, au moment de la
recherche**, avant même d'en connaître le résultat. Cela supprime l'impasse
sans attendre la notification.

### Q-24 · Comment dire à l'utilisateur de laisser le numéro vide s'il doute

**Statut :** ouverte · **Jalon 4**

Conséquence de D-007 (`MATCHING.md` §3.5) : un numéro **absent** est neutre, un
numéro **faux** est éliminatoire. C'est contre-intuitif — l'utilisateur croit
bien faire en remplissant un maximum de champs. L'interface doit le dire
explicitement, sans effrayer ni décourager la saisie quand elle est fiable.

### Q-25 · Le cas légitime « je déclare pour un proche »

**Statut :** ouverte · **Jalon 4**

M-02 impose un contrôle de cohérence entre le nom du compte et le nom déclaré.
Mais déclarer la perte pour un parent âgé, un enfant ou un conjoint est un cas
réel et fréquent. Il ne doit pas être traité par une case à cocher, qui
contournerait le contrôle ; il relève de la revue humaine. Reste à concevoir un
parcours qui ne soit ni humiliant pour l'utilisateur légitime, ni exploitable.

### Q-26 · Rotation de la clé HMAC

**Statut :** ouverte · **Jalon 1**

D-007 impose que la rotation de la clé HMAC recalcule tous les `number_hmac`.
La procédure doit être écrite **avant qu'il y ait des données**, quand elle est
encore triviale.

### Q-27 · Charge de travail de la file de revue administrateur

**Statut :** ouverte · **Jalon 5**

Plusieurs décisions envoient des cas en revue humaine : incohérence de nom
(M-02), score intermédiaire, `possible_number_typo`, doublons, Trouveurs à
faible score, types sensibles. **Cumulées, ces files peuvent excéder ce que 2 à
3 administrateurs peuvent traiter.** Une file ingérable équivaut à une absence
de revue. À mesurer sur le jeu synthétique au jalon 5, avant d'être découvert en
production.

---

### Q-28 · Les seuils 0,75 / 0,55 sont-ils trop élevés ?

**Statut :** ouverte · **Jalon 5**

Les premières mesures (`MATCHING.md` §3.6) montrent qu'avec le trigramme seul,
une faute d'un caractère donnait 0,667 — sous le seuil de notification. Le
score composite de D-018 corrige ce cas précis, mais **suggère que le
calibrage global des seuils reste à établir**, et pas seulement la formule.
À trancher par le balayage complet du §6 de `MATCHING.md`, pas par intuition.

---

## 5. Plan des jalons révisé et ce qui le bloque

Réordonné par rapport au master prompt : `DECISIONS.md` C-01 fait remonter
l'infrastructure de notification du jalon 5 au jalon 2.

| Jalon | Contenu | Bloqué par |
|---|---|---|
| **0** | Cadrage documentaire | — **terminé** |
| **1** | Socle technique, migrations, conteneur local, cron + file | **terminé** — *Q-04, Q-05, Q-26 en dette (D-017)* |
| **2** | Comptes, **vérification SMS**, notifications, Policies, limitation de débit, tests de refus | **terminé** — *Q-15 bloque la production, pas le développement* |
| **3** | Parcours Trouveur, upload, suppression EXIF, doublons | Q-07, **arbitrage Q-14** |
| **4** | Déclaration de perte, recherche N1, **notifications de résultat** | Q-23, Q-24, Q-25 |
| **5** | Moteur de rapprochement, seuils, métriques | Q-27 |
| **6** | Revendication, N2/N3, paiement | **Q-13, Q-14**, Q-18 |
| **7** | Administration, files de revue, journal d'audit | — |
| **8** | Durcissement, E2E, accessibilité, performance, purge | — |
| **9** | Adaptateurs réels paiement et SMS | Q-15, Q-16 |

**Les jalons 1 à 5 sont réalisables sans réponse juridique.** Seul le jalon 6
en dépend, et le jalon 3 doit trancher l'arbitrage Q-14 sans attendre.

---

## 6. État du jalon 0

| Livrable | Statut |
|---|---|
| `AUDIT_PROTOTYPE.md` | ✅ (ajouté — l'hypothèse « pas de code existant » était fausse) |
| `DECISIONS.md` | ✅ 16 décisions, 4 conséquences transverses |
| `THREAT_MODEL.md` | ✅ 13 menaces, dont 7 non couvertes par la §4 du master prompt |
| `DISCLOSURE_LEVELS.md` | ✅ champ par champ, 3 écarts assumés |
| `DATA_MODEL.md` | ✅ 16 entités, justification par champ, diagramme |
| `MATCHING.md` | ✅ conception — **aucune valeur mesurée** |
| `PERMISSIONS.md` | ✅ matrice + tests de refus par jalon |
| `PAYMENT_DECISION.md` | ✅ 2 questions sur 3 tranchées |
| `COMPLIANCE_OPEN_QUESTIONS.md` | ✅ 24 questions, aucune réponse |
| `OPEN_QUESTIONS.md` | ✅ ce document |

**Volontairement absents du jalon 0 :** `DATABASE.md`, `STORAGE.md`,
`ASYNC.md`, `PERFORMANCE.md`. Ils doivent contenir des **mesures réelles**, pas
des suppositions. Ils sont produits au jalon 1.

**Aucun code applicatif n'a été écrit** au jalon 0, conformément à la §10 du
master prompt.

---

## 7. État du jalon 1

| Livrable | Statut | Vérification |
|---|---|---|
| Prototype archivé en `legacy/` | ✅ | — |
| Laravel 13.30.1, Livewire 4.4.3, Pest 5.1.3, PHP 8.4 | ✅ | `php artisan --version` |
| Contraintes de plateforme dès la première ligne | ✅ | jamais `file`, jamais `local` |
| `docker-compose` : PostgreSQL + MinIO, bucket privé | ✅ | — |
| 19 migrations, 16 entités | ✅ | appliquées, annulées, réappliquées |
| Extensions `unaccent`, `pg_trgm`, `fuzzystrmatch` | ✅ | `CREATE EXTENSION` et exécution |
| Normalisation SQL + miroir PHP | ✅ | test d'équivalence, 2 défauts trouvés |
| Seeders : 8 catégories, seuils de rapprochement | ✅ | — |
| **`EXPLAIN` sur 100 000 lignes** | ✅ **VALIDÉ** | *index scan* sur les 3 chemins |
| File d'attente + endpoints internes | ✅ | 25 tests, verrou vérifié en conteneur |
| Tailwind, jetons bleu/vert/blanc, galerie `/dev/ui` | ✅ | 13 paires de contraste mesurées |
| `Dockerfile.vercel` **construit et exécuté** | ✅ | conteneur sain, `/up` à 200 en 8 ms |
| CI GitHub Actions, PHPStan niveau 6 | ✅ | 0 erreur |
| `DATABASE.md`, `ASYNC.md`, `STORAGE.md`, `PERFORMANCE.md` | ✅ | mesures réelles uniquement |

**Suite de vérification :** Pint ✅ · PHPStan niveau 6, 0 erreur ✅ ·
**35 tests, 97 assertions, tous passants** ✅

**Restent en dette de validation (D-017) :** tout ce qui dépend de Supabase et
de Vercel — mode de connexion, pooler, extensions côté Supabase, latence,
runtime container, Vercel Cron.

---

## 8. État du jalon 2

| Livrable | Statut | Vérification |
|---|---|---|
| `User` réécrit sur le schéma réel | ✅ | 11 tests |
| Couche notification, contenu minimal **structurel** | ✅ | 12 tests |
| Fortify : inscription, connexion | ✅ | 9 tests |
| Vérification e-mail par la file | ✅ | 3 tests |
| **Vérification SMS** — contrôle anti-Sybil | ✅ | 12 tests |
| 2FA administrateur obligatoire (TOTP) | ✅ | 11 tests |
| Gates et middleware, **404 au lieu de 403** | ✅ | inclus ci-dessus |
| Limitation de débit, l'IP en signal seulement | ✅ | — |
| Page d'accueil, mobile-first 360 px | ✅ | — |
| **CSP stricte**, mesurée au navigateur | ✅ | 7 tests + mesure Chromium |
| Durcissement de session, CSRF | ✅ | 5 tests |
| Mesures 3G et requêtes SQL | ✅ | `PERFORMANCE.md` §2-3 |

**Suite de vérification :** Pint ✅ · PHPStan niveau 6, 0 erreur ✅ ·
**115 tests, 265 assertions, tous passants** ✅

### Questions nées du jalon 2

**Q-29 · L'écran d'activation de la 2FA reste à construire**
*Statut :* ouverte · *Jalon :* 3

Le middleware exige une 2FA confirmée et redirige vers `two-factor.setup`, mais
cet écran ne présente pas encore le code QR ni les codes de récupération. **Un
administrateur ne peut donc pas encore activer sa 2FA par l'interface**, ce qui
rend l'espace d'administration inaccessible en pratique. Sans conséquence tant
qu'aucune fonction d'administration n'existe (jalon 7), mais à traiter avant.

**Q-30 · Les écrans Fortify secondaires sont des ébauches**
*Statut :* ouverte · *Jalon :* 3

Mot de passe oublié, réinitialisation, confirmation de mot de passe et défi 2FA
affichent un gabarit minimal. Les routes fonctionnent, les vues sont à écrire.

**Q-31 · `style-src 'unsafe-inline'` reste dans la CSP**
*Statut :* ouverte · *Jalon :* 8

Seul écart à une CSP pleinement stricte, dû aux attributs `style` produits par
Blade et Tailwind. Permet l'exfiltration par CSS, pas l'exécution de code
(`SECURITY_HEADERS.md` §5).
