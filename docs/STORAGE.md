# Stockage des images de documents

**Version :** 1.0 · **Date :** 2026-09-05

> Les images de documents sont **les données les plus sensibles du système**.
> Elles sont fournies par un tiers — le Trouveur — sur une personne qui n'a pas
> consenti et ignore que ce traitement existe.

---

## 1. Décision : MinIO en local, arbitrage de production différé

**En développement : MinIO**, serveur compatible S3, lancé par
`docker-compose`.

Le code utilise donc le driver `s3` et les URL signées **exactement comme en
production**, quel que soit le fournisseur retenu ensuite. Le disque local est
écarté même en développement : il entraînerait le code au mauvais motif,
l'upload direct et les URL signées ne seraient jamais exercés, et tous les
problèmes apparaîtraient d'un coup au branchement du stockage réel — ce que
§3.1 cherche précisément à éviter.

**`FILESYSTEM_DISK=local` ne doit exister nulle part.**

## 2. Arbitrage Supabase Storage / Vercel Blob — NON TRANCHÉ

| Critère | Supabase Storage | Vercel Blob |
|---|---|---|
| Chiffrement au repos | **NON VÉRIFIÉ** | **NON VÉRIFIÉ** |
| URL signées de courte durée | **NON VÉRIFIÉ** | **NON VÉRIFIÉ** |
| Granularité des accès | **NON VÉRIFIÉ** | **NON VÉRIFIÉ** |
| Coût | **NON CONNU** | **NON CONNU** |
| Latence depuis le Cameroun | **NON MESURÉ** | **NON MESURÉ** |

> Aucun accès aux deux services n'ayant été fourni (D-017), **aucune de ces
> cases ne sera remplie de mémoire ni par analogie.** Ce tableau reste vide
> jusqu'à vérification effective.

L'architecture étant construite sur l'API S3, le basculement de l'un à l'autre
est un changement de configuration tant que le fournisseur retenu expose une
API compatible S3 et des URL signées. Ce point est à confirmer pour Vercel
Blob, dont la compatibilité S3 n'est pas connue.

## 3. Règles applicables quel que soit le fournisseur

Ces règles découlent de §3.3 et du modèle de menaces (M-12), et ne dépendent
d'aucun arbitrage :

| Règle | Justification |
|---|---|
| Bucket **privé**, aucune URL publique ni devinable | Une URL publique annule tous les autres contrôles |
| URL signées de **très courte durée**, générées à la demande | Limite la fenêtre d'exploitation d'une URL fuitée |
| **Chaque génération d'URL signée est journalisée** | `disclosures.signed_url_generated` |
| Upload **direct depuis le navigateur** via URL pré-signée | Les images ne transitent pas par le conteneur applicatif |
| Suppression **serveur** des métadonnées EXIF, systématique | Une photo de pièce d'identité embarque des coordonnées GPS |
| Validation stricte : type MIME **réel**, taille, dimensions | La validation client est de l'ergonomie, pas de la sécurité |
| Un enregistrement sans `exif_stripped_at` **n'est jamais servi** | Preuve que le traitement a eu lieu |
| **Aucune image sur le système de fichiers du conteneur** | Il n'est pas persistant, et ce serait une fuite |
| Rétention **90 jours**, purge effective | La durée la plus courte du modèle (D-010) |
| **L'image n'est visible d'aucun utilisateur, à aucun niveau** | D-006 — seul l'Administrateur y accède, avec motif saisi |

## 4. État au jalon 1

| Élément | État |
|---|---|
| MinIO dans `docker-compose`, bucket privé sans accès anonyme | ✅ |
| `FILESYSTEM_DISK=s3` par défaut, jamais `local` | ✅ |
| Table `report_attachments` : références seulement, jamais de binaire | ✅ |
| Colonne `exif_stripped_at` comme preuve de traitement | ✅ |
| Upload direct par URL pré-signée | **À FAIRE** — jalon 3 |
| Suppression EXIF serveur | **À FAIRE** — jalon 3 |
| Compression côté client avant upload | **À FAIRE** — jalon 3 |
| Journalisation des URL signées | **À FAIRE** — jalon 3 |
| Choix du fournisseur de production | **NON TRANCHÉ** (§2) |
