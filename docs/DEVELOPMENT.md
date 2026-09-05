# Environnement de développement

**Version :** 1.0 · **Date :** 2026-09-05

Le projet tourne **entièrement en local, sans aucun service payant** (D-017).

---

## 1. Prérequis

| Outil | Version vérifiée | Note |
|---|---|---|
| PHP | **8.4** | Requis par Pest 5 (D-003) |
| Composer | 2.8+ | |
| Docker + Compose | | PostgreSQL et MinIO |
| Node | 22+ | Chaîne de build front-end |

Extensions PHP requises : `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `zip`,
`gd`, `intl`, `sodium`, `fileinfo`, `tokenizer`, `xml`. `bcmath` n'est **pas**
nécessaire : les montants sont manipulés en unités mineures entières.

## 2. Démarrage

```sh
make setup    # services + dépendances + base + seeders
make check    # style, analyse statique, tests
```

`make help` liste les autres cibles.

## 3. Services locaux

| Service | Rôle | Port |
|---|---|---|
| `database` | PostgreSQL 16 | 5432 |
| `storage` | MinIO, API compatible S3 | 9000 (console 9001) |
| `storage-init` | Crée le bucket **privé**, puis s'arrête | — |

### Pourquoi MinIO et non le disque local

Le §3.3 du cahier des charges interdit toute image de document sur le système
de fichiers de l'application, et impose un bucket privé avec URL signées de
courte durée. Utiliser le disque local en développement entraînerait le code au
mauvais motif : l'upload direct depuis le navigateur et les URL signées ne
seraient jamais exercés, et tous les problèmes apparaîtraient d'un coup au
branchement du stockage réel.

`FILESYSTEM_DISK=local` ne doit exister nulle part, pas même en développement.

### Extensions PostgreSQL

`unaccent`, `pg_trgm` et `fuzzystrmatch` sont **bloquantes** : sans elles, le
moteur de rapprochement ne fonctionne pas (`MATCHING.md` §7). Elles sont créées
à deux endroits, volontairement :

- `docker/postgres/init-extensions.sql`, à la première initialisation du volume ;
- dans une migration Laravel, de façon idempotente, pour que le projet
  fonctionne aussi sur une base fournie autrement.

## 4. Ce qui n'est PAS validé

> D-017 : aucun accès Supabase ni Vercel n'est fourni. La §10 du master prompt
> exigeait que le déploiement et le mode de connexion soient validés au jalon 1.
> **Cette exigence n'est pas satisfaite.**

| Élément | État |
|---|---|
| Mode de connexion Supabase (direct 5432 / pooler 6543) | **NON VALIDÉ** |
| Compatibilité du pooler avec les *prepared statements* PDO | **NON VALIDÉ** |
| Extensions sur Supabase | **NON VALIDÉ** — vérifiées en local seulement |
| Latence Supabase ↔ Vercel depuis le Cameroun | **NON MESURÉ** |
| Runtime `container` de Vercel, régions, limites, tarif | **NON VALIDÉ** |
| Vercel Cron | **NON VALIDÉ** |
| Image FrankenPHP construite et exécutée **en local** | Validé au jalon 1 |
| Endpoints internes exercés en local, y compris en concurrence | Validé au jalon 1 |

Le point le plus susceptible de surprendre reste le **pooler en mode
transaction**. Voir `OPEN_QUESTIONS.md` Q-04 et Q-05.

## 5. Réseau restreint : installer les dépendances

> Cette section ne concerne que les environnements dont l'accès à GitHub est
> filtré. Sur un poste ordinaire, `composer install` suffit.

Certains proxys bloquent le téléchargement des archives GitHub
(`codeload.github.com` et `api.github.com/.../zipball` répondent `403`) tout en
autorisant `git clone`. Composer échoue alors avec
`Could not authenticate against github.com`.

Contournement, à appliquer **en configuration globale de Composer, jamais dans
le dépôt** — c'est une particularité de poste, pas du projet :

```sh
composer config --global use-github-api false
composer install --prefer-source
```

`phpstan/phpstan` demande un traitement à part : il est publié **sans source
git** sur Packagist, donc `--prefer-source` ne peut rien pour lui.

```sh
git clone --depth 1 --branch <version> https://github.com/phpstan/phpstan.git /opt/vendorsrc/phpstan
composer config --global repositories.phpstan \
  '{"type":"path","url":"/opt/vendorsrc/phpstan","options":{"symlink":false}}'
composer update phpstan/phpstan --prefer-source
```

> ⚠️ **`composer update` écrit alors le chemin local dans `composer.lock`.**
> Ce chemin n'existe sur aucune autre machine et casserait l'installation pour
> tout le monde. Après l'opération, rétablir dans le verrou l'entrée `dist`
> canonique de Packagist pour `phpstan/phpstan`, et vérifier :
>
> ```sh
> grep -c '/opt/vendorsrc' composer.lock   # doit renvoyer 0
> ```
>
> C'est ce qui a été fait sur ce dépôt : le verrou versionné pointe la source
> Packagist, pas le chemin local.

## 6. Conventions

- Code et identifiants **en anglais**, interface **en français**, i18n prête
  pour l'anglais (§11.8).
- Commits atomiques au format Conventional Commits, messages en anglais.
- La documentation accompagne le code **dans le même commit**.
- Toute décision d'architecture est consignée dans `DECISIONS.md`.
