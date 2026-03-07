<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Libraries\JwtLibrary;
use App\Libraries\SmsLibrary;
use App\Modules\Auth\Entities\UserEntity;
use App\Modules\Auth\Models\UserModel;
use Config\Auth as AuthConfig;

/**
 * Service d'authentification global (non tenant-scoped).
 *
 * Gère : inscription, vérification OTP, connexion, refresh token,
 * logout, reset de mot de passe, profil utilisateur, switch d'association.
 *
 * N'étend PAS BaseService car AuthService est transversal (multi-association).
 * La logique métier est ici ; le contrôleur ne fait que valider l'entrée
 * et formater la réponse.
 */
class AuthService
{
    /**
     * @var JwtLibrary
     */
    private JwtLibrary $jwt;

    /**
     * @var SmsLibrary
     */
    private SmsLibrary $sms;

    /**
     * @var UserModel
     */
    private UserModel $userModel;

    /**
     * @var AuthConfig
     */
    private AuthConfig $config;

    public function __construct()
    {
        $this->jwt       = new JwtLibrary();
        $this->sms       = new SmsLibrary();
        $this->userModel = new UserModel();
        $this->config    = config(AuthConfig::class);
    }

    // -------------------------------------------------------------------------
    // Helpers internes
    // -------------------------------------------------------------------------

    /**
     * Retourne la date et l'heure courante en UTC au format MySQL.
     *
     * @return string 'Y-m-d H:i:s' UTC
     */
    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Génère un UUID v4 conforme à la RFC 4122.
     *
     * @return string UUID v4 (ex: 550e8400-e29b-41d4-a716-446655440000)
     */
    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Récupère la première association active d'un utilisateur.
     *
     * @param int $userId Identifiant de l'utilisateur
     *
     * @return array<string, mixed>|null Enregistrement association_members ou null
     */
    private function getFirstMembership(int $userId): ?array
    {
        $db = \Config\Database::connect();

        return $db->table('association_members am')
            ->select('am.association_id, am.effective_role, a.name as association_name')
            ->join('associations a', 'a.id = am.association_id')
            ->where('am.user_id', $userId)
            ->where('am.is_active', 1)
            ->where('am.left_at IS NULL')
            ->orderBy('am.joined_at', 'ASC')
            ->get()
            ->getRowArray();
    }

    /**
     * Récupère toutes les associations actives d'un utilisateur.
     *
     * @param int $userId Identifiant de l'utilisateur
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAllMemberships(int $userId): array
    {
        $db = \Config\Database::connect();

        return $db->table('association_members am')
            ->select('am.association_id, am.effective_role, a.name as association_name, am.joined_at')
            ->join('associations a', 'a.id = am.association_id')
            ->where('am.user_id', $userId)
            ->where('am.is_active', 1)
            ->where('am.left_at IS NULL')
            ->orderBy('am.joined_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Construit la réponse tokens complète pour un utilisateur.
     *
     * @param UserEntity               $user       Entité utilisateur
     * @param array<string, mixed>|null $membership Membership actif ou null
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}
     */
    private function buildTokenResponse(UserEntity $user, ?array $membership): array
    {
        $associationId = $membership ? (int) $membership['association_id'] : null;
        $role          = $membership ? (string) $membership['effective_role'] : null;

        $accessData  = $this->jwt->generateAccessToken(
            (int) $user->id,
            (string) $user->uuid,
            (string) $user->phone,
            $associationId,
            $role,
            (bool) $user->is_super_admin
        );

        $refreshData = $this->jwt->generateRefreshToken((int) $user->id, $accessData['jti']);

        return [
            'access_token'  => $accessData['token'],
            'refresh_token' => $refreshData['raw_token'],
            'expires_in'    => $this->config->accessTokenTtl,
            'user'          => $user->toPublicArray(),
        ];
    }

    // -------------------------------------------------------------------------
    // Inscription
    // -------------------------------------------------------------------------

