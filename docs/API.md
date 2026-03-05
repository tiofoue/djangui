# Endpoints API — Djangui

Base URL : `http://djangui.test/api/`
Auth : `Authorization: Bearer <access_token>` (sauf routes publiques)

---

## Auth

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| POST | `/auth/register` | ❌ | Créer un compte (phone obligatoire, email optionnel) → envoie OTP SMS |
| POST | `/auth/verify-phone` | ❌ | Vérifier OTP SMS → active le compte |
| POST | `/auth/resend-otp` | ❌ | Renvoyer OTP SMS |
| POST | `/auth/login` | ❌ | Connexion via phone ou email + password (flux principal) |
| POST | `/auth/login/otp` | ❌ | Demander un OTP de connexion (flux alternatif, sans password) |
| POST | `/auth/login/otp/verify` | ❌ | Vérifier l'OTP → retourne tokens (flux alternatif) |
| POST | `/auth/refresh` | ❌ | Rafraîchir access token |
| POST | `/auth/logout` | ✅ | Invalider token |
| POST | `/auth/forgot-password` | ❌ | Reset par SMS OTP (primaire) ou email (si renseigné) |
| POST | `/auth/reset-password` | ❌ | Réinitialiser mot de passe |
| GET  | `/auth/me` | ✅ | Profil utilisateur connecté |
| PUT  | `/auth/me` | ✅ | Modifier profil |
| POST | `/auth/switch-association` | ✅ | Changer d'association active |

---

## Associations

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations` | ✅ | Mes associations |
| POST | `/associations` | ✅ | Créer une association (→ pending_review) |
| GET  | `/associations/{id}` | member | Détail association |
| PUT  | `/associations/{id}` | president | Modifier association |
| DELETE | `/associations/{id}` | president | Supprimer association |
| GET  | `/associations/{id}/settings` | member | Lire settings |
| PUT  | `/associations/{id}/settings` | president | Modifier settings |
| POST | `/associations/{id}/logo` | president | Upload logo |

### Fédération — Sous-associations
| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/children` | member | Liste des sous-associations |
| POST | `/associations/{id}/children` | president | Rattacher une sous-association |
| DELETE | `/associations/{id}/children/{childId}` | president | Détacher une sous-association |
| GET  | `/associations/{id}/children/{childId}/overview` | president | Vue d'ensemble sous-association |

### Super Admin — Validation associations
| Méthode | Endpoint | Rôle | Description |
|---------|----------|------|-------------|
| GET  | `/admin/associations` | super_admin | Liste toutes les entités |
| GET  | `/admin/associations/pending` | super_admin | En attente de validation |
| PUT  | `/admin/associations/{id}/approve` | super_admin | Approuver |
| PUT  | `/admin/associations/{id}/reject` | super_admin | Rejeter avec motif |
| PUT  | `/admin/associations/{id}/suspend` | super_admin | Suspendre |
| PUT  | `/admin/associations/{id}/reinstate` | super_admin | Réhabiliter |

---

## Bureau & Elections

### Postes du bureau
| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/bureau/positions` | member | Liste des postes définis |
| POST | `/associations/{id}/bureau/positions` | president | Créer un poste |
| PUT  | `/associations/{id}/bureau/positions/{pId}` | president | Modifier un poste |
| DELETE | `/associations/{id}/bureau/positions/{pId}` | president | Supprimer un poste (si vacant) |

### Mandats (bureau actuel & historique)
| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/bureau` | member | Bureau actuel (mandats actifs) |
| GET  | `/associations/{id}/bureau/history` | member | Historique de tous les bureaux |
| POST | `/associations/{id}/bureau/terms` | president | Nommer/promouvoir un membre |
| PUT  | `/associations/{id}/bureau/terms/{tId}/end` | president | Mettre fin à un mandat |

