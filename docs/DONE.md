# DONE — Décisions et documentation terminées

## Sprint 1 — Module Associations & Plans (2026-03-07) commit 584b2e2

### Associations + Plans complets — 15 routes, QuotaFilter, sécurité
- [x] `Modules/Associations/Entities/AssociationEntity` : casts, `isActive()`, `toPublicArray()`
- [x] `Modules/Associations/Models/AssociationModel` : `$scopedByAssociation=false`, `findBySlug/UserId/Children/Pending/AllPaginated`, `countPending/AllActive()`
- [x] `Modules/Associations/Models/AssociationSettingModel` : `$scopedByAssociation=true`, `$useTimestamps=false`, upsert pattern, `getAllForAssociation/getByKey/upsertSetting/deleteCustom`
- [x] `Modules/Plans/Models/PlanModel` + `SubscriptionModel` : `$scopedByAssociation=false`, `getActivePlans/findByName`, `getForAssociation/getWithPlan` (JOIN)
- [x] `Modules/Plans/Services/PlanService` : `getPlans/getSubscription/subscribe/cancel/createFreeTrial/checkQuota` + `requireMembership/requirePresident` (vérification DB)
- [x] `Modules/Associations/Services/AssociationService` : CRUD complet, workflows approve/reject/suspend/reinstate, settings, `SYSTEM_SETTING_KEYS` allowlist, rate-limit Redis 10/h, transaction DB sur create(), validation parent_id
- [x] `Modules/Associations/Controllers/AssociationController` : 12 endpoints + guards super_admin
- [x] `Modules/Associations/Controllers/SettingsController` : 2 endpoints GET/PUT settings
- [x] `Modules/Plans/Controllers/SubscriptionController` : 4 endpoints (plans public + sub auth)
- [x] `Modules/Associations/Config/Routes.php` + `Modules/Plans/Config/Routes.php` : 15 routes, filtre auth
- [x] `Filters/QuotaFilter` : HTTP 402, association_id depuis URL (pas JWT), `$resourceTables`, `tableExists` guard Sprint 1
- [x] `Config/Filters.php` : alias `'quota'` enregistré
- [x] `code-reviewer` : 5 NEEDS FIXES corrigés (soft-delete redondants, countAll→countAllActive, transaction DB, accès subscription, QuotaFilter URL)
- [x] `security-auditor` : 9 CRITICAL/HIGH corrigés (IDOR subscription, privilege escalation, rate-limiting, statutes_file/logo regex, parent_id validation, getById 404 uniforme, SYSTEM_SETTING_KEYS, QuotaFilter URL)

---

## Sprint 1 — i18n & Diaspora (2026-03-07) commits fe74457 + 4e262f7

### Bilingue FR/EN + membres internationaux — TERMINÉ
- [x] Migration corrective `2026-03-07-000001_AddLanguageToUsers` : `language ENUM('fr','en') NOT NULL DEFAULT 'fr'` après `avatar`
- [x] `UserModel` : `'language'` ajouté à `$allowedFields` + `$validationRules` (fix security-auditor)
- [x] `SmsLibrary::sendOtp($phone, $purpose, $language='fr')` + `buildOtpMessage()` bilingues (match FR / match EN)
- [x] `JwtLibrary::generateAccessToken()` : claim `lang` dans le payload JWT
- [x] `AuthService` : langue propagée à tous les `sendOtp()` et `generateAccessToken()` ; whitelist `in_array(['fr','en'])` + fallback `'fr'`
- [x] `AuthController` : `permit_empty|in_list[fr,en]` sur `register()` et `updateMe()`
- [x] `BUSINESS_RULES.md` : section "Internationalisation & Diaspora" (bilingue, E.164 international, convention timezone/deadline_utc côté client)
- [x] `DATABASE.md` : colonne `language` documentée sur `users`
- [x] `ARCHITECTURE.md` : sections "Internationalisation" + "Convention Datetime & Timezone"
- [x] `CLAUDE.md` : section "Timezone & i18n" enrichie
- [x] `API.md` : `language` documenté sur `POST /auth/register` et `PUT /auth/me`
- [x] `code-reviewer` : APPROVED (1 point mineur corrigé : dead import `use CodeIgniter\Model`)
- [x] `security-auditor` : NEEDS FIXES → 2 corrections appliquées (`allowedFields` + `validationRules` dans `UserModel`)

