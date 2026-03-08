# Règles métier — Djangui

## Internationalisation & Diaspora

### Langues officielles

Le Cameroun ayant deux langues officielles (français et anglais), la plateforme est **bilingue FR/EN** :

- Chaque utilisateur dispose d'une préférence de langue (`language` : `fr` | `en`, défaut `fr`)
- Les SMS (OTP, notifications) sont envoyés dans la langue de l'utilisateur
- Les rapports PDF sont générés dans la langue de l'association (`association_settings.language`)
- La langue est modifiable par l'utilisateur via `PUT /auth/me` (champ `language`)

### Diaspora — Membres hors Cameroun

Un utilisateur résidant à l'étranger peut s'inscrire et être membre d'une association ou tontine camerounaise :

- **Téléphone** : tout numéro E.164 international est accepté (`+33`, `+44`, `+1`, etc.)
- **SMS** : Africa's Talking supporte les envois internationaux — aucune restriction côté backend
- **Cotisations** : même règles que les membres locaux ; le paiement mobile money est géré hors plateforme (Phase 1)

### Timezone — Convention immuable

L'heure officielle de toutes les activités (séances, délais) est celle de la tontine ou de l'association :

```
Hiérarchie effective :
  tontine.timezone  →  association.timezone  →  "Africa/Douala" (défaut plateforme)
```

**Le backend ne stocke ni n'utilise le timezone personnel du membre.**

Les datetimes sont **toujours stockés en UTC** et **toujours retournés en UTC ISO 8601** dans les réponses API. Le champ `timezone` (IANA) de la tontine/association est systématiquement inclus pour permettre au client de convertir :

```json
{
  "session_date": "2026-03-20",
  "deadline_time": "18:00:00",
  "timezone": "Africa/Douala",
  "deadline_utc": "2026-03-20T17:00:00Z"
}
```

**Les décomptes (countdowns) sont calculés côté client** (Vue 3 / Flutter) à partir de `deadline_utc`. Le membre en Australie voit automatiquement l'heure dans son fuseau local et le temps restant.

Les SMS de notification incluent l'heure officielle avec l'abréviation timezone :
> "Séance le 20/03 à 18h00 WAT (Douala). Consultez l'application pour l'heure locale."

---

## Associations

- Un utilisateur peut créer autant d'entités qu'il veut
- Un utilisateur peut être membre de plusieurs associations avec des rôles différents
- Le créateur devient automatiquement `president` :
  - **tontine_group** : immédiatement à la création (status = active dès création)
  - **association / federation** : après approbation super_admin (status: pending_review → active)
- Il ne peut y avoir qu'un seul `president` par association
- Une association ne peut pas être supprimée si elle a des tontines actives ou des emprunts en cours
- Le slug est unique globalement et auto-généré à partir du nom

### Types d'entités

| Type | Description | Exemple |
|------|-------------|---------|
| `tontine_group` | Groupe informel de tontine | Amis, collègues, commerçants |
| `association` | Association structurée | Association de quartier, club |
| `federation` | Association-mère regroupant des sous-associations | Ressortissants d'une région subdivisés en secteurs |

### Fonctionnalités disponibles par type

| Feature | tontine_group | association | federation |
|---------|:---:|:---:|:---:|
| Tontines | ✅ | ✅ | ✅ |
| Caisse commune informelle | ✅* | ❌ | ❌ |
| Séances formelles | ❌ | ✅ | ✅ |
| Bureau & élections | ❌ | ✅ | ✅ |
| Emprunts | ❌ | ✅ | ✅ |
| Épargnes | ❌ | ✅ | ✅ |
| Cycle d'activité | ❌ | ✅ | ✅ |
| Caisse de solidarité | ❌ | ✅ | ✅ |
| Main levée | ❌ | ✅ | ✅ |
| Documents | ❌ | ✅ | ✅ |
| Sous-associations | ❌ | ❌ | ✅ |
| Validation statuts | ❌ | ✅ | ✅ |

*Caisse commune : uniquement si la tontine a `is_presentielle = true`

### Workflow de création / validation par type

**tontine_group** :
- Auto-approuvé à la création, aucun statut requis, pas de bureau formel
- Le créateur reçoit `effective_role = president` immédiatement (le frontend affiche "Animateur")
- Rôles disponibles : `president`, `treasurer`, `member` **uniquement** — `secretary`, `auditor`, `censor` ne s'appliquent pas
- Le `president` hérite implicitement des permissions `treasurer` pour toutes les opérations tontine
- Le `president` peut déléguer `treasurer` via `PUT /members/{userId}/role` sans élection
- Invitations limitées aux rôles `treasurer` et `member`

**association** :
- Création avec `status = pending_review`
- Statuts obligatoires (rédigés ou uploadés)
- Validation par le super admin ou son équipe
- Le créateur devient `president` après approbation

**federation** :
- Création avec `status = pending_review`
- Validation plus stricte (statuts + règlement des sous-associations)
- Les sous-associations sont rattachées via `parent_id`
- Chaque sous-association suit son propre workflow de validation

### Statuts possibles (association & federation)
```
pending_review → active    (approuvé par super admin)
pending_review → rejected  (rejeté avec motif)
active         → suspended (violation conditions)
suspended      → active    (réhabilitation)
```

### Règles fédération
- Une fédération peut avoir un nombre illimité de sous-associations
- Chaque sous-association conserve son autonomie (bureau, règles, finances propres)
- La fédération a son propre bureau distinct de ceux des sous-associations
- Les membres d'une sous-association ne sont pas automatiquement membres de la fédération
- La fédération peut avoir une visibilité sur les activités des sous-associations (configurable)
- Une sous-association ne peut appartenir qu'à une seule fédération à la fois
- Une sous-association peut exister de façon indépendante (sans fédération)

---

## Cycle d'activité

> **Réservé aux entités de type `association` et `federation`.**
> Les `tontine_group` n'ont pas accès à ce module.
>
> **Distinct du cycle de tontine** : le cycle d'activité de l'association (`association_cycles`) est l'exercice financier qui encadre les épargnes et les prêts. Le cycle de tontine (`tontines.current_cycle`) est propre à chaque tontine et gère la rotation des bénéficiaires. Ces deux notions sont indépendantes.

