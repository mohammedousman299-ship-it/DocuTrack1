# Audit du prototype existant

**Date de l'audit :** 2026-09-05
**Périmètre :** commit `c61c70b` (`updated2`), branche `main`
**Statut :** clos — décision prise (voir `DECISIONS.md`, D-001)

---

## 1. Pourquoi cet audit existe

Le master prompt posait l'hypothèse (a) : « le projet démarre sans base de code
existante ». Cette hypothèse est **infirmée**. Le dépôt contient un prototype
front-end fonctionnel, et il devait être audité avant toute décision
d'architecture.

## 2. Inventaire

| Fichier | Taille | Lignes | Statut |
|---|---:|---:|---|
| `index.html` | 90 805 o | 1 611 | **Vivant** — contient toute la maquette |
| `app.js` | 12 881 o | — | **Code mort** — référencé nulle part |
| `style.css` | 806 o | — | **Code mort** — référencé nulle part |
| `.gitattributes` | 66 o | — | Normalisation LF, à conserver |

Dépendances externes : Bootstrap 5.3.0 et FontAwesome 6.4.0, chargés par CDN.

### 2.1 Deux générations superposées

`app.js` et `style.css` ne sont chargés par aucune balise de `index.html`. Ce
sont les restes d'une génération antérieure de la maquette. Les identifiants
divergent des deux côtés, ce qui confirme qu'ils ne peuvent pas fonctionner
ensemble :

| Concept | `app.js` (mort) | `index.html` (vivant) |
|---|---|---|
| Navigation | `showSection()` | `navigateTo()` |
| Écran d'accueil | `landing` | `landingPage` |
| Champ « type » du signalement | `reportType` | `repType` |
| Persistance | `localStorage` | objet `db` en mémoire |

Deux fonctions homonymes (`handleReportSubmit`, `handleLogin`, `startCamera`)
existent dans les deux fichiers avec des corps différents. Toute tentative de
réutilisation partielle produirait des collisions silencieuses.

## 3. Nature réelle du prototype

C'est une **maquette de démonstration cliquable, sans back-end**. Il n'y a ni
serveur, ni base de données, ni persistance au-delà du rechargement de page.

- L'objet `db` (l. 1127) est une structure JavaScript en dur : 2 utilisateurs,
  8 catégories de documents, 1 signalement, 1 déclaration, 2 retours.
- L'authentification (l. 1253) est factice : la saisie de
  `admin@docutrack.cm` donne le rôle Administrateur, n'importe quelle autre
  valeur donne un compte utilisateur nommé « John Doe ». **Le mot de passe
  n'est jamais lu.**
- Le « paiement » (l. 1388) est un `setTimeout` de 1 200 ms.

Ce n'est pas une critique du prototype : c'est exactement ce qu'une maquette
de parcours doit être. Le point important est qu'il ne contient **aucune**
brique réutilisable pour un système de production.

## 4. Ce qui a de la valeur — conservé comme spécification

Ces éléments sont repris dans la conception, en tant qu'**intention produit**,
sans reprise de code :

1. **Le parcours complet des trois acteurs** sur ~14 écrans. Il valide que la
   description fonctionnelle du cahier des charges tient debout de bout en bout.
2. **La taxonomie des 8 catégories de documents camerounais** (`db.categories`,
   l. 1131) : CNI, passeport ordinaire, permis de conduire, attestation
   d'identité, acte de naissance, carte CNPS, diplômes, carte d'étudiant. Elle
   sert de jeu de départ pour la table `document_types`.
3. **Le champ `depositPoint`** (l. 1149) — « Police Station 1st District,
   Yaoundé ». La maquette avait implémenté la remise médiatisée avant que la
   spécification ne pose la question. C'est le modèle retenu pour N3 (D-009).
4. **Le squelette i18n** par attributs `data-i18n` avec un dictionnaire fr/en,
   qui confirme que le bilinguisme a été pensé dès le départ.
