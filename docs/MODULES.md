# Détail des modules — Djangui

## Structure type d'un module

```
app/Modules/{Module}/
├── Config/
│   └── Routes.php          # Routes du module
├── Controllers/
│   └── {Name}Controller.php
├── Models/
│   └── {Name}Model.php
├── Entities/
│   └── {Name}Entity.php    # Optionnel
└── Services/
    └── {Name}Service.php   # Logique métier
```

---

## Module Auth

**Responsabilité** : Gestion des identités utilisateurs et des tokens JWT.

### Composants
- `AuthController` — Endpoints register, verify-phone, resend-otp, login, login/otp, login/otp/verify, refresh, logout, forgot-password, reset-password, switch-association, GET/PUT me
- `UserModel` — CRUD users, validation phone unique (NOT NULL), email unique si fourni (NULL autorisé)
- `UserEntity` — Accesseurs (fullName, isActive, etc.)
- `AuthService` — Logique register/login/login-otp/logout/reset
- `JwtLibrary` — Génération, vérification, blacklist JWT
- `AuthFilter` — Middleware vérifiant le Bearer token sur chaque requête protégée

### Tokens
- **Access token** : durée 15 minutes, payload = `{ user_id, phone, exp }`
- **Refresh token** : durée 7 jours, stocké en DB (table `refresh_tokens`) — persistance garantie
- **Blacklist** : à la déconnexion, le JTI est blacklisté dans Redis jusqu'à expiration

### Identité & vérification
- **Téléphone** : identifiant principal, obligatoire, unique — vérification OTP SMS à l'inscription
- **Email** : optionnel — si fourni, un lien de vérification est envoyé en complément
- **Connexion bloquée** si `phone_verified_at IS NULL`
- **Fournisseur SMS** : Africa's Talking (couverture Cameroun/CEMAC, API REST)
- **OTP** : 6 chiffres, durée de validité 10 minutes, stocké en Redis

### Flux de connexion (hybride)
```
Flux principal  (sans SMS) : phone ou email + password → tokens
Flux alternatif (avec SMS) : phone → OTP envoyé → vérifier OTP → tokens
```
- Le flux principal est recommandé — pas de coût SMS, pas de dépendance réseau
- Le flux alternatif est proposé en option pour les utilisateurs peu à l'aise avec les mots de passe (familier du pattern MTN MoMo / Orange Money)
- Les deux flux produisent les mêmes tokens JWT
- L'OTP de connexion est distinct de l'OTP de vérification d'inscription (Redis keys différentes)

---

## Module Associations

**Responsabilité** : Création et gestion des associations, settings personnalisés.

### Composants
- `AssociationController` — CRUD association
- `SettingsController` — GET/PUT settings
- `AssociationModel` — CRUD, slug auto-généré
- `AssociationSettingModel` — Stockage clé/valeur
- `AssociationService` — Logique création, validation slug unique, gestion settings
- `AssociationEntity` — Accesseurs (getSettings, isActive...)

### Champs d'identité (table `associations`)
Communs aux 3 types, tous éditables après création :
- `slogan` — message affiché sur états imprimables et PDF
- `logo` — uploadé dans `public/uploads/associations/{id}/`
- `phone`, `address`, `bp` — coordonnées
- `tax_number`, `auth_number` — champs administratifs (pertinents pour association/federation)

