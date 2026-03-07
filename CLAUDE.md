# CLAUDE.md — Chef d'Orchestre du Projet Djangui SaaS CI4

## 🎯 Identité du projet
- **Produit** : SaaS multi-associations · Gestion tontines, épargne, emprunts, solidarité · Marché Cameroun/CEMAC
- **Framework** : CodeIgniter 4.7+ (architecture HMVC modulaire)
- **Paradigme** : API First → Web (Vue 3, repo séparé) + Mobile (Flutter, repo séparé)
- **Stack** : PHP 8.2, MySQL 8.0 (UTC), Redis (OTP cache + JWT blacklist), JWT (firebase/php-jwt), Africa's Talking (SMS)
- **Dev local** : Laragon (Apache + PHP 8.2 + MySQL 8) — pas de Docker
- **VCS** : GitHub → https://github.com/tiofoue/djangui
- **Phase actuelle** : Sprint 1 — Fondations & Auth (prêt à démarrer)

---

## 🔁 WORKFLOW OBLIGATOIRE — À respecter à chaque intervention

### Étape 0 — Avant TOUTE action
1. Lire `docs/TODO.md` → identifier la tâche prioritaire du sprint en cours
2. Lire `docs/DONE.md` → éviter les doublons et respecter les décisions passées
3. Lire `ARCHITECTURE.md` → respecter la structure HMVC et les flux définis
4. Lire `docs/BUSINESS_RULES.md` si la tâche touche une règle métier (tontines, emprunts, solidarité)
5. Lire `docs/DATABASE.md` si la tâche nécessite une migration ou une requête
6. Lire `docs/API.md` si la tâche touche une route API

### Étape 1 — Développement PHP/CI4
- **TOUJOURS** déléguer à l'agent `php-pro` pour toute écriture/modification de code PHP
- `php-pro` doit respecter : PSR-12, HMVC CI4, API First, docblocks complets, multi-tenant
- Si la tâche crée/modifie une migration → activer aussi la skill `migration-generator`
- Si la tâche implémente une règle métier (tontines, loans, solidarity) → activer aussi la skill `business-rules`

### Étape 2 — Review systématique
- Après **chaque** modification PHP → appeler automatiquement l'agent `code-reviewer`
- Si `code-reviewer` détecte des problèmes → refaire appel à `php-pro` pour corrections
- Si la tâche touche Auth / Filters / JWT / uploads / OTP → appeler aussi `security-auditor`

### Étape 3 — Tests
- Après chaque implémentation → utiliser la skill `phpunit-ci4` pour générer les tests
- Lancer : `vendor/bin/phpunit --stop-on-failure`
- Si les tests échouent → retourner à `php-pro` avec l'erreur exacte
- Couvrir systématiquement : succès, validation échouée, isolation multi-tenant, rôles insuffisants, quotas SaaS

### Étape 4 — Documentation
- Toute nouvelle route API → mise à jour `docs/API.md` (appeler `api-architect` si nouveau module)
- Toute nouvelle méthode publique → docblock PHPDoc obligatoire
- Toute décision d'architecture → mise à jour `ARCHITECTURE.md`
- Cocher la tâche dans `docs/TODO.md` et déplacer dans `docs/DONE.md`

### Étape 5 — Git
- Appeler l'agent `git-flow-manager` pour le commit et le push
- Format de commit : `[TYPE] Module: description courte`
  - Types : `FEAT`, `FIX`, `REFACTOR`, `DOCS`, `SECURITY`, `TEST`, `MIGRATION`
  - Exemples : `[FEAT] Auth: ajout endpoint POST /auth/switch-association`
  - Exemples : `[MIGRATION] Tontines: create_tontines_table et create_tontine_sessions_table`
- Appeler `deploy-manager` uniquement si déploiement VPS demandé explicitement

---

## 🤖 Agents — Rôles et déclenchement automatique

| Agent | Rôle | Déclenchement automatique |
|-------|------|--------------------------|
| `php-pro` | Implémentation PHP/CI4 | **Toujours** — toute écriture/modification de code PHP |
| `code-reviewer` | Revue qualité, architecture, conventions | **Toujours** — après chaque modification PHP |
| `security-auditor` | Audit sécurité JWT, multi-tenant, inputs, uploads | Si : Auth, Filters, JWT, Redis, uploads, OTP SMS |
| `api-architect` | Design et documentation des routes API | Si : nouveau module ou nouveau groupe d'endpoints |
| `database-architect` | Schéma DB, migrations CI4, modélisation | Si : nouvelle table, nouvelle migration, design schéma |
| `database-optimization` | Perf MySQL, index, N+1, cache Redis | Si : query lente, rapport lourd, dashboard, jobs planifiés |
| `git-flow-manager` | Commits, branches, tags | Si : commit, push, merge ou tag demandé |
| `deploy-manager` | CI/CD, déploiement VPS | Si : déploiement explicitement demandé |

### Orchestration par type de tâche

