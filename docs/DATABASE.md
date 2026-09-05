# Base de données — mesures et configuration

**Version :** 1.0 · **Date :** 2026-09-05
**Environnement mesuré :** PostgreSQL **16.13** local · PHP 8.4.19 · Laravel 13.30.1

> Ce document ne contient que des **mesures réelles**. Ce qui n'a pas été
> mesuré est marqué **NON VALIDÉ** et le reste jusqu'à ce qu'il le soit.

---

## 1. Extensions

| Extension | État | Usage |
|---|---|---|
| `unaccent` | ✅ installée et fonctionnelle | Normalisation des noms |
| `pg_trgm` | ✅ installée et fonctionnelle | Similarité et index GIN |
| `fuzzystrmatch` | ✅ installée et fonctionnelle | `levenshtein()`, score composite (D-018) |
| `pgcrypto` | ✅ installée | Primitives cryptographiques |

Les trois premières sont **bloquantes**. Créées à deux endroits — le script
d'initialisation du conteneur et une migration idempotente — pour que le projet
fonctionne aussi sur une base fournie autrement.

## 2. Présélection du moteur — critère de validation du jalon 1

> **Critère (docs/MATCHING.md §4.1) :** la présélection doit produire un *index
> scan*. Un *seq scan* invaliderait la conception et imposerait de la revoir
> avant d'écrire le moteur.

**Protocole :** 100 000 signalements synthétiques, `ANALYZE`, puis
`EXPLAIN (ANALYZE, BUFFERS)` sur les trois chemins. Reproductible par
`php artisan docutrack:benchmark-matching --rows=100000`.

### Résultat : ✅ **VALIDÉ**

| Chemin | Plan retenu | Temps |
|---|---|---:|
| Nom (trigramme) | `Bitmap Index Scan` sur `found_reports_owner_name_trgm` | **18,97 ms** |
| Numéro (HMAC) | `Index Scan` sur `found_reports_number_hmac_index` | **0,03 ms** |
| Présélection complète (`OR`) | `BitmapOr` combinant les deux index | **17,96 ms** |

Aucun parcours séquentiel sur `found_reports`.

### 2.1 Observation à surveiller — la présélection est large

Le `Bitmap Index Scan` sur le trigramme remonte **2 011 lignes** avant filtrage,
soit **≈ 2 % de la table**, pour n'en retenir que 50.

À 100 000 lignes c'est sans conséquence. À 1 000 000 de lignes, la même
proportion donnerait ~20 000 candidats à scorer par recherche, et le coût
deviendrait dominant. Trois leviers, à évaluer **avant** d'atteindre ce volume :

1. relever `pg_trgm.similarity_threshold` pour la présélection — au prix du
   rappel, qu'il faudra mesurer ;
2. borner explicitement le nombre de candidats scorés ;
3. restreindre davantage par le filtre dur (type de document, statut, fenêtre
   temporelle) avant le trigramme.

La rétention de 180 jours (D-010) limite mécaniquement la croissance de la
table : c'est un effet secondaire favorable d'une décision prise pour la
sécurité.

## 3. Stockage des empreintes — hexadécimal, pas `bytea`

`number_hmac`, `duplicate_fingerprint`, `content_hash` et
`submitted_fields_hash` sont stockés en **hexadécimal** (`varchar(64)`), non en
`bytea`.

Raison mesurée : PDO refuse d'insérer des octets bruts dans une colonne `bytea`
sans liaison LOB explicite, et l'erreur produite est illisible. L'hexadécimal
s'indexe et se compare aussi bien, pour un coût de stockage de 64 octets par
empreinte au lieu de 32.

`number_encrypted` est en `text` : le cast chiffré de Laravel produit une chaîne
base64, pas des octets bruts.

## 4. Contrôles de sécurité vérifiés en base

Ces propriétés ont été **testées par exécution**, pas supposées.

| Contrôle | Vérification | Résultat |
|---|---|---|
| `audit_logs` append-only | `UPDATE` sur la table | ✅ rejeté par déclencheur |
| `audit_logs` append-only | `DELETE` sur la table | ✅ rejeté par déclencheur |
| D-004, pas d'auto-attribution | Revendication par un tiers | ✅ acceptée |
| D-004, pas d'auto-attribution | Revendication par le Trouveur | ✅ rejetée |
| Index trigramme | `pg_indexes` | ✅ présents sur les deux tables |
| Contraintes `CHECK` | `pg_constraint` | ✅ 28 contraintes métier |

> Le déclencheur sur `audit_logs` est la barrière **portable**. En production il
> doit être doublé d'une révocation de droits sur le rôle applicatif
> (`REVOKE UPDATE, DELETE ON audit_logs FROM <rôle>`), qu'un contournement
> applicatif ne peut pas lever. Cette révocation n'est pas appliquée ici : le
> rôle local est propriétaire de la base et pourrait se les réattribuer.

## 5. Migrations

19 migrations. Vérifié : elles s'appliquent, s'annulent jusqu'à un schéma vide
(seule `migrations` subsiste) et se réappliquent.

## 6. NON VALIDÉ — ce qui attend des accès Supabase

> D-017 : le développement est entièrement local. La §10 du master prompt
> exigeait que le mode de connexion soit validé au jalon 1. **Cette exigence
> n'est pas satisfaite.**

| Élément | État | Pourquoi c'est risqué |
|---|---|---|
| Connexion directe `:5432` | **NON VALIDÉ** | — |
| Pooler Supavisor `:6543` en mode transaction | **NON VALIDÉ** | Le mode transaction s'accommode mal des *prepared statements* de PDO. **Point le plus susceptible de surprendre.** |
| Migrations et transactions Eloquent via le pooler | **NON VALIDÉ** | |
| Extensions sur Supabase | **NON VALIDÉ** | Si `pg_trgm` ou `unaccent` manquent, `MATCHING.md` tombe |
| Latence Supabase ↔ Vercel depuis le Cameroun | **NON MESURÉ** | Détermine le choix des régions |

`DB_EMULATE_PREPARES` est exposé dans `.env.example` pour que le basculement
soit un changement de configuration, pas un changement de code.

Voir `OPEN_QUESTIONS.md` Q-04.
