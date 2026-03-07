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

### ✅ Migrations Phase 1 — TERMINÉES (2026-03-05) commit ac8fcd9
**Ordre corrigé (dépendances FK) :**
- [x] Migration `plans`
- [x] Migration `users` (avant associations — FK reviewed_by/created_by)
- [x] Migration `associations` (country CHAR(2), currency CHAR(3))
- [x] Migration `association_settings` (label, is_custom, UNIQUE assoc+key)
- [x] Migration `subscriptions` (FK → plans RESTRICT, UNIQUE association_id)
- [x] Migration `password_resets` (sans FK — anti-énumération)
- [x] Migration `refresh_tokens` (token_hash, jti, index composite user+revoked)
- [x] Migration `association_members` (effective_role inclut censor, joined_at NOT NULL)
- [x] Migration `invitations` (phone OR email, FK invited_by RESTRICT)

### ✅ Module Auth — TERMINÉ (2026-03-07) commit e2c023c
**Code :**
- [x] `UserModel` + `UserEntity`
- [x] `SmsLibrary` : intégration Africa's Talking (envoi OTP), hash SHA-256 Redis
- [x] `JwtLibrary` : generate, verify, blacklist Redis (access) + DB (refresh)
- [x] `AuthService` : register, login, login-otp, refresh, logout, reset, switch-association
- [x] `AuthController` : 13 endpoints REST
- [x] `AuthFilter` middleware + `AuthContext` (PHP 8.2 compat)
- [x] Anti-énumération, rate limiting Redis login (10/15min), OTP reset SMS

### ✅ i18n & Diaspora — TERMINÉ (2026-03-07)
- [x] Migration corrective `2026-03-07-000001_AddLanguageToUsers` — `language ENUM('fr','en') DEFAULT 'fr'`
- [x] `SmsLibrary::sendOtp()` + `buildOtpMessage()` bilingues (FR/EN)
- [x] `JwtLibrary::generateAccessToken()` — claim `lang` dans le JWT
- [x] `AuthService` — langue propagée à tous les `sendOtp()` + `generateAccessToken()`
- [x] `AuthController` — validation `permit_empty|in_list[fr,en]` sur `register` + `updateMe`
- [x] Docs : BUSINESS_RULES, DATABASE, ARCHITECTURE, CLAUDE.md, API.md

### ✅ Module Associations + Plans — TERMINÉ (2026-03-07) commit 584b2e2
**Code :**
- [x] `AssociationModel` + `AssociationEntity`
- [x] `AssociationSettingModel` (CRUD custom fields + protection clés système)
- [x] `AssociationService` : CRUD + normalisation clés custom (trim→lower→accents→snake_case)
- [x] `AssociationController` : CRUD complet + admin endpoints (super_admin)
- [x] `SettingsController` : GET/PUT /associations/{id}/settings (clés système + custom)
- [x] `PlanModel` + `SubscriptionModel` + `PlanService`
- [x] `SubscriptionController` : GET/POST/DELETE /associations/{id}/subscription
- [x] `QuotaFilter` middleware (max_members, max_tontines, max_entities, features)
- [ ] Tests Associations + Plans

### ✅ Module Members — TERMINÉ (2026-03-07)
**Code :**
- [x] `AssociationMemberModel` + `InvitationModel`
- [x] `MemberService` : invite (SMS si phone + email si dispo), accepter, changer rôle, retirer
- [x] `MemberService` : valider rôles selon type entité (tontine_group → treasurer|member uniquement)
- [x] `MemberController` : liste, profil, retrait, changement rôle
- [x] `InvitationController` : créer invitation + accepter via token
- [x] `MeController` : GET /me/overview (dashboard cross-associations)
- [ ] `RoleFilter` middleware (president = treasurer implicite pour tontine_group) ← Sprint 2
- [ ] `TontineModeratorFilter` middleware (moderateur_id OU treasurer OU president) ← Sprint 2
- [ ] Tests Members

### 🔴 PROCHAINE TÂCHE — Seeds

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

## Sprint 3 — Cycles, Épargnes & Emprunts

### Module Associations — Cycles d'activité
**Migrations :**
- [ ] `association_cycles` (draft → active → closing → closed ; UNIQUE(association_id, cycle_number))

**Code :**
- [ ] `CycleModel` : CRUD association_cycles, requête cycle actif par association
- [ ] `CycleService` : activation (un seul actif par association), validation prêts soldés avant initiation clôture, distribution intérêts (appel `InterestDistributionService`), retrait épargnes à la clôture
- [ ] `CycleController` : CRUD cycles, activate, initiate-closing, close, interest-preview
- [ ] Tests Cycles

### Module Savings (Épargnes)
**Migrations (ordre FK) :**
- [ ] `savings_accounts` (UNIQUE(cycle_id, member_id))
- [ ] `savings_transactions` (type ENUM: deposit / withdrawal / interest_payout / presence)
- [ ] `savings_snapshots` (UNIQUE(account_id, snapshot_date))
- [ ] `savings_pool_entries`

**Mise à jour `loan_guarantees` :**
- [ ] Ajouter colonne `savings_account_id BIGINT UNSIGNED NULL FK → savings_accounts.id` (si type = savings)

**Code :**
- [ ] `SavingsAccountModel`, `SavingsTransactionModel`, `SavingsSnapshotModel`, `SavingsPoolEntryModel`
- [ ] `SavingsService` : `deposit()`, `recordPresence()`, `takeSnapshot()`, `getAvailableCapital()`, `blockForGuarantee()`, `releaseGuarantee()`
- [ ] `InterestDistributionService` : calcul pro-rata (score membre / score total × intérêts × loan_interest_distribution), génération transactions `interest_payout`
- [ ] `SavingsController` : comptes, dépôts, présence (POST/GET /savings/presence), pool, snapshots
- [ ] `SavingsPoolController` : apports externes (pool/entries)
- [ ] Tests Savings

### Module Loans
**Migrations :**
- [ ] `loans` (cycle_id FK → association_cycles.id, original_amount, renewal_count)
- [ ] `loan_repayments`
- [ ] `loan_guarantees` (guarantor_user_id, savings_account_id, tontine_member_id — pas de ref_id)

**Code :**
- [ ] `LoanService` : workflow pending → approved → active (disburse) → completed
- [ ] `LoanService` : contrainte due_date ≤ cycle.end_date, taux depuis association_settings.loan_max_rate
- [ ] `LoanService` : reconduction CAS 1 (capitalisation : amount × (1+rate)) + CAS 2 (solde restant)
- [ ] `LoanService` : création garanties en cascade (savings → blockForGuarantee, released → releaseGuarantee)
- [ ] `InterestCalculator` : intérêts simple + composé, génération échéancier à disbursed_at
- [ ] Endpoint `PUT /loans/{lId}/disburse` (génère échéancier, calcule total_due)
- [ ] Imputation remboursements : pénalités → intérêts → capital
- [ ] Tests Loans

### Jobs planifiés
- [ ] `CheckLoanDefaults` : active → defaulted après loan_default_delay_days (configurable)
- [ ] `TakeSavingsSnapshots` : déclenché au moment de chaque séance d'assemblée (snapshot solde cumulatif + loans_active)
- [ ] `CheckLoanRenewals` : reconduction sur solde restant si non soldé à due_date (CAS 2)

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
