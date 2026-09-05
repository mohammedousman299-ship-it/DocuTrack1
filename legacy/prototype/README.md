# Prototype d'origine — ARCHIVE

> ⚠️ **Ce code n'est ni déployé, ni sécurisé, ni maintenu. Ne le mettez jamais
> en ligne.**

Maquette de démonstration cliquable qui précède le développement de DocuTrack.
Conservée comme **référence de parcours** pour le travail de conception, à la
suite de la décision D-001.

## Ce que c'est

Une maquette front-end sans back-end : pas de serveur, pas de base de données,
aucune persistance au-delà du rechargement de page. L'authentification est
factice et le mot de passe n'est jamais vérifié. Le « paiement » est un
`setTimeout`.

`app.js` et `style.css` appartiennent à une génération antérieure et ne sont
chargés par aucune page : ils sont conservés en l'état, comme code mort.

## Pourquoi elle n'est pas mise en ligne

Elle charge toutes les données en clair côté client puis en masque une partie à
l'affichage. Le nom du propriétaire, le numéro du document et le point de
retrait sont accessibles dans le navigateur avant tout paiement. L'audit relève
sept violations de garde-fous, dont un nom de personne réelle utilisé comme
donnée de démonstration.

## Ce qu'elle a apporté

Le parcours des trois acteurs, les huit catégories de documents camerounais, le
champ `depositPoint` — qui préfigure le modèle de remise médiatisée retenu — et
le squelette d'internationalisation français/anglais.

## À lire

- `docs/AUDIT_PROTOTYPE.md` — l'audit complet et les sept violations
- `docs/DECISIONS.md` — D-001