    /**
     * Enregistre un nouveau compte utilisateur.
     *
     * Processus :
     * 1. Vérifie que le numéro de téléphone n'est pas déjà utilisé
     * 2. Crée l'utilisateur (is_active=0, en attente de vérification OTP)
     * 3. Envoie l'OTP de vérification par SMS
     *
     * @param array<string, mixed> $data Données : first_name, last_name, phone, password, email (optionnel)
     *
     * @throws \RuntimeException        Si le numéro de téléphone est déjà utilisé
     * @throws \InvalidArgumentException Si des données obligatoires sont manquantes
     *
     * @return array{message: string, phone: string}
     */
    public function register(array $data): array
    {
        $phone = (string) ($data['phone'] ?? '');

        if ($phone === '') {
            throw new \InvalidArgumentException('Le numéro de téléphone est obligatoire.');
        }

        // Vérifier l'unicité du téléphone
        $existing = $this->userModel->findByPhone($phone);
        if ($existing !== null) {
            throw new \RuntimeException('Ce numéro de téléphone est déjà utilisé.');
        }

        // Créer l'utilisateur (désactivé jusqu'à vérification OTP)
        $user = new UserEntity();
        $user->fill([
            'uuid'       => $this->generateUuid(),
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name'  => (string) ($data['last_name'] ?? ''),
            'phone'      => $phone,
            'email'      => ($data['email'] ?? null) ?: null,
            'is_active'  => 0,
            'is_super_admin' => 0,
        ]);
        $user->setPassword((string) ($data['password'] ?? ''));

        $inserted = $this->userModel->save($user);
        if (!$inserted) {
            throw new \RuntimeException('Impossible de créer le compte : ' . implode(', ', $this->userModel->errors()));
        }

        // Envoyer l'OTP d'activation
        $this->sms->sendOtp($phone, 'register');

        return [
            'message' => 'Un code de vérification a été envoyé par SMS.',
            'phone'   => $phone,
        ];
    }

    // -------------------------------------------------------------------------
    // Vérification OTP d'activation
    // -------------------------------------------------------------------------

    /**
     * Vérifie l'OTP d'activation et active le compte.
     *
     * Processus :
     * 1. Vérifie le code OTP via SmsLibrary
     * 2. Active le compte (is_active=1, phone_verified_at=now)
     * 3. Récupère les memberships existants (si l'utilisateur a été invité)
     * 4. Génère et retourne les tokens JWT
     *
     * @param string $phone Numéro de téléphone E.164
     * @param string $otp   Code OTP à 6 chiffres
     *
     * @throws \RuntimeException Si l'OTP est invalide ou expiré
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}
     */
    public function verifyPhone(string $phone, string $otp): array
    {
        $user = $this->userModel->findByPhone($phone);

        // Message générique — évite l'énumération de comptes (compte inexistant = même réponse qu'OTP invalide)
        if ($user === null || (bool) $user->is_active) {
            throw new \RuntimeException('Code OTP invalide ou expiré.');
        }

        // Vérifier l'OTP (lève RuntimeException si trop de tentatives)
        $valid = $this->sms->verifyOtp($phone, 'register', $otp);
        if (!$valid) {
            throw new \RuntimeException('Code OTP invalide ou expiré.');
        }

        // Activer le compte
        $this->userModel->update((int) $user->id, [
            'is_active'          => 1,
            'phone_verified_at'  => $this->now(),
        ]);

        // Recharger l'entité mise à jour
        /** @var UserEntity $user */
        $user = $this->userModel->find((int) $user->id);

        $membership = $this->getFirstMembership((int) $user->id);

        return $this->buildTokenResponse($user, $membership);
    }

    // -------------------------------------------------------------------------
    // Renvoi d'OTP
    // -------------------------------------------------------------------------

