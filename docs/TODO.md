# TODO — Djangui

## Sprint 1 — Fondations & Auth ← EN COURS

### ✅ Setup environnement — TERMINÉ (2026-03-05)
- [x] Installer Laragon sur Windows (PHP 8.3, Apache, MySQL 8)
- [x] Créer virtual host `djangui.test` (auto-créé par Laragon)
- [x] Installer CI4 4.7.0 via Composer
- [x] Configurer `.env` (DB, baseURL, JWT secret, Africa's Talking API key)
- [x] Installer dépendances : `firebase/php-jwt` v7
- [x] Configurer `phpunit.xml`
- [x] Push initial sur GitHub

### ✅ Structure HMVC — TERMINÉE (2026-03-05)
- [x] Créer `app/Common/BaseController.php` (12 méthodes respond* JSON standardisées)
- [x] Créer `app/Common/BaseModel.php` (scoping association_id + RuntimeException si oublié)
- [x] Créer `app/Common/BaseService.php` (abstract, injection tenant, gmdate UTC)
- [x] Configurer autoload des modules dans `app/Config/Autoload.php`
- [x] Configurer routes globales dans `app/Config/Routes.php`

### 🔴 PROCHAINE TÂCHE — Migrations Phase 1 (database-architect → php-pro)
**Ordre obligatoire (dépendances FK) :**
- [ ] Migration `plans` (plans SaaS : free/starter/pro/federation)
- [ ] Migration `associations` (+ champs identité : slogan, phone, address, bp, tax_number, auth_number)
- [ ] Migration `association_settings` (+ colonnes label, is_custom)
- [ ] Migration `subscriptions` (FK → plans + associations)
- [ ] Migration `users` (phone NOT NULL UNIQUE, email NULL, is_super_admin, phone_verified_at, email_verified_at)
- [ ] Migration `password_resets`
- [ ] Migration `refresh_tokens` (token_hash, jti, expires_at, revoked_at)
- [ ] Migration `association_members` (effective_role inclut `censor`, left_at)
- [ ] Migration `invitations` (phone NULL, email NULL — contrainte phone OR email au niveau Service)

### Module Auth
**Code :**
- [ ] `UserModel` + `UserEntity`
- [ ] `SmsLibrary` : intégration Africa's Talking (envoi OTP)
- [ ] `JwtLibrary` : generate, verify, blacklist Redis (access) + DB (refresh)
- [ ] `AuthService` : register, login, login-otp, refresh, logout, reset, switch-association
- [ ] `AuthController` : POST /auth/register (phone obligatoire, email optionnel)
- [ ] `AuthController` : POST /auth/verify-phone (OTP SMS)
- [ ] `AuthController` : POST /auth/resend-otp
- [ ] `AuthController` : POST /auth/login (phone ou email + password)
- [ ] `AuthController` : POST /auth/login/otp + /auth/login/otp/verify
- [ ] `AuthController` : POST /auth/refresh
- [ ] `AuthController` : POST /auth/logout
- [ ] `AuthController` : POST /auth/forgot-password + /auth/reset-password
- [ ] `AuthController` : GET/PUT /auth/me
- [ ] `AuthController` : POST /auth/switch-association
- [ ] `AuthFilter` middleware
- [ ] Tests Auth

### Module Associations
**Code :**
- [ ] `AssociationModel` + `AssociationEntity`
- [ ] `AssociationSettingModel` (CRUD custom fields + protection clés système)
- [ ] `AssociationService` : CRUD + normalisation clés custom (trim→lower→accents→snake_case)
- [ ] `AssociationController` : CRUD complet + admin endpoints (super_admin)
- [ ] `SettingsController` : GET/PUT /associations/{id}/settings (clés système + custom)
- [ ] `PlanModel` + `SubscriptionModel` + `PlanService`
- [ ] `SubscriptionController` : GET/POST/DELETE /associations/{id}/subscription
- [ ] `QuotaFilter` middleware (max_members, max_tontines, max_entities, features)
- [ ] Tests Associations + Plans

### Module Members
**Code :**
- [ ] `AssociationMemberModel` + `InvitationModel`
- [ ] `MemberService` : invite (SMS si phone + email si dispo), accepter, changer rôle, retirer
- [ ] `MemberService` : valider rôles selon type entité (tontine_group → treasurer|member uniquement)
- [ ] `MemberController` : liste, profil, retrait, changement rôle
- [ ] `InvitationController` : créer invitation + accepter via token
- [ ] `MeController` : GET /me/overview (dashboard cross-associations)
- [ ] `RoleFilter` middleware (president = treasurer implicite pour tontine_group)
- [ ] `TontineModeratorFilter` middleware (moderateur_id OU treasurer OU president)
- [ ] Tests Members

### Seeds
- [ ] `DemoSeeder` : 1 tontine_group + 1 association + 1 admin (avec phone) + 5 membres

---

## Sprint 2 — Tontines & Bureau

### Module Tontines
**Migrations :**
- [ ] `tontines` (session_deadline_time, timezone)
- [ ] `tontine_members` (left_at)
- [ ] `tontine_sessions` (cycle_number NOT NULL DEFAULT 1, opened_at, UNIQUE(tontine_id, cycle_number, session_number))
- [ ] `contributions` (UNIQUE(session_id, member_id))
- [ ] `tontine_caisse_distributions` (cycle_number NOT NULL, UNIQUE(tontine_id, cycle_number, member_id))
- [ ] `tontine_session_bids`, `tontine_session_beneficiaries`, `tontine_slot_demotions`

**Code :**
- [ ] `TontineService` : démarrage, génération sessions, clôture, reconduction
- [ ] `RotationService` : random / manual / bidding / session_auction
- [ ] `PenaltyCalculator` : 8 modes (fixed, fixed_per_day/week/month, percentage, percentage_per_day/week/month)
- [ ] Résolution timezone effectif : `TontineService::getTimezone()` (tontine → association → plateforme)
- [ ] Règle bidding : tous les membres doivent avoir bid_amount > 0 avant démarrage
- [ ] Logique reconduction : incrémenter current_cycle, reset slots_received, nouvelles sessions
- [ ] `BidController` : PUT /members/me/bid (bidding) + POST/GET /sessions/{sId}/bids (session_auction)
- [ ] Tests Tontines

### Module Bureau & Elections
**Migrations :**
- [ ] `bureau_positions`, `bureau_terms` (unicité mandat actif enforced au Service, pas en DB)
- [ ] `bureau_substitutions`
- [ ] `elections`, `election_positions`
- [ ] `election_candidates` (election_position_id FK → election_positions.id)
- [ ] `election_votes` (election_position_id FK → election_positions.id)

**Code :**
- [ ] `BureauService` : calcul permissions effectives, cascade suppléance, mise à jour effective_role
- [ ] `ElectionService` : workflow draft → open → closed + publication résultats → création bureau_terms
- [ ] Tous les controllers Bureau (positions, terms, substitutions, elections)
- [ ] Tests Bureau & Elections

### Jobs planifiés
- [ ] `OpenDueSessions` : pending → open/auction au matin de session_date (timezone effectif)

---

## Sprint 3 — Emprunts

### Module Loans
**Migrations :**
- [ ] `loans`, `loan_repayments`
- [ ] `loan_guarantees` (guarantor_user_id, tontine_member_id — pas de ref_id)

**Code :**
- [ ] `LoanService` : workflow pending → approved → active (disburse) → completed
- [ ] `InterestCalculator` : intérêts simple + composé, génération échéancier à disbursed_at
- [ ] Endpoint `PUT /loans/{lId}/disburse` (génère échéancier, calcule total_due)
- [ ] Imputation remboursements : pénalités → intérêts → capital
- [ ] Tests Loans

### Jobs planifiés
- [ ] `CheckLoanDefaults` : active → defaulted après loan_default_delay_days (configurable)

---

## Sprint 4 — Solidarité & Documents

### Module Solidarity & Fundraising
**Migrations :**
- [ ] `solidarity_funds`, `solidarity_contributions`, `solidarity_requests`
- [ ] `fundraisings` (status: open/closed/handed_over), `fundraising_contributions`

**Code :**
- [ ] Endpoint `PUT /solidarity/requests/{rId}/cancel` (member*, pending uniquement)
- [ ] `FundraisingService` : clôture, remise (handed_over réservé au président)
- [ ] Tests Solidarity + Fundraising

### Module Documents
**Migrations :**
- [ ] `documents` (is_current TINYINT DEFAULT 0)

**Code :**
- [ ] `DocumentService` : enforcer is_current = 1 unique par type par association
- [ ] GET /documents/{dId} (métadonnées JSON) + GET /documents/{dId}/download (stream binaire)
- [ ] Tests Documents

---

## Sprint 5 — Reports & Business Model

### Module Reports
- [ ] Installer `dompdf` via Composer
- [ ] `PdfGenerator` : rendu HTML→PDF avec entête (logo + champs identité + custom fields)
- [ ] `CsvExporter` : export générique
- [ ] `ReportService` : 8 types (members, member, tontine, loans, solidarity, bureau, fundraising, session)
- [ ] `ReportController` : GET /associations/{id}/reports/{type}?format=pdf|csv
- [ ] Templates HTML par type de rapport (Twig ou CI4 views)
- [ ] Tests Reports

### Business model — Paiement
- [ ] Intégration MTN Mobile Money API
- [ ] Intégration Orange Money API
- [ ] `SubscriptionService::activate()` via webhook de confirmation paiement
- [ ] Job `CheckSubscriptions` : abonnements expirés → downgrade plan free
