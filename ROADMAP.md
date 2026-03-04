# Roadmap — Djangui

## Vision
Plateforme SaaS multi-associations pour la gestion complète de tontines,
d'épargne, d'emprunts et de caisses de solidarité.
API-first → Web (Vue 3) + Mobile (Flutter).

---

## Statut global

| Sprint | Périmètre | Statut |
|--------|-----------|--------|
| Sprint 1 | Fondations, Auth, Associations, Members | 🟡 Planifié — prêt à démarrer |
| Sprint 2 | Tontines & Bureau | 🔲 En attente Sprint 1 |
| Sprint 3 | Emprunts | 🔲 En attente Sprint 1 |
| Sprint 4 | Solidarité & Documents | 🔲 En attente Sprint 3 |
| Sprint 5 | Notifications & Polish API | 🔲 En attente Sprint 4 |
| Sprint 6 | Frontend Web (Vue 3) | 🔲 En attente Sprint 5 |
| Sprint 7 | Mobile (Flutter) | 🔲 En attente Sprint 5 |
| Sprint 8 | Mise en production | 🔲 En attente Sprint 5 |

> Phase de planification complète (2026-03-04). Code non démarré.

## Dépendances entre sprints
```
Sprint 1 (Auth + Membres)
  ├── Sprint 2 (Tontines & Bureau)
  ├── Sprint 3 (Emprunts)          → dépend Sprint 1
  └── Sprint 4 (Solidarité)        → dépend Sprint 3
        └── Sprint 5 (Polish API)  → dépend Sprint 4
              ├── Sprint 6 (Web)
              ├── Sprint 7 (Mobile)
              └── Sprint 8 (Prod)
```

---

## Sprint 1 — Fondations & Auth

**Objectif** : Projet CI4 opérationnel, auth JWT, associations, membres.

- [ ] Setup CI4 4.7+ avec Laragon + structure HMVC + BaseController/Model/Service
- [ ] Module Auth : register, verify-phone (OTP SMS), login, refresh, logout, reset-password, switch-association, GET/PUT me
- [ ] Module Associations : CRUD, champs identité (slogan, logo, phone, address, bp, tax_number, auth_number), custom fields normalisés, settings, workflow validation super_admin
- [ ] Module Members : invitation (SMS + email), rôles, retrait, dashboard cross-associations
- [ ] Middleware AuthFilter + RoleFilter + TontineModeratorFilter + **QuotaFilter**
- [ ] Module Plans : tables `plans` + `subscriptions`, `PlanService`, `QuotaFilter`
- [ ] Migrations + DemoSeeder (1 tontine_group + 1 association + admin + 5 membres)
- [ ] Tests PHPUnit (Auth + Associations + Members)

---

## Sprint 2 — Tontines & Bureau

**Objectif** : Cycle de tontine complet + organe dirigeant.

> Bureau & Elections : `association` et `federation` uniquement (pas `tontine_group`)

- [ ] Module Tontines : CRUD, inscription membres, génération sessions, 4 modes rotation (random/manual/bidding/session_auction)
- [ ] Cotisations + pénalités (8 modes via `PenaltyCalculator`, `late_penalty_type` + `late_penalty_value`)
- [ ] Heure limite cotisation (`session_deadline_time`) interprétée dans le timezone effectif
- [ ] Enchères bidding (pré-tontine) + session_auction (par séance + redistribution caisse)
- [ ] Modérateur de tontine + rétrogradation membres défaillants
- [ ] Reconduction tacite (`auto_renew`, `max_cycles`, `current_cycle`)
- [ ] Job planifié `OpenDueSessions` : pending → open/auction au matin de session_date
- [ ] Module Bureau & Elections : postes, mandats, suppléances, workflow élection (draft → open → closed)
- [ ] Tests PHPUnit (Tontines + Bureau)

---

## Sprint 3 — Emprunts

**Objectif** : Système d'emprunt complet avec calcul d'intérêts.

> Réservé aux entités `association` et `federation`

- [ ] Module Loans : demande, garanties (membre/épargne/tontine/admin), approbation, décaissement, remboursements
- [ ] Calcul intérêts simple et composé (formule d'annuité), génération échéancier
- [ ] Imputation remboursements : pénalités → intérêts → capital
- [ ] Job planifié `CheckLoanDefaults` : active → defaulted après `loan_default_delay_days`
- [ ] Tests PHPUnit Loans

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
