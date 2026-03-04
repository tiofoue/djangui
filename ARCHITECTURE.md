# Architecture Technique — Djangui

## Vue d'ensemble

```
┌─────────────────────────────────────────────────────┐
│                   CLIENTS                           │
│   Vue 3 (Web)          Flutter (Mobile)             │
└──────────────┬──────────────────┬───────────────────┘
               │   HTTPS/JSON     │
┌──────────────▼──────────────────▼───────────────────┐
│              API REST (CI4 — PHP 8.2)               │
│                                                     │
│  Auth  │  Associations  │  Bureau   │  Members      │
│Tontines│  Loans         │ Solidarity│  Documents    │
│                   Notifications                     │
└──────────────┬──────────────────┬───────────────────┘
               │                  │
       ┌───────▼───────┐  ┌───────▼───────┐
       │   MySQL 8.0   │  │     Redis     │
       │  (données +   │  │  (OTP cache + │
       │refresh_tokens)│  │JWT blacklist) │
       └───────────────┘  └───────────────┘
```

## Structure des dossiers

```
djangui/
├── app/
│   ├── Config/
│   │   ├── App.php
│   │   ├── Auth.php          # Config JWT, durées tokens
│   │   ├── Database.php
│   │   └── Routes.php        # Routes globales → délèguent aux modules
│   ├── Filters/
│   │   ├── AuthFilter.php               # Vérification JWT
│   │   ├── RoleFilter.php               # Vérification effective_role (president = treasurer implicite pour tontine_group)
│   │   └── TontineModeratorFilter.php   # Vérification modérateur tontine (tontine-scoped)
│   ├── Modules/
│   │   ├── Auth/
│   │   │   ├── Controllers/AuthController.php
│   │   │   ├── Models/UserModel.php
│   │   │   ├── Services/AuthService.php
│   │   │   ├── Entities/UserEntity.php
│   │   │   └── Config/Routes.php
│   │   ├── Associations/
│   │   │   ├── Controllers/AssociationController.php
│   │   │   ├── Controllers/SettingsController.php
│   │   │   ├── Models/AssociationModel.php
│   │   │   ├── Models/AssociationSettingModel.php
│   │   │   ├── Services/AssociationService.php
│   │   │   ├── Entities/AssociationEntity.php
│   │   │   └── Config/Routes.php
│   │   ├── Bureau/
│   │   │   ├── Controllers/BureauPositionController.php
│   │   │   ├── Controllers/BureauTermController.php
│   │   │   ├── Controllers/BureauSubstitutionController.php
│   │   │   ├── Controllers/ElectionController.php
│   │   │   ├── Models/BureauPositionModel.php
│   │   │   ├── Models/BureauTermModel.php
│   │   │   ├── Models/BureauSubstitutionModel.php
│   │   │   ├── Models/ElectionModel.php
│   │   │   ├── Models/ElectionPositionModel.php       # table election_positions
│   │   │   ├── Models/ElectionCandidateModel.php
│   │   │   ├── Models/ElectionVoteModel.php
│   │   │   ├── Services/BureauService.php
│   │   │   ├── Services/ElectionService.php
│   │   │   └── Config/Routes.php
│   │   ├── Members/
│   │   │   ├── Controllers/MemberController.php
│   │   │   ├── Controllers/InvitationController.php
│   │   │   ├── Controllers/MeController.php          # GET /me/overview (dashboard cross-associations)
│   │   │   ├── Models/AssociationMemberModel.php
│   │   │   ├── Models/InvitationModel.php
│   │   │   ├── Services/MemberService.php
│   │   │   └── Config/Routes.php
│   │   ├── Tontines/
│   │   │   ├── Controllers/TontineController.php
│   │   │   ├── Controllers/SessionController.php
│   │   │   ├── Controllers/ContributionController.php
│   │   │   ├── Controllers/BidController.php          # PUT /tontines/{tId}/members/me/bid (mode bidding) + POST/GET /sessions/{sId}/bids (mode session_auction)
│   │   │   ├── Models/TontineModel.php
│   │   │   ├── Models/TontineMemberModel.php
│   │   │   ├── Models/TontineSessionModel.php
│   │   │   ├── Models/ContributionModel.php
│   │   │   ├── Models/BidModel.php
│   │   │   ├── Services/TontineService.php
│   │   │   ├── Services/RotationService.php
│   │   │   └── Config/Routes.php
│   │   ├── Loans/
│   │   │   ├── Controllers/LoanController.php
│   │   │   ├── Controllers/RepaymentController.php
│   │   │   ├── Models/LoanModel.php
│   │   │   ├── Models/LoanGuaranteeModel.php
│   │   │   ├── Models/LoanRepaymentModel.php
│   │   │   ├── Services/LoanService.php
│   │   │   ├── Services/InterestCalculator.php
│   │   │   └── Config/Routes.php
│   │   ├── Solidarity/
│   │   │   ├── Controllers/SolidarityController.php
│   │   │   ├── Controllers/FundraisingController.php  # Main levée (collecte ponctuelle)
│   │   │   ├── Models/SolidarityFundModel.php
│   │   │   ├── Models/SolidarityContributionModel.php
│   │   │   ├── Models/SolidarityRequestModel.php
│   │   │   ├── Models/FundraisingModel.php
│   │   │   ├── Models/FundraisingContributionModel.php
│   │   │   ├── Services/SolidarityService.php
│   │   │   ├── Services/FundraisingService.php
│   │   │   └── Config/Routes.php
│   │   ├── Documents/
│   │   │   ├── Controllers/DocumentController.php
│   │   │   ├── Models/DocumentModel.php
│   │   │   ├── Services/DocumentService.php
│   │   │   └── Config/Routes.php
│   │   └── Notifications/
│   │       ├── Services/NotificationService.php
│   │       ├── Services/EmailService.php
│   │       ├── Services/SmsService.php               # Notifications SMS (s'appuie sur SmsLibrary)
│   │       └── Services/PushService.php
│   ├── Common/
│   │   ├── BaseController.php   # Réponses JSON standardisées
│   │   ├── BaseModel.php        # Scoping multi-tenant
│   │   └── BaseService.php
│   ├── Database/
│   │   ├── Migrations/          # Une migration par table
│   │   └── Seeds/
│   │       ├── DemoSeeder.php
│   │       └── AssociationSeeder.php
│   ├── Libraries/
│   │   ├── JwtLibrary.php
│   │   ├── SmsLibrary.php    # Africa's Talking — envoi OTP et invitations SMS
│   │   └── FileUpload.php
│   └── Commands/
│       ├── OpenDueSessions.php     # Job planifié : pending → open/auction au matin de session_date
│       └── CheckLoanDefaults.php   # Job planifié : active → defaulted si retard > loan_default_delay_days
├── public/
│   └── uploads/
│       ├── associations/        # Logos (public)
│       └── documents/           # Documents publics uniquement
├── writable/
│   └── uploads/
│       └── documents/           # Documents privés (hors webroot, accès via CI4 stream)
├── tests/
│   ├── Unit/
│   └── Feature/
├── docs/
│   ├── TODO.md
│   ├── DONE.md
│   ├── DATABASE.md
│   ├── API.md
│   ├── MODULES.md
│   └── BUSINESS_RULES.md
├── CLAUDE.md
├── ARCHITECTURE.md
├── ROADMAP.md
├── .env.example
├── .gitignore
├── composer.json
└── phpunit.xml
```