    /**
     * Renvoie un OTP SMS avec rate limiting.
     *
     * Limite : 3 renvois en 15 minutes par numéro.
     * Clé Redis : "otp:resend:{phone}"
     *
     * @param string $phone   Numéro de téléphone E.164
     * @param string $purpose Contexte : 'register' | 'login' | 'reset'
     *
     * @throws \RuntimeException Si le rate limit est dépassé ou si l'utilisateur n'existe pas
     *
     * @return array{message: string, phone: string}
     */
    public function resendOtp(string $phone, string $purpose = 'register'): array
    {
        // Rate limiting : max 3 renvois en 15 min
        $resendKey   = "otp:resend:{$phone}";
        $resendCount = (int) (cache()->get($resendKey) ?? 0);

        if ($resendCount >= 3) {
            throw new \RuntimeException('Trop de renvois. Veuillez attendre 15 minutes.');
        }

        // Rechercher l'utilisateur — réponse générique si inexistant (anti-énumération)
        $user = $this->userModel->findByPhone($phone);
        if ($user === null) {
            // Incrémenter quand même pour éviter un oracle de timing
            cache()->save($resendKey, $resendCount + 1, 900);

            return [
                'message' => 'Code de vérification renvoyé par SMS.',
                'phone'   => $phone,
            ];
        }

        // Incrémenter le compteur de renvois (TTL = 15 min)
        cache()->save($resendKey, $resendCount + 1, 900);

        $this->sms->sendOtp($phone, $purpose);

        return [
            'message' => 'Code de vérification renvoyé par SMS.',
            'phone'   => $phone,
        ];
    }

    // -------------------------------------------------------------------------
    // Connexion
    // -------------------------------------------------------------------------

    /**
     * Connexion standard par identifiant + mot de passe.
     *
     * Processus :
     * 1. Recherche l'utilisateur par téléphone (prioritaire) ou email
     * 2. Vérifie le mot de passe et le statut actif du compte
     * 3. Récupère la première association active (ou null si aucune)
     * 4. Génère les tokens JWT scopés
     *
     * @param string $identifier Numéro de téléphone ou adresse email
     * @param string $password   Mot de passe en clair
     *
     * @throws \RuntimeException Si les identifiants sont invalides ou le compte inactif
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>, associations: list<array<string, mixed>>}
     */
    public function login(string $identifier, string $password): array
    {
        // Rate limiting : max 10 tentatives par identifiant en 15 minutes
        $loginKey = 'login:attempts:' . hash('sha256', $identifier);
        $attempts = (int) (cache()->get($loginKey) ?? 0);
        if ($attempts >= 10) {
            throw new \RuntimeException('Trop de tentatives de connexion. Réessayez dans 15 minutes.');
        }

        $user = $this->userModel->findByPhoneOrEmail($identifier);

        if ($user === null || !$user->verifyPassword($password)) {
            // Incrémenter le compteur d'échecs (TTL = 15 min)
            cache()->save($loginKey, $attempts + 1, 900);
            throw new \RuntimeException('Identifiant ou mot de passe incorrect.');
        }

        if (!(bool) $user->is_active) {
            throw new \RuntimeException('Ce compte n\'est pas encore activé. Vérifiez votre téléphone.');
        }

        $membership    = $this->getFirstMembership((int) $user->id);
        $associations  = $this->getAllMemberships((int) $user->id);
        $tokenResponse = $this->buildTokenResponse($user, $membership);

        return array_merge($tokenResponse, ['associations' => $associations]);
    }

    // -------------------------------------------------------------------------
    // Connexion OTP (sans mot de passe)
    // -------------------------------------------------------------------------

    /**
     * Demande un OTP de connexion sans mot de passe.
     *
     * @param string $phone Numéro de téléphone E.164
     *
     * @throws \RuntimeException Si l'utilisateur n'existe pas ou compte inactif
     *
     * @return array{message: string, phone: string}
     */
    public function requestLoginOtp(string $phone): array
    {
        $user = $this->userModel->findByPhone($phone);

        // Réponse générique si compte inexistant ou inactif — anti-énumération
        if ($user === null || !(bool) $user->is_active) {
            return [
                'message' => 'Si ce numéro est enregistré, un code de connexion a été envoyé.',
                'phone'   => $phone,
            ];
        }

        $this->sms->sendOtp($phone, 'login');

        return [
            'message' => 'Code de connexion envoyé par SMS.',
            'phone'   => $phone,
        ];
    }

