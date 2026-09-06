# Autorisations

**Version :** 1.0 · **Date :** 2026-09-05

> **Rappel d'architecture (§3.2 du master prompt).** Laravel se connecte à
> Supabase avec **un seul rôle applicatif**. Les politiques Row Level Security
> de Supabase sont conçues autour des JWT de Supabase Auth, que nous n'utilisons
> pas : **elles ne protégeront rien par elles-mêmes**. Toute la barrière
> d'autorisation est dans Laravel — Policies, Gates, middleware et scopes
> Eloquent globaux. Il n'y a pas de filet en dessous.

---

## 1. Acteurs

Il n'existe **qu'un seul type de compte utilisateur** (D-004). Les acteurs
ci-dessous sont des *positions* qu'un même compte occupe selon la ressource.

| Acteur | Définition opérationnelle |
|---|---|
| **Anonyme** | Non authentifié |
| **Utilisateur non vérifié** | Authentifié, `phone_verified_at` nul |
| **Propriétaire** | Utilisateur vérifié, considéré vis-à-vis d'une déclaration **dont il est l'auteur** |
| **Trouveur** | Utilisateur vérifié, considéré vis-à-vis d'un signalement **dont il est l'auteur** |
| **Revendiquant** | Utilisateur vérifié ayant une `claim` ouverte sur une correspondance |
| **Admin fonctionnel** | `admin_role ∈ {functional, both}` |
| **Admin sensible** | `admin_role ∈ {sensitive, both}` |

**L'autorisation ne porte jamais sur un rôle seul.** Elle porte toujours sur une
**relation à une ressource** : « peut voir le N2 de ce signalement » signifie
« a une revendication vérifiée sur ce signalement », pas « est Propriétaire ».

---

## 2. Matrice acteur × action × ressource

Légende : ✅ autorisé · ❌ refusé · 🔶 autorisé sous condition · 📝 journalisé
dans `audit_logs` · 👁 journalisé dans `disclosures`

### 2.1 Compte et authentification

| Action | Anonyme | Non vérifié | Utilisateur vérifié | Admin |
|---|:--:|:--:|:--:|:--:|
| Consulter la page d'accueil | ✅ | ✅ | ✅ | ✅ |
| S'inscrire | ✅ | — | — | — |
| Vérifier son e-mail | ❌ | ✅ | ✅ | ✅ |
| Vérifier son téléphone (SMS) | ❌ | ✅ | ✅ | ✅ |
| Changer de numéro de téléphone | ❌ | 🔶 | 🔶 📝 | 🔶 📝 |
| Activer la 2FA | ❌ | ✅ | ✅ | Obligatoire |
| Déposer un retour d'expérience | ❌ | ✅ | ✅ | ✅ |

🔶 Changement de numéro : ré-authentification par mot de passe, notification sur
l'e-mail, et **délai de latence** avant que le nouveau numéro ne puisse servir à
une revendication (`THREAT_MODEL.md` M-13, SIM swap).

### 2.2 Signalement d'un document trouvé

| Action | Anonyme | Non vérifié | Trouveur (auteur) | Autre utilisateur | Admin fonct. | Admin sensible |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| Créer un signalement | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| Voir ses propres signalements (champs non sensibles) | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ |
| **Voir le statut de rapprochement de ses signalements** | ❌ | ❌ | **❌** | ❌ | ✅ | ✅ |
| **Voir un compteur de correspondances** | ❌ | ❌ | **❌** | ❌ | ❌ | ✅ |
| Modifier un signalement | ❌ | ❌ | 🔶 | ❌ | ❌ | 📝 |
| Supprimer un signalement | ❌ | ❌ | 🔶 | ❌ | ❌ | 📝 |
| Voir l'image jointe | ❌ | ❌ | **❌** | ❌ | ❌ | ✅ 👁📝 |

**Les deux refus en gras sont contre-intuitifs et délibérés** : ils ferment le
canal d'extraction par injection dans l'index de rapprochement
(`THREAT_MODEL.md` M-06). Le Trouveur voit « signalement enregistré », rien de
plus. Y compris sur ses propres données.

🔶 Modification et suppression : uniquement tant que le signalement n'est pas
`matched`, et sans jamais permettre d'effacer une trace d'audit.

### 2.3 Déclaration de perte et recherche

| Action | Anonyme | Non vérifié | Propriétaire (auteur) | Autre utilisateur | Admin fonct. | Admin sensible |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| **Rechercher** | ❌ | ❌ | 🔶 | 🔶 | ❌ | ❌ |
| Voir le résultat d'une recherche (N1) | ❌ | ❌ | ✅ 👁 | ✅ 👁 | ❌ | ❌ |
| Déclarer une perte | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Voir ses propres déclarations | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ |
| Voir la déclaration d'autrui | ❌ | ❌ | ❌ | ❌ | 🔶 | ✅ 👁📝 |