## Flux d'authentification (JWT)

```
Client → POST /api/auth/login
       ← { access_token, refresh_token, user }

Client → GET /api/associations (Header: Authorization: Bearer <token>)
       → AuthFilter vérifie JWT
       → RoleFilter vérifie rôle si nécessaire
       ← données
```

## Format de réponse API standard

```json
// Succès
{
  "status": "success",
  "data": { ... },
  "message": "Operation successful"
}

// Erreur
{
  "status": "error",
  "errors": { "field": "message" },
  "message": "Validation failed"
}

// Liste paginée
{
  "status": "success",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

## Multi-tenant

- Chaque requête authentifiée porte un `association_id` (résolu depuis le JWT ou le paramètre de route)
- Le `BaseModel` scope automatiquement les requêtes sur `association_id`
- Un utilisateur peut switcher d'association via `POST /api/auth/switch-association`

## Sécurité

- JWT avec expiration courte (access: 15min, refresh: 7j)
- Refresh tokens stockés en DB (table `refresh_tokens`) — persistance garantie même si Redis redémarre
- Blacklist access tokens dans Redis à la déconnexion (TTL = durée restante du token)
- Rate limiting sur les endpoints sensibles
- Validation stricte de toutes les entrées (CI4 Validation)
- CORS configuré (whitelist frontend + mobile)
- Uploads : validation type MIME + taille + stockage hors public/ si sensible
