# Modèle de données — Djangui

## Vue d'ensemble des tables

```
users
associations                 -- type: tontine_group | association | federation
association_members
association_settings         -- clés système + clés personnalisées (is_custom)
invitations
password_resets
refresh_tokens

plans                        -- plans SaaS (free, starter, pro, federation)
subscriptions                -- abonnement actif par association

bureau_positions
bureau_terms
bureau_substitutions
elections
election_positions
election_candidates
election_votes

tontines
tontine_members
tontine_sessions
tontine_session_beneficiaries
contributions
tontine_session_bids          -- enchères par séance (mode session_auction)
tontine_caisse_distributions  -- redistribution caisse fin de cycle
tontine_slot_demotions        -- rétrogradations par le modérateur
caisse_commune_transactions   -- caisse commune informelle (tontine_group is_presentielle uniquement)

association_cycles           -- exercices financiers de l'association (label: "Exercice YYYY-YYYY", dates calculées)

public_holidays              -- jours fériés nationaux par pays + jours fériés locaux de l'association
seances                      -- réunions périodiques récurrentes (pré-générées à l'activation du cycle)
seance_participants          -- membres présents à une séance
assemblees                   -- réunions ad hoc convoquées par sujet
assemblee_participants       -- membres présents à une assemblée
agenda_items                 -- points de l'ordre du jour (séance ou assemblée, système + personnalisés)

loans
loan_guarantees
loan_repayments

savings_accounts             -- compte épargne par membre par cycle
savings_transactions         -- dépôts/retraits épargne
savings_snapshots            -- avoir par séance (pro-rata intérêts)
savings_pool_entries         -- apports externes au capital de prêt

solidarity_funds
solidarity_contributions
solidarity_requests

fundraisings
fundraising_contributions

documents
notifications
audit_logs
```

---

## Détail des tables

### `users`
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
uuid                CHAR(36) UNIQUE NOT NULL
first_name          VARCHAR(100) NOT NULL
last_name           VARCHAR(100) NOT NULL
phone               VARCHAR(20) NOT NULL UNIQUE   -- identifiant principal, obligatoire
email               VARCHAR(191) UNIQUE NULL       -- optionnel
password            VARCHAR(255) NOT NULL
avatar              VARCHAR(255) NULL
language            ENUM('fr','en') NOT NULL DEFAULT 'fr'  -- langue préférée (SMS, notifications, PDF)
is_active           TINYINT(1) DEFAULT 1
is_super_admin      TINYINT(1) DEFAULT 0           -- accès total plateforme (super_admin)
phone_verified_at   DATETIME NULL                  -- vérification OTP SMS
email_verified_at   DATETIME NULL                  -- vérification email (si email fourni)
created_at          DATETIME
updated_at          DATETIME
deleted_at          DATETIME NULL
```

### `associations`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
uuid            CHAR(36) UNIQUE NOT NULL
name            VARCHAR(191) NOT NULL
slug            VARCHAR(191) UNIQUE NOT NULL
description     TEXT NULL
slogan          VARCHAR(255) NULL      -- slogan ou message de bienvenue
logo            VARCHAR(255) NULL      -- chemin vers l'image uploadée
phone           VARCHAR(20) NULL       -- contact téléphonique officiel
address         TEXT NULL              -- adresse du siège social
bp              VARCHAR(100) NULL      -- boîte postale
tax_number      VARCHAR(100) NULL      -- numéro de contribuable (association/federation)
auth_number     VARCHAR(100) NULL      -- numéro d'autorisation officielle (association/federation)
country         CHAR(2) DEFAULT 'CM'         -- code ISO 3166-1 alpha-2 (ex: CM, FR, SN)
currency        CHAR(3) DEFAULT 'XAF'        -- code ISO 4217 (ex: XAF, EUR, USD)
type            ENUM('tontine_group','association','federation') DEFAULT 'association'
parent_id       BIGINT UNSIGNED NULL FK → associations.id  -- sous-association d'une fédération
statutes_text   LONGTEXT NULL          -- statuts rédigés dans le formulaire
statutes_file   VARCHAR(500) NULL      -- statuts uploadés (PDF/DOCX)
status          ENUM('pending_review','active','rejected','suspended') DEFAULT 'pending_review'
-- tontine_group : auto-approuvé (status = active dès création)
rejection_reason TEXT NULL
reviewed_by     BIGINT UNSIGNED FK → users.id NULL
reviewed_at     DATETIME NULL
created_by      BIGINT UNSIGNED FK → users.id
created_at      DATETIME
updated_at      DATETIME
deleted_at      DATETIME NULL
```