5. **Les paramètres économiques affichés** : 1 000 XAF de frais de service,
   canaux MTN Mobile Money et Orange Money. À traiter comme des hypothèses
   d'origine inconnue, pas comme des décisions validées (voir
   `PAYMENT_DECISION.md`).

## 5. Violations de garde-fous constatées

Ces constats portent sur le prototype tel qu'il est, et servent de liste de
contrôle négative pour l'implémentation : **aucun de ces motifs ne doit
réapparaître.**

| # | Constat | Emplacement | Garde-fou violé (§12 du master prompt) |
|---|---|---|---|
| V1 | Nom d'une personne réelle utilisé comme donnée de démonstration et comme `placeholder` de formulaire | l. 327, 430, 1146 | « Jamais de données réelles de personnes dans le dépôt, les tests ou les jeux de démonstration » |
| V2 | La recherche affiche le nom complet du propriétaire et le lieu précis dès le premier écran | l. 1364-1380 | Divulgation N2/N3 servie en N1 |
| V3 | Le masquage est **rédactionnel** : le texte annonce que l'information est cachée alors que `db` est intégralement en clair côté client. Un `console.log(db)` livre tout | l. 1379 | « Le masquage est **serveur** » |
| V4 | Déblocage de l'information complète après un `setTimeout`, sur retour client | l. 1388-1405 | « Jamais de déblocage N3 sur la seule base d'un retour client de paiement » |
| V5 | `fuzzyMatch()` est une inclusion de sous-chaîne : chercher `"a"` remonte tout | l. 1356-1361 | Anti-énumération, anti-recherche à l'aveugle |
| V6 | L'image du document est encodée en base64 (`canvas.toDataURL`) dans un `<input type="hidden">` puis conservée dans l'objet en mémoire | l. 375, 1300 | Image de document exposée dans le DOM |
| V7 | La photo par webcam est **obligatoire** pour signaler un document | l. 1307-1311 | Contredit §9.1 « le parcours du Trouveur doit être le plus court du site » |

**Note sur V6.** L'usage du `<canvas>` supprime de fait les métadonnées EXIF,
donc les coordonnées GPS. C'est un effet de bord heureux, pas un contrôle : il
ne s'appliquera pas au chargement d'un fichier existant, qui devra faire l'objet
d'une suppression EXIF explicite côté serveur.

**Note sur V7.** Rendre la photo obligatoire bloque tout Trouveur qui refuse
l'accès à la caméra ou dont le navigateur ne l'expose pas. Chaque obstacle sur
le parcours Trouveur réduit le nombre de documents signalés, donc l'utilité de
la plateforme entière. La photo sera facultative.

## 6. Décision

**Aucune ligne de code n'est migrée.** Le prototype est archivé dans
`legacy/prototype/`, accompagné d'un `README.md` indiquant qu'il n'est ni
déployé, ni sécurisé, ni maintenu, et qu'il sert uniquement de référence de
parcours pour le travail de conception.

Trois raisons :

1. **Aucune couche serveur.** Toute la logique de sécurité du projet est,
   par construction, côté serveur. Le prototype n'en a pas.
2. **Socle front-end incompatible.** Bootstrap 5 + jQuery-style DOM contre
   Tailwind + Livewire + Alpine : ce n'est pas une migration, c'est une
   réécriture.
3. **Le modèle de divulgation est inversé.** Le prototype charge toutes les
   données en clair chez le client puis en cache une partie à l'affichage.
   L'architecture cible ne construit jamais côté serveur ce que le client n'a
   pas le droit de voir. Ce sont deux conceptions opposées, pas deux degrés
   de rigueur.

Le déplacement effectif vers `legacy/prototype/` a lieu au **jalon 1**, en même
temps que l'initialisation du projet Laravel à la racine. Le jalon 0 ne
contient que de la documentation.

## 7. Conséquence sur les hypothèses de départ

- Hypothèse (a) « pas de base de code existante » : **infirmée**, traitée par
  cet audit.
- Hypothèse (b) « stack Laravel + Supabase + Vercel » : **confirmée**, avec
  les corrections de version et la réserve consignées en D-002, D-003 et D-011.