    /**
     * Vérifie l'OTP de connexion et retourne les tokens JWT.
     *
     * @param string $phone Numéro de téléphone E.164
     * @param string $otp   Code OTP à 6 chiffres
     *
     * @throws \RuntimeException Si l'OTP est invalide ou l'utilisateur introuvable
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}
     */
    public function verifyLoginOtp(string $phone, string $otp): array
    {
        $user = $this->userModel->findByPhone($phone);
        // Message générique — anti-énumération
        if ($user === null) {
            throw new \RuntimeException('Code OTP invalide ou expiré.');
        }

        $valid = $this->sms->verifyOtp($phone, 'login', $otp);
        if (!$valid) {
            throw new \RuntimeException('Code OTP invalide ou expiré.');
        }

        $membership = $this->getFirstMembership((int) $user->id);

        return $this->buildTokenResponse($user, $membership);
    }

    // -------------------------------------------------------------------------
    // Refresh Token
    // -------------------------------------------------------------------------

    /**
     * Rafraîchit un couple access/refresh token.
     *
     * Processus :
     * 1. Lookup du refresh token en DB par hash SHA-256
     * 2. Révocation de l'ancien refresh token (rotation)
     * 3. Génération d'un nouveau couple access + refresh token
     *
     * @param string $rawRefreshToken Token refresh brut transmis par le client
     *
     * @throws \RuntimeException Si le refresh token est invalide, révoqué ou expiré
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function refreshToken(string $rawRefreshToken): array
    {
        $record = $this->jwt->findValidRefreshToken($rawRefreshToken);
        if ($record === null) {
            throw new \RuntimeException('Refresh token invalide ou expiré.');
        }

        $userId = (int) $record['user_id'];

        /** @var UserEntity|null $user */
        $user = $this->userModel->find($userId);
        if ($user === null || !(bool) $user->is_active) {
            throw new \RuntimeException('Compte introuvable ou désactivé.');
        }

        // Révoquer l'ancien refresh token (rotation)
        $this->jwt->revokeRefreshToken((string) $record['token_hash']);

        // Récupérer le membership actif
        $membership = $this->getFirstMembership($userId);

        $associationId = $membership ? (int) $membership['association_id'] : null;
        $role          = $membership ? (string) $membership['effective_role'] : null;

        $accessData  = $this->jwt->generateAccessToken(
            $userId,
            (string) $user->uuid,
            (string) $user->phone,
            $associationId,
            $role,
            (bool) $user->is_super_admin
        );

        $refreshData = $this->jwt->generateRefreshToken($userId, $accessData['jti']);

        return [
            'access_token'  => $accessData['token'],
            'refresh_token' => $refreshData['raw_token'],
            'expires_in'    => $this->config->accessTokenTtl,
        ];
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    /**
     * Déconnecte l'utilisateur.
     *
     * 1. Blackliste l'access token dans Redis (TTL = durée restante)
     * 2. Révoque le refresh token en DB
     *
     * @param string $accessToken    Token JWT brut
     * @param string $jti            JWT ID (claim jti du payload)
     * @param int    $accessTokenExp Timestamp Unix d'expiration de l'access token
     * @param string $refreshToken   Token refresh brut
     */
    public function logout(
        string $accessToken,
        string $jti,
        int $accessTokenExp,
        string $refreshToken
    ): void {
        // Blacklister l'access token
        $this->jwt->blacklistAccessToken($jti, $accessTokenExp);

        // Révoquer le refresh token
        $tokenHash = hash('sha256', $refreshToken);
        $this->jwt->revokeRefreshToken($tokenHash);
    }