### `association_settings`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
key             VARCHAR(100) NOT NULL   -- normalisé snake_case (ex: num_de_compte)
label           VARCHAR(255) NULL       -- libellé original saisi par l'utilisateur (ex: "Num de Compte")
value           TEXT NULL
is_custom       TINYINT(1) DEFAULT 0    -- 0 = clé système protégée | 1 = clé créée par l'utilisateur
UNIQUE(association_id, key)
```
**Normalisation des clés personnalisées (`is_custom = 1`) :**
Appliquée automatiquement par `AssociationService` avant INSERT/UPDATE :
1. Trim (supprimer espaces en début/fin)
2. Lowercase (tout en minuscules)
3. Translitération accents : é→e, è→e, ç→c, etc.
4. Remplacer espaces et caractères spéciaux par `_`
5. Supprimer les `_` en double

Exemple : `"Num de Compte"` → key=`num_de_compte`, label=`"Num de Compte"`

**Clés système prédéfinies (`is_custom = 0`) :**
- `timezone` — Timezone de l'association (défaut : `Africa/Douala`) — peut être surchargé par `tontines.timezone`
- `tontine_default_amount` — Montant par défaut cotisation
- `late_penalty_type` — Type de pénalité : `fixed` | `fixed_per_day` | `fixed_per_week` | `fixed_per_month` | `percentage` | `percentage_per_day` | `percentage_per_week` | `percentage_per_month` (défaut : `percentage_per_month`)
- `late_penalty_value` — Valeur de la pénalité : montant XAF pour les types `fixed*`, taux décimal pour les types `percentage*` (ex: `0.05` = 5%)
- `loan_max_rate` — Taux d'intérêt emprunt appliqué **par période de prêt** (ex: 0.07 = 7%/trimestre — PAS annualisé)
- `loan_default_interest_type` — simple | compound
- `loan_max_duration_months` — Durée max emprunt en mois
- `loan_requires_guarantor` — true | false
- `solidarity_monthly_amount` — Cotisation mensuelle solidarité
- `rotation_default_mode` — random | manual | bidding
- `invitation_requires_approval` — true | false
- `loan_default_delay_days` — nb de jours de retard avant mise en défaut automatique (défaut : 30)
- `presence_amount` — montant de cotisation Présence par membre par séance d'assemblée (ex: 1000 XAF)
- `savings_enabled` — true | false — active le module épargne pour l'association (défaut : false pour tontine_group)
- `cycle_start_month` — mois de démarrage de l'exercice (1–12, défaut : 1 = janvier) — utilisé avec l'année de départ pour calculer `start_date`
- `cycle_duration_months` — durée en mois de chaque exercice (défaut : 12) — modifiable uniquement entre deux exercices ; si non modifié, la valeur est reconduite tacitement au prochain exercice
- `loan_interest_distribution` — pourcentage des intérêts reversés aux épargnants (défaut : 1.0 = 100%)
- `country_code` — code ISO 3166-1 alpha-2 du pays principal de l'association (ex: CM) — utilisé pour filtrer les jours fériés nationaux
- `seance_recurrence_type` — 'nth_weekday' | 'fixed_day' — règle de récurrence des séances
- `seance_week_ordinal` — 1|2|3|4|-1 (pour nth_weekday ; -1 = dernier) — Nème occurrence du jour dans le mois
- `seance_weekday` — 1–7 (1=lundi … 7=dimanche, pour nth_weekday)
- `seance_day_of_month` — 1–31 (pour fixed_day)

### `association_members`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
user_id         BIGINT UNSIGNED FK → users.id
effective_role  ENUM('president','treasurer','secretary','auditor','censor','member') DEFAULT 'member'
-- effective_role est calculé depuis bureau_terms + bureau_substitutions (mis à jour auto)
joined_at       DATETIME
left_at         DATETIME NULL          -- rempli quand is_active passe à 0
is_active       TINYINT(1) DEFAULT 1
UNIQUE(association_id, user_id)
```

### `bureau_positions` — Définition des postes du bureau
```sql
id                      BIGINT UNSIGNED PK AUTO_INCREMENT
association_id          BIGINT UNSIGNED FK → associations.id
title                   VARCHAR(191) NOT NULL
-- Exemples : "Président", "Censeur", "1er Vice-Censeur", "2ème Vice-Censeur"
category                ENUM('presidency','secretariat','treasury','audit','censorship','honorary','other')
-- censorship = censeur et ses suppléants
rank                    INT UNSIGNED DEFAULT 1    -- 1=principal, 2=1er adjoint, 3=2ème...
parent_position_id      BIGINT UNSIGNED NULL FK → bureau_positions.id
permission_level        ENUM('president','treasurer','secretary','auditor','censor','member')
mandate_duration_months INT UNSIGNED DEFAULT 24
is_elective             TINYINT(1) DEFAULT 1
is_active               TINYINT(1) DEFAULT 1
created_at              DATETIME
UNIQUE(association_id, category, rank)
```

### `bureau_terms` — Historique des mandats
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
association_id      BIGINT UNSIGNED FK → associations.id
position_id         BIGINT UNSIGNED FK → bureau_positions.id
member_id           BIGINT UNSIGNED FK → users.id
appointment_type    ENUM('elected','promoted','interim')
election_id         BIGINT UNSIGNED NULL FK → elections.id
started_at          DATETIME NOT NULL
ends_at             DATETIME NULL              -- date théorique de fin (calculée)
ended_at            DATETIME NULL              -- date réelle de fin
status              ENUM('active','ended','suspended') DEFAULT 'active'
notes               TEXT NULL
created_at          DATETIME
-- Un membre ne peut avoir qu'un seul mandat actif par association
-- Contrainte enforced au niveau Service (MySQL ne supporte pas les index UNIQUE partiels)
-- Index non-unique sur (association_id, member_id, status) pour les requêtes de vérification
```

### `bureau_substitutions` — Suppléances temporaires
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
association_id      BIGINT UNSIGNED FK → associations.id
absent_term_id      BIGINT UNSIGNED FK → bureau_terms.id   -- poste du membre indisponible
substitute_term_id  BIGINT UNSIGNED FK → bureau_terms.id   -- terme du suppléant actif
reason              TEXT NOT NULL
from_date           DATETIME NOT NULL
to_date             DATETIME NULL              -- NULL = durée indéterminée
status              ENUM('active','ended') DEFAULT 'active'
declared_by         BIGINT UNSIGNED FK → users.id
ended_at            DATETIME NULL
created_at          DATETIME
```

### `elections` — Organisation des scrutins
```sql
id                      BIGINT UNSIGNED PK AUTO_INCREMENT
association_id          BIGINT UNSIGNED FK → associations.id
title                   VARCHAR(255) NOT NULL
description             TEXT NULL
election_date           DATE NOT NULL
voting_opens_at         DATETIME NULL
voting_closes_at        DATETIME NULL
status                  ENUM('draft','open','closed','cancelled') DEFAULT 'draft'
organized_by            BIGINT UNSIGNED FK → users.id
results_published_at    DATETIME NULL
created_at              DATETIME
updated_at              DATETIME
```