- Chaque association définit un **exercice** (période d'activité financière, ex. "Exercice 2024-2025" : décembre 2024 → novembre 2025)
- Un seul exercice peut être `active` à la fois par association
- Les emprunts, les épargnes et la distribution des intérêts sont tous rattachés à l'exercice actif
- La `end_date` de l'exercice fixe la **date limite absolue** de remboursement de tous les prêts

### Paramétrage de l'exercice

Deux clés dans `association_settings` pilotent le calcul automatique des dates :

- `cycle_start_month` (1–12, défaut : 1) — mois de démarrage de l'exercice (ex : 12 = décembre)
- `cycle_duration_months` (défaut : 12) — durée en mois de chaque exercice

À la création d'un exercice, le président fournit uniquement l'**année de départ**. `CycleService` calcule :
```
start_date = 1er jour du cycle_start_month de l'année fournie
end_date   = start_date + cycle_duration_months − 1 jour
label      = "Exercice YYYY-YYYY"  (année de start_date − année de end_date)
```
Exemple : `cycle_start_month = 12`, `cycle_duration_months = 12`, année fournie = 2024
→ `start_date = 2024-12-01`, `end_date = 2025-11-30`, `label = "Exercice 2024-2025"`

**Reconduction tacite** : si le président ne modifie pas `cycle_duration_months` entre deux exercices, la même durée s'applique automatiquement au prochain exercice. Les paramètres `cycle_start_month` et `cycle_duration_months` ne sont modifiables que **hors exercice actif** (entre la clôture du précédent et le démarrage du suivant).

### Cycle de vie d'un exercice
```
draft → active   (démarré manuellement par le président)
active → closing (initiation clôture : tous les prêts doivent être soldés)
closing → closed (clôture effective : distribution intérêts + retrait épargnes)
```

### Clôture de cycle — procédure en deux étapes

**Étape 1 — `active → closing` (`initiate-closing`) :**
- Bloqué si au moins un prêt est en statut `active` ou `approved`
- Les prêts `defaulted` sont **tolérés** à cette étape (ils doivent être résolus avant la clôture finale)
- Objectif : signaler la fin imminente de l'exercice, laisser le temps de résoudre les défauts

**Étape 2 — `closing → closed` (`close`) :**
1. Vérifier qu'aucun prêt n'est en statut `active`, `approved` ou `defaulted` → sinon blocage total
2. Calculer le pro-rata des intérêts par membre (via snapshots)
3. Créditer `savings_accounts.interest_earned` pour chaque membre
4. Enregistrer les transactions `interest_payout` puis `withdrawal` (capital + intérêts)
5. Passer les comptes épargne en `closed`
6. Passer le cycle en `closed`
7. Nouveau cycle : le président crée un nouveau cycle `draft` → `active`, les membres déposent leurs nouvelles épargnes

> **Remarque :** Les nouveaux dépôts d'épargne du cycle suivant se font **le même jour** que les retraits du cycle clôturé — il n'y a pas de rupture de continuité pour les membres.

---

## Séances & Assemblées

### Séances — réunions financières récurrentes

> **Réservé aux entités de type `association` et `federation`.** Un `tontine_group` ne bénéficie **pas** du module Séances. Ses réunions sont informelles et ne passent pas par ce module (voir section "Tontine Group" ci-dessous).

Les séances sont les réunions périodiques au cours desquelles se déroulent les opérations financières : cotisations tontine, versements épargne, présence, remboursements de prêts, demandes de prêt, etc.

#### Règle de récurrence

Chaque association configure **une seule règle de récurrence** dans ses settings (`seance_recurrence_type`) :

| Type | Description | Exemple |
|------|-------------|---------|
| `nth_weekday` | Nème jour de la semaine du mois | 2ème samedi, dernier dimanche |
| `fixed_day` | Jour fixe du mois | Le 15 de chaque mois |

Paramètres associés dans les settings :
- `nth_weekday` → `seance_week_ordinal` (1|2|3|4|-1) + `seance_weekday` (1=lundi … 7=dimanche)
- `fixed_day` → `seance_day_of_month` (1–31)

#### Génération automatique

À l'**activation d'un exercice**, `CycleService` génère automatiquement **toutes les séances du cycle** selon la règle de récurrence. Chaque séance générée a :
- `scheduled_date` = date planifiée calculée (immuable, conservée pour l'historique)
- `actual_date` = date effective (= `scheduled_date` tant que non reportée)
- `status = scheduled`

Si une séance générée tombe sur un **jour férié** (table `public_holidays` filtrée par le `country_code` de l'association), son statut est automatiquement mis à `needs_reschedule`. Le président ou trésorier décide alors : annuler ou reporter.

#### Statuts d'une séance
```
scheduled        → held       (séance tenue)
scheduled        → cancelled  (annulée définitivement)
scheduled        → postponed  (reportée : actual_date mise à jour, postponed_reason renseigné)
needs_reschedule → held | cancelled | postponed
```

#### Clôture d'une séance

- **Clôture manuelle** : le trésorier ou président déclare la séance `held` — voie normale.
- **Clôture automatique** : un job planifié (`CloseOverdueSeances`) clôture automatiquement toute séance non encore `held` à **23h59 de son `actual_date`** — filet de sécurité si oubli du trésorier.
- Dans les deux cas, dès la clôture, les opérations financières suivantes sont rattachées à la séance suivante.

**Édition post-clôture du compte-rendu :**
Les champs administratifs (`start_time`, `end_time`, `notes`, `report_text`, `report_file`) restent **éditables après clôture** par le secrétaire général et les membres habilités, **jusqu'à la clôture de la séance suivante**. Une fois la séance suivante clôturée, le compte-rendu est **figé définitivement**.

#### Report & annulation

- **Reportée** (`postponed`) : `scheduled_date` reste inchangé (historique), `actual_date` = nouvelle date, `postponed_reason` obligatoire. Les opérations financières suivent la séance ; la clôture automatique s'applique sur la nouvelle `actual_date`.
- **Annulée** (`cancelled`) : les opérations financières déjà rattachées sont **automatiquement réassignées à la séance suivante**.

#### Contenu d'une séance
- Ordre du jour (voir section dédiée ci-dessous)
- Liste et nombre de participants (membres présents)
- Heure de début et de fin (saisies après la tenue)
- Notes / commentaires globaux
- Rapport (texte saisi et/ou fichier uploadé)

---

### Assemblées — réunions ad hoc

Les assemblées sont convoquées de façon **spontanée** pour un sujet précis (modification des textes, calcul et redistribution des intérêts/épargnes, élection, etc.).

- La date est fixée par accord entre les membres, sans règle de récurrence
- Une assemblée peut être reportée à une nouvelle date (même mécanique que les séances : `scheduled_date` immuable, `actual_date` = date effective)
- Une assemblée et une séance **peuvent se tenir le même jour** — les deux sont des entités indépendantes
- Le sujet (`subject`) est obligatoire à la création

#### Contenu d'une assemblée
- Ordre du jour (voir section dédiée ci-dessous)
- Liste et nombre de participants
- Heure de début et de fin
- Notes / commentaires globaux
- Rapport (texte saisi et/ou fichier uploadé)

---

### Ordre du jour

Chaque séance et chaque assemblée dispose d'un **ordre du jour** composé de points ordonnés. C'est l'ordre du jour qui rythme la réunion ; chaque point commenté alimente directement la rédaction du rapport.

#### Points système — séance

Générés automatiquement à la création de chaque séance, dans l'ordre par défaut :

| # | Point | Supprimable |
|---|-------|-------------|
| 1 | Prière d'ouverture | ✅ |
| 2 | Appel / émargement | ✅ |
| 3 | Lecture et adoption du rapport de la séance précédente | ✅ |
| 4 | Opérations financières | ❌ |
| 5 | Points divers | ✅ |
| 6 | Nouvelles de la communauté | ✅ |
| 7 | Astuces (santé, bien-être...) | ✅ |
| 8 | Rafraîchissement | ✅ |
| 9 | Prière de clôture | ✅ |

#### Points système — assemblée

Générés automatiquement à la création de chaque assemblée :

| # | Point | Supprimable |
|---|-------|-------------|
| 1 | Prière d'ouverture | ✅ |
| 2 | Appel / émargement | ✅ |
| 3 | [Sujet de l'assemblée] | ❌ |
| 4 | Points divers | ✅ |
| 5 | Prière de clôture | ✅ |

#### Règles

- L'ordre des points est **modifiable** par le secrétaire général et les habilités
- Des points **personnalisés** peuvent être insérés n'importe où entre les points système
- Les points système supprimables peuvent être retirés d'une séance/assemblée spécifique
- **"Opérations financières"** (séance) et **"[Sujet de l'assemblée]"** (assemblée) sont les seuls points non-supprimables
- Chaque point a un **statut** (`pending` / `done` / `skipped`) mis à jour en temps réel pendant la réunion
- Chaque point dispose d'un **champ commentaire** (`comment`) saisi par le secrétaire — l'ensemble des commentaires constitue le brouillon du rapport

#### Suggestion automatique

`AgendaService::suggest(association_id, type)` analyse les ordres du jour des séances/assemblées précédentes et propose les titres les plus fréquents dans leur ordre habituel. La suggestion est une base modifiable, pas un modèle figé.

### Rattachement des opérations financières aux séances

> **Règle fondamentale :** toute opération financière est rattachée à la **séance courante** de l'association, c'est-à-dire la séance dont le statut n'est pas encore `held` ou `cancelled`.

- Tant qu'une séance n'est pas déclarée close (`held`), **toutes** les opérations financières lui sont rattachées — qu'elles soient effectuées le jour de la séance ou entre deux séances.
- Dès qu'elle est déclarée close, la séance suivante (selon `actual_date` ASC) devient la séance courante et reçoit toutes les opérations ultérieures.

Cette règle couvre : versements épargne, cotisations tontine en avance, remboursements de prêt, cotisations de solidarité.

`SeanceService::getCurrent(association_id)` retourne la séance dont `status NOT IN ('held', 'cancelled')`, ordonnée par `actual_date ASC LIMIT 1`.

**Cas particuliers :**
- Si la séance courante est **reportée** (`postponed`) → elle reste la séance courante avec sa nouvelle `actual_date` ; les opérations continuent de lui être rattachées.
- Si la séance courante est **annulée** (`cancelled`) → les opérations déjà rattachées sont réassignées automatiquement à la séance suivante.

---

## Épargnes

> **Réservé aux entités de type `association` et `federation`.**
> Activé par `association_settings.savings_enabled = true`.

- Chaque membre actif peut ouvrir un **compte épargne** rattaché au cycle en cours
- Les dépôts s'effectuent lors des **mêmes séances** que la tontine (si tontine existante) ou lors d'assemblées de l'association
- L'épargne poolée de tous les membres constitue le **capital de prêt** de l'association
- Des **apports externes** (dons, subventions, apport fédération) peuvent compléter ce capital (`savings_pool_entries`)

### Capital de prêt disponible
```
Capital disponible = Σ(savings_accounts.balance) + Σ(savings_pool_entries actifs) − Σ(loans actifs)
```
Le trésorier consulte ce solde avant d'approuver tout nouvel emprunt.

### Snapshot d'avoir par séance
Un snapshot est enregistré pour chaque compte épargne actif **au moment de la clôture de chaque séance** (transition `status → held`, qu'elle soit manuelle ou automatique à 23h59) :
- `balance` = **solde cumulatif** du membre à cette date (Σ tous les dépôts depuis début de cycle + solde initial report du cycle précédent)
- `loans_active` = `1` si au moins un prêt est actif dans l'association à cette date, `0` sinon

> Le déclenchement au moment de la clôture garantit que toutes les opérations de la séance (y compris celles effectuées entre deux séances et rattachées à celle-ci) sont intégrées dans le snapshot avant sa prise.

> Le solde cumulatif (et non le dépôt mensuel seul) est la base du pro-rata : un membre qui épargne tôt contribue davantage sur le long terme.

### Formule de distribution des intérêts (pro-rata)
```
score_membre = Σ(balance_membre sur toutes séances où loans_active = 1)
score_total  = Σ(balance_tous_membres sur les mêmes séances)

part_intérêts_membre = (score_membre / score_total) × total_intérêts_collectés_du_cycle × loan_interest_distribution
```
- `loan_interest_distribution` = pourcentage des intérêts reversés aux épargnants (défaut : 1.0 = 100%), configuré dans `association_settings`

**Protection anti-gaming :**
Seules les séances avec au moins un prêt actif entrent dans le calcul.
Un membre déposant une somme importante **après** la clôture de tous les prêts ne perçoit **aucun intérêt** (mais récupère son capital intégralement).

### Exemples illustratifs

**Cas 1 — Prêt court (3 mois sur cycle de 12)**
| Séance | Avoir A | Avoir B | Prêts actifs | Comptabilisé |
|--------|---------|---------|--------------|--------------|
| M1 | 20 000 | 0 | ✅ | ✅ |
| M2 | 40 000 | 0 | ✅ | ✅ |
| M3 | 60 000 | 0 | ✅ | ✅ |
| M4→M12 | 60 000 | 90 000 | ❌ | ❌ |

Score A = 120 000 | Score B = 0 → **A reçoit 100 % des intérêts**

**Cas 2 — Prêt long (12 mois, tout le cycle)**
| Séance | Solde cumulatif A | Solde cumulatif B | Prêts actifs | Comptabilisé |
|--------|-------------------|-------------------|--------------|--------------|
| M1 | 20 000 | 0 | ✅ | ✅ |
| M2 | 60 000 | 0 | ✅ | ✅ |
| M3 | 120 000 | 0 | ✅ | ✅ |
| M4→M12 | 180 000…660 000 | 90 000…810 000 | ✅ | ✅ |

Score A = 660 000 | Score B = 810 000 | Total = 1 470 000
→ **A : 44,9 %** (récompensé pour épargne précoce) | **B : 55,1 %** (contribution réelle depuis M4)

### Présence
- Cotisation versée à **chaque séance** d'assemblée par chaque membre actif (le montant est défini par l'association)
- **Distinct de l'épargne variable** : tracé séparément via `savings_transactions.type = 'presence'`
- **Non inclus dans le calcul du pro-rata des intérêts** — réservé aux frais de fonctionnement de l'association
- Montant configuré via `association_settings.presence_amount`
- Enregistré par le trésorier lors de chaque séance d'assemblée

---

## Membres & Bureau

- Un membre peut appartenir simultanément à **plusieurs entités** (tontine_group, association, federation), sans limite
- Dans chaque entité, un membre ne peut occuper qu'**un seul poste au bureau à la fois**
- Un membre retiré conserve son historique (soft-delete)
- L'invitation expire après 7 jours si non acceptée
- Tout membre actif d'une entité peut être élu ou nommé à un poste du bureau de cette entité

### Bureau de l'association et de la fédération

Le bureau est l'organe dirigeant de l'entité (association **ou fédération**). Les règles sont **identiques** pour les deux types :
- La fédération a son propre bureau, distinct des bureaux de ses sous-associations
- Les membres du bureau de la fédération ne sont pas automatiquement membres du bureau des sous-associations
- Toutes les fonctionnalités bureau (postes, mandats, suppléances, élections) s'appliquent de la même façon

Il est constitué de postes définis par l'entité elle-même.

#### Postes principaux (obligatoires)
- Président
- Secrétaire Général
- Trésorier
- Commissaire aux Comptes
- Censeur

#### Postes suppléants (facultatifs, créés selon les besoins)
Organisés par **catégorie** et **rang** :
- Présidence : 1er Vice-Président, 2ème Vice-Président, ...
- Secrétariat : 1er Secrétaire Général Adjoint, 2ème SGA, ...
- Trésorerie : 1er Vice-Trésorier, 2ème Vice-Trésorier, ...
- Commissariat : 1er Vice-Commissaire aux Comptes, ...
- Censure : 1er Vice-Censeur, 2ème Vice-Censeur, ...
- Postes libres : Membre d'honneur, Chargé de communication, ...

#### Règles du bureau
- Chaque poste a une **durée de mandat** configurable (ex: 2 ans)
- Un membre ne peut occuper qu'**un seul poste au bureau** à la fois (principal ou suppléant, sans cumul)
- Les postes sont pourvus par **élection** ou **promotion** selon la configuration
- L'historique des bureaux successifs est conservé indéfiniment
- Un bureau est **actif** tant que des membres y occupent des postes en cours

### Suppléance hiérarchique

- Lorsqu'un membre du bureau déclare une **indisponibilité**, son suppléant immédiat (rang suivant de la même catégorie) hérite temporairement de ses permissions
- La suppléance est **en cascade** : si le suppléant direct est aussi indisponible, le suivant prend le relais
- La suppléance est **temporaire** et traçée (date début, date fin, motif)
- À la fin de l'indisponibilité, les permissions reviennent automatiquement

```
Président indisponible → 1er Vice-Président supplée
1er VP aussi indisponible → 2ème Vice-Président supplée
... et ainsi de suite
```

### Hiérarchie des permissions (dérivée du poste)
```
super_admin > président > trésorier = secrétaire général > commissaire aux comptes > censeur > membre
```
- Les postes suppléants héritent des permissions du poste principal de leur catégorie.
- Dans un `tontine_group` : `president` englobe les permissions `treasurer` pour les opérations tontine.

### Elections
- Une élection est organisée par le président ou son suppléant actif
- Chaque élection cible un ou plusieurs postes du bureau
- Les candidats doivent être membres actifs de l'association
- Chaque membre actif dispose d'une voix par poste à pourvoir
- Les résultats sont publiés par le président et génèrent automatiquement les nouveaux `bureau_terms`
- Un poste non pourvu reste vacant jusqu'à élection ou nomination complémentaire

---

## Tontines

### Tontines & Séances

> Cette section concerne uniquement les tontines des entités `association` et `federation`. Les tontines d'un `tontine_group` ont leur propre fonctionnement (voir section "Tontine Group").

Une session tontine dans une `association` ou `federation` se tient **toujours dans le cadre d'une séance** de l'association (`tontine_sessions.seance_id` obligatoire pour ces types). Le `session_date` correspond à l'`actual_date` de la séance associée. Une séance peut contenir les sessions de **plusieurs tontines** simultanément — une association peut avoir plusieurs tontines actives en parallèle.

Pour les tontines d'un `tontine_group`, `tontine_sessions.seance_id` est **NULL** (il n'y a pas de séance formelle).

### Création
- Une association peut avoir plusieurs tontines simultanément
- Chaque tontine a sa propre fréquence, son montant et son mode de rotation
- Le nombre minimum de membres est 2
- `max_members` est facultatif (illimité si NULL)

### Cycle de vie d'une session
```
pending → open (ou auction) → closed
```
- `pending → open` : automatique via job planifié au matin de `session_date`
- `pending → auction` : idem si `tontine.rotation_mode == session_auction`
- `pending → open` (manuel) : `PUT /sessions/{sId}/open` par le treasurer (secours)
- `open/auction → closed` : manuel par le treasurer via `PUT /sessions/{sId}/close`

### Démarrage
- Une tontine ne peut démarrer que si elle a au moins 2 membres inscrits
- Au démarrage, les sessions sont générées automatiquement sur toute la durée
- Avec rotation `random` : l'ordre est tiré au sort automatiquement
- Avec rotation `manual` : l'admin doit définir l'ordre avant de démarrer
- Avec rotation `bidding` : la période d'enchère doit être clôturée avant le démarrage

### Modes d'enchères — comparatif

| | `bidding` | `session_auction` |
|---|---|---|
| **Moment** | Avant démarrage (pré-tontine) | À chaque séance |
| **Objectif** | Déterminer l'ordre de rotation | Déterminer qui reçoit la cagnotte |
| **Résultat** | Ordre fixé pour tout le cycle | Gagnant reçoit cagnotte − montant adjugé |
| **Caisse** | Montant ajouté à la cagnotte du gagnant | Montant adjugé → caisse commune redistribuée fin de cycle |
| **Endpoint** | `PUT /members/me/bid` | `POST/GET /sessions/{sId}/bids` + `PUT /sessions/{sId}/adjudicate` |

### Enchères (mode `bidding`)
- Le `bid_amount` peut être saisi à l'inscription par le treasurer, ou soumis/modifié par le membre via `PUT /members/me/bid`
- **Obligatoire avant démarrage** : la tontine ne peut pas démarrer tant que tous les membres n'ont pas soumis un `bid_amount > 0`
- L'ordre est déterminé par enchère décroissante (celui qui paie le plus passe en 1er)
- En cas d'égalité : tirage au sort entre les ex-aequo
- Le bid_amount est ajouté à la cagnotte de la session où le membre est bénéficiaire

### Cotisations
- Un membre doit avoir payé sa cotisation avant la **date limite** = `session_date` à `session_deadline_time`
- L'heure limite est définie sur la tontine (`session_deadline_time`, défaut `23:59`) et interprétée dans le **fuseau horaire de la tontine**
- Paiement partiel autorisé (status = `partial`)
- Un membre ne peut pas être désigné bénéficiaire s'il a des cotisations impayées (configurable)

### Pénalités de retard
Configurées via `late_penalty_type` + `late_penalty_value` dans les settings de l'association.

| `late_penalty_type` | Formule |
|---------------------|---------|
| `fixed` | `value` (montant fixe unique) |
| `fixed_per_day` | `value × jours_de_retard` |
| `fixed_per_week` | `value × ceil(jours_de_retard / 7)` |
| `fixed_per_month` | `value × ceil(jours_de_retard / 30)` |
| `percentage` | `amount_due × value` (une seule fois) |
| `percentage_per_day` | `amount_due × value × jours_de_retard` |
| `percentage_per_week` | `amount_due × value × ceil(jours_de_retard / 7)` |
| `percentage_per_month` | `amount_due × value × ceil(jours_de_retard / 30)` |

- `value` = montant en XAF pour les types `fixed*`, taux décimal pour les types `percentage*` (ex: `0.05` = 5%)
- `jours_de_retard` = nombre de jours entre `session_date` et `paid_at`
- `ceil()` : 1 jour de retard = 1 semaine (ou mois) entière facturée
- Mode par défaut : `percentage_per_month` avec `value = 0.05` (5%/mois)

### Parts multiples
- À l'inscription à une tontine, chaque membre définit son **nombre de parts** (minimum 1)
- À chaque session, le membre cotise `parts × montant_base`
- Un membre est bénéficiaire **autant de fois que son nombre de parts** dans le cycle
- Le nombre total de sessions du cycle = **SUM(parts)** de tous les membres inscrits
- La rotation attribue des **slots** : un membre avec 3 parts a 3 slots dans la rotation

### Éligibilité au bénéfice (règle par défaut)
- Le cycle est divisé en **X tranches égales** pour un membre à **X parts**
- Chaque slot K est éligible dans la **fenêtre** allant de la fin de la tranche K-1 à la fin de la tranche K
- `total_sessions` dans la formule désigne le **nombre de séances réel** du cycle :
  ```
  total_sessions = SUM(shares de tous les membres) / beneficiaries_per_session
  ```
- Formule d'éligibilité (utilise **ceil** = élévation au nombre supérieur) :
  ```
  slot_K_fin   = ceil(K / X × total_sessions)
  slot_K_debut = ceil((K-1) / X × total_sessions) + 1   [pour K > 1]
  slot_1_debut = 1
  ```
- Le `ceil()` gère naturellement pair/impair sans condition supplémentaire :
  - Division entière → résultat exact (aucune élévation)
  - Division fractionnaire → élévation au supérieur
- Exemples (beneficiaries_per_session = 1) :

  | Cas | slot 1 | slot 2 | slot 3 |
  |-----|--------|--------|--------|
  | 2 parts, N=20 (pair) | 1 – 10 | 11 – 20 | — |
  | 2 parts, N=21 (impair) | 1 – 11 | 12 – 21 | — |
  | 3 parts, N=21 | 1 – 7 | 8 – 14 | 15 – 21 |
  | 3 parts, N=20 | 1 – 7 | 8 – 14 | 15 – 20 |
  | 1 part,  N=21 | 1 – 21 | — | — |

- Exemple (beneficiaries_per_session = 2, SUM(shares) = 42) :
  - total_sessions = 42 / 2 = 21 séances réelles
  - La formule s'applique identiquement sur ces 21 séances
- Cette règle peut être **remplacée par une règle personnalisée** définie à la création de la tontine

### Bénéficiaire
- Un membre ne peut pas être bénéficiaire deux fois consécutives **sauf** si ses slots représentent la fin du cycle
- Dans ce cas, il peut recevoir toutes ses parts de façon **successive** sur les dernières séances
- Exemple : membre à 3 parts dans un cycle de 21 séances — si ses 3 slots tombent en séances 19, 20, 21, il bénéficie consécutivement
- Tous les slots de tous les membres doivent être épuisés avant clôture du cycle
- La cagnotte versée = somme des cotisations reçues pour cette session (parts × montant_base × nb_membres)

### Bénéficiaires multiples par séance
- Configurable à la création via `beneficiaries_per_session` (défaut : 1)
- À chaque séance, N membres reçoivent chacun une quote-part égale de la cagnotte
- La cagnotte est divisée équitablement entre les N bénéficiaires de la séance
- Nombre total de séances = SUM(shares) / beneficiaries_per_session
- Chaque slot de rotation est assigné à une position dans une séance (séance 1 slot A, séance 1 slot B, séance 2 slot A, etc.)

### Tontine aux enchères par séance (`session_auction`)
Ce mode est **distinct** du `bidding` (qui détermine l'ordre de rotation en amont).
Ici l'enchère a lieu **à chaque séance** :

- La cagnotte est mise aux enchères à chaque séance
- Mise à prix de départ = montant d'une part (montant_base)
- Tout membre **éligible** peut surenchérir (éligible = n'a pas encore reçu tous ses slots)
- Le plus-disant remporte la cagnotte **diminuée du montant adjugé**
- Le montant adjugé est versé dans une **caisse commune** de la tontine

**Redistribution en fin de cycle :**
```
Redistribution = cumul_caisse / SUM(shares) × shares_du_membre
```
Exemple : caisse = 210 000 XAF, 21 parts totales
- Membre à 2 parts → 2/21 × 210 000 = 20 000 XAF
- Membre à 1 part  → 1/21 × 210 000 = 10 000 XAF

**Règles enchères :**
- Un membre ayant reçu tous ses slots (`slots_received >= shares`) n'est plus éligible aux enchères
- En cas d'égalité de mise : tirage au sort entre les ex-aequo
- Le membre gagnant d'une enchère voit son slot correspondant marqué comme reçu (`slots_received++`)
- La caisse est gérée séparément du fond de solidarité

**Fenêtres d'éligibilité en session_auction :**
- Un membre est éligible à partir de sa **1ère séance** (pas de fenêtre retardée en mode auction)
- Pour un membre à **N parts** (shares = N) : il peut enchérir jusqu'à N fois dans le cycle
- Sa 2ème part ne devient éligible qu'après la séance `W` définie par la formule d'éligibilité :
  ```
  W = ceil(total_sessions / N)
  ```
  (identique au mode rotation standard — il ne peut pas remporter 2 enchères consécutives)
- En fin de cycle : `slots_received` de tous les membres est remis à 0 (reconduction ou nouveau cycle)

### Membre défaillant & Modérateur de tontine
- Chaque tontine peut désigner un **modérateur** (membre actif inscrit à la tontine) chargé de la discipline
- Le modérateur peut **rétrograder** un membre défaillant (n'ayant pas cotisé sa ou ses parts) dans l'ordre de rotation
- La rétrogradation décale le slot du défaillant vers une séance ultérieure
- L'action de rétrogradation est **loguée et justifiée** (motif obligatoire)
- Le trésorier ou le président peut aussi effectuer cette action en l'absence du modérateur

### Reconduction tacite
> **Note :** `tontines.current_cycle` est le compteur de cycles de la tontine elle-même (rotation des bénéficiaires). Il est distinct du cycle d'activité de l'association (`association_cycles`).

- Par défaut, une tontine est **reconduite automatiquement** à la fin de chaque cycle (`auto_renew = true`)
- Au début de chaque nouveau cycle :
  - `tontines.current_cycle` est incrémenté
  - `tontine_members.slots_received` est remis à 0 pour tous les membres actifs
  - De nouvelles sessions sont générées avec le nouveau `cycle_number`
  - L'ordre de rotation est **redéfini** selon le même mode que le cycle précédent
- Si `auto_renew = false` ou si `max_cycles` est atteint, la tontine passe en statut `completed`
- Les membres peuvent se désinscrire entre deux cycles (pendant la période de renouvellement)
- En mode `session_auction` : la `caisse_balance` est redistribuée en fin de cycle avant reconduction, puis remise à 0

### Clôture
- Une tontine peut être clôturée manuellement ou automatiquement à `end_date`
- Une tontine ne peut pas être clôturée si des cotisations sont impayées (configurable)
- En mode `session_auction` : la redistribution de la caisse est effectuée à la clôture

---

## Tontines — Association & Fédération — Spécificités

> Les règles de cette section s'appliquent **uniquement** aux tontines des entités de type `association` et `federation`. Elles complètent ou remplacent les règles générales de la section "Tontines" ci-dessus.

### Chevauchement de cycles d'activité

Une tontine d'association **n'est pas rattachée à un cycle d'activité** (`tontines` n'a pas de `cycle_id`). Elle peut démarrer dans un exercice et se terminer dans un autre — ses sessions sont liées à des séances, qui elles appartiennent à des cycles, mais la tontine s'étend librement.

### Modérateur

- Désigné parmi les **membres actifs inscrits à la tontine** uniquement
- Un membre ne peut pas modérer plus de **2 tontines simultanément lors d'une même séance**
- La désignation est **permanente pour la tontine** (pas par session) — modifiable à tout moment par le président ou trésorier
- En l'absence du modérateur lors d'une séance : le président ou trésorier prend le relais automatiquement (`TontineModeratorFilter`)
- `TontineService` valide à la désignation : le candidat ne modère pas déjà 2 autres tontines actives dans la même séance

### Pénalités — fallback depuis association_settings

Les règles de pénalité suivent une **chaîne de fallback** :

```
tontines.penalty_type / penalty_value (non NULL)  → override par tontine
  ↓ si NULL
association_settings.late_penalty_type / value     → règle globale de l'association
```

Permet à une association avec plusieurs tontines de définir des règles différentes par tontine. Sans configuration sur la tontine → la règle globale `association_settings` s'applique.

### Non-contribution — rétrogradation automatique

Règle plus stricte qu'en `tontine_group` : à la clôture de chaque session, tout membre n'ayant **pas payé** est **automatiquement rétrogradé** au dernier slot disponible du cycle en cours.

- La rétrogradation est loguée dans `tontine_slot_demotions` avec motif `auto_non_payment`
- Le président/trésorier peut annuler la rétrogradation manuellement si justifiée

### Remise des gains

Même logique que pour une tontine présentielle : la **clôture de session = remise implicite** au bénéficiaire (`received_at` = horodatage de clôture, `amount_received` = `total_collected`).

### Saisie des paiements en séance

Les cotisations tontine sont enregistrées sous le point **"Opérations financières"** de l'ordre du jour de la séance.

- Même checklist qu'en présentielle : une action par membre (`POST /sessions/{sId}/pay`) confirme le paiement
- Mode de paiement enregistré sur la cotisation (optionnel : cash, MoMo, virement)
- **Séance multi-tontines** : vue par tontine (une tontine à la fois) — chaque tontine ayant ses propres montants et membres, une vue consolidée serait trompeuse

### Reconduction de cycle

- **Bloquée si cotisations impayées** — règle stricte, non configurable (contrairement au `tontine_group`)
- Le trésorier doit solder tous les impayés avant de pouvoir reconduire
- Nouvelles sessions pré-liées aux **séances du cycle en cours** au moment de la reconduction (les séances sont déjà générées par `CycleService` à l'activation du cycle)
- Pénalités impayées **reportées** au cycle suivant
- Fenêtre `renewal_window_days` : modification de parts avec approbation trésorier/président (voir § Parts)

### Clôture

- Comportement configurable via `tontines.close_requires_full_payment` (défaut : `true`)
- **Si `true`** : clôture bloquée tant qu'il reste des cotisations impayées
- **Forcer la clôture** (`PUT /tontines/{tId}/force-close`) : réservé au président uniquement — les dettes sont conservées comme tracées dans `contributions`
- **Si `false`** : clôture possible avec impayés (dettes tracées, non bloquantes)
- Mode `session_auction` : redistribution de la `caisse_balance` effectuée à la clôture

**Cycle d'activité se clôturant avec tontine encore active :**
La tontine **continue indépendamment** (elle n'est pas rattachée au cycle). Une notification est envoyée au président/trésorier pour les informer que l'exercice se clôture mais que la tontine est toujours en cours.

### Parts — modification entre cycles avec approbation

Les parts (`shares`) sont modifiables entre deux cycles uniquement (pendant la fenêtre `renewal_window_days`), mais la modification requiert une **approbation explicite du trésorier ou président** — contrairement au `tontine_group` où la modification est libre.

Workflow :
```
Membre soumet demande de modification de parts
  → status = pending_approval
Trésorier/Président approuve ou refuse
  → status = approved → effectif au cycle suivant
  → status = rejected → parts inchangées
```

---

## Tontine Group — Spécificités

> Les règles de cette section s'appliquent **uniquement** aux entités de type `tontine_group`. Elles sont **distinctes** des tontines organisées au sein d'une `association` ou `federation`.

### Philosophie générale

Le `tontine_group` est une entité informelle : « on se retrouve, on paie, on repart ». Pas de bureau élu, pas d'exercice financier, pas de séances formelles. La plateforme lui offre une traçabilité numérique légère sans imposer la lourdeur organisationnelle d'une association.

### Modules disponibles / non disponibles

| Module | tontine_group |
|--------|:---:|
| Tontines | ✅ |
| Réunions informelles (présence optionnelle) | ✅ |
| Caisse commune informelle | ✅ si `is_presentielle = true` |
| Bureau & élections | ❌ |
| Emprunts | ❌ |
| Épargnes | ❌ |
| Cycle d'activité (`association_cycles`) | ❌ |
| Séances formelles (`seances`) | ❌ |
| Caisse de solidarité | ❌ |
| Main levée | ❌ |
| Documents | ❌ |

### Tontines présentielle vs non-présentielle

Chaque tontine d'un `tontine_group` est classifiée selon son mode de tenue :

| | `is_presentielle = true` | `is_presentielle = false` |
|---|---|---|
| **Réunion** | Physique (membres se déplacent) | À distance (paiement mobile, pas de déplacement) |
| **Caisse commune** | ✅ Possible | ❌ Non applicable |
| **Présence** | Optionnellement tracée | Non tracée |
| **Heure limite paiement** | Fixée (`session_deadline_time`) | Peut être élargie (délai supplémentaire configurable) |

### Modérateur — règle pour tontine_group

Le modérateur d'une session est résolu selon une **chaîne de fallback à 3 niveaux** :

```
tontine_sessions.moderated_by   ← animateur désigné pour cette session uniquement
  ↓ si NULL
tontines.moderateur_id          ← animateur permanent de la tontine
  ↓ si NULL
president / treasurer           ← défaut implicite (toujours actif)
```

- Le président peut désigner un **animateur permanent** via `PUT /tontines/{tId}/moderateur` (champ `moderateur_id`).
- Le président ou trésorier peut désigner un **animateur ponctuel** pour une session spécifique via `PUT /sessions/{sId}/moderateur` (champ `moderated_by`).
- `TontineModeratorFilter` vérifie les 3 niveaux dans l'ordre ; le president et le treasurer passent **toujours** ce filtre.

#### Permissions de l'animateur délégué (niveaux 1 et 2)

L'animateur délégué a des droits **limités à la présence et à l'animation** :

| Action | Animateur délégué | President / Treasurer |
|--------|:-----------------:|:---------------------:|
| Enregistrer le nombre de présents (`present_count`) | ✅ | ✅ |
| Mettre à jour le statut des points de l'ordre du jour | ✅ | ✅ |
| Saisir les commentaires sur les points d'ordre du jour | ✅ | ✅ |
| Enregistrer des paiements / cotisations | ❌ | ✅ |
| Ouvrir / clôturer une session | ❌ | ✅ |
| Rétrograder un membre défaillant | ❌ | ✅ |
| Enregistrer des mouvements caisse commune | ❌ | ✅ |

> Les opérations financières restent la responsabilité exclusive du president ou du treasurer.

### Réunions informelles

Un `tontine_group` ne dispose **pas** du module `seances`. Ses sessions de tontine n'ont donc pas de `seance_id`. L'état `tontine_sessions.seance_id = NULL` est normal et attendu.

Si la tontine est `is_presentielle = true`, le trésorier/président peut enregistrer optionnellement le **nombre de présents** sur la session (`tontine_sessions.present_count`) — simple comptage, sans liste nominative ni émargement.

### Caisse commune informelle

Disponible uniquement pour les tontines où `is_presentielle = true`.

**Objectif :** collecter des fonds informels en marge de la tontine principale (frais de fonctionnement, pot commun, fonds d'entraide basique).

**Type de collecte** (`caisse_commune_type`) :

| Type | Déclenchement |
|------|--------------|
| `per_session` | Montant fixe collecté automatiquement à chaque session |
| `ad_hoc` | Collecte ponctuelle initiée par le président/trésorier |
| `both` | Les deux modes actifs simultanément |

**Règles :**
- Le trésorier ou le président enregistre les entrées et sorties (`caisse_commune_transactions`)
- Tous les membres peuvent **consulter** le solde de la caisse
- Une `target_amount` optionnelle peut être définie (objectif à atteindre — pour information, pas de blocage)
- Les fonds sont distincts de la cagnotte de la tontine principale
- En mode `session_auction` : la caisse commune est **séparée** de la caisse d'adjudication (`tontines.caisse_balance`) — deux caisses indépendantes

### Désignation et remise des gains au bénéficiaire

#### Désignation du bénéficiaire

Le bénéficiaire d'une session est déterminé par le **mode de rotation** de la tontine, défini au démarrage :

| Mode | Moment de désignation |
|------|-----------------------|
| `random` | Tirage au sort à l'activation de la tontine — ordre fixé pour tout le cycle |
| `manual` | Défini par le président avant le démarrage — ordre fixé pour tout le cycle |
| `bidding` | Enchères soumises avant démarrage — ordre fixé pour tout le cycle |
| `session_auction` | Enchère à chaque session — bénéficiaire connu uniquement en fin d'enchère |

Pour les 3 premiers modes, chaque membre sait longtemps à l'avance quand il sera bénéficiaire. Pour `session_auction`, c'est le plus-disant de la séance qui remporte la cagnotte.

#### Remise des gains — tontine présentielle

La remise est **implicite à la clôture de session** : l'argent a physiquement changé de mains pendant la réunion.

- Le trésorier clôture la session via `PUT /sessions/{sId}/close`
- Le système renseigne automatiquement `amount_received = total_collected` et `received_at = horodatage de clôture`
- Pas d'étape intermédiaire

#### Remise des gains — tontine non-présentielle

La remise est une **étape explicite** déclenchée par le trésorier après avoir effectué le virement :

1. Les membres paient via Mobile Money jusqu'à `session_deadline_time + grace_period_hours`
2. Le trésorier regroupe les fonds reçus et effectue le virement au bénéficiaire
3. `PUT /sessions/{sId}/disburse` → saisie de `amount_received` + `payment_reference` (optionnelle)
4. La clôture de session (`PUT /sessions/{sId}/close`) est **bloquée** tant que `disburse` n'a pas été appelé

**Pot partiel autorisé :** si certains membres n'ont pas encore payé à la deadline, le bénéficiaire reçoit ce qui a été collecté. Le bénéficiaire n'est pas pénalisé pour les défaillances des autres membres. Les cotisations impayées restent tracées (`contributions.status = late`) et sont poursuivies séparément (pénalités applicables).

| | Présentielle | Non-présentielle |
|---|---|---|
| Remise | Implicite à la clôture | Étape `disburse` explicite |
| Pot partiel | N/A (cash collecté sur place) | ✅ Autorisé |
| Référence paiement | N/A | Optionnelle |
| Blocage clôture | Non | Oui, jusqu'au `disburse` |

### Saisie des paiements en session présentielle

#### Checklist membre à l'ouverture de session

À l'ouverture d'une session (`status = open`), le trésorier dispose d'une **liste de tous les membres actifs** avec pour chacun :
- Montant cotisation tontine dû (`shares × amount`)
- Montant caisse per_session dû (si `caisse_commune_type = per_session|both`)
- Statut : non payé / partiel / payé

#### Confirmation de paiement — une seule action par membre

`POST /sessions/{sId}/pay` — le trésorier confirme le paiement d'un membre en **une seule action** qui enregistre simultanément :
1. La cotisation tontine → `contributions`
2. La caisse per_session → `caisse_commune_transactions` (type `credit`)

Le système ventile en interne ; le trésorier ne fait qu'une seule manipulation par membre.

**Exception — paiement partiel ou dissocié :** si un membre paie l'un sans l'autre (tontine sans caisse, ou caisse sans tontine), le trésorier peut confirmer chaque partie indépendamment via le même endpoint avec les champs correspondants à `null` ou au montant partiel réel.

#### Mouvements caisse commune — disponibles à tout moment

Les mouvements ad_hoc et les dépenses ne sont **pas restreints à une session ouverte** — la caisse est informelle et les opérations peuvent survenir à n'importe quel moment.

`POST /tontines/{tId}/caisse/transactions` — disponible toujours :

| `type` | Usage | Qui |
|--------|-------|-----|
| `credit` | Don spontané, apport exceptionnel, collecte ad_hoc | Trésorier / Président |
| `debit` | Dépense (rafraîchissements, impression, fournitures…) | Trésorier / Président |

- `session_id` facultatif : renseigné si la transaction est liée à une session spécifique, `NULL` sinon
- `reason` obligatoire pour les débits (motif de la dépense)

### Référence de paiement (payment_reference)

Pour les tontines `is_presentielle = false` ou les paiements effectués à distance, un champ **`payment_reference`** facultatif est disponible sur chaque cotisation (`contributions`) :
- Saisie libre (numéro de transaction Mobile Money, référence virement, etc.)
- Visible par tous les membres
- Permet de justifier un paiement effectué sans présence physique

### Délai de paiement étendu (non-présentielle)

Pour les tontines `is_presentielle = false`, un **délai supplémentaire** peut être accordé aux membres avant que la cotisation ne soit considérée en retard :
- Configuré via `tontines.grace_period_hours` (défaut : 0 = pas de délai)
- La date de comptage des pénalités est décalée de `grace_period_hours` après `session_deadline_time`
- Permet d'absorber les délais de transfert Mobile Money sans pénaliser le membre de bonne foi

### Pénalités de retard

#### Paramétrage — par tontine

Chaque tontine configure ses propres règles indépendamment. Pas de règle héritée depuis l'entité — dans un groupe informel, chaque tontine est autonome.

- `tontines.penalty_type` + `tontines.penalty_value` (voir table `tontines` en DATABASE.md)
- **Défaut : aucune pénalité** (`penalty_value = 0`) — le groupe choisit consciemment d'en activer une

Les 8 modes disponibles sont identiques à ceux des tontines d'association (`fixed`, `fixed_per_day`, `fixed_per_week`, `fixed_per_month`, `percentage`, `percentage_per_day`, `percentage_per_week`, `percentage_per_month`).

Pour les tontines **non-présentielle**, le compteur de retard démarre après `session_deadline_time + grace_period_hours`.

#### Destination des pénalités

| Mode | Destination |
|------|-------------|
| Présentielle | → Caisse commune (argent collecté sur place, bénéficie à tout le groupe) |
| Non-présentielle | → Pot de la session (le bénéficiaire reçoit cotisations + pénalités collectées) |

#### Plafond

La pénalité ne peut jamais dépasser `amount_due`. Enforced par `PenaltyCalculator` — un long retard ne génère pas une dette supérieure à la cotisation initiale.

#### Non-contribution totale

Si un membre n'a pas payé à la clôture de session :
- **Notification automatique** au président/trésorier
- **Rétrogradation manuelle** uniquement — le modérateur décide selon le contexte (absence justifiée, maladie, mauvaise volonté)
- La pénalité continue de s'accumuler (dans la limite du plafond) jusqu'au paiement effectif

### Reconduction de cycle

À la clôture de la dernière session d'un cycle, si `auto_renew = true` et `max_cycles` non atteint :

1. `tontines.current_cycle` est incrémenté
2. `tontine_members.slots_received` est remis à 0 pour tous les membres actifs
3. De nouvelles sessions sont générées selon le même mode de rotation
4. Une **fenêtre de renouvellement** de 7 jours s'ouvre

**Pendant la fenêtre de renouvellement (7 jours) :**
- Les membres peuvent **se désinscrire** de la tontine
- Les membres peuvent **modifier leur nombre de parts** (`shares`) pour le nouveau cycle
- De nouveaux membres peuvent **rejoindre** la tontine
- Passé ce délai, la composition est figée et les sessions du nouveau cycle sont générées

**Caisse commune :**
Le solde de la caisse commune est **reporté au cycle suivant** — il s'accumule dans le temps. Il n'est redistribué qu'à la **clôture définitive** de la tontine.

**Pénalités impayées :**
Les pénalités et cotisations impayées du cycle précédent sont **reportées** au cycle suivant. Le membre repart avec sa dette ; il ne repart pas à zéro.

**Mode `session_auction` :**
La `caisse_balance` (caisse d'adjudication) est redistribuée pro-rata des parts **avant** la reconduction, puis remise à 0. Distincte de la caisse commune informelle qui, elle, est reportée.

### Clôture définitive de tontine

La clôture est déclenchée par le président/trésorier, ou automatiquement si `max_cycles` est atteint ou à `end_date`.

**Conditions :** aucun blocage sur les impayés — un groupe informel ne doit pas être bloqué par un membre défaillant. La clôture est possible même avec des cotisations ou pénalités en suspens.

**Procédure de clôture :**
1. Dernière session fermée (`status = closed`)
2. Mode `session_auction` : redistribution `caisse_balance` pro-rata des parts
3. **Redistribution de la caisse commune** (si solde > 0) : pro-rata des parts de chaque membre actif — cohérent avec la redistribution `session_auction`
4. Tontine passe en `status = completed`

**Cotisations et pénalités impayées à la clôture :**
Restent tracées comme dettes (`contributions.status = late`). Non bloquantes — le groupe décide humainement de les poursuivre ou non. L'historique complet est conservé.

### Parts multiples et fenêtres d'éligibilité

Les règles de parts multiples et d'éligibilité définies dans la section générale "Tontines" s'appliquent identiquement au `tontine_group` (fenêtres réparties, formule `ceil`).

**Assouplissement pour tontine_group :** en mode `random` et `manual`, le président peut placer les slots d'un membre **consécutivement** dans leurs fenêtres respectives si le groupe le décide — le garde-fou "pas deux fois consécutifs" reste le comportement par défaut mais peut être outrepassé manuellement.

### Conversion de tontine entre cycles

Lorsqu'une tontine change de type (ex: `is_presentielle` modifié, `caisse_commune_type` changé), la conversion n'est possible qu'**entre deux cycles** :
- La conversion est **bloquée** si `caisse_commune_transactions` a un solde non nul (caisse doit être vidée avant)
- Applicable uniquement au début d'un nouveau cycle (avant la première session)

---

## Emprunts

> **Réservé aux entités de type `association` et `federation`.**
> Les `tontine_group` n'ont pas accès au module Emprunts.

### Éligibilité
- Seuls les membres actifs d'une association peuvent emprunter
- Un membre avec un emprunt en cours ne peut pas en contracter un deuxième (configurable)
- Le montant maximum empruntable peut être limité par les settings de l'association
- Un membre peut utiliser comme garantie : ses parts tontine non perçues, son épargne, un garant

### Corps de la demande (DTO — POST /loans)
Le membre soumet en une seule requête :
```json
{
  "amount": 100000,
  "duration_months": 6,
  "purpose": "Achat matériel",
  "guarantees": [
    { "type": "member", "guarantor_user_id": 42 },
    { "type": "tontine_share", "tontine_member_id": 7 }
  ]
}
```
- `interest_rate` et `interest_type` sont **fixés automatiquement par `LoanService`** depuis les settings de l'association (`loan_max_rate`, `loan_default_interest_type`) — le membre ne les choisit pas
- Les garanties sont soumises **en même temps** que la demande (pas d'appel séparé)
- `LoanService` crée le loan + toutes les `loan_guarantees` en cascade dans la même transaction

### Garanties
- **Garant membre** (`type = member`) :
  - Le garant doit être membre actif de la même association
  - La garantie est créée avec `status = pending` à la soumission de la demande
  - Le garant doit **confirmer explicitement** via `PUT /loans/{lId}/guarantees/{gId}/confirm` → `status = confirmed`
  - Le trésorier peut approuver le prêt avant ou après confirmation (selon `loan_requires_guarantor` dans les settings)
  - Si l'emprunteur est en défaut, le garant est notifié et peut être sollicité
- **Épargne** (`type = savings`) : les fonds du compte épargne du membre (`savings_account_id`) sont bloqués jusqu'à remboursement complet — `status = confirmed` automatiquement ; `SavingsService::blockForGuarantee()` / `releaseGuarantee()` gèrent le cycle de vie
- **Part tontine non perçue** (`type = tontine_share`) : valeur des tours non encore reçus mise en garantie — `status = confirmed` automatiquement
- **Approbation admin** (`type = admin_approval`) : pas de garantie financière, juste accord du président/trésorier — `status = confirmed` à l'approbation du prêt

### Cycle de vie des garanties
```
[type=member]        pending → confirmed (par le garant) → released (remboursement complet)
                     pending → released  (si prêt rejeté)
[type=savings/tontine_share/admin_approval]  confirmed dès création → released
```

### Calcul intérêts simples
```
Total intérêts = Principal × Taux_annuel × (Durée_mois / 12)
Mensualité constante = (Principal + Total intérêts) / Durée_mois
```
Exemple : 100 000 XAF à 10%/an sur 6 mois
- Total intérêts = 100 000 × 0.10 × 0.5 = 5 000 XAF
- Mensualité = 105 000 / 6 = 17 500 XAF

### Calcul intérêts composés
```
Mensualité = Principal × (taux_mensuel × (1 + taux_mensuel)^n) / ((1 + taux_mensuel)^n - 1)
où taux_mensuel = Taux_annuel / 12, n = Durée_mois
```
Exemple : 100 000 XAF à 12%/an sur 6 mois
- Taux mensuel = 1% = 0.01
- Mensualité = 100 000 × (0.01 × 1.01^6) / (1.01^6 - 1) ≈ 17 255 XAF

### Workflow approbation
```
member soumet POST /loans + guarantees[]
  ├── guarantees type=member → status=pending (notification SMS/email au garant)
  └── guarantees autres types → status=confirmed automatiquement

[garant] PUT /guarantees/{gId}/confirm → status=confirmed

[treasurer] PUT /loans/approve  → status=approved
[treasurer] PUT /loans/disburse → status=active (disbursed_at, génère échéancier)
[treasurer] PUT /loans/reject   → status=rejected (toutes garanties → released)
[auto job]  CheckLoanDefaults   → status=defaulted
[treasurer] POST /repayments    → status=active → completed si total_repaid >= total_due
```
- Le trésorier ou le président peut approuver/rejeter
- `approved` : décision prise, fonds pas encore remis
- `active` : fonds décaissés (`disbursed_at` rempli), échéancier généré à ce moment
- La date de première échéance = 1 mois après `disbursed_at`
- À l'approbation : garanties `admin_approval` passent à `confirmed`
- Au remboursement complet : toutes les garanties passent à `released`

### Remboursements
- Chaque versement est imputé d'abord sur les pénalités, puis sur les intérêts, puis sur le capital
- Si un remboursement dépasse l'échéance due, le surplus est crédité sur l'échéance suivante
- Un emprunt est marqué `completed` quand `total_repaid >= total_due`
- Un emprunt est `defaulted` automatiquement via job planifié (`CheckLoanDefaults`) après `loan_default_delay_days` jours de retard sur une échéance (configurable dans `association_settings`)
- À la mise en défaut : notification automatique au treasurer et au président, et au membre concerné

### Lien avec le cycle d'activité
- Chaque prêt est **rattaché au cycle actif** au moment de la demande (`cycle_id` FK)
- `LoanService` contraint : `due_date ≤ cycle.end_date` (erreur 422 sinon)
- **Remboursement obligatoire en fin de cycle** : aucun prêt `active` ou `approved` ne peut subsister lors de la clôture du cycle → la clôture est bloquée jusqu'à soldement complet

### Remboursement flexible
- Le membre peut rembourser **en tranches** (selon l'échéancier mensuel) ou **en totalité** avant terme
- Tout versement est imputé : pénalités → intérêts → capital
- Le remboursement anticipé total est autorisé (sans pénalité de remboursement anticipé sauf configuration contraire)

### Taux d'intérêt — par période de prêt
- Le `interest_rate` est défini par l'association via `association_settings.loan_max_rate`
- Il s'applique **par période de prêt** (pas annualisé) : un prêt de 3 mois à 7% → intérêts = montant × 7%
- Unique pour tous les membres (non négociable individuellement)
- Exemple : 400 000 XAF × 7% = 28 000 XAF d'intérêts pour une période de 3 mois

### Reconduction de prêt
À l'échéance, **deux cas** selon la situation du membre — dans les deux cas, un **nouvel enregistrement `loans`** est créé pour garantir la traçabilité complète :

**CAS 1 — Reconduction choisie** (`source = 'renewal_cap'`) :
Le membre rembourse intégralement (capital + intérêts) et veut continuer à emprunter :
1. `POST /repayments` → remboursement complet du prêt courant → `status = completed`
2. `LoanService::renew()` crée un **nouveau** `loans` record :
   - `amount = old_amount × (1 + rate)` — intérêts capitalisés dans le nouveau principal
   - `parent_loan_id = old_loan_id`
   - `source = 'renewal_cap'`
   - `original_amount = new_amount`
   - `due_date ≤ cycle.end_date`
   - `renewal_count = old.renewal_count + 1`

```
Exemple Hermann (7%/trimestre, cycle Déc→Nov) :
  loan #1 : 400 000 XAF  → remboursé en mars  → status=completed
  loan #2 : 428 000 XAF  → remboursé en juin  → status=completed  (parent=#1)
  loan #3 : 457 960 XAF  → remboursé en sept  → status=completed  (parent=#2)
  loan #4 : 490 017 XAF  → dû en novembre    → status=active      (parent=#3)
  (fin de cycle → remboursement obligatoire, pas de loan #5)
```

**CAS 2 — Reconduction forcée** (`source = 'renewal_forced'`) :
Le membre ne peut pas rembourser à l'échéance :
1. `LoanService::forceRenew()` crée un **nouveau** `loans` record :
   - `amount = old_loan.total_due - old_loan.total_repaid` (solde restant)
   - `parent_loan_id = old_loan_id`
   - `source = 'renewal_forced'`
   - `due_date ≤ cycle.end_date`
   - `renewal_count = old.renewal_count + 1`
2. L'ancien prêt passe en `status = completed` (soldé par la reconduction)
3. Notification au membre + trésorier
4. Si `new_due_date = cycle.end_date` et prêt non soldé à terme → `status = defaulted` + blocage clôture cycle

**Dans les deux cas :**
- Le prêt précédent est marqué `completed` (la dette est transférée sur le nouveau prêt)
- `original_amount` = montant au décaissement de CE prêt (pas du prêt racine)
- Traçabilité complète : suivre la chaîne `parent_loan_id` → `NULL` pour voir tout l'historique
- La fin de cycle (`cycle.end_date`) est la limite absolue — aucune reconduction possible au-delà

---

## Caisse de solidarité

> **Réservé aux entités de type `association` et `federation`.**
> Les `tontine_group` n'ont pas accès au module Solidarité.

- Chaque association peut avoir une seule caisse de solidarité permanente
- Les cotisations à la caisse sont distinctes des cotisations tontine
- Le montant de cotisation périodique est configuré dans les settings
- Une demande de déblocage doit être approuvée par le trésorier ou président
- Le fond ne peut pas être à découvert (validation avant approbation)
- Le solde du fond est recalculé à chaque transaction (cotisation + déblocage)
- Un membre peut soumettre plusieurs demandes pour des raisons différentes
- Un membre peut annuler sa propre demande uniquement si elle est encore en statut `pending`
- Workflow complet : `pending → approved → disbursed` | `pending → rejected` | `pending → cancelled`
- `cancelled` (par le membre) est distinct de `rejected` (par le treasurer) — les deux sont conservés dans l'historique

### Traçabilité des versements
- À la transition `approved → disbursed`, le trésorier ou président doit renseigner :
  - `payment_method` : mode de remise (cash, mtn_momo, orange_money, transfer)
  - `recorded_by` : identifiant de la personne ayant enregistré le versement
- Ces deux champs sont **obligatoires** au moment du disburse (validés par `SolidarityService`)
- Traçabilité complète : chaque aide est reliée au membre bénéficiaire, au montant, à la raison, au mode de paiement et à la personne ayant enregistré

### Renflouement de la caisse
- **Cotisations périodiques** : montant mensuel configuré dans `association_settings.solidarity_monthly_amount`
- **Main levée de renflouement** : une main levée peut cibler la caisse (`beneficiary_type = 'fund'`) pour la renflouer après un décaissement important
  - Les fonds collectés sont versés directement dans `solidarity_funds.balance` lors du `handed_over`
  - `SolidarityService::creditFundFromFundraising()` est appelé par `FundraisingService` à cette étape

## Main levée (collecte ponctuelle)

- Initiée par le **président** ou le **trésorier** de l'association
- Motif obligatoire et détaillé (membre en difficulté, don, événement...)
- Deux modes de participation :
  - **Montant fixe** : le président définit un montant de base suggéré
  - **Montant libre** : chaque membre contribue selon ses moyens
- Les membres contribuent **volontairement** (aucune obligation)
- La main levée reste **archivée et consultable** indéfiniment après clôture

### Types de bénéficiaire (`beneficiary_type`)
| Type | Description | Champ requis |
|------|-------------|-------------|
| `member` | Aide à un membre identifié | `beneficiary_id` (FK users) |
| `external` | Bénéficiaire hors association | `beneficiary_name` (texte) |
| `fund` | Renflouement de la caisse de solidarité | `fund_id` (FK solidarity_funds) |

### Statuts d'une main levée
```
open → closed → handed_over
```
- `open` : collecte en cours, contributions acceptées
- `closed` : collecte terminée, fonds consolidés
- `handed_over` : fonds remis/versés, dossier archivé

### À la remise (`handed_over`)
- La transition `closed → handed_over` est **réservée au président** uniquement
- Enregistrement obligatoire : bénéficiaire, montant remis, date, notes justificatives
- **Si `beneficiary_type = member`** : `beneficiary_id` requis, versement manuel tracé
- **Si `beneficiary_type = external`** : `beneficiary_name` requis
- **Si `beneficiary_type = fund`** : `FundraisingService` appelle `SolidarityService::creditFundFromFundraising()` → `solidarity_funds.balance += amount_handed`
- Le dossier complet (motif initial + contributions + remise) reste consultable par les membres

---

## Champs personnalisés (custom fields)

- Chaque association peut définir des champs libres en plus des champs système
- Le créateur saisit un **libellé** (`label`) — le système génère automatiquement la **clé** (`key`) normalisée
- **Normalisation** : trim → lowercase → translitération accents → espaces/spéciaux → `_` → dédoublonnage
  - `"Num de Compte"` → key = `num_de_compte`
  - `"Email RH"` → key = `email_rh`
- Les clés système (`is_custom = 0`) sont **protégées** : non modifiables, non supprimables par l'utilisateur
- Les champs personnalisés apparaissent dans les états imprimables (entête des PDF)
- Champs d'identité fixes dans la table `associations` (non key-value) : `slogan`, `logo`, `phone`, `address`, `bp`, `tax_number`, `auth_number`
  - `tax_number` et `auth_number` ne s'appliquent pas aux `tontine_group` (affichés vides)
  - `slogan` s'applique aux 3 types d'entités et aux tontines

---

## Business model — Plans SaaS

- Toute entité créée démarre en période d'essai (`trial`) sur le plan `free`
- Un abonnement actif (`active`) débloque les features et quotas du plan souscrit
- En cas d'expiration sans renouvellement → downgrade automatique vers `free`
- Le `QuotaFilter` vérifie les limites **avant chaque action** critique

### Plans
| Plan | Prix/mois | Entités | Membres | Tontines | Features |
|------|-----------|---------|---------|----------|----------|
| `free` | Gratuit | 1 | 15 | 1 | Tontines basiques uniquement |
| `starter` | ~2 000 XAF | 1 | 50 | 3 | + Emprunts, solidarité, documents, exports CSV |
| `pro` | ~5 000 XAF | 3 | illimité | illimité | + Bureau, élections, exports PDF |
| `federation` | ~15 000 XAF | illimité | illimité | illimité | + Fédération, sous-associations |

### Règles
- Les limites (`max_members`, `max_tontines`, `max_entities`) sont vérifiées à la création, pas rétroactivement
- Un downgrade ne supprime pas les données existantes, mais bloque les nouvelles créations
- Paiement accepté : MTN Mobile Money, Orange Money, virement manuel

---

## Documents

- Les statuts et règlements peuvent être marqués `is_public = true` → accessibles sans auth
- Les PV de réunion sont privés par défaut (membres uniquement)
- Formats autorisés : PDF, JPG, PNG, DOCX
- Taille max configurable (défaut : 10 MB)
- Un seul document peut être le document "en vigueur" par type (statuts, règlements)

---

## Timezone

- Le timezone **par défaut de la plateforme** est `Africa/Douala` (UTC+1, Cameroun/CEMAC)
- Toutes les **dates et heures sont stockées en UTC** dans la base de données
- L'affichage est converti dans le timezone effectif au moment de la restitution

### Hiérarchie des timezones
```
Plateforme (Africa/Douala)
  └── Association (association_settings.timezone — surcharge plateforme si défini)
        └── Tontine (tontines.timezone — surcharge association si défini)
```
- Chaque association peut définir son **propre timezone** (utile pour les fédérations multi-pays)
- Chaque tontine peut définir son **propre timezone** (cas : tontine regroupant des membres dans des pays différents)
- Si `tontines.timezone IS NULL`, on hérite du timezone de l'association
- Si `association_settings.timezone` n'est pas défini, on hérite du timezone plateforme

### Usages du timezone effectif
- Heure limite de paiement des cotisations (`session_deadline_time`)
- Ouverture automatique des sessions (job `OpenDueSessions` au matin de `session_date`)
- Rappels et notifications
- Échéances d'emprunt
- Délais d'invitation

---

## Sécurité & Intégrité

- Toutes les actions financières sont loguées dans `audit_logs`
- Un trésorier ne peut pas approuver son propre emprunt
- Un président ne peut pas approuver son propre emprunt
- Les montants sont toujours stockés en `DECIMAL(15,2)` — jamais en float
- La devise de l'association est fixée à la création (non modifiable après)
- Les suppressions sont soft-delete (jamais de DELETE physique sur données financières)