    // -------------------------------------------------------------------------
    // Réinitialisation de mot de passe
    // -------------------------------------------------------------------------

    /**
     * Initie un reset de mot de passe par SMS (primaire) ou email (secondaire).
     *
     * Génère un token opaque unique, le stocke en DB avec TTL,
     * et envoie le code par SMS (si téléphone trouvé).
     *
     * @param string $identifier Numéro de téléphone ou adresse email
     *
     * @throws \RuntimeException Si aucun compte n'est trouvé avec cet identifiant
     *
     * @return array{message: string}
     */
    public function forgotPassword(string $identifier): array
    {
        $user = $this->userModel->findByPhoneOrEmail($identifier);

        // Réponse identique qu'un compte existe ou non — anti-énumération
        if ($user === null) {
            return ['message' => 'Si ce compte existe, un code de réinitialisation a été envoyé.'];
        }

        // Canal SMS : OTP 6 chiffres (hashé dans Redis, TTL = otpTtl)
        // Avantage : code court mémorisable, même mécanisme sécurisé que les autres OTP
        if ($user->phone !== null) {
            $this->sms->sendOtp((string) $user->phone, 'reset');
        }

        // Canal email : TODO — intégrer MailLibrary quand disponible
        // (token long via password_resets pour les liens email)

        return ['message' => 'Si ce compte existe, un code de réinitialisation a été envoyé.'];
    }

    /**
     * Réinitialise le mot de passe via OTP SMS (code 6 chiffres).
     *
     * Cohérent avec le flow forgotPassword qui utilise sendOtp('reset').
     * L'OTP est stocké hashé dans Redis (pas en DB) — même mécanisme que
     * les autres flows OTP du projet.
     *
     * @param string $phone       Numéro de téléphone E.164
     * @param string $otp         Code OTP à 6 chiffres reçu par SMS
     * @param string $newPassword Nouveau mot de passe en clair (min 8 caractères)
     *
     * @throws \RuntimeException Si le code OTP est invalide ou expiré
     *
     * @return array{message: string}
     */
    public function resetPassword(string $phone, string $otp, string $newPassword): array
    {
        // Message générique — anti-énumération (numéro inexistant = même réponse qu'OTP invalide)
        $user = $this->userModel->findByPhone($phone);
        if ($user === null) {
            throw new \RuntimeException('Code OTP invalide ou expiré.');
        }

        // Vérifier l'OTP via SmsLibrary (hash + tentatives + TTL Redis)
        $valid = $this->sms->verifyOtp($phone, 'reset', $otp);
        if (!$valid) {
            throw new \RuntimeException('Code OTP invalide ou expiré.');
        }

        // Mettre à jour le mot de passe
        $entity = new UserEntity();
        $entity->setPassword($newPassword);

        $this->userModel->update((int) $user->id, [
            'password' => $entity->password,
        ]);

        return ['message' => 'Mot de passe réinitialisé avec succès.'];
    }

    // -------------------------------------------------------------------------
    // Profil utilisateur
    // -------------------------------------------------------------------------

    /**
     * Retourne le profil complet de l'utilisateur courant.
     *
     * @param int $userId Identifiant de l'utilisateur (extrait du JWT)
     *
     * @throws \RuntimeException Si l'utilisateur n'est pas trouvé
     *
     * @return array{user: array<string, mixed>, associations: list<array<string, mixed>>}
     */
    public function getMe(int $userId): array
    {
        /** @var UserEntity|null $user */
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        $associations = $this->getAllMemberships($userId);

        return [
            'user'         => $user->toPublicArray(),
            'associations' => $associations,
        ];
    }

