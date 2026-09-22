# DocuTrack : application Laravel

Backend Laravel 13 et vues Blade, convertis depuis le prototype statique (`../index.html`).

## Installation

```bash
composer install
cp .env.example .env         # régler DB_* (MySQL) et DOCUTRACK_FEE
php artisan key:generate
php artisan migrate --seed   # catégories et comptes de démo
php artisan storage:link     # photos des documents trouvés
php artisan serve
```

Comptes de démo (mot de passe `password`) : `admin@docutrack.cm` (admin) et `john@example.com`.

## Fonctionnalités

- Inscription et connexion (session). Un compte désactivé ne peut plus se connecter.
- **Trouveur** : signale un document trouvé, avec une photo prise à la webcam (enregistrée dans `storage/app/public/found`).
- **Propriétaire** : recherche par catégorie + nom (insensible à la casse) ou par numéro, et déclaration de perte.
- Correspondance automatique dans les deux sens (déclaration ↔ document trouvé), avec des notifications en base.
- Paiement **simulé**. Les frais sont fixés par `DOCUTRACK_FEE`. Le lieu de dépôt et le contact du trouveur ne s'affichent qu'une fois le paiement fait.
- Avis des utilisateurs.
- Admin (`/admin`) : documents, déclarations, utilisateurs (activer / désactiver), catégories et traitement des avis.
- Interface en FR et EN (`lang/en.json`, `lang/fr.json`).

## Tests

```bash
php artisan test
```