#### Créer un nouveau module (ex: Tontines, Loans)
```
Étape 0 → database-architect (schéma + migrations)
        → php-pro + skill:migration-generator + skill:business-rules
        → code-reviewer
        → security-auditor
        → api-architect
        → skill:phpunit-ci4 → lancer les tests
        → git-flow-manager
```

#### Modifier un endpoint existant
```
Étape 0 → php-pro
        → code-reviewer
        → (si auth/sécurité) security-auditor
        → skill:phpunit-ci4 → lancer les tests
        → git-flow-manager
```

#### Créer ou modifier une migration
```
Étape 0 (DATABASE.md obligatoire)
        → database-architect (valider le schéma)
        → php-pro + skill:migration-generator
        → code-reviewer
        → git-flow-manager
```

#### Implémenter une règle métier tontine / loan / solidarity
```
Étape 0 (BUSINESS_RULES.md obligatoire)
        → skill:business-rules → php-pro
        → code-reviewer
        → skill:phpunit-ci4 → lancer les tests
        → git-flow-manager
```

#### Query lente ou problème de performance DB
```
database-optimization (EXPLAIN + index manquants + N+1)
        → php-pro (appliquer les corrections)
        → code-reviewer
        → git-flow-manager
```

#### Module Reports ou dashboard lourd
```
Étape 0 → database-optimization (analyser les queries du module)
        → php-pro (appliquer optimisations + cache Redis)
        → code-reviewer
        → git-flow-manager
```

#### Audit ou correction sécurité
```
security-auditor → php-pro (corrections) → code-reviewer → git-flow-manager
```

#### Déploiement VPS
```
git-flow-manager (vérifier que main est propre) → deploy-manager
```

---

## 🎨 Skills — Activation automatique

| Skill | Activation automatique |
|-------|----------------------|
| `migration-generator` | Toute création/modification de migration CI4 |
| `business-rules` | Toute logique métier tontines / loans / solidarity / quotas SaaS |
| `phpunit-ci4` | Génération de tests PHPUnit après chaque implémentation |

---

## 📁 Architecture HMVC CI4

```
app/
├── Modules/
│   ├── Auth/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/      ← Logique métier UNIQUEMENT ici
│   │   ├── Entities/
│   │   └── Config/Routes.php
│   └── [Module]/          ← Même structure pour chaque module
├── Common/                ← BaseController, BaseModel, BaseService
├── Filters/               ← AuthFilter, RoleFilter, TontineModeratorFilter, QuotaFilter
├── Libraries/             ← JwtLibrary, SmsLibrary, PdfGenerator, CsvExporter
├── Commands/              ← Jobs planifiés (OpenDueSessions, CheckLoanDefaults, CheckSubscriptions)
└── Database/
    ├── Migrations/        ← Une migration par table
    └── Seeds/
```

### Modules actifs
| Module | Routes | Disponible pour |
|--------|--------|-----------------|
| Auth | `/api/auth/*` | Tous types |
| Associations | `/api/associations/*` | Tous types |
| Members | `/api/associations/{id}/members/*` | Tous types |
| Plans | `/api/plans/*` | Tous types |
| Tontines | `/api/associations/{id}/tontines/*` | Tous types |
| Bureau | `/api/associations/{id}/bureau/*` | `association`, `federation` uniquement |
| Loans | `/api/associations/{id}/loans/*` | `association`, `federation` uniquement |
| Solidarity | `/api/associations/{id}/solidarity/*` | `association`, `federation` uniquement |
| Documents | `/api/associations/{id}/documents/*` | `association`, `federation` uniquement |
| Notifications | interne | Tous types |
| Reports | `/api/associations/{id}/reports/*` | Tous types |

---

