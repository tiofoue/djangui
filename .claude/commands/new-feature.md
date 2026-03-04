# Commande : /new-feature

Crée une feature complète pour Djangui avec orchestration automatique des agents.

## Usage
```
/new-feature [NomModule] [description courte]
```

## Exemples
```
/new-feature Auth "JWT login/logout/refresh/switch-association"
/new-feature Tontines "Cycles, sessions, cotisations, 4 modes rotation"
/new-feature Loans "Emprunts avec calcul intérêts et échéancier"
```

---

## Workflow automatique exécuté

### Étape 1 — Lecture du contexte
```
→ Lire docs/TODO.md           (tâche prioritaire du sprint)
→ Lire docs/DONE.md           (éviter doublons)
→ Lire ARCHITECTURE.md        (structure HMVC à respecter)
→ Lire docs/DATABASE.md       (tables liées au module)
→ Lire docs/BUSINESS_RULES.md (règles métier du module)
→ Lire docs/API.md            (endpoints existants)
```

### Étape 2 — Design API
```
→ Appeler api-architect
  · Définir les routes RESTful du module
  · Définir les Filters requis (AuthFilter, RoleFilter, QuotaFilter)
  · Définir les DTOs request/response
  · Spécifier les restrictions par type d'entité
  → Attendre validation du design avant de coder
```

### Étape 3 — Implémentation
```
→ Appeler php-pro + skill:migration-generator + skill:business-rules
  · Créer la structure du module :
    app/Modules/{Nom}/
    ├── Controllers/{Nom}Controller.php
    ├── Models/{Nom}Model.php
    ├── Services/{Nom}Service.php
    ├── Entities/{Nom}Entity.php
    └── Config/Routes.php
  · Créer la/les migration(s) (skill:migration-generator)
  · Implémenter la logique métier (skill:business-rules si tontines/loans/solidarity)
  · Lancer php spark migrate
```

### Étape 4 — Review qualité
```
→ Appeler code-reviewer
  · Si APPROVED → continuer
  · Si NEEDS FIXES ou BLOCKED → retourner à php-pro avec corrections précises
    puis relancer code-reviewer
```

### Étape 5 — Audit sécurité (si applicable)
```
→ Appeler security-auditor SI le module touche :
  Auth / Filters / JWT / Redis / uploads / OTP SMS / données financières
  · Si PASS → continuer
  · Si FAIL → retourner à php-pro avec corrections puis relancer security-auditor
```

### Étape 6 — Tests
```
→ Utiliser skill:phpunit-ci4 pour générer les tests
  · Tests feature (endpoints) + tests unit (Services complexes)
  · Couvrir : succès, validation échouée, isolation multi-tenant, rôles, quotas SaaS
→ Lancer vendor/bin/phpunit --stop-on-failure
  · Si échec → retourner à php-pro avec l'erreur exacte
```

### Étape 7 — Documentation API
```
→ Appeler api-architect pour mettre à jour docs/API.md
  · Documenter tous les nouveaux endpoints en OpenAPI 3.0
  · Exemples payload avec données camerounaises réalistes
```

### Étape 8 — Git
```
→ Appeler git-flow-manager
  · Vérifications pre-commit (secrets, debug statements, .env)
  · Commit format : [FEAT] {Module}: {description}
  · Push sur feature/sprint{N}-{module}
→ Mettre à jour docs/TODO.md (cocher) et docs/DONE.md
```

---

## Sortie attendue
```
✅ /new-feature {NomModule} terminée

Fichiers créés :
  ✚ app/Modules/{Nom}/Controllers/{Nom}Controller.php
  ✚ app/Modules/{Nom}/Models/{Nom}Model.php
  ✚ app/Modules/{Nom}/Services/{Nom}Service.php
  ✚ app/Modules/{Nom}/Entities/{Nom}Entity.php
  ✚ app/Modules/{Nom}/Config/Routes.php
  ✚ app/Database/Migrations/{timestamp}_create_{table}_table.php
  ✚ tests/Feature/Modules/{Nom}/{Nom}Test.php

Agents invoqués  : api-architect → php-pro → code-reviewer → [security-auditor] → git-flow-manager
Skills activées  : migration-generator · business-rules · phpunit-ci4
Verdict review   : APPROVED ✅
Verdict sécurité : PASS ✅ (ou N/A)
Tests            : {N}/{N} passent
Commit           : [FEAT] {Module}: {description}
```