### `election_positions` — Postes mis aux voix
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
election_id     BIGINT UNSIGNED FK → elections.id
position_id     BIGINT UNSIGNED FK → bureau_positions.id
UNIQUE(election_id, position_id)
```

### `election_candidates` — Candidats par poste
```sql
id                   BIGINT UNSIGNED PK AUTO_INCREMENT
election_id          BIGINT UNSIGNED FK → elections.id
election_position_id BIGINT UNSIGNED FK → election_positions.id  -- garantit que le poste est dans cette élection
member_id            BIGINT UNSIGNED FK → users.id
votes_count          INT DEFAULT 0
result               ENUM('pending','elected','not_elected') DEFAULT 'pending'
created_at           DATETIME
UNIQUE(election_position_id, member_id)
```

### `election_votes` — Votes individuels
```sql
id                   BIGINT UNSIGNED PK AUTO_INCREMENT
election_id          BIGINT UNSIGNED FK → elections.id
election_position_id BIGINT UNSIGNED FK → election_positions.id
voter_id             BIGINT UNSIGNED FK → users.id
candidate_id         BIGINT UNSIGNED FK → election_candidates.id
voted_at             DATETIME
UNIQUE(election_position_id, voter_id)   -- un vote par membre par poste
```

### `invitations`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
invited_by      BIGINT UNSIGNED FK → users.id
phone           VARCHAR(20) NULL                   -- canal primaire (SMS)
email           VARCHAR(191) NULL                  -- canal secondaire (optionnel)
-- contrainte Service : phone OR email requis (au moins un)
token           VARCHAR(100) UNIQUE NOT NULL
role            ENUM('treasurer','secretary','auditor','member') DEFAULT 'member'
-- Pour tontine_group : seuls 'treasurer' et 'member' sont valides (validé au niveau Service)
status          ENUM('pending','accepted','expired') DEFAULT 'pending'
expires_at      DATETIME
created_at      DATETIME
```

### `password_resets`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
phone           VARCHAR(20) NULL      -- reset par SMS (canal primaire)
email           VARCHAR(191) NULL     -- reset par email (canal secondaire si email fourni)
-- contrainte Service : phone OR email requis
token           VARCHAR(100) UNIQUE NOT NULL
expires_at      DATETIME NOT NULL
used_at         DATETIME NULL
created_at      DATETIME
```

### `refresh_tokens`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
user_id         BIGINT UNSIGNED FK → users.id
token_hash      VARCHAR(255) UNIQUE NOT NULL  -- hash SHA-256 du refresh token (ne jamais stocker en clair)
jti             CHAR(36) UNIQUE NOT NULL      -- JWT ID (corrélation avec l'access token émis)
expires_at      DATETIME NOT NULL
revoked_at      DATETIME NULL                 -- rempli à la déconnexion ou au refresh
created_at      DATETIME
-- Index : (user_id), (jti), (token_hash)
```