---

## Sprint 1 — Module Auth (2026-03-07) commit e2c023c

### Auth complet — 13 endpoints, JWT, OTP, sécurité
- [x] `Config/Auth.php` : JWT (HS256, access 15min, refresh 7j), OTP (TTL 10min, max 5 tentatives, blocage 15min)
- [x] `Libraries/JwtLibrary.php` : generateAccessToken + generateRefreshToken (hash SHA-256 DB) + verifyAccessToken + blacklistAccessToken (Redis) + revokeRefreshToken + findValidRefreshToken
- [x] `Libraries/SmsLibrary.php` : Africa's Talking OTP 6 chiffres, stockage hash SHA-256 Redis (jamais le code en clair), TTL dynamique, compteur tentatives
- [x] `Services/AuthContext.php` : singleton statique PHP 8.2 compatible (remplace `$request->user` propriété dynamique dépréciée)
- [x] `Filters/AuthFilter.php` : middleware JWT → `AuthContext::set()`, 401 si token absent/invalide
- [x] `Modules/Auth/Models/UserModel.php` + `Entities/UserEntity.php` : findByPhone, findByEmail, findByPhoneOrEmail, setPassword (bcrypt), verifyPassword, toPublicArray
- [x] `Modules/Auth/Services/AuthService.php` : register, verifyPhone, resendOtp, login (rate limit 10/15min Redis), requestLoginOtp, verifyLoginOtp, refreshToken (rotation), logout (blacklist + revoke), forgotPassword, resetPassword, getMe, updateMe, switchAssociation
- [x] `Modules/Auth/Controllers/AuthController.php` : 13 endpoints REST, validation CI4, délégation AuthService
- [x] `code-reviewer` : NEEDS FIXES → 5 corrections appliquées (AuthContext PHP 8.2, resetPassword body, TTL dynamique)
- [x] `security-auditor` : NEEDS FIXES → 4 corrections appliquées (anti-énumération, rate limiting login, OTP reset via Redis pas DB)

---

## Sprint 1 — Migrations Phase 1 (2026-03-05) commit ac8fcd9

### 9 migrations tables fondations — TERMINÉES
- [x] Ordre FK corrigé : `plans` → `users` → `associations` → `association_settings` → `subscriptions` → `password_resets` → `refresh_tokens` → `association_members` → `invitations` (TODO.md avait `associations` avant `users` — corrigé)
- [x] `database-architect` : validation schémas + ON DELETE/UPDATE + index composites + corrections (country CHAR(2), currency CHAR(3), joined_at NOT NULL, expires_at NOT NULL)
- [x] `php-pro` : 9 fichiers `app/Database/Migrations/2026-03-05-00000X_CreateXxxTable.php`, CI4 DbForge, InnoDB/utf8mb4/utf8mb4_unicode_ci
- [x] `code-reviewer` : APPROVED — PSR-12, types, FK, index, down() tous conformes
- [x] `DATABASE.md` : `country CHAR(2)` + `currency CHAR(3)` (conformité ISO 3166-1 / ISO 4217)

---

## Sprint 1 — Architecture reconduction prêts (2026-03-05)