### Champs personnalisés (`association_settings`, `is_custom = 1`)
- L'admin peut ajouter des champs libres (ex: "Num de Compte", "Email RH")
- **Normalisation automatique** par `AssociationService` : `"Num de Compte"` → key=`num_de_compte`, label=`"Num de Compte"`
- Algorithme : trim → lowercase → translitération accents → espaces/spéciaux → `_` → dédoublonnage `_`
- Les clés système (`is_custom = 0`) sont protégées (non modifiables/supprimables par l'utilisateur)
- Affichés sur les états imprimables via le `label` original

### Notes
- **tontine_group** : auto-approuvé à la création, pas de bureau formel, pas de statuts requis
  - `president` a implicitement les permissions `treasurer` pour les opérations tontine
  - Rôles disponibles : `president`, `treasurer`, `member` uniquement
  - Invitation limitée aux rôles `treasurer` et `member` (validé dans `MemberService`)
  - `PUT /members/{userId}/role` permet d'assigner `treasurer` sans élection
- **association** : `pending_review` → validation super admin → `active`, créateur devient `president`
- **federation** : idem association + gestion des sous-associations via `parent_id`
- Le formulaire de création varie selon le type (statuts requis uniquement pour association/federation)
- Les endpoints `/admin/associations/*` sont réservés au `super_admin`
- Les features disponibles (bureau, emprunts, solidarité...) sont conditionnées au `type` et au plan SaaS actif

---

## Module Bureau

**Responsabilité** : Gestion de l'organe dirigeant de l'association — postes, mandats, suppléances, élections.

### Postes principaux du bureau (par défaut)
| Poste | Catégorie | Permission |
|-------|-----------|------------|
| Président | presidency | president |
| Secrétaire Général | secretariat | secretary |
| Trésorier | treasury | treasurer |
| Commissaire aux Comptes | audit | auditor |
| Censeur | censorship | censor |

Chaque poste peut avoir des suppléants (1er Vice-..., 2ème Vice-..., Adjoint...)

### Composants
- `BureauPositionController` — CRUD postes du bureau
- `BureauTermController` — Mandats actifs, nominations, fin de mandat
- `BureauSubstitutionController` — Déclaration et gestion des suppléances
- `ElectionController` — Organisation complète du scrutin
- `BureauPositionModel` — Définition des postes (catégorie, rang, durée mandat)
- `BureauTermModel` — Historique des mandats
- `BureauSubstitutionModel` — Suppléances actives et archivées
- `ElectionModel` — Élections
- `ElectionCandidateModel` — Candidatures + résultats
- `ElectionVoteModel` — Votes individuels
- `BureauService` — Logique métier bureau (calcul permissions effectives, cascade suppléance)
- `ElectionService` — Workflow élection (ouverture, vote, clôture, publication → création mandats)

### Logique de permissions effectives
Les permissions sont **calculées à la volée** par `RoleFilter` à chaque requête :
1. Chercher un `bureau_term` actif → récupérer `permission_level` du poste
2. Chercher une `bureau_substitution` active → hériter le `permission_level` du poste suppléé
3. Prendre le niveau le plus élevé entre les deux
4. Mettre à jour `association_members.effective_role` (cache — mis à jour par `BureauService`)

### Logique de cascade suppléance
```
Poste X indisponible → chercher bureau_positions WHERE parent_position_id = X ORDER BY rank ASC
→ premier rang disponible et actif prend la suppléance
→ si indisponible aussi → rang suivant, etc.
```

### Workflow élection
```
draft → open (vote actif) → closed (dépouillement + publication résultats)
```
- `published` n'est pas un statut — c'est une **action** sur une élection `closed`
- À la publication : `elections.results_published_at = now()`, création automatique des `bureau_terms` pour les élus
- Les mandats précédents pour ces postes sont automatiquement clôturés (`ended_at = now()`)
- Une élection `cancelled` peut survenir depuis `draft` ou `open`

---

## Module Members

**Responsabilité** : Gestion des membres d'une association, invitations.

### Composants
- `MemberController` — Liste, profil, retrait membre
- `InvitationController` — Créer invitation, accepter via token
- `AssociationMemberModel` — Pivot user ↔ association avec effective_role
- `InvitationModel` — Token d'invitation avec expiration
- `MemberService` — Logique invitation (SMS primaire + email si dispo), acceptation, changement rôle, retrait, validation rôles par type entité
- `RoleFilter` — Middleware vérifiant le `effective_role` calculé par BureauService (association-scoped)

### Logique d'invitation
1. Admin/secretary crée une invitation avec phone et/ou email (au moins un requis) + rôle
2. Si phone fourni : SMS envoyé avec lien court vers `https://app.djangui.test/invite/{token}` (canal primaire)
3. Si email fourni : email envoyé (canal primaire si pas de phone, secondaire sinon)
4. L'invité clique, s'inscrit ou se connecte
5. `POST /api/invitations/{token}/accept` crée l'entrée dans `association_members`

### Dashboard global
- `GET /me/overview` agrège les données de toutes les associations de l'utilisateur
- Calcul : épargne tontine en attente, dettes actives, cotisations solidarité

---

## Module Tontines

**Responsabilité** : Gestion complète des cycles de tontine.

### Composants
- `TontineController` — CRUD tontine, démarrage, clôture, désignation modérateur
- `SessionController` — Gestion des sessions (ouverture, clôture, bénéficiaires)
- `ContributionController` — Enregistrement des paiements
- `BidController` — Enchères pré-tontine (mode bidding) et enchères par séance (mode session_auction)
- `TontineModel` — CRUD tontines
- `TontineMemberModel` — Inscription membres + parts + bid_amount + left_at
- `TontineSessionModel` — Sessions avec cycle_number, opened_at, closed_at
- `ContributionModel` — Paiements par membre par session (UNIQUE session+member)
- `BidModel` — Enchères session_auction (`tontine_session_bids`)
- `TontineService` — Logique métier (démarrage, clôture, génération sessions, reconduction)
- `RotationService` — Détermination du bénéficiaire selon rotation_mode (random/manual/bidding/session_auction)
- `TontineModeratorFilter` — Middleware tontine-scoped : accès si `moderateur_id == user` OU `effective_role IN (treasurer, president)`

### Parts multiples
- À l'inscription, chaque membre choisit son nombre de parts (`shares`, minimum 1)
- Cotisation par session = `shares × montant_base`
- Nombre total de sessions du cycle = `SUM(shares) / beneficiaries_per_session`
- La rotation génère autant de **slots** que de parts par membre

### Modes de rotation
> Voir BUSINESS_RULES.md § "Modes d'enchères — comparatif" pour le détail complet.

| Mode | Résumé |
|------|--------|
| `random` | Tirage au sort automatique au démarrage |
| `manual` | Admin définit l'ordre manuellement |
| `bidding` | Enchères pré-tontine → détermine l'ordre pour tout le cycle |
| `session_auction` | Enchères à chaque séance → caisse commune redistribuée en fin de cycle |

### Bénéficiaires multiples par séance
- `beneficiaries_per_session` (défaut 1) : N membres bénéficient à chaque séance
- La cagnotte est divisée équitablement entre les N bénéficiaires
- Nombre de séances = SUM(shares) / beneficiaries_per_session
- Exemple : 2 membres × 2 parts, beneficiaries_per_session = 2 → total_sessions = 4/2 = **2 séances** ; à chaque séance, 2 membres reçoivent la cagnotte/2
- Géré via `tontine_session_beneficiaries` (un enregistrement par slot par séance)

### Mode session_auction
- À chaque séance : enchères ouvertes aux membres éligibles (slots restants > 0)
- Gagnant = plus-disant → reçoit cagnotte − montant_adjugé
- Montant adjugé → `tontines.caisse_balance` (cumulé)
- Enchères tracées dans `tontine_session_bids`
- À la clôture : redistribution proportionnelle aux parts via `tontine_caisse_distributions`

### Modérateur de tontine
- Membre désigné à la création ou ultérieurement (`tontines.moderateur_id`)
- Peut rétrograder un membre défaillant dans l'ordre de rotation (motif obligatoire)
- Rétrogradation loguée dans `tontine_slot_demotions`
- En son absence, le trésorier ou président peut exercer cette action

### Reconduction tacite
- `auto_renew = true` (défaut) : nouveau cycle automatique à la fin du précédent
- L'ordre est redéfini au début de chaque cycle selon le même `rotation_mode`
- `max_cycles` : limite optionnelle de cycles (NULL = illimité)
- Entre deux cycles : fenêtre de désistement pour les membres

### Génération des sessions
- Au `start`, les sessions sont générées automatiquement
- Nombre de séances = SUM(shares) / beneficiaries_per_session
- Fréquence `daily` : une session par jour ouvrable
- Fréquence `weekly` : une session par semaine
- Fréquence `monthly` : une session par mois
- Bénéficiaires pré-assignés via `tontine_session_beneficiaries` (sauf `session_auction`)
- Un membre avec 3 parts apparaît 3 fois dans la rotation (pas consécutivement)

### Pénalités
- Si `paid_at` > `session_date` + `session_deadline_time` (dans le timezone effectif), une pénalité est calculée
- 8 modes via `late_penalty_type` + `late_penalty_value` (voir BUSINESS_RULES.md § Pénalités de retard)
- Calcul délégué à `PenaltyCalculator` :
  - **Entrées** : `amount_due`, `jours_de_retard`, `late_penalty_type`, `late_penalty_value`
  - **Sortie** : `penalty` (DECIMAL, jamais négatif)

### Timezone effectif
- `TontineService::getTimezone(tontine)` : retourne `tontines.timezone` → sinon `association_settings.timezone` → sinon `Africa/Douala`
- Utilisé pour : heure limite de paiement, ouverture automatique des sessions (job `OpenDueSessions`), notifications

---

## Module Loans

**Responsabilité** : Gestion des emprunts entre l'association et ses membres.

### Composants
- `LoanController` — CRUD demandes, approbation, rejet, confirmation garant
- `RepaymentController` — Enregistrement remboursements
- `LoanModel` — CRUD loans
- `LoanGuaranteeModel` — Garanties associées (création en cascade avec le loan)
- `LoanRepaymentModel` — Échéancier et paiements
- `LoanService` — Workflow approbation, création garanties en cascade, taux depuis settings, génération échéancier
- `InterestCalculator` — Calcul intérêts simple/composé, génération échéancier

### Calcul d'intérêts

**Intérêt simple :**
```
Total intérêts = Principal × Taux × Durée (en années)
Mensualité = (Principal + Total intérêts) / Durée en mois
```

**Intérêt composé (formule d'annuité) :**
```
taux_mensuel = Taux_annuel / 12
Mensualité = Principal × (taux_mensuel × (1 + taux_mensuel)^n) / ((1 + taux_mensuel)^n - 1)
où n = Durée en mois
```

### Types de garantie
| Type | Description |
|------|-------------|
| `member` | Un autre membre de l'association se porte garant |
| `savings` | L'épargne du demandeur est bloquée en garantie |
| `tontine_share` | Les parts tontine non encore perçues sont en garantie |
| `admin_approval` | Simple approbation par le président/trésorier |

### Workflow emprunt
```
member    → POST /loans           (status: pending)
treasurer → PUT  /loans/approve   (status: approved)
treasurer → PUT  /loans/disburse  (status: active, disbursed_at = now(), génère échéancier)
treasurer → PUT  /loans/reject    (status: rejected)
treasurer → POST /repayments      (status: active → completed si soldé)
```

---

## Module Solidarity

**Responsabilité** : Caisse de solidarité permanente + main levées ponctuelles.

### Composants
- `SolidarityController` — Gestion fond permanent + demandes de déblocage
- `FundraisingController` — Main levées (initiation, contributions, remise)
- `SolidarityFundModel` — Balance du fond permanent
- `SolidarityContributionModel` — Cotisations périodiques au fond
- `SolidarityRequestModel` — Demandes de déblocage fond permanent
- `FundraisingModel` — Main levées
- `FundraisingContributionModel` — Contributions volontaires aux main levées
- `SolidarityService` — Logique versements, approbations, balance, `creditFundFromFundraising()`
- `FundraisingService` — Logique main levée : clôture, remise, archivage, versement au fond si `beneficiary_type = fund`

### Raisons de demande (fond permanent)
- Décès (membre ou famille proche)
- Mariage
- Maladie
- Naissance
- Autre (avec description)

### Traçabilité des versements
À la transition `approved → disbursed`, le Service requiert :
- `payment_method` : cash | mtn_momo | orange_money | transfer
- `recorded_by` : id du trésorier/président ayant enregistré le versement

### Main levée
- Initiée par le président ou le trésorier. La remise (`handed_over`) est réservée au président.
- Montant fixe suggéré ou libre
- Contributions volontaires des membres
- Archivage complet et permanent à la remise (motif + contributions + bénéficiaire + notes)
- **3 types de bénéficiaire** : `member` (membre identifié) | `external` (personne externe) | `fund` (renflouement caisse solidarité)
- Si `beneficiary_type = fund` : `FundraisingService` appelle `SolidarityService::creditFundFromFundraising()` → crédite `solidarity_funds.balance`

---

## Module Documents

**Responsabilité** : Stockage et accès aux documents de l'association.

### Composants
- `DocumentController` — Upload, liste, téléchargement, suppression
- `DocumentModel` — Métadonnées fichiers
- `DocumentService` — Validation upload, stockage, accès
- `FileUpload` Library — Gestion des uploads (validation MIME, taille max)

### Types de documents
- `statutes` — Statuts de l'association
- `regulations` — Règlement intérieur
- `pv` — Procès verbaux de réunion
- `other` — Autres documents

### Endpoints
- `GET /documents/{dId}` → métadonnées JSON (title, type, size, mime_type, is_public...)
- `GET /documents/{dId}/download` → stream binaire du fichier (Content-Disposition: attachment)

### Stockage
- **Documents privés** : stockés dans `writable/uploads/documents/{association_id}/` (hors `public/`, non accessible via URL directe)
- **Documents publics** (`is_public = true`) : stockés dans `public/uploads/documents/{association_id}/` (servis directement par Apache)
- `GET /documents/{dId}/download` : le Service lit le fichier et le streame via CI4 → AuthFilter appliqué pour les documents privés
- Logos associations : `public/uploads/associations/{id}/` (toujours public)

---

## Module Notifications

**Responsabilité** : Envoi et gestion des notifications.

### Événements déclencheurs
| Événement | Canal |
|-----------|-------|
| Invitation reçue | SMS (primaire) + Email si disponible |
| Remboursement enregistré | SMS + Email si disponible + Push |
| Rappel cotisation (J-2) | SMS + Email si disponible + Push |
| Cotisation en retard | SMS + Email si disponible + Push |
| Emprunt approuvé/rejeté | SMS + Email si disponible + Push |
| Emprunt en défaut | SMS + Email si disponible + Push |
| Demande solidarité approuvée | SMS + Email si disponible + Push |
| Nouveau document publié | Push |

### Services
- `NotificationService` — Création notifications en DB, dispatch vers les bons canaux
- `SmsService` — Envoi SMS via Africa's Talking (rappels, alertes, notifications critiques)
- `EmailService` — Envoi via SMTP (Mailgun/SMTP local), uniquement si email fourni
- `PushService` — Firebase FCM (Sprint 7)

> `SmsLibrary` (Auth) gère les OTP. `SmsService` (Notifications) gère les alertes métier — deux usages distincts, bibliothèque partagée.

---

## Module Reports (exports imprimables)

**Responsabilité** : Génération d'états imprimables PDF et exports CSV.

### Composants
- `ReportController` — Endpoints GET par type de rapport
- `ReportService` — Collecte des données, formatage
- `PdfGenerator` — Rendu HTML → PDF via `dompdf` (logo + en-tête association)
- `CsvExporter` — Export CSV des listes

### États disponibles
| Rapport | Contenu | Format | Rôle min |
|---------|---------|--------|----------|
| `members` | Liste membres (nom, téléphone, rôle, adhésion) | PDF + CSV | treasurer |
| `member/{userId}` | Fiche membre (cotisations, emprunts, solidarité) | PDF | treasurer |
| `tontine/{tId}` | État tontine (sessions, avoirs, dettes, rotation) | PDF | treasurer |
| `loans` | État des emprunts actifs et en retard | PDF + CSV | treasurer |
| `solidarity` | Relevé caisse solidarité | PDF | treasurer |
| `bureau` | Bureau actuel (postes, titulaires, mandats) | PDF | member |
| `fundraising/{fId}` | Détail main levée (contributions, remise) | PDF | member |
| `session/{sId}` | PV de séance tontine (présents, paiements, bénéficiaire) | PDF | member |

### Entête des documents PDF
Composée des champs d'identité de l'association : logo, name, slogan, address, bp, phone, tax_number, auth_number + champs personnalisés (`is_custom = 1`).

### Endpoints
```
GET /associations/{id}/reports/{type}?format=pdf|csv
```

> **Plan requis** : exports PDF disponibles à partir du plan `pro`. CSV disponible dès `starter`.

---

## Business model — Plans & Quotas

**Responsabilité** : Gestion des plans SaaS et enforcement des limites.

### Composants
- `QuotaFilter` — Middleware vérifiant les limites du plan avant chaque action critique
- `PlanService` — Récupération plan actif, vérification features, calcul quotas
- `SubscriptionController` — Gestion abonnement par association
- `PlanModel` + `SubscriptionModel`

### Plans (voir DATABASE.md § Plans initiaux)
| Plan | Prix/mois | Limites | Features supplémentaires |
|------|-----------|---------|--------------------------|
| `free` | 0 | 1 entité, 15 membres, 1 tontine | Tontines basiques |
| `starter` | ~2 000 XAF | 1 entité, 50 membres, 3 tontines | + Emprunts, solidarité, documents |
| `pro` | ~5 000 XAF | 3 entités, illimité, illimité | + Bureau, élections, exports PDF |
| `federation` | ~15 000 XAF | illimité | + Fédération, sous-associations |

### QuotaFilter — Actions vérifiées
| Action | Quota vérifié |
|--------|---------------|
| Créer une entité | `max_entities` |
| Inviter un membre | `max_members` |
| Créer une tontine | `max_tontines` |
| Accéder au module Bureau | feature `bureau` dans plan |
| Accéder aux emprunts | feature `loans` dans plan |
| Générer un PDF | feature `reports` dans plan |

- Réponse si quota dépassé : **HTTP 402 Payment Required** `{ "message": "Limite du plan atteinte. Passez au plan supérieur." }`
- Réponse si feature non incluse : **HTTP 403 Forbidden** `{ "message": "Cette fonctionnalité nécessite un plan supérieur." }`

### Paiement (Sprint 5)
- Méthodes : MTN Mobile Money, Orange Money, virement manuel
- Webhooks de confirmation → `SubscriptionService::activate()`
- Job planifié `CheckSubscriptions` : expiration → downgrade vers plan `free`