🔶 Rechercher : exige un compte **vérifié par SMS**, une combinaison de critères
valide (`DISCLOSURE_LEVELS.md` §3), et le respect des quotas (§4 ci-dessous).
Le résultat est **différé** (D-008).

**L'Administrateur ne peut pas lancer de recherche.** Ce n'est pas un oubli :
la recherche est l'outil d'extraction du système, et un administrateur dispose
déjà d'un accès direct et tracé aux données dont il a besoin. Lui laisser la
recherche créerait un chemin d'accès moins tracé que les autres.

### 2.4 Correspondance, revendication, divulgation

| Action | Revendiquant | Propriétaire non revendiquant | Trouveur du signalement | Admin fonct. | Admin sensible |
|---|:--:|:--:|:--:|:--:|:--:|
| Ouvrir une revendication | ✅ | ✅ | **❌** | ❌ | ❌ |
| Soumettre une preuve de connaissance | 🔶 | ❌ | ❌ | ❌ | ❌ |
| Accéder au niveau N2 | 🔶 👁 | ❌ | ❌ | ❌ | ✅ 👁📝 |
| Accéder au niveau N3 | 🔶 👁 | ❌ | ❌ | ❌ | ✅ 👁📝 |
| Voir le score de correspondance | ❌ | ❌ | ❌ | ❌ | ✅ |
| Valider une revendication sensible | ❌ | ❌ | ❌ | ❌ | ✅ 📝 |

**❌ Trouveur** : un compte ne peut pas revendiquer un signalement dont il est
l'auteur. Sinon on s'auto-attribue un document (D-004). Vérifié par contrainte
de base **et** par Policy — deux barrières, parce qu'une seule serait une seule.

🔶 Preuve : maximum **3 tentatives à vie** par revendication (M-03).
🔶 N2 : après preuve réussie. 🔶 N3 : après preuve réussie **et** paiement
confirmé serveur-à-serveur — ou preuve seule si le module de paiement est
désactivé (D-015). Pour un type de document `sensitivity = high` (passeport,
CNI), une **validation administrateur** est en outre obligatoire.

### 2.5 Administration

| Action | Admin fonctionnel | Admin sensible |
|---|:--:|:--:|
| Gérer les utilisateurs (blocage, rôles) | ✅ 📝 | ✅ 📝 |
| Gérer les catégories de documents | ✅ 📝 | ✅ 📝 |
| Gérer les points de dépôt | ✅ 📝 | ✅ 📝 |
| Traiter les retours d'expérience | ✅ 📝 | ✅ 📝 |
| Voir les tableaux de bord agrégés | ✅ | ✅ |
| Régler les seuils de rapprochement | ✅ 📝 | ✅ 📝 |
| **Voir nom complet et numéro** | ❌ | ✅ 👁📝 **+ motif** |
| **Voir une image de document** | ❌ | ✅ 👁📝 **+ motif**, floutée par défaut |
| Voir les éléments de preuve d'une revendication | ❌ | ✅ 👁📝 **+ motif** |
| Consulter le journal d'audit | ✅ | ✅ |
| **Modifier ou supprimer le journal d'audit** | **❌** | **❌** |

Le refus d'écriture sur `audit_logs` n'est pas une règle applicative : il est
appliqué par **révocation des droits `UPDATE` et `DELETE` sur cette table pour
le rôle applicatif PostgreSQL**. Un contournement de code ne suffit pas à le
lever.

---

## 3. Implémentation

### 3.1 Répartition des mécanismes

| Mécanisme | Usage |
|---|---|
| **Middleware** | Authentification, vérification téléphone, 2FA administrateur, limitation de débit |
| **Policies** | Toute décision portant sur une ressource : `FoundReportPolicy`, `LostDeclarationPolicy`, `MatchPolicy`, `ClaimPolicy`, `AttachmentPolicy` |
| **Gates** | Décisions non liées à une ressource : `admin.functional`, `admin.sensitive`, `search.perform` |
| **Scopes globaux Eloquent** | Filtrage systématique par propriétaire et par statut, pour qu'une requête oubliée ne remonte pas des données d'autrui |
| **Droits SQL** | `audit_logs` en append-only |

### 3.2 Le niveau de divulgation n'est jamais un paramètre de requête

Le niveau atteint est **calculé par la Policy** à partir de l'état de la
revendication et du paiement. Il n'est jamais lu depuis la requête, ni depuis
une propriété Livewire modifiable par le client, ni depuis un champ caché.

### 3.3 Ordre d'évaluation

```
1. Authentifié ?                          sinon → 401
2. Téléphone vérifié ?                    sinon → parcours de vérification
3. Compte non bloqué ?                    sinon → 403
4. Quota respecté ?                       sinon → 429
5. Policy sur la ressource                sinon → 404 (pas 403)
6. Niveau de divulgation calculé
7. Construction du DTO du niveau
8. Écriture dans disclosures
```