### `association_cycles`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
cycle_number    INT UNSIGNED NOT NULL
label           VARCHAR(100) NOT NULL              -- auto-généré : "Exercice YYYY-YYYY" (ex: "Exercice 2024-2025")
start_date      DATE NOT NULL                      -- calculé : 1er jour du cycle_start_month de l'année saisie
end_date        DATE NOT NULL                      -- calculé : start_date + cycle_duration_months − 1 jour
status          ENUM('draft','active','closing','closed') DEFAULT 'draft'
-- draft   : en préparation
-- active  : exercice en cours (un seul actif par association)
-- closing : clôture initiée, attente remboursement tous prêts
-- closed  : clôture effective, intérêts distribués, épargnes retirées
closed_at       DATETIME NULL
closing_notes   TEXT NULL
created_by      BIGINT UNSIGNED FK → users.id
created_at      DATETIME
updated_at      DATETIME
UNIQUE(association_id, cycle_number)
-- Index : (association_id, status) pour récupérer le cycle actif
-- Note : start_date et end_date sont calculés par CycleService depuis cycle_start_month + année fournie + cycle_duration_months
```

---

### `tontines`
```sql
id                          BIGINT UNSIGNED PK AUTO_INCREMENT
association_id              BIGINT UNSIGNED FK → associations.id
name                        VARCHAR(191) NOT NULL
description                 TEXT NULL
slogan                      VARCHAR(255) NULL                -- slogan ou message affiché sur les états imprimables
frequency                   ENUM('daily','weekly','monthly') NOT NULL
amount                      DECIMAL(15,2) NOT NULL           -- montant de base (1 part)
rotation_mode               ENUM('random','manual','bidding','session_auction') NOT NULL DEFAULT 'random'
beneficiaries_per_session   INT UNSIGNED DEFAULT 1           -- nb de bénéficiaires par séance
eligibility_rule            ENUM('default','custom') DEFAULT 'default'
-- default : slot K éligible à K/X × total_sessions
-- custom  : défini manuellement par le créateur
caisse_balance              DECIMAL(15,2) DEFAULT 0          -- cumul caisse (session_auction uniquement)
auto_renew                  TINYINT(1) DEFAULT 1             -- reconduction tacite
max_cycles                  INT UNSIGNED NULL                -- NULL = illimité
current_cycle               INT UNSIGNED DEFAULT 1
moderateur_id               BIGINT UNSIGNED NULL FK → users.id  -- animateur permanent désigné (association/federation : modérateur tontine ; tontine_group : animateur permanent, optionnel)
start_date                  DATE NOT NULL
end_date                    DATE NULL
max_members                 INT UNSIGNED NULL
session_deadline_time       TIME DEFAULT '23:59:00'             -- heure limite de paiement le jour de la session
grace_period_hours          TINYINT UNSIGNED DEFAULT 0          -- délai supplémentaire avant pénalité (tontine_group non-présentielle ; défaut 0)
penalty_type                ENUM('fixed','fixed_per_day','fixed_per_week','fixed_per_month','percentage','percentage_per_day','percentage_per_week','percentage_per_month') DEFAULT 'fixed'
penalty_value               DECIMAL(10,4) DEFAULT 0             -- 0 = aucune pénalité (défaut) ; montant XAF pour fixed*, taux décimal pour percentage*
-- pénalité plafonnée à amount_due (enforced par PenaltyCalculator)
-- destination : présentielle → caisse commune | non-présentielle → pot de la session
timezone                    VARCHAR(50) NULL                    -- fuseau horaire (NULL = hérite de l'association)
status                      ENUM('draft','active','completed','cancelled') DEFAULT 'draft'
-- Champs réservés aux tontines de tontine_group
is_presentielle             TINYINT(1) DEFAULT 1             -- 1 = tontine physique, 0 = à distance (tontine_group uniquement)
caisse_commune_type         ENUM('per_session','ad_hoc','both') NULL  -- NULL si is_presentielle = 0 ou association/federation
caisse_commune_per_session_amount DECIMAL(15,2) NULL         -- montant fixe collecté par session si type = per_session|both
caisse_commune_target       DECIMAL(15,2) NULL               -- objectif optionnel (indicatif, pas de blocage)
created_by                  BIGINT UNSIGNED FK → users.id
created_at                  DATETIME
updated_at                  DATETIME
-- Note : caisse commune disponible uniquement si association.type = tontine_group AND is_presentielle = 1
-- Note : moderateur_id toujours NULL pour tontine_group (modérateur implicite = president/treasurer)
```

### `tontine_session_bids` — Enchères par séance (mode session_auction)
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
session_id      BIGINT UNSIGNED FK → tontine_sessions.id
tontine_id      BIGINT UNSIGNED FK → tontines.id
member_id       BIGINT UNSIGNED FK → users.id
bid_amount      DECIMAL(15,2) NOT NULL
is_winner       TINYINT(1) DEFAULT 0
bid_at          DATETIME NOT NULL
created_at      DATETIME
```

### `tontine_caisse_distributions` — Redistribution caisse en fin de cycle
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
tontine_id      BIGINT UNSIGNED FK → tontines.id
cycle_number    INT UNSIGNED NOT NULL     -- cycle concerné par cette redistribution
member_id       BIGINT UNSIGNED FK → users.id
shares          INT UNSIGNED NOT NULL
total_caisse    DECIMAL(15,2) NOT NULL    -- cumul total caisse au moment de la clôture
amount_received DECIMAL(15,2) NOT NULL    -- part reçue = total_caisse / SUM(shares) × shares
distributed_at  DATETIME NOT NULL
created_at      DATETIME
UNIQUE(tontine_id, cycle_number, member_id)
```

### `caisse_commune_transactions` — Caisse commune informelle (tontine_group présentielle)
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
tontine_id      BIGINT UNSIGNED FK → tontines.id
session_id      BIGINT UNSIGNED NULL FK → tontine_sessions.id  -- NULL si collecte ad_hoc hors session
type            ENUM('credit','debit') NOT NULL
amount          DECIMAL(15,2) NOT NULL
balance_after   DECIMAL(15,2) NOT NULL
reason          VARCHAR(255) NULL         -- motif de la dépense ou de la collecte
recorded_by     BIGINT UNSIGNED FK → users.id
created_at      DATETIME
-- Contrainte : disponible uniquement pour tontine_group + is_presentielle = 1
-- Index : (tontine_id, created_at)
```

### `tontine_members`
```sql
id                      BIGINT UNSIGNED PK AUTO_INCREMENT
tontine_id              BIGINT UNSIGNED FK → tontines.id
user_id                 BIGINT UNSIGNED FK → users.id
shares                  INT UNSIGNED DEFAULT 1   -- nombre de parts souscrites
bid_amount              DECIMAL(15,2) NULL        -- enchère en amont (mode bidding)
slots_received          INT UNSIGNED DEFAULT 0    -- slots déjà perçus ce cycle
is_active               TINYINT(1) DEFAULT 1
joined_at               DATETIME
left_at                 DATETIME NULL             -- rempli quand is_active passe à 0
UNIQUE(tontine_id, user_id)
```
> Nombre total de sessions = SUM(shares) / beneficiaries_per_session.
> Un membre avec shares=3 a 3 slots. Les slots individuels sont assignés via `tontine_session_beneficiaries`
> (un enregistrement par slot par séance). Formule éligibilité : voir BUSINESS_RULES.md § Éligibilité.

### `tontine_slot_demotions` — Rétrogradations par le modérateur
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
tontine_id      BIGINT UNSIGNED FK → tontines.id
member_id       BIGINT UNSIGNED FK → users.id         -- membre rétrogradé
old_session_id  BIGINT UNSIGNED FK → tontine_sessions.id
new_session_id  BIGINT UNSIGNED FK → tontine_sessions.id
reason          TEXT NOT NULL
actioned_by     BIGINT UNSIGNED FK → users.id          -- modérateur ou trésorier/président
created_at      DATETIME
```

### `tontine_sessions`
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
tontine_id          BIGINT UNSIGNED FK → tontines.id
seance_id           BIGINT UNSIGNED NULL FK → seances.id  -- séance formelle liée (obligatoire pour association/federation, NULL pour tontine_group)
cycle_number        INT UNSIGNED NOT NULL DEFAULT 1   -- numéro du cycle auquel appartient cette session
session_number      INT NOT NULL                      -- numéro de séance dans le cycle
session_date        DATE NOT NULL
total_collected     DECIMAL(15,2) DEFAULT 0
auction_winning_bid DECIMAL(15,2) NULL    -- montant adjugé par le gagnant de l'enchère (session_auction)
caisse_contribution DECIMAL(15,2) NULL    -- montant effectivement versé en caisse (= auction_winning_bid ; séparé pour traçabilité)
present_count       TINYINT UNSIGNED NULL -- nombre de présents (optionnel, tontine_group is_presentielle uniquement)
moderated_by        BIGINT UNSIGNED NULL FK → users.id  -- animateur ponctuel désigné pour cette session (optionnel, tontine_group uniquement)
-- fallback : moderated_by → tontines.moderateur_id → president/treasurer
-- droits animateur délégué : présence + ordre du jour uniquement (pas d'opérations financières)
status              ENUM('pending','open','auction','closed') DEFAULT 'pending'
notes               TEXT NULL
opened_at           DATETIME NULL
closed_at           DATETIME NULL
UNIQUE(tontine_id, cycle_number, session_number)
-- Note (association/federation) : seance_id obligatoire — une session tontine se tient dans le cadre d'une séance
-- Note (tontine_group) : seance_id = NULL, present_count + moderated_by optionnels
```

### `tontine_session_beneficiaries` — Bénéficiaires par séance (supporte N bénéficiaires)
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
session_id          BIGINT UNSIGNED FK → tontine_sessions.id
tontine_id          BIGINT UNSIGNED FK → tontines.id
member_id           BIGINT UNSIGNED FK → users.id
slot_number         INT NOT NULL              -- position du slot dans la séance (1, 2, ...)
amount_received     DECIMAL(15,2) NULL        -- montant effectivement remis (peut être < total_collected si pot partiel)
received_at         DATETIME NULL             -- horodatage de la remise
payment_reference   VARCHAR(255) NULL         -- référence virement (optionnel, non-présentielle)
disbursed_by        BIGINT UNSIGNED NULL FK → users.id  -- trésorier/président ayant confirmé la remise
UNIQUE(session_id, slot_number)
-- Présentielle   : amount_received et received_at remplis automatiquement à la clôture de session
-- Non-présentielle : remplis via PUT /sessions/{sId}/disburse (étape obligatoire avant clôture)
-- Pot partiel autorisé : amount_received peut être < total_collected théorique
```

### `contributions`
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
session_id          BIGINT UNSIGNED FK → tontine_sessions.id
tontine_id          BIGINT UNSIGNED FK → tontines.id
member_id           BIGINT UNSIGNED FK → users.id
seance_id           BIGINT UNSIGNED NULL FK → seances.id  -- séance de comptabilisation (association/federation uniquement ; NULL pour tontine_group)
amount_due          DECIMAL(15,2) NOT NULL
amount_paid         DECIMAL(15,2) DEFAULT 0
penalty             DECIMAL(15,2) DEFAULT 0
status              ENUM('pending','partial','paid','late') DEFAULT 'pending'
paid_at             DATETIME NULL
payment_method      VARCHAR(50) NULL
payment_reference   VARCHAR(255) NULL   -- référence optionnelle (numéro transaction Mobile Money, virement, etc.) — visible par tous les membres
recorded_by         BIGINT UNSIGNED FK → users.id NULL
created_at          DATETIME
updated_at          DATETIME
UNIQUE(session_id, member_id)
-- Note (association/federation) : seance_id renseigné quand le paiement est effectué entre deux séances
-- Note (tontine_group) : seance_id = NULL ; payment_reference utile pour paiements à distance
```

---

### `public_holidays`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
country_code    CHAR(2) NULL       -- NULL = jour férié local propre à une association
association_id  BIGINT UNSIGNED NULL FK → associations.id  -- NULL = national
date            DATE NOT NULL
label           VARCHAR(191) NOT NULL
is_recurring    TINYINT(1) DEFAULT 1  -- 1 = se répète chaque année (même mois/jour), 0 = date unique
created_at      DATETIME
-- Index : (country_code, date), (association_id, date)
-- Exemples nationaux CM : 2026-01-01 "Nouvel An", 2026-05-20 "Fête nationale", 2026-12-25 "Noël"
```

### `seances`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
cycle_id        BIGINT UNSIGNED FK → association_cycles.id
scheduled_date  DATE NOT NULL        -- date originale planifiée (immuable)
actual_date     DATE NOT NULL        -- date effective (= scheduled_date si non reportée)
start_time      TIME NULL            -- heure de début (saisie après tenue)
end_time        TIME NULL            -- heure de fin (saisie après tenue)
status          ENUM('scheduled','needs_reschedule','postponed','held','cancelled') DEFAULT 'scheduled'
postponed_reason TEXT NULL           -- obligatoire si status = postponed
participant_count INT UNSIGNED NULL  -- nombre de présents (saisi à la clôture)
notes           TEXT NULL
report_text     TEXT NULL            -- rapport saisi
report_file     VARCHAR(255) NULL    -- rapport uploadé (chemin)
created_by      BIGINT UNSIGNED FK → users.id
created_at      DATETIME
updated_at      DATETIME
-- Index : (association_id, actual_date), (cycle_id, status)
-- Note : séances pré-générées par CycleService à l'activation du cycle
-- Note : la séance courante = première séance dont status NOT IN ('held','cancelled') ORDER BY actual_date ASC
-- Note : si cancelled, les opérations financières déjà rattachées → réassignées à la séance suivante
-- Note : tant que status ≠ held, toute nouvelle opération financière de l'association lui est rattachée
```

### `seance_participants`
```sql
id          BIGINT UNSIGNED PK AUTO_INCREMENT
seance_id   BIGINT UNSIGNED FK → seances.id
member_id   BIGINT UNSIGNED FK → users.id
created_at  DATETIME
UNIQUE(seance_id, member_id)
```

### `assemblees`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
cycle_id        BIGINT UNSIGNED NULL FK → association_cycles.id  -- NULL si hors exercice actif
subject         VARCHAR(255) NOT NULL  -- sujet obligatoire
scheduled_date  DATE NOT NULL          -- date initiale convenue (immuable)
actual_date     DATE NOT NULL          -- date effective (= scheduled_date si non reportée)
start_time      TIME NULL
end_time        TIME NULL
status          ENUM('scheduled','postponed','held','cancelled') DEFAULT 'scheduled'
postponed_reason TEXT NULL
participant_count INT UNSIGNED NULL
notes           TEXT NULL
report_text     TEXT NULL
report_file     VARCHAR(255) NULL
created_by      BIGINT UNSIGNED FK → users.id
created_at      DATETIME
updated_at      DATETIME
-- Index : (association_id, actual_date)
-- Note : peut coïncider avec une séance (entités indépendantes)
```

### `assemblee_participants`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
assemblee_id    BIGINT UNSIGNED FK → assemblees.id
member_id       BIGINT UNSIGNED FK → users.id
created_at      DATETIME
UNIQUE(assemblee_id, member_id)
```

### `agenda_items`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
meeting_type    ENUM('seance','assemblee') NOT NULL
meeting_id      BIGINT UNSIGNED NOT NULL         -- polymorphic : seances.id ou assemblees.id
order           TINYINT UNSIGNED NOT NULL        -- position dans l'ordre du jour (1, 2, 3...)
title           VARCHAR(255) NOT NULL            -- intitulé du point
is_system       TINYINT(1) DEFAULT 0             -- 1 = généré automatiquement par le système
is_deletable    TINYINT(1) DEFAULT 1             -- 0 = non supprimable (ex: "Opérations financières")
status          ENUM('pending','done','skipped') DEFAULT 'pending'
comment         TEXT NULL                        -- commentaire du secrétaire sur ce point (alimente le rapport)
created_at      DATETIME
updated_at      DATETIME
-- Index : (meeting_type, meeting_id, order)
-- Points système séance (is_system=1) : Prière ouverture, Appel/émargement, Lecture rapport précédent,
--   Opérations financières (is_deletable=0), Points divers, Nouvelles communauté, Astuces, Rafraîchissement, Prière clôture
-- Points système assemblée (is_system=1) : Prière ouverture, Appel/émargement,
--   [Sujet assemblée] (is_deletable=0), Points divers, Prière clôture
```

---

### `loans`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
member_id       BIGINT UNSIGNED FK → users.id
cycle_id        BIGINT UNSIGNED FK → association_cycles.id   -- cycle auquel appartient ce prêt
parent_loan_id  BIGINT UNSIGNED NULL FK → loans.id           -- prêt précédent dans la chaîne (NULL si premier emprunt)
source          ENUM('new','renewal_cap','renewal_forced') DEFAULT 'new'
-- new              : nouvelle demande membre
-- renewal_cap      : reconduction CAS 1 — remboursement complet + re-emprunt (intérêts capitalisés : new_amount = old × (1+rate))
-- renewal_forced   : reconduction CAS 2 — solde restant impayé reconduit
amount          DECIMAL(15,2) NOT NULL
interest_rate   DECIMAL(5,4) NOT NULL   -- taux par PÉRIODE de prêt (ex: 0.0700 = 7%/trimestre, PAS annualisé) — fixé depuis association_settings(loan_max_rate)
interest_type   ENUM('simple','compound') NOT NULL
duration_months INT NOT NULL
original_amount DECIMAL(15,2) NOT NULL   -- montant au moment du décaissement de CE prêt spécifique
renewal_count   INT UNSIGNED DEFAULT 0   -- profondeur dans la chaîne (cache : = COUNT(ancestors via parent_loan_id))
purpose         TEXT NULL
status          ENUM('pending','approved','rejected','active','completed','defaulted')
approved_by     BIGINT UNSIGNED FK → users.id NULL
approved_at     DATETIME NULL
disbursed_at    DATETIME NULL
due_date        DATE NULL
total_due       DECIMAL(15,2) NULL      -- calculé au décaissement (disbursed_at), généré avec l'échéancier
total_repaid    DECIMAL(15,2) DEFAULT 0
created_at      DATETIME
updated_at      DATETIME
```

### `loan_guarantees`
```sql
id                   BIGINT UNSIGNED PK AUTO_INCREMENT
loan_id              BIGINT UNSIGNED FK → loans.id
type                 ENUM('member','savings','tontine_share','admin_approval')
guarantor_user_id    BIGINT UNSIGNED NULL FK → users.id          -- si type = member
tontine_member_id    BIGINT UNSIGNED NULL FK → tontine_members.id -- si type = tontine_share
savings_account_id   BIGINT UNSIGNED NULL FK → savings_accounts.id -- si type = savings (épargne bloquée)
-- Contrainte Service : guarantor_user_id requis si type=member, tontine_member_id requis si type=tontine_share, savings_account_id requis si type=savings
amount_pledged       DECIMAL(15,2) NULL
status               ENUM('pending','confirmed','released') DEFAULT 'pending'
confirmed_at         DATETIME NULL
created_at           DATETIME
updated_at           DATETIME
```
> **Garantie `savings`** : les fonds du compte épargne référencé sont bloqués jusqu'au remboursement complet du prêt. `status = confirmed` automatiquement à la création. `SavingsService::blockForGuarantee()` / `releaseGuarantee()` gèrent le blocage/déblocage. Le montant bloqué est exclu du capital disponible dans `SavingsService::getAvailableCapital()`.

### `loan_repayments`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
loan_id         BIGINT UNSIGNED FK → loans.id
seance_id       BIGINT UNSIGNED NULL FK → seances.id  -- séance de comptabilisation (prochaine séance à la date du paiement)
installment_no  INT NOT NULL
due_date        DATE NOT NULL
amount_due      DECIMAL(15,2) NOT NULL
principal_due   DECIMAL(15,2) NOT NULL
interest_due    DECIMAL(15,2) NOT NULL
amount_paid     DECIMAL(15,2) DEFAULT 0
penalty         DECIMAL(15,2) DEFAULT 0
status          ENUM('pending','paid','partial','late') DEFAULT 'pending'
paid_at         DATETIME NULL
recorded_by     BIGINT UNSIGNED FK → users.id NULL
created_at      DATETIME
```

> **Chaîne de reconduction :** Un prêt reconduit crée un **nouvel enregistrement** `loans` avec `parent_loan_id` pointant vers le précédent. Le prêt précédent passe en `status = completed`. `original_amount` = montant au décaissement de ce prêt spécifique. Pour retrouver l'historique complet : suivre la chaîne `parent_loan_id` jusqu'à `NULL`. `renewal_count` = profondeur dans cette chaîne (cache).

---

### `savings_accounts` — Compte épargne par membre par cycle
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
cycle_id        BIGINT UNSIGNED FK → association_cycles.id
member_id       BIGINT UNSIGNED FK → users.id
balance         DECIMAL(15,2) DEFAULT 0
total_deposited DECIMAL(15,2) DEFAULT 0
interest_earned DECIMAL(15,2) DEFAULT 0   -- calculé et crédité lors de la clôture du cycle
status          ENUM('active','closed') DEFAULT 'active'
closed_at       DATETIME NULL             -- rempli à la clôture du cycle (retrait effectué)
created_at      DATETIME
updated_at      DATETIME
UNIQUE(cycle_id, member_id)
```

### `savings_transactions` — Historique dépôts / retraits
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
account_id          BIGINT UNSIGNED FK → savings_accounts.id
association_id      BIGINT UNSIGNED FK → associations.id
seance_id           BIGINT UNSIGNED NULL FK → seances.id  -- séance de comptabilisation (prochaine séance à la date de l'opération)
type                ENUM('deposit','withdrawal','interest_payout','presence') NOT NULL
-- deposit          : dépôt d'épargne (peut être effectué entre deux séances → rattaché à la prochaine)
-- withdrawal       : retrait capital en fin de cycle
-- interest_payout  : versement de la part d'intérêts en fin de cycle
-- presence         : cotisation de présence (frais de fonctionnement, non inclus pro-rata intérêts)
amount              DECIMAL(15,2) NOT NULL
balance_after       DECIMAL(15,2) NOT NULL
operation_date      DATE NOT NULL              -- date réelle de l'opération (peut différer de actual_date de la séance)
tontine_session_id  BIGINT UNSIGNED NULL FK → tontine_sessions.id  -- si lié à une session tontine spécifique
payment_method      VARCHAR(50) NULL
notes               TEXT NULL
recorded_by         BIGINT UNSIGNED FK → users.id
created_at          DATETIME
```

### `savings_snapshots` — Avoir par séance (pro-rata intérêts)
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
account_id      BIGINT UNSIGNED FK → savings_accounts.id
association_id  BIGINT UNSIGNED FK → associations.id
cycle_id        BIGINT UNSIGNED FK → association_cycles.id
snapshot_date   DATE NOT NULL
balance         DECIMAL(15,2) NOT NULL    -- solde épargne CUMULATIF du membre à cette date (Σ dépôts depuis début cycle + report cycle précédent)
loans_active    TINYINT(1) DEFAULT 0      -- y avait-il au moins un prêt actif dans l'association à cette date ?
created_at      DATETIME
UNIQUE(account_id, snapshot_date)
-- Note : seuls les snapshots avec loans_active = 1 entrent dans le calcul du pro-rata
```

### `savings_pool_entries` — Apports externes au capital de prêt
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
cycle_id        BIGINT UNSIGNED FK → association_cycles.id
amount          DECIMAL(15,2) NOT NULL
source          VARCHAR(255) NOT NULL      -- description de la source (ex: "Apport fédération", "Don externe")
type            ENUM('injection','withdrawal') DEFAULT 'injection'
recorded_by     BIGINT UNSIGNED FK → users.id
notes           TEXT NULL
created_at      DATETIME
```

---

### `solidarity_funds`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id UNIQUE
name            VARCHAR(191) DEFAULT 'Caisse de solidarité'
balance         DECIMAL(15,2) DEFAULT 0
total_collected DECIMAL(15,2) DEFAULT 0
total_disbursed DECIMAL(15,2) DEFAULT 0
is_active       TINYINT(1) DEFAULT 1
created_at      DATETIME
updated_at      DATETIME
```

### `solidarity_contributions`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
fund_id         BIGINT UNSIGNED FK → solidarity_funds.id
member_id       BIGINT UNSIGNED FK → users.id
seance_id       BIGINT UNSIGNED NULL FK → seances.id  -- séance de comptabilisation (prochaine séance à la date de l'opération)
amount          DECIMAL(15,2) NOT NULL
operation_date  DATE NOT NULL   -- date réelle du versement (peut différer de actual_date de la séance)
reason          VARCHAR(255) NULL
payment_method  VARCHAR(50) NULL
recorded_by     BIGINT UNSIGNED FK → users.id NULL
created_at      DATETIME
```

### `fundraisings` (Main levée)
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
association_id      BIGINT UNSIGNED FK → associations.id
initiated_by        BIGINT UNSIGNED FK → users.id
title               VARCHAR(255) NOT NULL
reason              TEXT NOT NULL
amount_mode         ENUM('fixed','free') DEFAULT 'free'
suggested_amount    DECIMAL(15,2) NULL   -- si amount_mode = fixed
total_collected     DECIMAL(15,2) DEFAULT 0
status              ENUM('open','closed','handed_over') DEFAULT 'open'
beneficiary_type    ENUM('member','external','fund') NULL
-- 'member'   : aide à un membre identifié (beneficiary_id requis)
-- 'external' : bénéficiaire externe (beneficiary_name requis)
-- 'fund'     : renflouement de la caisse de solidarité (fund_id requis)
beneficiary_id      BIGINT UNSIGNED FK → users.id NULL       -- si beneficiary_type = member
beneficiary_name    VARCHAR(255) NULL                        -- si beneficiary_type = external
fund_id             BIGINT UNSIGNED FK → solidarity_funds.id NULL  -- si beneficiary_type = fund
amount_handed       DECIMAL(15,2) NULL
handed_at           DATETIME NULL
handed_notes        TEXT NULL
closed_at           DATETIME NULL
created_at          DATETIME
updated_at          DATETIME
```

### `fundraising_contributions`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
fundraising_id  BIGINT UNSIGNED FK → fundraisings.id
member_id       BIGINT UNSIGNED FK → users.id
amount          DECIMAL(15,2) NOT NULL
contributed_at  DATETIME NOT NULL
payment_method  VARCHAR(50) NULL
notes           TEXT NULL
recorded_by     BIGINT UNSIGNED FK → users.id NULL
created_at      DATETIME
```

### `solidarity_requests`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
fund_id         BIGINT UNSIGNED FK → solidarity_funds.id
member_id       BIGINT UNSIGNED FK → users.id
amount          DECIMAL(15,2) NOT NULL
reason          ENUM('death','marriage','illness','birth','other')
description     TEXT NULL
status          ENUM('pending','approved','rejected','cancelled','disbursed') DEFAULT 'pending'
approved_by     BIGINT UNSIGNED FK → users.id NULL
approved_at     DATETIME NULL
disbursed_at    DATETIME NULL
payment_method  VARCHAR(50) NULL          -- mode de versement : cash | mtn_momo | orange_money | transfer
recorded_by     BIGINT UNSIGNED NULL FK → users.id  -- trésorier/président ayant enregistré le versement
created_at      DATETIME
updated_at      DATETIME
```

---

### `documents`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
type            ENUM('statutes','regulations','pv','other')
title           VARCHAR(255) NOT NULL
description     TEXT NULL
file_path       VARCHAR(500) NOT NULL
file_size       INT NULL
mime_type       VARCHAR(100) NULL
is_public       TINYINT(1) DEFAULT 0
is_current      TINYINT(1) DEFAULT 0   -- document en vigueur pour ce type (un seul par type, enforced au niveau Service)
uploaded_by     BIGINT UNSIGNED FK → users.id
created_at      DATETIME
updated_at      DATETIME
deleted_at      DATETIME NULL
```

---

### `notifications`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
user_id         BIGINT UNSIGNED FK → users.id
association_id  BIGINT UNSIGNED FK → associations.id NULL
type            VARCHAR(100) NOT NULL
title           VARCHAR(255) NOT NULL
body            TEXT NULL
data            JSON NULL
is_read         TINYINT(1) DEFAULT 0
read_at         DATETIME NULL
created_at      DATETIME
```

### `audit_logs`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id NULL
user_id         BIGINT UNSIGNED FK → users.id NULL
action          VARCHAR(100) NOT NULL
model           VARCHAR(100) NULL
model_id        BIGINT UNSIGNED NULL
old_values      JSON NULL
new_values      JSON NULL
ip_address      VARCHAR(45) NULL
created_at      DATETIME
```

---

---

## Business model — Plans & Abonnements

### `plans`
```sql
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
name                VARCHAR(50) UNIQUE NOT NULL     -- identifiant technique : 'free','starter','pro','federation'
label               VARCHAR(100) NOT NULL            -- libellé affiché : 'Gratuit','Starter','Pro','Fédération'
price_monthly       DECIMAL(10,2) DEFAULT 0          -- prix mensuel en XAF (0 = gratuit)
max_entities        INT UNSIGNED NULL                -- nb max d'entités gérées (NULL = illimité)
max_members         INT UNSIGNED NULL                -- nb max de membres par entité (NULL = illimité)
max_tontines        INT UNSIGNED NULL                -- nb max de tontines actives (NULL = illimité)
features            JSON NOT NULL                    -- liste des features activées (ex: ["bureau","loans","reports"])
is_active           TINYINT(1) DEFAULT 1
created_at          DATETIME
updated_at          DATETIME
```

**Plans initiaux :**
| name | label | Prix/mois | Entités | Membres | Tontines | Features |
|------|-------|-----------|---------|---------|----------|----------|
| `free` | Gratuit | 0 | 1 | 15 | 1 | tontines basiques |
| `starter` | Starter | ~2 000 XAF | 1 | 50 | 3 | + emprunts, solidarité, documents |
| `pro` | Pro | ~5 000 XAF | 3 | illimité | illimité | + bureau, élections, exports PDF |
| `federation` | Fédération | ~15 000 XAF | illimité | illimité | illimité | + fédération, sous-associations |

### `subscriptions`
```sql
id                      BIGINT UNSIGNED PK AUTO_INCREMENT
association_id          BIGINT UNSIGNED FK → associations.id UNIQUE
plan_id                 BIGINT UNSIGNED FK → plans.id
status                  ENUM('trial','active','expired','cancelled') DEFAULT 'trial'
trial_ends_at           DATETIME NULL
current_period_start    DATETIME NOT NULL
current_period_end      DATETIME NOT NULL
payment_method          VARCHAR(50) NULL        -- 'mtn_momo', 'orange_money', 'manual'
cancelled_at            DATETIME NULL
created_at              DATETIME
updated_at              DATETIME
```

---

## Index recommandés (performance)

```sql
-- Fédérations : lookup sous-associations
INDEX ON associations(parent_id)

-- Enchères session_auction
INDEX ON tontine_session_bids(session_id, member_id)
INDEX ON tontine_session_bids(session_id, is_winner)

-- Refresh tokens
UNIQUE INDEX ON refresh_tokens(jti)
UNIQUE INDEX ON refresh_tokens(token_hash)
INDEX ON refresh_tokens(user_id, revoked_at)
```

---

## Vue agrégée membre (cross-associations)
Calculée à la volée via `GET /api/me/overview` :

```json
{
  "user": { ... },
  "associations": [
    {
      "association": { "id": 1, "name": "Association Alpha" },
      "role": "member",
      "tontines": {
        "active": 2,
        "total_contributed": 150000,
        "pending_to_receive": 100000
      },
      "loans": {
        "active_count": 1,
        "total_debt": 50000,
        "next_repayment": { "due_date": "2026-04-01", "amount": 5500 }
      },
      "solidarity": {
        "total_contributed": 12000,
        "balance_fund": 85000
      }
    }
  ],
  "totals": {
    "total_contributed": 162000,
    "total_debt": 50000,
    "net_position": 112000
  }
}
```