    /**
     * Met à jour le profil de l'utilisateur courant.
     *
     * Champs modifiables : first_name, last_name, email, avatar.
     * Le numéro de téléphone ne peut PAS être changé via cet endpoint.
     *
     * @param int                  $userId Identifiant de l'utilisateur (extrait du JWT)
     * @param array<string, mixed> $data   Données à mettre à jour
     *
     * @throws \RuntimeException Si l'utilisateur n'est pas trouvé ou si la mise à jour échoue
     *
     * @return array{user: array<string, mixed>}
     */
    public function updateMe(int $userId, array $data): array
    {
        /** @var UserEntity|null $user */
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        // Filtrer les champs autorisés (pas de phone, password, is_active, etc.)
        $allowed = ['first_name', 'last_name', 'email', 'avatar'];
        $update  = array_intersect_key($data, array_flip($allowed));

        if (empty($update)) {
            return ['user' => $user->toPublicArray()];
        }

        $success = $this->userModel->update($userId, $update);
        if (!$success) {
            throw new \RuntimeException(
                'Mise à jour échouée : ' . implode(', ', $this->userModel->errors())
            );
        }

        /** @var UserEntity $updated */
        $updated = $this->userModel->find($userId);

        return ['user' => $updated->toPublicArray()];
    }

    // -------------------------------------------------------------------------
    // Switch d'association
    // -------------------------------------------------------------------------

    /**
     * Change l'association active de l'utilisateur et génère de nouveaux tokens.
     *
     * Processus :
     * 1. Vérifie que l'utilisateur est bien membre actif de l'association cible
     * 2. Blackliste l'access token courant
     * 3. Révoque le refresh token courant
     * 4. Génère de nouveaux tokens scopés sur la nouvelle association
     *
     * @param int    $userId                 Identifiant de l'utilisateur
     * @param int    $targetAssociationId     Association vers laquelle switcher
     * @param string $currentAccessToken      Token JWT courant (à invalider)
     * @param string $currentJti              JTI de l'access token courant
     * @param int    $currentAccessTokenExp   Timestamp d'expiration de l'access token courant
     * @param string $currentRefreshToken     Refresh token courant (à révoquer)
     *
     * @throws \RuntimeException Si l'utilisateur n'est pas membre de l'association cible
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, association: array<string, mixed>}
     */
    public function switchAssociation(
        int $userId,
        int $targetAssociationId,
        string $currentAccessToken,
        string $currentJti,
        int $currentAccessTokenExp,
        string $currentRefreshToken
    ): array {
        $db = \Config\Database::connect();

        // Vérifier le membership
        $membership = $db->table('association_members am')
            ->select('am.association_id, am.effective_role, a.name as association_name')
            ->join('associations a', 'a.id = am.association_id')
            ->where('am.user_id', $userId)
            ->where('am.association_id', $targetAssociationId)
            ->where('am.is_active', 1)
            ->where('am.left_at IS NULL')
            ->get()
            ->getRowArray();

        if ($membership === null) {
            throw new \RuntimeException('Vous n\'êtes pas membre de cette association.');
        }

        /** @var UserEntity|null $user */
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        // Invalider les tokens courants
        $this->jwt->blacklistAccessToken($currentJti, $currentAccessTokenExp);
        $tokenHash = hash('sha256', $currentRefreshToken);
        $this->jwt->revokeRefreshToken($tokenHash);

        // Générer nouveaux tokens scopés sur la nouvelle association
        $accessData  = $this->jwt->generateAccessToken(
            $userId,
            (string) $user->uuid,
            (string) $user->phone,
            (int) $membership['association_id'],
            (string) $membership['effective_role'],
            (bool) $user->is_super_admin
        );

        $refreshData = $this->jwt->generateRefreshToken($userId, $accessData['jti']);

        return [
            'access_token'  => $accessData['token'],
            'refresh_token' => $refreshData['raw_token'],
            'expires_in'    => $this->config->accessTokenTtl,
            'association'   => [
                'id'   => $membership['association_id'],
                'name' => $membership['association_name'],
                'role' => $membership['effective_role'],
            ],
        ];
    }
}