### Suppléances
| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/bureau/substitutions` | member | Suppléances actives |
| POST | `/associations/{id}/bureau/substitutions` | president | Déclarer indisponibilité |
| PUT  | `/associations/{id}/bureau/substitutions/{sId}/end` | president | Mettre fin à la suppléance |

### Elections
| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/elections` | member | Liste des élections |
| POST | `/associations/{id}/elections` | president | Organiser une élection |
| GET  | `/associations/{id}/elections/{eId}` | member | Détail élection |
| PUT  | `/associations/{id}/elections/{eId}/open` | president | Ouvrir le vote |
| PUT  | `/associations/{id}/elections/{eId}/close` | president | Clôturer le vote |
| PUT  | `/associations/{id}/elections/{eId}/publish` | president | Publier résultats → crée bureau_terms |
| PUT  | `/associations/{id}/elections/{eId}/cancel` | president | Annuler élection |
| POST | `/associations/{id}/elections/{eId}/candidates` | member | Se porter candidat |
| DELETE | `/associations/{id}/elections/{eId}/candidates/{cId}` | member* | Retirer sa propre candidature uniquement |
| POST | `/associations/{id}/elections/{eId}/vote` | member | Voter |
| GET  | `/associations/{id}/elections/{eId}/results` | member | Résultats (si publiés) |

---