### Reconduction = nouvel enregistrement (décision tracabilité)
- [x] Décision : reconduction crée un **nouveau** `loans` record (pas UPDATE) pour traçabilité financière complète
- [x] `loans` : ajout `parent_loan_id FK → loans.id` + `source ENUM('new','renewal_cap','renewal_forced')`
- [x] `original_amount` = montant au décaissement de ce prêt spécifique (pas du prêt racine)
- [x] `renewal_count` = cache de profondeur dans la chaîne (recalculable via parent_loan_id)
- [x] `BUSINESS_RULES.md` : section Reconduction réécrite — 2 cas documentés avec création nouveau record, exemple Hermann complet (loan #1→#4), chaîne parent_loan_id
- [x] `DATABASE.md` : parent_loan_id + source ajoutés à loans, note explicative chaîne
- [x] `MODULES.md` : LoanService::renew() + forceRenew() documentés
- [x] `API.md` : GET /loans/{lId}/chain ajouté, note contrainte mise à jour

---

## Sprint 1 — Corrections épargne-crédit (analyse fichier réel AJRD 2025) (2026-03-05)

### Corrections issues de l'analyse du fichier Excel réel (association AJRD)
- [x] `BUSINESS_RULES.md` : taux d'intérêt documenté comme "par période" (pas annualisé) + exemple 7%/trimestre
- [x] `BUSINESS_RULES.md` : reconduction — 2 cas documentés : CAS 1 (capitalisation : amount × (1+rate) si remboursement complet) | CAS 2 (solde restant si impayé forcé) — remplace la doc "reconduction sur solde restant" unique
- [x] `BUSINESS_RULES.md` : snapshot = solde **cumulatif** clarifié (pas dépôt mensuel seul) — vérifié sur données réelles AJRD
- [x] `BUSINESS_RULES.md` : Présence documentée (cotisation versée à chaque séance d'assemblée, non inclus dans pro-rata intérêts)
- [x] `DATABASE.md` : `loans.interest_rate` commentaire corrigé (par période) ; `savings_snapshots.balance` précisé (cumulatif) ; `savings_transactions.type` étendu à `presence` ; nouvelle clé `presence_amount`
- [x] `MODULES.md` : LoanService reconduction mise à jour (2 cas) ; SavingsService présence (recordPresence) + snapshot cumulatif
- [x] `API.md` : endpoints Présence ajoutés (POST/GET /savings/presence) ; note contrainte cycle corrigée

---

## Sprint 1 — Setup & Fondations (2026-03-05)

### Environnement & CI4
- [x] Laragon installé : PHP 8.3, Apache, MySQL 8 — virtual host `djangui.test` auto-créé
- [x] CI4 4.7.0 installé via Composer (`codeigniter4/appstarter`)
- [x] `firebase/php-jwt` v7 installé
- [x] `.env` configuré (baseURL, DB, JWT_SECRET, JWT_ACCESS_TTL=900, JWT_REFRESH_TTL=604800, Redis, Africa's Talking)
- [x] `phpunit.xml` configuré depuis `phpunit.xml.dist`
- [x] Git initialisé + remote GitHub + push initial (commit `d80f6e3`)

### Structure HMVC
- [x] 11 modules créés : `Auth`, `Associations`, `Bureau`, `Members`, `Tontines`, `Loans`, `Solidarity`, `Documents`, `Notifications`, `Reports`, `Plans`
- [x] `app/Common/BaseController` — 12 méthodes `respond*` JSON standardisées (HTTP 200/201/204/400/401/402/403/404/409/422/429)
- [x] `app/Common/BaseModel` — scoping multi-tenant `association_id` ; RuntimeException si scope oublié ; `$scopedByAssociation = false` pour tables globales
- [x] `app/Common/BaseService` — classe abstraite, injection tenant, `gmdate()` UTC, `paginationMeta()`
- [x] `app/Config/Autoload` — namespaces PSR-4 pour tous les modules + Common + Filters + Libraries
- [x] `app/Config/Routes` — chargement automatique routes de chaque module, `setAutoRoute(false)`
- [x] Revue `code-reviewer` : 3 corrections appliquées (UTC gmdate, multi-tenant sécurisé, opt-out scopedByAssociation)

### Outillage Claude
- [x] `kit-djangui/` installé : 8 agents (php-pro, code-reviewer, security-auditor, api-architect, database-architect, database-optimization, git-flow-manager, deploy-manager) + 3 commandes (/new-feature, /review, /deploy)
- [x] `CLAUDE.md` mis à jour — workflow 5 étapes, orchestration agents par type de tâche

---

## Phase 1 — Planification & Architecture (2026-03-03)

- [x] Stack défini : CI4 4.7+, MySQL 8, Redis, JWT (firebase/php-jwt), Vue 3, Flutter
- [x] Architecture HMVC modulaire : `app/Modules/{Auth, Associations, Bureau, Members, Tontines, Loans, Solidarity, Documents, Notifications}`
- [x] Rôles définis : `super_admin`, `president`, `treasurer`, `secretary`, `auditor`, `censor`, `member`
- [x] Multi-tenant scoped par `association_id` ; un user → plusieurs associations avec rôles différents
- [x] 3 types d'entités : `tontine_group` (auto-approuvé) | `association` | `federation`
- [x] API-first : backend CI4 → Web (Vue 3) + Mobile (Flutter) séparés
- [x] Roadmap 8 sprints définie
- [x] Fichiers de documentation créés (CLAUDE.md, ARCHITECTURE.md, ROADMAP.md, docs/)
- [x] Repo GitHub créé : https://github.com/tiofoue/djangui

---

## Phase 2 — Compléments fonctionnels (2026-03-04)
- [x] Champs identité association : `slogan`, `logo`, `phone`, `address`, `bp`, `tax_number`, `auth_number`
- [x] Tontines : champ `slogan` ajouté
- [x] Custom fields : `association_settings` enrichi (`label` + `is_custom`) + règle de normalisation snake_case
- [x] Business model SaaS : plans `free/starter/pro/federation` + tables `plans` + `subscriptions`
- [x] `QuotaFilter` middleware documenté (limites plan + features)
- [x] Module Reports : 8 types d'états imprimables PDF + CSV, `PdfGenerator`, `CsvExporter`
- [x] Entête PDF : champs identité + custom fields de l'association
- [x] Paiement : MTN MoMo + Orange Money + job `CheckSubscriptions`
- [x] Intégration dans : DATABASE.md, BUSINESS_RULES.md, MODULES.md, API.md, ARCHITECTURE.md, ROADMAP.md, TODO.md

## Sprint 1 — Cycles, Épargnes & Emprunts enrichis (2026-03-05)

### Cycle d'activité, épargne-crédit, reconduction prêts
- [x] `association_cycles` : table ajoutée (`draft → active → closing → closed`) — bornes de tous les prêts et épargnes
- [x] `savings_accounts` + `savings_transactions` + `savings_snapshots` + `savings_pool_entries` : nouveau module Épargnes
- [x] `loans` : ajout `cycle_id` FK, `original_amount` (conservé lors reconductions), `renewal_count`
- [x] `BUSINESS_RULES.md` : nouvelles sections "Cycle d'activité" + "Épargnes" (formule pro-rata, protection anti-gaming, 2 exemples chiffrés) + sous-sections Emprunts (lien cycle, remboursement flexible, reconduction sur solde restant)
- [x] `MODULES.md` : Associations → CycleController/Model/Service ; nouveau module Savings (SavingsController, InterestDistributionService, flux complet) ; Loans → LoanService reconduction
- [x] `API.md` : nouvelle section Cycles + nouvelle section Épargnes + note contraintes cycle sur Emprunts
- [x] `DATABASE.md` : 5 nouvelles tables, 3 nouveaux champs loans, 3 nouvelles clés settings
- [x] `association_settings` : nouvelles clés `savings_enabled`, `cycle_start_month`, `loan_interest_distribution`

---

## Sprint 1 — Processus demande de prêt (2026-03-05)

### Clarifications flux emprunt
- [x] `BUSINESS_RULES.md` : section "Corps de la demande (DTO)" — body POST /loans documenté (amount, duration_months, purpose, guarantees[])
- [x] `BUSINESS_RULES.md` : `interest_rate` fixé par `LoanService` depuis `association_settings.loan_max_rate` (pas saisi par le membre)
- [x] `BUSINESS_RULES.md` : cycle de vie garanties `pending → confirmed → released` documenté
- [x] `BUSINESS_RULES.md` : diagramme workflow prêt mis à jour (incluant étape confirmation garant)
- [x] `MODULES.md` : `LoanController` — endpoint confirmation garant ajouté ; `LoanService` — création garanties en cascade + taux depuis settings
- [x] `API.md` : endpoint `PUT /loans/{lId}/guarantees/{gId}/confirm` ajouté ; note corps POST /loans (DTO + interest_rate auto + garanties cascade)
- [x] `DATABASE.md` : `loans.interest_rate` — note "fixé par LoanService depuis association_settings(loan_max_rate)"

---

## Sprint 1 — Corrections solidarité (2026-03-05)

### Traçabilité & renflouement caisse de solidarité
- [x] `solidarity_requests` : ajout `payment_method` (cash|mtn_momo|orange_money|transfer) + `recorded_by FK → users.id` — champs obligatoires au moment du disburse
- [x] `fundraisings.beneficiary_type` : ENUM étendu à `'member'|'external'|'fund'` + `fund_id FK → solidarity_funds.id` — permet de collecter pour renflouer la caisse
- [x] `BUSINESS_RULES.md` : section "Traçabilité des versements" + section "Renflouement" + tableau types bénéficiaire + logique `creditFundFromFundraising()`
- [x] `MODULES.md` : SolidarityService + FundraisingService mis à jour (creditFundFromFundraising, payment_method, recorded_by)
- [x] `API.md` : `/disburse` note payment_method requis ; `/hand-over` note 3 types bénéficiaire
- [x] `DATABASE.md` : schémas `solidarity_requests` + `fundraisings` mis à jour

---

## Phase 2 — Audits & corrections documentation (2026-03-03 → 2026-03-04)

### Modèle de données (DATABASE.md)
- [x] `tontine_sessions` : `cycle_number` + `opened_at` ajoutés, UNIQUE(tontine_id, cycle_number, session_number)
- [x] `tontine_caisse_distributions` : `cycle_number` + UNIQUE(tontine_id, cycle_number, member_id)
- [x] `contributions` : UNIQUE(session_id, member_id)
- [x] `bureau_terms` : contrainte UNIQUE partielle supprimée → enforced au niveau Service
- [x] `election_candidates` + `election_votes` : `election_position_id FK → election_positions.id`
- [x] `documents` : champ `is_current` ajouté
- [x] `loan_guarantees` : `guarantor_user_id` + `tontine_member_id` (suppression `ref_id`), timestamps
- [x] `fundraisings.status` : `remise_au_beneficiaire` → `handed_over`
- [x] `association_members` + `tontine_members` : `left_at` ajouté
- [x] `users` : champ `is_super_admin TINYINT(1) DEFAULT 0` ajouté
- [x] Tables `password_resets` + `refresh_tokens` ajoutées
- [x] `tontines` : `session_deadline_time TIME DEFAULT '23:59:00'` + `timezone VARCHAR(50) NULL`
- [x] `tontines.max_members` → `INT UNSIGNED NULL` | `tontine_members.slots_received` → `INT UNSIGNED DEFAULT 0`
- [x] Settings : `late_penalty_rate` remplacé par `late_penalty_type` + `late_penalty_value`

### Règles métier (BUSINESS_RULES.md)
- [x] Tontine_group : president = treasurer implicite ; rôles limités à president/treasurer/member
- [x] Éligibilité bénéfice : formule `ceil()` (remplace `round()`) + table d'exemples
- [x] Parts multiples : `total_sessions = SUM(shares) / beneficiaries_per_session`
- [x] Modérateur tontine : renommé depuis "censeur de tontine" (éviter confusion avec censeur du bureau)
- [x] Bureau : un seul poste à la fois (principal ou suppléant, sans cumul)
- [x] Bureau fédération : identique à l'association, distinct des bureaux des sous-associations
- [x] session_auction : enchères par séance, caisse commune, redistribution proportionnelle aux parts
- [x] Reconduction : `auto_renew`, `max_cycles`, `current_cycle`, reset `slots_received`
- [x] Main levée : initiée par président OU trésorier ; `handed_over` réservé au président
- [x] Emprunts + Solidarité : réservés aux `association` et `federation` (pas `tontine_group`)
- [x] Remboursements : imputation pénalités → intérêts → capital
- [x] Pénalités : 8 modes documentés avec formules (`fixed*`, `percentage*`, par jour/semaine/mois)
- [x] Heure limite cotisation : `session_deadline_time` sur la tontine, interprétée dans le timezone effectif
- [x] Hiérarchie timezone : plateforme (Africa/Douala) → association → tontine

### API & Architecture
- [x] `POST /auth/switch-association` ajouté (API.md + MODULES.md + ARCHITECTURE.md)
- [x] `GET/PUT /auth/me` ajoutés dans description AuthController
- [x] `PUT /sessions/{sId}/close` uniformisé (était POST)
- [x] Stockage tokens : refresh → DB (`refresh_tokens`), blacklist access → Redis
- [x] BidController : mode bidding (PUT /members/me/bid) + mode session_auction (POST/GET /sessions/{sId}/bids)
- [x] Sprint 2 ROADMAP.md → "Tontines & Bureau" (Bureau & Elections ajouté)
- [x] Notification "Remboursement enregistré" (était "approuvé")
- [x] Invitation : phone OR email (au moins un requis), contrainte au niveau Service
