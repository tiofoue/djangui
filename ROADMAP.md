# Roadmap — Djangui

## Vision
Plateforme SaaS multi-associations pour la gestion complète de tontines,
d'épargne, d'emprunts et de caisses de solidarité.
API-first → Web (Vue 3) + Mobile (Flutter).

---

## Statut global

| Sprint | Périmètre | Statut |
|--------|-----------|--------|
| Sprint 1 | Fondations, Auth, Associations, Members, Plans | ✅ Complet (2026-03-07) |
| Sprint 2 | Séances & Assemblées, Tontines & Bureau | 🟡 En cours |
| Sprint 3 | Cycles, Épargnes & Emprunts | 🔲 En attente Sprint 2 |
| Sprint 4 | Solidarité & Documents | 🔲 En attente Sprint 3 |
| Sprint 5 | Notifications, Reports & Polish API | 🔲 En attente Sprint 4 |
| Sprint 6 | Frontend Web (Vue 3) | 🔲 En attente Sprint 5 |
| Sprint 7 | Mobile (Flutter) | 🔲 En attente Sprint 5 |
| Sprint 8 | Mise en production | 🔲 En attente Sprint 5 |

> Sprint 1 complet (2026-03-07) — 83 tests passing. Sprint 2 business rules en cours (2026-03-08).

## Dépendances entre sprints
```
Sprint 1 (Auth + Membres) ✅
  ├── Sprint 2 (Séances + Tontines + Bureau)
  ├── Sprint 3 (Cycles + Épargnes + Emprunts)  → dépend Sprint 2
  └── Sprint 4 (Solidarité + Documents)         → dépend Sprint 3
        └── Sprint 5 (Polish API)               → dépend Sprint 4
              ├── Sprint 6 (Web)
              ├── Sprint 7 (Mobile)
              └── Sprint 8 (Prod)
```

---

## Sprint 1 — Fondations & Auth ✅ COMPLET (2026-03-07)

**Objectif** : Projet CI4 opérationnel, auth JWT, associations, membres.

- [x] Setup CI4 4.7+ avec Laragon + structure HMVC + BaseController/Model/Service
- [x] Module Auth : register, verify-phone (OTP SMS), login, refresh, logout, reset-password, switch-association, GET/PUT me
- [x] Module Associations : CRUD, champs identité, custom fields, settings, workflow validation super_admin
- [x] Module Members : invitation (SMS + email), rôles, retrait, dashboard cross-associations
- [x] Middleware AuthFilter + RoleFilter + TontineModeratorFilter + QuotaFilter
- [x] Module Plans : tables `plans` + `subscriptions`, `PlanService`, `QuotaFilter`
- [x] Migrations + DemoSeeder
- [x] Tests PHPUnit Members (28/28 ✅) — Tests Associations/Plans : en attente

---

## Sprint 2 — Séances & Assemblées, Tontines & Bureau

**Objectif** : Réunions périodiques + cycle de tontine complet + organe dirigeant.

> Bureau & Elections : `association` et `federation` uniquement (pas `tontine_group`)

**Module Séances & Assemblées :**
- [ ] Migrations : `public_holidays`, `seances`, `seance_participants`, `assemblees`, `assemblee_participants`, `agenda_items`
- [ ] `SeanceService` : génération auto cycle, getCurrent(), clôture manuelle + job, snapshot épargne, réassignation si cancelled
- [ ] `AgendaService` : points système auto (séance + assemblée), suggest() historique
- [ ] Job `CloseOverdueSeances` : clôture auto à 23h59 de actual_date
- [ ] Tests PHPUnit Séances & Assemblées

**Module Tontines :**
- [ ] CRUD, inscription membres, génération sessions liées aux séances (`seance_id` obligatoire)
- [ ] 4 modes rotation (random/manual/bidding/session_auction) + PenaltyCalculator
- [ ] Enchères bidding + session_auction + redistribution caisse
- [ ] Modérateur + rétrogradation, reconduction tacite
- [ ] Job `OpenDueSessions` : pending → open/auction au matin de session_date
- [ ] Tests PHPUnit Tontines

