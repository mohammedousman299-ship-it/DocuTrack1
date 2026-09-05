# Traitements asynchrones

**Version :** 1.0 · **Date :** 2026-09-05

---

## 1. Pourquoi c'est un point central

DocuTrack est **fondamentalement asynchrone**. Ce n'est pas un détail
d'optimisation :

- le **rapprochement** s'exécute par lots, jamais dans une requête HTTP ;
- la **recherche est différée** (D-008) : son résultat parvient par
  notification, ce qui est le contrôle décisif contre le canal temporel et
  l'énumération rapide ;
- les **notifications** partent par la file ;
- la **réconciliation des paiements** rattrape les transactions en suspens ;
- la **purge de rétention** est un contrôle de sécurité de premier rang.

Or la plateforme cible n'offre **ni worker permanent, ni scheduler en
processus** : `queue:work` et `schedule:work` ne survivent pas à un conteneur
éphémère. Tout le traitement de fond passe donc par des **endpoints HTTP
internes**, déclenchés par un ordonnanceur externe.

## 2. Les endpoints

| Endpoint | Rôle | État |
|---|---|---|
| `POST /internal/queue/drain` | Draine la file — remplace `queue:work` | ✅ implémenté |
| `POST /internal/cron/match` | Rapprochement par lots | Squelette — moteur au jalon 5 |
| `POST /internal/cron/notify` | Envoi des notifications | Squelette — jalon 4 |
| `POST /internal/cron/reconcile-payments` | Transactions en suspens | Squelette — jalon 6 |
| `POST /internal/cron/purge` | Purge de rétention (D-010) | ✅ implémenté |

Ce ne sont pas des routes web : ni session, ni cookie.

## 3. Les trois garanties, et comment elles sont vérifiées

### 3.1 Secret d'en-tête

En-tête `X-DocuTrack-Internal-Secret`, comparé en **temps constant**
(`hash_equals`) — une comparaison naïve permettrait de retrouver le secret
octet par octet en mesurant le temps de réponse.

Un secret **vide n'autorise jamais** : sans cette règle, une configuration
incomplète ouvrirait les endpoints à tout le monde.

L'échec renvoie **404, pas 403** : un 403 confirmerait que l'endpoint existe.

### 3.2 Verrou anti-concurrence

`Cache::lock` acquis **sans attente**. Si un lot tourne déjà, l'appel repart
immédiatement avec `ran: false` plutôt que de consommer son budget à attendre.
Ce n'est pas une erreur : un ordonnanceur peut redéclencher avant la fin du
passage précédent, et rien ne garantit l'unicité côté plateforme.

Le verrou est libéré dans un bloc `finally`, donc **même si la tâche échoue** —
sans quoi un échec bloquerait la tâche jusqu'à expiration du verrou.

### 3.3 Budget de temps

Chaque lot reçoit un budget (20 s par défaut) qu'il consulte **entre deux
unités de travail**. Épuisé, il s'arrête proprement et rend `budget_exhausted:
true` ; le passage suivant reprend. Un lot interrompu par la plateforme au
milieu d'une unité laisserait un travail dans un état indéterminé.

### 3.4 Idempotence

Portée par les tâches elles-mêmes, via des clés uniques :
`matches (lost_declaration_id, found_report_id, algorithm_version)` et
`notifications.idempotency_key`, dérivée de l'évènement métier et **jamais du
passage de cron**. Rejouer un passage ne produit aucun effet de bord.

## 4. Vérifications exécutées

25 tests, tous passants :

| Vérification | Résultat |
|---|---|
| Chaque endpoint refuse l'absence de secret | ✅ |
| Chaque endpoint refuse un secret erroné | ✅ |
| Un secret configuré vide n'ouvre rien | ✅ |
| L'échec renvoie 404, pas 403 | ✅ |
| Chaque endpoint accepte un secret valide | ✅ |
| Une exécution concurrente repart sans traiter (`ran: false`) | ✅ |
| Le verrou est libéré même en cas d'exception | ✅ |
| La purge supprime **réellement**, sans suppression logique | ✅ |
| La purge est idempotente | ✅ |

## 5. L'exception des webhooks de paiement

Les webhooks de paiement **ne passent pas par la file à la réception**. Ils sont
traités en synchrone — validation de signature, enregistrement idempotent — le
traitement lourd étant seul délégué à la file. C'est l'exception prévue par
§3.1 : un prestataire de paiement attend un accusé de réception immédiat et
rejoue sinon.

## 6. NON VALIDÉ — l'ordonnanceur

> D-017 : développement entièrement local, aucun accès Vercel.

| Élément | État |
|---|---|
| Verrou, budget, idempotence, secret | ✅ **validés localement, par tests** |
| Endpoints exercés par appels HTTP répétés et concurrents | ✅ **validés** |
| Déclenchement par **Vercel Cron** | **NON VALIDÉ** |
| Limite de durée d'exécution de la plateforme | **NON CONNUE** — le budget de 20 s est un choix prudent, pas une valeur calibrée |
| Fréquence de déclenchement soutenable | **NON CONNUE** |

**Ce qui est validé est le mécanisme lui-même, qui est le vrai risque
architectural. Vercel Cron n'en est que l'ordonnanceur** : le remplacer par
`cron`, un service externe ou un scheduler quelconque ne changerait rien au
code. C'était la raison d'écrire cette couche au jalon 1 plutôt qu'au dernier.

Voir `OPEN_QUESTIONS.md` Q-05.