## 🔒 Règles de sécurité non négociables
- Validation **toujours** côté serveur via CI4 Rules — jamais faire confiance au client
- Pas de SQL raw sans Query Builder CI4 (pas d'interpolation de variables)
- JWT validé sur chaque endpoint protégé via `AuthFilter`
- Multi-tenant : toutes les requêtes scopées par `association_id` extrait du JWT (jamais du body)
- `BaseModel` applique le scope automatiquement — ne jamais le contourner
- Uploads : validation MIME type + taille ; documents privés → `writable/uploads/` (hors webroot)
- Africa's Talking SMS OTP : rate limiting obligatoire — chaque SMS = coût réel
- Aucune clé/secret dans le code → `.env` uniquement
- Logs sans données sensibles (pas de tokens, mots de passe, données personnelles)

---

## 📐 Standards de code (PSR-12 strict)
- `declare(strict_types=1)` en tête de chaque fichier PHP
- Type hints sur tous les paramètres et retours
- Docblocks sur toutes les méthodes publiques et protected
- **Logique métier dans les Services, JAMAIS dans les Controllers**
- Validation CI4 Rules dans les Models, pas dans les Controllers
- Commentaires en français, code en anglais
- Nommage : `camelCase` méthodes, `snake_case` tables/colonnes

---

## 🗃️ Format de réponse API (standard obligatoire)
```php
// Succès
['status' => 'success', 'data' => $data, 'message' => 'OK']

// Erreur validation
['status' => 'error', 'errors' => $errors, 'message' => 'Validation failed']  // HTTP 422

// Liste paginée
['status' => 'success', 'data' => $items,
 'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 150, 'last_page' => 8]]
```

---

## 🌍 Timezone & i18n

### Timezone
- Stockage DB : **toujours UTC** (`DATETIME` sans timezone, MySQL UTC)
- Hiérarchie effective : `tontine.timezone` → `association.timezone` → `"Africa/Douala"` (défaut plateforme)
- `session_deadline_time` (TIME) interprétée dans le timezone effectif de la tontine
- Les réponses API incluent `timezone` (IANA) + `deadline_utc` (ISO 8601 UTC calculé)
- **Aucun timezone personnel utilisateur** — les décomptes sont côté client (Vue 3 / Flutter)

### Internationalisation (i18n)
- Bilingue **FR / EN** — langues officielles du Cameroun
- `users.language ENUM('fr','en') DEFAULT 'fr'` — préférence par utilisateur
- Claim JWT `lang` — langue transportée dans le token pour les notifications
- SMS et notifications envoyés dans `user.language`
- Membres diaspora supportés : numéro E.164 international accepté, Africa's Talking gère l'envoi

---

## 👥 Rôles (par association)
| Rôle | Niveau | tontine_group |
|------|--------|:---:|
| `super_admin` | Plateforme entière | — |
| `president` | Association (hérite `treasurer` pour tontines) | ✅ |
| `treasurer` | Finances + validation paiements | ✅ |
| `secretary` | Membres + documents | ❌ |
| `auditor` | Lecture seule finances | ❌ |
| `censor` | Lecture seule + surveillance | ❌ |
| `member` | Compte personnel + demandes | ✅ |

> Permissions effectives dérivées dynamiquement via `bureau_terms` + `bureau_substitutions` → `RoleFilter`

---

## 🛠️ Commandes utiles
```bash
# CI4
php spark migrate
php spark migrate:rollback
php spark migrate:status
php spark db:seed DemoSeeder
php spark routes
php spark schedule:run

# Tests
vendor/bin/phpunit --stop-on-failure
vendor/bin/phpunit tests/Feature/
vendor/bin/phpunit --filter NomDuTest
```

---

## 🚀 Commandes slash disponibles
- `/new-feature [nom]` — Crée une feature complète avec orchestration automatique des agents
- `/review` — Lance `code-reviewer` + `security-auditor` sur le code modifié
- `/deploy [staging|prod]` — Déploie sur VPS via `deploy-manager`
- `/audit-security [--module=Nom]` — Audit sécurité complet ou ciblé via `security-auditor`
- `/sprint-start [numéro]` — Prépare le plan du sprint depuis `ROADMAP.md`
- `/generate-module [Nom]` — Génère la structure complète d'un module HMVC
- `/generate-tests [Module]` — Génère les tests PHPUnit d'un module via skill `phpunit-ci4`
- `/document-api [Module]` — Génère la doc OpenAPI d'un module via `api-architect`

---

## 🗺️ Roadmap
| Sprint | Périmètre | Statut |
|--------|-----------|--------|
| Sprint 1 | Fondations, Auth, Associations, Members, Plans | 🟡 Prêt à démarrer |
| Sprint 2 | Tontines & Bureau | 🔲 En attente Sprint 1 |
| Sprint 3 | Emprunts | 🔲 En attente Sprint 1 |
| Sprint 4 | Solidarité & Documents | 🔲 En attente Sprint 3 |
| Sprint 5 | Notifications, Reports, Polish API | 🔲 En attente Sprint 4 |
| Sprint 6 | Frontend Web (Vue 3) | 🔲 En attente Sprint 5 |
| Sprint 7 | Mobile (Flutter) | 🔲 En attente Sprint 5 |
| Sprint 8 | Mise en production VPS + Docker + SSL | 🔲 En attente Sprint 5 |

> Détail : `ROADMAP.md` · Tâches : `docs/TODO.md` · Décisions : `docs/DONE.md`

---

## 📂 Fichiers de référence
| Fichier | Lire quand |
|---------|-----------|
| `ARCHITECTURE.md` | Avant toute modification structurelle |
| `ROADMAP.md` | Pour prioriser et planifier |
| `docs/DATABASE.md` | Avant toute migration ou requête complexe |
| `docs/API.md` | Avant tout ajout/modification d'endpoint |
| `docs/MODULES.md` | Pour comprendre le rôle de chaque module |
| `docs/BUSINESS_RULES.md` | Avant toute logique métier tontines/loans/solidarity |
| `docs/TODO.md` | Pour identifier la tâche prioritaire |
| `docs/DONE.md` | Pour éviter les doublons |
