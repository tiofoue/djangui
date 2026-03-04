# Commande : /review

Lance une revue de code complète sur les fichiers modifiés ou un module spécifique.

## Usage
```
/review                              ← revue des fichiers modifiés (git diff)
/review app/Modules/Tontines/        ← revue d'un module spécifique
/review app/Filters/                 ← revue d'un dossier
/review --security                   ← review + audit sécurité forcé
```

---

## Ce que cette commande fait

### Étape 1 — Identification des fichiers
```bash
git diff --name-only          # fichiers modifiés non stagés
git diff --staged --name-only # fichiers stagés
```

### Étape 2 — Appel code-reviewer
Lance `code-reviewer` sur les fichiers identifiés avec la grille complète :
- Architecture HMVC (logique métier dans Services ?)
- Multi-tenant (association_id extrait du JWT ?)
- Format de réponse API standard respecté ?
- Restrictions par type d'entité vérifiées ?
- Docblocks complets ?
- Tests présents ?

### Étape 3 — Outils qualité
```bash
# Syntaxe PHP
php -l {fichier_modifié}

# Tests
vendor/bin/phpunit --stop-on-failure

# Si phpcs installé
vendor/bin/phpcs --standard=phpcs.xml {fichier}
```

### Étape 4 — Audit sécurité (automatique si applicable)
`security-auditor` est **automatiquement invoqué** si les fichiers modifiés contiennent :
- `Auth`, `Filter`, `Jwt`, `Otp`, `Sms` dans le nom
- Upload/document logic
- Données financières (Loans, Tontines contributions)

Si `--security` est passé → `security-auditor` invoqué dans tous les cas.

---

## Format du rapport

```
## /review — Rapport [date]
Fichiers analysés : {liste}

### code-reviewer
Score   : X/10
Verdict : APPROVED ✅ | NEEDS FIXES ⚠️ | BLOCKED 🚨

CRITICAL 🚨 (bloquant) :
  - fichier:ligne → description + correction

HIGH ⚠️ (important) :
  - fichier:ligne → description

MEDIUM 📋 :
  - description

LOW 💡 :
  - suggestion

### security-auditor (si invoqué)
Verdict : PASS ✅ | FAIL 🔴
  [Vulnérabilités trouvées le cas échéant]

### Tests
vendor/bin/phpunit : {N}/{N} passent ✅ | {N} échecs 🔴

### Décision finale
→ PRÊT POUR COMMIT ✅     (code-reviewer APPROVED + security PASS + tests OK)
→ CORRECTIONS REQUISES ⚠️  (liste des points à corriger par php-pro)
→ BLOQUÉ 🚨               (renvoi obligatoire à php-pro)
```

---

## Checklist automatique
- [ ] PSR-12 et strict_types=1
- [ ] Zéro logique métier dans les Controllers
- [ ] association_id extrait du JWT (pas du body)
- [ ] BaseModel scope appliqué
- [ ] Format réponse API standard
- [ ] Codes HTTP corrects
- [ ] Pagination sur les listes
- [ ] Docblocks complets
- [ ] Tests couvrant isolation multi-tenant
- [ ] Pas de var_dump ni dd() oubliés
- [ ] Pas de secrets hardcodés
- [ ] docs/API.md à jour si nouvelle route
