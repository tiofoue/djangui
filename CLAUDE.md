# CLAUDE.md — Djangui Tontine Platform

## Contexte projet
Plateforme SaaS multi-associations de gestion de tontine.
Paradigme **API-first** → une API CI4 consommée par un frontend Vue 3 et une app mobile Flutter.

## Stack technique
- **Backend** : CodeIgniter 4.7+, PHP 8.2
- **Base de données** : MySQL 8.0
- **Cache / Blacklist JWT / OTP** : Redis
- **Auth** : JWT (firebase/php-jwt) — access token 15 min, refresh token 7 j (stocké en DB)
- **SMS** : Africa's Talking (OTP + notifications)
- **Frontend Web** : Vue 3 + Vite + Pinia (Sprint 6 — repo séparé)
- **Mobile** : Flutter (Sprint 7 — repo séparé)
- **Dev local** : Laragon (Apache + PHP 8.2 + MySQL 8)
- **VCS** : GitHub → https://github.com/tiofoue/djangui

## Architecture
- HMVC modulaire : `app/Modules/{Module}/`
- Chaque module : Controllers/, Models/, Services/, Entities/, Config/Routes.php
- Multi-tenant : toutes les requêtes scoped par `association_id`
- Un utilisateur peut appartenir à **plusieurs associations** avec des rôles différents
- **3 types d'entités** : `tontine_group` (auto-approuvé, léger) | `association` (validé) | `federation` (validé, hiérarchique)
- Les features (bureau, emprunts, solidarité...) sont conditionnées au type de l'entité

## Rôles (par association)
| Rôle | Niveau | tontine_group |
|------|--------|:---:|
| `super_admin` | Plateforme (accès total) | — |
| `president` | Association (accès total) | ✅ |
| `treasurer` | Finances, validation paiements | ✅ |
| `secretary` | Membres, documents | ❌ |
| `auditor` | Lecture seule finances | ❌ |
| `censor` | Lecture seule + surveillance | ❌ |
| `member` | Compte personnel, demandes | ✅ |

> Dans un `tontine_group`, seuls `president`, `treasurer` et `member` sont applicables.
> Le `president` hérite implicitement des permissions `treasurer` pour les opérations tontine.

## Modules
```
app/Modules/
├── Auth/           # JWT register/login/refresh/logout/reset/switch-association
├── Associations/   # CRUD + settings + validation super_admin
├── Bureau/         # Postes, mandats, suppléances, élections (association & federation)
├── Members/        # Invitation (SMS+email), profils, dashboard global
├── Tontines/       # Cycles, sessions, cotisations, 4 modes rotation, enchères
├── Loans/          # Emprunts, approbation, remboursements (association & federation)
├── Solidarity/     # Caisse solidarité + main levées (association & federation)
├── Documents/      # Statuts, règlements upload/download (association & federation)
└── Notifications/  # SMS (Africa's Talking), Email (SMTP), Push (FCM)
```

## Points architecturaux clés
- **Permissions effectives** : dérivées dynamiquement de `bureau_terms` + `bureau_substitutions` à chaque requête via `RoleFilter`
- **`association_members.effective_role`** : cache mis à jour par `BureauService`
- **4 modes de rotation tontine** : `random` | `manual` | `bidding` (pré-tontine) | `session_auction` (enchères par séance)
- **Parts multiples** : `total_sessions = SUM(shares) / beneficiaries_per_session` ; un membre peut bénéficier X fois
- **Heure limite cotisation** : `tontines.session_deadline_time` (TIME, défaut 23:59) interprétée dans le timezone effectif
- **Création association/federation** : workflow `pending_review → active` (validation super_admin)
- **Tokens JWT** : access → Redis blacklist à déconnexion ; refresh → table `refresh_tokens` en DB

## Timezone
- Plateforme : `Africa/Douala` (UTC+1) — stockage DB toujours en UTC
- Hiérarchie : **Plateforme → Association (`association_settings.timezone`) → Tontine (`tontines.timezone`)**
- Utilisé pour : heure limite cotisation, ouverture sessions, rappels, échéances

## Conventions de code
- **PSR-12** strict
- Logique métier dans les Services (pas dans les Controllers)
- Réponses API : `{ "status": "success|error", "data": {}, "message": "" }`
- Validation CI4 Rules dans les Models
- Migrations : `app/Database/Migrations/` | Seeds : `app/Database/Seeds/`

## Fichiers importants
- `ARCHITECTURE.md` — Structure dossiers, flux auth, format réponses
- `ROADMAP.md` — Sprints et planning
- `docs/DATABASE.md` — Schéma complet des tables
- `docs/API.md` — Tous les endpoints
- `docs/MODULES.md` — Détail des modules et composants
- `docs/BUSINESS_RULES.md` — Règles métier complètes
- `docs/TODO.md` — Tâches par sprint
- `docs/DONE.md` — Décisions et documentation terminées

## Setup local (Laragon)
1. Installer Laragon (laragon.org)
2. Cloner le repo dans `C:\laragon\www\djangui\`
3. Créer virtual host `djangui.test` via Laragon
4. `composer install`
5. Copier `.env.example` → `.env`, configurer DB + JWT secret + Africa's Talking API key
6. `php spark migrate` + `php spark db:seed DemoSeeder`
7. API dispo sur `http://djangui.test/api/`

## Notes importantes
- Pas de frontend dans ce repo (repo séparé)
- Toujours utiliser `php spark` pour les commandes CI4
- Tests : `tests/` avec PHPUnit, `vendor/bin/phpunit` (Laragon, pas Docker)
