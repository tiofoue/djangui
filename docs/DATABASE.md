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

association_cycles           -- exercices annuels (ou custom) de l'association

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
country         VARCHAR(100) DEFAULT 'CM'
currency        VARCHAR(10) DEFAULT 'XAF'
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
- `loan_max_rate` — Taux max intérêt emprunt
- `loan_default_interest_type` — simple | compound
- `loan_max_duration_months` — Durée max emprunt en mois
- `loan_requires_guarantor` — true | false
- `solidarity_monthly_amount` — Cotisation mensuelle solidarité
- `rotation_default_mode` — random | manual | bidding
- `invitation_requires_approval` — true | false
- `loan_default_delay_days` — nb de jours de retard avant mise en défaut automatique (défaut : 30)
- `fonds_caisse_monthly_amount` — montant fixe de cotisation Fonds de Caisse par membre par séance (ex: 1000 XAF)
- `savings_enabled` — true | false — active le module épargne pour l'association (défaut : false pour tontine_group)
- `cycle_start_month` — mois de démarrage de l'exercice (1=janvier, défaut : 1)
- `loan_interest_distribution` — pourcentage des intérêts reversés aux épargnants (défaut : 1.0 = 100%)

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
label           VARCHAR(100) NOT NULL              -- ex: "Exercice 2026"
start_date      DATE NOT NULL
end_date        DATE NOT NULL
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
caisse_balance              DECIMAL(15,2) DEFAULT 0          -- cumul caisse (session_auction)
auto_renew                  TINYINT(1) DEFAULT 1             -- reconduction tacite
max_cycles                  INT UNSIGNED NULL                -- NULL = illimité
current_cycle               INT UNSIGNED DEFAULT 1
moderateur_id               BIGINT UNSIGNED NULL FK → users.id  -- modérateur désigné de la tontine
start_date                  DATE NOT NULL
end_date                    DATE NULL
max_members                 INT UNSIGNED NULL
session_deadline_time       TIME DEFAULT '23:59:00'             -- heure limite de paiement le jour de la session
timezone                    VARCHAR(50) NULL                    -- fuseau horaire (NULL = hérite de l'association)
status                      ENUM('draft','active','completed','cancelled') DEFAULT 'draft'
created_by                  BIGINT UNSIGNED FK → users.id
created_at                  DATETIME
updated_at                  DATETIME
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
cycle_number        INT UNSIGNED NOT NULL DEFAULT 1   -- numéro du cycle auquel appartient cette session
session_number      INT NOT NULL                      -- numéro de séance dans le cycle
session_date        DATE NOT NULL
total_collected     DECIMAL(15,2) DEFAULT 0
auction_winning_bid DECIMAL(15,2) NULL    -- montant adjugé par le gagnant de l'enchère (session_auction)
caisse_contribution DECIMAL(15,2) NULL    -- montant effectivement versé en caisse (= auction_winning_bid ; séparé pour traçabilité)
status              ENUM('pending','open','auction','closed') DEFAULT 'pending'
notes               TEXT NULL
opened_at           DATETIME NULL
closed_at           DATETIME NULL
UNIQUE(tontine_id, cycle_number, session_number)
```

### `tontine_session_beneficiaries` — Bénéficiaires par séance (supporte N bénéficiaires)
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
session_id      BIGINT UNSIGNED FK → tontine_sessions.id
tontine_id      BIGINT UNSIGNED FK → tontines.id
member_id       BIGINT UNSIGNED FK → users.id
slot_number     INT NOT NULL              -- position du slot dans la séance (1, 2, ...)
amount_received DECIMAL(15,2) NULL        -- cagnotte / beneficiaries_per_session
received_at     DATETIME NULL
UNIQUE(session_id, slot_number)
```

### `contributions`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
session_id      BIGINT UNSIGNED FK → tontine_sessions.id
tontine_id      BIGINT UNSIGNED FK → tontines.id
member_id       BIGINT UNSIGNED FK → users.id
amount_due      DECIMAL(15,2) NOT NULL
amount_paid     DECIMAL(15,2) DEFAULT 0
penalty         DECIMAL(15,2) DEFAULT 0
status          ENUM('pending','partial','paid','late') DEFAULT 'pending'
paid_at         DATETIME NULL
payment_method  VARCHAR(50) NULL
recorded_by     BIGINT UNSIGNED FK → users.id NULL
created_at      DATETIME
updated_at      DATETIME
UNIQUE(session_id, member_id)
```

---

### `loans`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
association_id  BIGINT UNSIGNED FK → associations.id
member_id       BIGINT UNSIGNED FK → users.id
cycle_id        BIGINT UNSIGNED FK → association_cycles.id   -- cycle auquel appartient ce prêt
amount          DECIMAL(15,2) NOT NULL
interest_rate   DECIMAL(5,4) NOT NULL   -- taux par PÉRIODE de prêt (ex: 0.0700 = 7%/trimestre, PAS annualisé) — fixé depuis association_settings(loan_max_rate)
interest_type   ENUM('simple','compound') NOT NULL
duration_months INT NOT NULL
original_amount DECIMAL(15,2) NOT NULL   -- montant initial du prêt (conservé lors des reconductions)
renewal_count   INT UNSIGNED DEFAULT 0   -- nombre de fois recondu
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
tontine_member_id    BIGINT UNSIGNED NULL FK → tontine_members.id -- si type = tontine_share ou savings
-- Contrainte Service : guarantor_user_id requis si type=member, tontine_member_id requis si type=tontine_share|savings
amount_pledged       DECIMAL(15,2) NULL
status               ENUM('pending','confirmed','released') DEFAULT 'pending'
confirmed_at         DATETIME NULL
created_at           DATETIME
updated_at           DATETIME
```

### `loan_repayments`
```sql
id              BIGINT UNSIGNED PK AUTO_INCREMENT
loan_id         BIGINT UNSIGNED FK → loans.id
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
type                ENUM('deposit','withdrawal','interest_payout','fonds_caisse') NOT NULL
-- deposit          : dépôt d'épargne variable (séance courante)
-- withdrawal       : retrait capital en fin de cycle
-- interest_payout  : versement de la part d'intérêts en fin de cycle
-- fonds_caisse     : cotisation mensuelle fixe (frais de fonctionnement, non inclus pro-rata intérêts)
amount              DECIMAL(15,2) NOT NULL
balance_after       DECIMAL(15,2) NOT NULL
session_date        DATE NOT NULL              -- date de la séance ou opération
tontine_session_id  BIGINT UNSIGNED NULL FK → tontine_sessions.id  -- si lié à une séance tontine
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
amount          DECIMAL(15,2) NOT NULL
contribution_date DATE NOT NULL
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
