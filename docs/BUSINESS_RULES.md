# Règles métier — Djangui

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
| Bureau & élections | ❌ | ✅ | ✅ |
| Emprunts | ❌ | ✅ | ✅ |
| Caisse de solidarité | ❌ | ✅ | ✅ |
| Main levée | ❌ | ✅ | ✅ |
| Documents | ❌ | ✅ | ✅ |
| Sous-associations | ❌ | ❌ | ✅ |
| Validation statuts | ❌ | ✅ | ✅ |

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
- Chaque tontine peut désigner un **modérateur** (membre de la tontine) chargé de la discipline
- Le modérateur peut **rétrograder** un membre défaillant (n'ayant pas cotisé sa ou ses parts) dans l'ordre de rotation
- La rétrogradation décale le slot du défaillant vers une séance ultérieure
- L'action de rétrogradation est **loguée et justifiée** (motif obligatoire)
- Le trésorier ou le président peut aussi effectuer cette action en l'absence du modérateur

### Reconduction tacite
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

## Emprunts

> **Réservé aux entités de type `association` et `federation`.**
> Les `tontine_group` n'ont pas accès au module Emprunts.

### Éligibilité
- Seuls les membres actifs d'une association peuvent emprunter
- Un membre avec un emprunt en cours ne peut pas en contracter un deuxième (configurable)
- Le montant maximum empruntable peut être limité par les settings de l'association
- Un membre peut utiliser comme garantie : ses parts tontine non perçues, son épargne, un garant

### Garanties
- **Garant membre** : le garant doit être membre actif de la même association
  - Le garant doit confirmer explicitement son engagement
  - Si l'emprunteur est en défaut, le garant est notifié et peut être sollicité
- **Épargne** : les fonds épargne du membre sont bloqués jusqu'à remboursement complet
- **Part tontine non perçue** : la valeur des tours non encore reçus est mise en garantie
- **Approbation admin** : pas de garantie financière, juste accord du président/trésorier

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
pending → approved → active → completed
pending → rejected
```
- Le trésorier ou le président peut approuver/rejeter
- `approved` : décision prise, fonds pas encore remis
- `active` : fonds décaissés (`disbursed_at` rempli), échéancier généré à ce moment
- La date de première échéance = 1 mois après `disbursed_at`

### Remboursements
- Chaque versement est imputé d'abord sur les pénalités, puis sur les intérêts, puis sur le capital
- Si un remboursement dépasse l'échéance due, le surplus est crédité sur l'échéance suivante
- Un emprunt est marqué `completed` quand `total_repaid >= total_due`
- Un emprunt est `defaulted` automatiquement via job planifié (`CheckLoanDefaults`) après `loan_default_delay_days` jours de retard sur une échéance (configurable dans `association_settings`)
- À la mise en défaut : notification automatique au treasurer et au président, et au membre concerné

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