**Module Bureau & Elections :**
- [ ] Postes, mandats, suppléances, workflow élection (draft → open → closed)
- [ ] Tests PHPUnit Bureau

---

## Sprint 3 — Cycles, Épargnes & Emprunts

**Objectif** : Exercice financier, épargne collective, emprunts.

> Réservé aux entités `association` et `federation`

**Module Cycles :**
- [ ] Migrations `association_cycles` + settings `cycle_start_month` / `cycle_duration_months`
- [ ] `CycleService` : activation, génération séances du cycle, clôture (validation prêts soldés), distribution intérêts
- [ ] Label auto "Exercice YYYY-YYYY", end_date calculée

**Module Savings :**
- [ ] Migrations savings_accounts, savings_transactions, savings_snapshots, savings_pool_entries
- [ ] `SavingsService` : deposit, presence, snapshot (déclenché à clôture séance), capital disponible, blockForGuarantee
- [ ] `InterestDistributionService` : pro-rata, distribution fin de cycle

**Module Loans :**
- [ ] Migrations loans, loan_repayments, loan_guarantees
- [ ] `LoanService` : demande, garanties, approbation, décaissement, remboursements, renew/forceRenew
- [ ] Calcul intérêts simple et composé, génération échéancier
- [ ] Imputation remboursements : pénalités → intérêts → capital
- [ ] Job `CheckLoanDefaults` : active → defaulted après `loan_default_delay_days`
- [ ] Job `CheckLoanRenewals` : détecte prêts non soldés à due_date → notifie trésorier
- [ ] Tests PHPUnit (Cycles + Savings + Loans)

---

## Sprint 4 — Solidarité & Documents

**Objectif** : Caisse de solidarité + gestion documentaire.

> Réservé aux entités `association` et `federation`

- [ ] Module Solidarity : caisse permanente, cotisations, demandes de déblocage, main levées
- [ ] Module Documents : upload (PDF/images), statuts en vigueur (`is_current`), accès public/privé
- [ ] Tests PHPUnit (Solidarity + Documents)

---

## Sprint 5 — Notifications, Reports & Polish API

**Objectif** : Notifications, états imprimables, paiements, finalisation API.

- [ ] Notifications SMS (Africa's Talking) + Email (SMTP) + Push (Firebase FCM)
- [ ] Module Reports : 8 types de rapports PDF + CSV (membres, tontine, emprunts, bureau, PV séance...)
- [ ] `PdfGenerator` (dompdf) + `CsvExporter` — entête avec champs identité + custom fields association
- [ ] Intégration paiement : MTN MoMo + Orange Money → activation abonnement
- [ ] Job `CheckSubscriptions` : expiration → downgrade plan free
- [ ] Rate limiting global + CORS whitelist
- [ ] Audit log complet
- [ ] Tests d'intégration complets
- [ ] Documentation API (OpenAPI/Swagger)

---

## Sprint 6 — Frontend Web (Vue 3)

**Objectif** : Dashboard web complet. *(repo séparé)*

- [ ] Setup Vue 3 + Vite + Pinia + Vue Router + Tailwind CSS
- [ ] Auth, associations, membres, tontines, emprunts, solidarité, documents

---

## Sprint 7 — Mobile (Flutter)

**Objectif** : App mobile iOS + Android. *(repo séparé)*

- [ ] Setup Flutter, auth, dashboard, cotisations, emprunts, notifications push (FCM), mode hors-ligne partiel

---

## Sprint 8 — Mise en production

- [ ] VPS Ubuntu 22.04 + Docker (PHP 8.2 + MySQL 8 + Redis + Nginx)
- [ ] SSL Let's Encrypt + CI/CD GitHub Actions
- [ ] Monitoring + backup automatique DB
