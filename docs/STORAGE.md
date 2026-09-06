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
| MinIO dans `docker-compose`, bucket privé sans accès anonyme | ✅ vérifié — 403 en anonyme |
| `FILESYSTEM_DISK=s3` par défaut, jamais `local` | ✅ verrouillé par test |
| Table `report_attachments` : références seulement, jamais de binaire | ✅ |
| Colonne `exif_stripped_at` comme preuve de traitement | ✅ |
| **Envoi direct par URL pré-signée** | ✅ **vérifié au navigateur** |
| **Compression et nettoyage côté client** | ✅ GPS retirés avant de quitter l'appareil |
| **Suppression EXIF serveur** | ✅ tâche de file, vérifiée de bout en bout |
| Journalisation des URL signées | **À FAIRE** — jalon 6, avec la divulgation N3 |
| Choix du fournisseur de production | **NON TRANCHÉ** (§2) |

## 5. Mesures du jalon 3

Toutes prises contre les services réellement en marche.

| Vérification | Résultat |
|---|---|
| Lecture, écriture, URL signée | ✅ |
| Accès sans signature | **403** |
| Signature falsifiée | **403** |
| URL signée après expiration | **403** |
| `PUT` pré-signé | **200** |
| `PUT` sans signature | **403** |
| Même signature réutilisée sur **une autre clé** | **403** |
| Parcours complet au navigateur : image envoyée, sans GPS | ✅ 778 octets |
| Pièce jointe servable **seulement** après vérification serveur | ✅ |

### 5.1 Deux pièges rencontrés, à connaître

**Les variables `AWS_*` sont souvent définies au niveau du système** et priment
sur `.env`. La configuration du projet se retrouvait remplacée par des
identifiants étrangers, et l'erreur produite — « Unable to check existence » —
ne désignait rien d'utile. Les variables sont désormais préfixées
`DOCUTRACK_S3_`, et un test empêche le retour des noms `AWS_`.

**`upgrade-insecure-requests` cassait l'envoi direct** en réécrivant en
`https://` l'appel vers un stockage servi en clair, sans message exploitable.
La directive n'est plus émise qu'en HTTPS (D-033).