**Étape 5 — un refus d'accès à une ressource renvoie 404, jamais 403.** Un 403
confirmerait l'existence de la ressource, ce qui est une divulgation en soi
(`THREAT_MODEL.md` M-05, M-07).

---

## 4. Limitation de débit

| Point d'entrée | Limite | Motif |
|---|---|---|
| Inscription | Par IP, seuil large | M-04 — l'IP est un signal, pas une barrière |
| Envoi de SMS de vérification | Strict, par numéro et par compte | Coût unitaire réel |
| Connexion | Par compte et par IP | Bourrage d'identifiants |
| **Recherche** | **Quota quotidien par compte** | M-07 |
| Preuve de connaissance | **3 à vie par revendication** | M-03 |
| Signalement | Plafond par compte et par période | M-06 |
| Upload | Par compte | Abus de stockage |

Toutes les valeurs sont des paramètres de configuration, resserrables sans
redéploiement.

> **La limitation par IP ne compte jamais comme barrière unique.** Le CGNAT des
> opérateurs mobiles camerounais la rend contournable par l'attaquant et
> bloquante pour des utilisateurs légitimes (M-04). Elle sert à détecter et à
> corréler.

---

## 5. Tests de refus obligatoires

> **Chaque Policy doit avoir un test qui vérifie le refus** (§3.2 du master
> prompt). Un test qui ne vérifie que le cas autorisé ne prouve rien.

Liste à implémenter, par jalon.

**Jalon 2 — comptes** — *réalisé*
- [x] Anonyme sur une route protégée → redirection, et **404** sur
      l'administration
- [x] Compte sans téléphone vérifié → recherche refusée
- [ ] Compte sans téléphone vérifié → revendication refusée — *reporté au
      jalon 6, où la revendication existera ; le Gate `search.perform` couvre
      déjà la condition de téléphone vérifié*
- [x] Utilisateur ordinaire sur une route d'administration → 404
- [x] Admin sans 2FA active → accès administration refusé
- [x] Compte bloqué → refusé
- [x] Deux comptes ne peuvent pas partager un numéro de téléphone, **même écrit
      autrement**
- [x] Un message d'erreur d'authentification ne distingue pas compte inconnu de
      mot de passe erroné

Ajoutés en cours de route, parce que la construction les a rendus nécessaires :
- [x] Le middleware `auth` est absent des routes d'administration : sa
      redirection vers la connexion **confirmerait l'existence de la route**
- [x] Administrateur fonctionnel → pouvoirs sensibles refusés, et l'inverse
- [x] Administrateur → **recherche refusée**
- [x] Régénération de l'identifiant de session à la connexion (fixation de
      session)
- [x] Le code SMS ne peut pas être consommé par un autre compte
- [x] `unsafe-eval` ne peut pas réapparaître dans la CSP

**Jalon 3 — signalements**
- [ ] Un utilisateur ne peut pas lire le signalement d'un autre → 404
- [ ] **Le Trouveur ne voit aucun statut de rapprochement de ses signalements**
- [ ] Un utilisateur ne peut pas obtenir d'URL signée vers une image
- [ ] Un admin fonctionnel ne peut pas voir une image
- [ ] Un fichier sans `exif_stripped_at` n'est jamais servi

**Jalon 4 — recherche**
- [ ] Recherche par nom seul → refusée
- [ ] Recherche sans type de document → refusée
- [ ] Au-delà du quota → 429
- [ ] **Test de non-fuite N1** : la réponse complète ne contient aucune
      sentinelle (`DISCLOSURE_LEVELS.md` §5)
- [ ] Toute recherche écrit une ligne dans `search_requests`
- [ ] Aucune réponse n'expose un nombre de résultats supérieur à 1

**Jalon 6 — revendication et divulgation**
- [ ] **Un Trouveur ne peut pas revendiquer son propre signalement** —
      contrainte de base ET Policy
- [ ] 4ᵉ tentative de preuve → refusée, revendication verrouillée
- [ ] Un échec de preuve n'indique jamais quel champ était faux
- [ ] Un jeton N2 ne donne pas N3 par modification d'un paramètre
- [ ] N3 refusé sans `confirmed_server_side_at`
- [ ] **N3 refusé sur simple redirection navigateur de paiement**
- [ ] Un type `sensitivity = high` ne passe jamais en N3 sans décision
      administrateur
- [ ] Chaque accès N2/N3 écrit dans `disclosures`

**Jalon 7 — administration**
- [ ] Admin fonctionnel → nom complet et numéro refusés
- [ ] Accès sensible sans motif saisi → refusé
- [ ] `UPDATE` sur `audit_logs` → échec au niveau SQL
- [ ] `DELETE` sur `audit_logs` → échec au niveau SQL
- [ ] Tout accès sensible écrit dans `disclosures` **et** `audit_logs`

**Interdit absolu :** contourner un contrôle d'autorisation pour faire passer un
test. Si un test échoue, on corrige le test ou la politique — jamais le
contrôle (§12 du master prompt).
