# Commande : /deploy

Déploie Djangui sur le VPS de production.

> ⚠️ **Disponible uniquement à partir du Sprint 8.**
> Avant Sprint 8, utiliser les commandes locales Laragon.

## Usage
```
/deploy              ← déploiement standard sur production
/deploy --dry-run    ← simulation sans déploiement réel
/deploy --rollback   ← retour au commit précédent
/deploy --hotfix     ← déploiement accéléré pour correctif urgent
```

---

## Prérequis avant exécution
- [ ] `code-reviewer` a retourné **APPROVED**
- [ ] `security-auditor` a retourné **PASS** (si auth/sécurité impliqués)
- [ ] `git-flow-manager` a mergé sur `main`
- [ ] Tous les tests passent : `vendor/bin/phpunit`
- [ ] `docs/TODO.md` et `docs/DONE.md` à jour

---

## Procédure exécutée (Sprint 8+)

```bash
# 1. Tests locaux avant déploiement
vendor/bin/phpunit --stop-on-failure

# 2. Vérification état Git
git status
git log --oneline -3

# 3. Pull VPS
ssh -o ConnectTimeout=40 {user}@{ip} \
  "cd /home/{user}/djangui && git pull origin main"

# 4. Migrations en attente
ssh -o ConnectTimeout=40 {user}@{ip} \
  "docker exec djangui_app php spark migrate"

# 5. Vider le cache CI4
ssh -o ConnectTimeout=40 {user}@{ip} \
  "docker exec djangui_app php spark cache:clear"

# 6. Smoke test
ssh -o ConnectTimeout=40 {user}@{ip} \
  "docker exec djangui_app php /tmp/smoke_test.php"

# 7. Vérification containers
ssh -o ConnectTimeout=40 {user}@{ip} \
  "docker ps --format 'table {{.Names}}\t{{.Status}}'"

# 8. Logs post-déploiement
ssh -o ConnectTimeout=40 {user}@{ip} \
  "docker logs djangui_app --tail 50"
```

---

## En local (Sprints 1–7, avant VPS)
```bash
# Équivalent "déploiement local" pour validation
vendor/bin/phpunit --stop-on-failure
php spark migrate
php spark migrate:status
php spark cache:clear
```

---

## Critères de succès
- Tests locaux : tous passent
- HTTP 200 sur POST `/api/auth/login` (smoke test)
- Tous les containers UP et healthy
- Aucune erreur dans les logs (dernières 50 lignes)

## Rollback automatique
Si le smoke test échoue → rollback immédiat vers le commit précédent + alerte.

---

## Post-déploiement
- Mettre à jour `docs/DONE.md` avec la feature et le commit hash
- Cocher la tâche dans `docs/TODO.md`
- Vérifier les logs 5 minutes après : pas d'erreurs 500