## Membres

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/members` | member | Liste membres |
| GET  | `/associations/{id}/members/{userId}` | member | Profil membre |
| PUT  | `/associations/{id}/members/{userId}/role` | president | Changer rôle |
| DELETE | `/associations/{id}/members/{userId}` | president | Retirer membre |
| POST | `/associations/{id}/invitations` | secretary | Inviter un membre (phone et/ou email) |
| GET  | `/associations/{id}/invitations` | secretary | Liste invitations |
| DELETE | `/associations/{id}/invitations/{invitId}` | secretary | Annuler invitation |
| POST | `/invitations/{token}/accept` | ❌ | Accepter invitation |

---

## Tontines

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/tontines` | member | Liste tontines |
| POST | `/associations/{id}/tontines` | treasurer | Créer tontine |
| GET  | `/associations/{id}/tontines/{tId}` | member | Détail tontine |
| PUT  | `/associations/{id}/tontines/{tId}` | treasurer | Modifier tontine |
| PUT  | `/associations/{id}/tontines/{tId}/start` | treasurer | Démarrer tontine |
| PUT  | `/associations/{id}/tontines/{tId}/close` | treasurer | Clôturer tontine |
| GET  | `/associations/{id}/tontines/{tId}/members` | member | Membres inscrits |
| POST | `/associations/{id}/tontines/{tId}/members` | treasurer | Inscrire membre (bid_amount optionnel à l'inscription — obligatoire pour tous avant démarrage en mode bidding) |
| PUT  | `/associations/{id}/tontines/{tId}/members/me/bid` | member | Soumettre/modifier son enchère pré-tontine (mode bidding uniquement) |
| DELETE | `/associations/{id}/tontines/{tId}/members/{uId}` | treasurer | Désinscrire membre |
| GET  | `/associations/{id}/tontines/{tId}/sessions` | member | Liste sessions |
| GET  | `/associations/{id}/tontines/{tId}/sessions/{sId}` | member | Détail session |
| PUT  | `/associations/{id}/tontines/{tId}/sessions/{sId}/open` | treasurer | Ouvrir session manuellement (secours) |
| PUT  | `/associations/{id}/tontines/{tId}/sessions/{sId}/close` | treasurer | Clôturer session |
| GET  | `/associations/{id}/tontines/{tId}/sessions/{sId}/contributions` | member* | Cotisations session (membre voit les siennes, treasurer voit tout) |
| POST | `/associations/{id}/tontines/{tId}/sessions/{sId}/contributions` | treasurer | Enregistrer paiement |
| GET  | `/associations/{id}/tontines/{tId}/sessions/{sId}/beneficiaries` | member | Bénéficiaires de la séance |
| PUT  | `/associations/{id}/tontines/{tId}/rotation` | treasurer | Définir ordre rotation |
| POST | `/associations/{id}/tontines/{tId}/sessions/{sId}/bids` | member | Placer une enchère de séance (mode session_auction uniquement) |
| GET  | `/associations/{id}/tontines/{tId}/sessions/{sId}/bids` | member | Enchères en cours sur cette séance |
| PUT  | `/associations/{id}/tontines/{tId}/sessions/{sId}/adjudicate` | treasurer | Adjuger l'enchère — désigner gagnant (mode session_auction) |
| GET  | `/associations/{id}/tontines/{tId}/caisse` | member | Solde caisse + historique des distributions fin de cycle (mode session_auction) |
| POST | `/associations/{id}/tontines/{tId}/caisse/distribute` | treasurer | Redistribuer caisse (clôture) |
| PUT  | `/associations/{id}/tontines/{tId}/moderateur` | president | Désigner le modérateur |
| POST | `/associations/{id}/tontines/{tId}/members/{uId}/demote` | moderator* | Rétrograder membre défaillant |
| GET  | `/associations/{id}/tontines/{tId}/demotions` | member | Historique rétrogradations |

---

## Emprunts

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/loans` | treasurer | Liste emprunts |
| POST | `/associations/{id}/loans` | member | Demander un emprunt |
| GET  | `/associations/{id}/loans/{lId}` | member* | Détail emprunt |
| PUT  | `/associations/{id}/loans/{lId}/approve` | treasurer | Approuver (→ status: approved) |
| PUT  | `/associations/{id}/loans/{lId}/disburse` | treasurer | Décaisser les fonds (→ status: active, remplit disbursed_at, génère échéancier) |
| PUT  | `/associations/{id}/loans/{lId}/reject` | treasurer | Rejeter |
| GET  | `/associations/{id}/loans/{lId}/schedule` | member* | Échéancier |
| GET  | `/associations/{id}/loans/{lId}/repayments` | member* | Historique paiements |
| POST | `/associations/{id}/loans/{lId}/repayments` | treasurer | Enregistrer remboursement |

*membre peut voir seulement ses propres emprunts

> **moderator*** : vérifié par `TontineModeratorFilter` — accès accordé si l'utilisateur est le modérateur désigné de la tontine (`tontines.moderateur_id`), ou s'il est `treasurer` ou `president` de l'association (fallback).

> **Deux modes d'enchères distincts :**
> - **`bidding`** (pré-tontine) : chaque membre soumet un `bid_amount` avant le démarrage. L'ordre de rotation est déterminé une fois pour tout le cycle. Endpoint : `PUT /members/me/bid`
> - **`session_auction`** (par séance) : enchères à chaque séance entre membres éligibles. Le gagnant reçoit la cagnotte moins le montant adjugé, qui alimente une caisse redistribuée en fin de cycle. Endpoints : `POST/GET /sessions/{sId}/bids` + `PUT /sessions/{sId}/adjudicate`

---

## Main levée (collecte ponctuelle)

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/fundraisings` | member | Liste main levées |
| POST | `/associations/{id}/fundraisings` | treasurer | Initier une main levée (président ou trésorier) |
| GET  | `/associations/{id}/fundraisings/{fId}` | member | Détail + contributions |
| PUT  | `/associations/{id}/fundraisings/{fId}/close` | president | Clôturer la collecte |
| PUT  | `/associations/{id}/fundraisings/{fId}/hand-over` | president | Marquer remise (→ status: handed_over) — bénéficiaire : member \| external \| fund |
| POST | `/associations/{id}/fundraisings/{fId}/contributions` | member | Contribuer |
| GET  | `/associations/{id}/fundraisings/{fId}/contributions` | member | Liste contributions |

---

## Solidarité

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/solidarity` | member | Info caisse solidarité |
| GET  | `/associations/{id}/solidarity/contributions` | treasurer | Liste cotisations |
| POST | `/associations/{id}/solidarity/contributions` | treasurer | Enregistrer cotisation |
| GET  | `/associations/{id}/solidarity/requests` | member | Liste demandes |
| POST | `/associations/{id}/solidarity/requests` | member | Soumettre demande |
| GET  | `/associations/{id}/solidarity/requests/{rId}` | member* | Détail demande |
| PUT  | `/associations/{id}/solidarity/requests/{rId}/cancel` | member* | Annuler sa propre demande (status: pending uniquement) |
| PUT  | `/associations/{id}/solidarity/requests/{rId}/approve` | treasurer | Approuver |
| PUT  | `/associations/{id}/solidarity/requests/{rId}/reject` | treasurer | Rejeter |
| PUT  | `/associations/{id}/solidarity/requests/{rId}/disburse` | treasurer | Marquer versé (body: payment_method requis — recorded_by extrait du JWT) |

---

## Documents

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET  | `/associations/{id}/documents` | member | Liste documents |
| POST | `/associations/{id}/documents` | secretary | Uploader document |
| GET  | `/associations/{id}/documents/{dId}` | member | Métadonnées document (JSON) |
| GET  | `/associations/{id}/documents/{dId}/download` | member | Télécharger le fichier (stream binaire) |
| PUT  | `/associations/{id}/documents/{dId}` | secretary | Modifier infos |
| DELETE | `/associations/{id}/documents/{dId}` | secretary | Supprimer |

---

## Mon compte

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| GET  | `/me/overview` | ✅ | Dashboard global cross-associations (tontines, emprunts, solidarité) |
| GET  | `/me/loans` | ✅ | Mes emprunts (toutes associations) |

---

## Reports (états imprimables)

| Méthode | Endpoint | Rôle min | Description |
|---------|----------|----------|-------------|
| GET | `/associations/{id}/reports/members` | treasurer | Liste membres (PDF + CSV) |
| GET | `/associations/{id}/reports/member/{userId}` | treasurer | Fiche membre (PDF) |
| GET | `/associations/{id}/reports/tontine/{tId}` | treasurer | État tontine — avoirs, rotation, dettes (PDF) |
| GET | `/associations/{id}/reports/loans` | treasurer | État emprunts actifs + retards (PDF + CSV) |
| GET | `/associations/{id}/reports/solidarity` | treasurer | Relevé caisse solidarité (PDF) |
| GET | `/associations/{id}/reports/bureau` | member | Bureau actuel — postes et mandats (PDF) |
| GET | `/associations/{id}/reports/fundraising/{fId}` | member | Détail main levée (PDF) |
| GET | `/associations/{id}/reports/session/{sId}` | member | PV de séance tontine (PDF) |

Query param : `?format=pdf` (défaut) ou `?format=csv` (disponible selon le plan)

---

## Abonnements & Plans

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| GET | `/plans` | ❌ | Liste des plans disponibles |
| GET | `/associations/{id}/subscription` | president | Abonnement actif |
| POST | `/associations/{id}/subscription` | president | Souscrire / changer de plan |
| DELETE | `/associations/{id}/subscription` | president | Annuler l'abonnement |
| GET | `/admin/subscriptions` | super_admin | Vue globale abonnements |

---

## Notifications

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| GET  | `/notifications` | ✅ | Mes notifications |
| PUT  | `/notifications/{nId}/read` | ✅ | Marquer lue |
| PUT  | `/notifications/read-all` | ✅ | Tout marquer lu |

---

## Codes de réponse HTTP utilisés

| Code | Signification |
|------|---------------|
| 200 | OK |
| 201 | Created |
| 204 | No Content (DELETE) |
| 400 | Bad Request (validation) |
| 401 | Unauthorized (token manquant/expiré) |
| 403 | Forbidden (rôle insuffisant) |
| 404 | Not Found |
| 409 | Conflict (ex: email déjà pris) |
| 422 | Unprocessable Entity |
| 402 | Payment Required (quota plan dépassé) |
| 429 | Too Many Requests (rate limit) |
| 500 | Internal Server Error |
