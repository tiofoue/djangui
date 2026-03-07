<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Common\BaseController;
use App\Modules\Auth\Services\AuthService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur Auth — Endpoints d'authentification Djangui.
 *
 * Responsabilités :
 * - Validation des données d'entrée (CI4 Validation)
 * - Délégation de la logique métier à AuthService
 * - Formatage des réponses JSON via BaseController
 *
 * Aucune logique métier dans ce contrôleur.
 *
 * Routes :
 *   POST /api/auth/register
 *   POST /api/auth/verify-phone
 *   POST /api/auth/resend-otp
 *   POST /api/auth/login
 *   POST /api/auth/login/otp
 *   POST /api/auth/login/otp/verify
 *   POST /api/auth/refresh
 *   POST /api/auth/logout             [AUTH REQUIRED]
 *   POST /api/auth/forgot-password
 *   POST /api/auth/reset-password
 *   GET  /api/auth/me                 [AUTH REQUIRED]
 *   PUT  /api/auth/me                 [AUTH REQUIRED]
 *   POST /api/auth/switch-association [AUTH REQUIRED]
 */
class AuthController extends BaseController
{
    /**
     * @var AuthService
     */
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/register
    // -------------------------------------------------------------------------

    /**
     * Inscription d'un nouveau compte.
     *
     * Body JSON : { first_name, last_name, phone, password, email? }
     *
     * @return ResponseInterface
     */
    public function register(): ResponseInterface
    {
        $rules = [
            'first_name' => 'required|max_length[100]',
            'last_name'  => 'required|max_length[100]',
            'phone'      => 'required|max_length[20]',
            'password'   => 'required|min_length[8]',
            'email'      => 'permit_empty|valid_email|max_length[191]',
            'language'   => 'permit_empty|in_list[fr,en]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        try {
            $result = $this->authService->register($this->request->getJSON(true) ?? []);

            return $this->respondCreated($result, $result['message']);
        } catch (\InvalidArgumentException $e) {
            return $this->respondValidationError(['general' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return $this->respondConflict($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/verify-phone
    // -------------------------------------------------------------------------

    /**
     * Vérification OTP d'activation du compte.
     *
     * Body JSON : { phone, otp }
     *
     * @return ResponseInterface
     */
    public function verifyPhone(): ResponseInterface
    {
        $rules = [
            'phone' => 'required',
            'otp'   => 'required|exact_length[6]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body  = $this->request->getJSON(true) ?? [];
        $phone = (string) ($body['phone'] ?? '');
        $otp   = (string) ($body['otp'] ?? '');

        try {
            $result = $this->authService->verifyPhone($phone, $otp);

            return $this->respond($result, 200, 'Compte activé avec succès.');
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 422);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/resend-otp
    // -------------------------------------------------------------------------

    /**
     * Renvoi d'un OTP SMS.
     *
     * Body JSON : { phone, purpose? }
     *
     * @return ResponseInterface
     */
    public function resendOtp(): ResponseInterface
    {
        $rules = [
            'phone'   => 'required',
            'purpose' => 'permit_empty|in_list[register,login,reset]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body    = $this->request->getJSON(true) ?? [];
        $phone   = (string) ($body['phone'] ?? '');
        $purpose = (string) ($body['purpose'] ?? 'register');

        try {
            $result = $this->authService->resendOtp($phone, $purpose);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondTooManyRequests($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login
    // -------------------------------------------------------------------------

    /**
     * Connexion standard par identifiant + mot de passe.
     *
     * Body JSON : { identifier, password }
     *
     * @return ResponseInterface
     */
    public function login(): ResponseInterface
    {
        $rules = [
            'identifier' => 'required',
            'password'   => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body       = $this->request->getJSON(true) ?? [];
        $identifier = (string) ($body['identifier'] ?? '');
        $password   = (string) ($body['password'] ?? '');

        try {
            $result = $this->authService->login($identifier, $password);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondUnauthorized($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login/otp
    // -------------------------------------------------------------------------

    /**
     * Demande d'OTP de connexion (sans mot de passe).
     *
     * Body JSON : { phone }
     *
     * @return ResponseInterface
     */
    public function requestLoginOtp(): ResponseInterface
    {
        $rules = ['phone' => 'required'];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body  = $this->request->getJSON(true) ?? [];
        $phone = (string) ($body['phone'] ?? '');

        try {
            $result = $this->authService->requestLoginOtp($phone);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 404);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login/otp/verify
    // -------------------------------------------------------------------------

    /**
     * Vérification de l'OTP de connexion.
     *
     * Body JSON : { phone, otp }
     *
     * @return ResponseInterface
     */
    public function verifyLoginOtp(): ResponseInterface
    {
        $rules = [
            'phone' => 'required',
            'otp'   => 'required|exact_length[6]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body  = $this->request->getJSON(true) ?? [];
        $phone = (string) ($body['phone'] ?? '');
        $otp   = (string) ($body['otp'] ?? '');

        try {
            $result = $this->authService->verifyLoginOtp($phone, $otp);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondUnauthorized($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/refresh
    // -------------------------------------------------------------------------

    /**
     * Rafraîchissement des tokens (rotation du refresh token).
     *
     * Body JSON : { refresh_token }
     *
     * @return ResponseInterface
     */
    public function refreshToken(): ResponseInterface
    {
        $rules = ['refresh_token' => 'required'];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body         = $this->request->getJSON(true) ?? [];
        $refreshToken = (string) ($body['refresh_token'] ?? '');

        try {
            $result = $this->authService->refreshToken($refreshToken);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondUnauthorized($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/logout  [AUTH REQUIRED]
    // -------------------------------------------------------------------------

    /**
     * Déconnexion — blacklist l'access token et révoque le refresh token.
     *
     * Body JSON : { refresh_token }
     * Header   : Authorization: Bearer <access_token>
     *
     * @return ResponseInterface
     */
    public function logout(): ResponseInterface
    {
        $rules = ['refresh_token' => 'required'];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body         = $this->request->getJSON(true) ?? [];
        $refreshToken = (string) ($body['refresh_token'] ?? '');

        // Extraire le token brut depuis l'en-tête (AuthFilter l'a déjà validé)
        $authHeader  = $this->request->getHeaderLine('Authorization');
        $accessToken = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $accessToken = substr($authHeader, 7);
        }

        /** @var object $jwtPayload */
        $jwtPayload = AuthContext::get();
        $jti        = (string) ($jwtPayload->jti ?? '');
        $exp        = (int) ($jwtPayload->exp ?? 0);

        $this->authService->logout($accessToken, $jti, $exp, $refreshToken);

        return $this->respond(['message' => 'Déconnecté avec succès.']);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/forgot-password
    // -------------------------------------------------------------------------

    /**
     * Demande de réinitialisation de mot de passe.
     *
     * Body JSON : { identifier }  (téléphone ou email)
     *
     * @return ResponseInterface
     */
    public function forgotPassword(): ResponseInterface
    {
        $rules = ['identifier' => 'required'];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body       = $this->request->getJSON(true) ?? [];
        $identifier = (string) ($body['identifier'] ?? '');

        $result = $this->authService->forgotPassword($identifier);

        // Toujours 200 (évite l'énumération de comptes)
        return $this->respond($result);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/reset-password
    // -------------------------------------------------------------------------

    /**
     * Réinitialisation du mot de passe via OTP SMS.
     *
     * Body JSON : { phone, otp, new_password }
     *
     * @return ResponseInterface
     */
    public function resetPassword(): ResponseInterface
    {
        $rules = [
            'phone'        => 'required',
            'otp'          => 'required|exact_length[6]',
            'new_password' => 'required|min_length[8]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body        = $this->request->getJSON(true) ?? [];
        $phone       = (string) ($body['phone'] ?? '');
        $otp         = (string) ($body['otp'] ?? '');
        $newPassword = (string) ($body['new_password'] ?? '');

        try {
            $result = $this->authService->resetPassword($phone, $otp, $newPassword);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 422);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/auth/me  [AUTH REQUIRED]
    // -------------------------------------------------------------------------

    /**
     * Retourne le profil de l'utilisateur authentifié.
     *
     * @return ResponseInterface
     */
    public function me(): ResponseInterface
    {
        /** @var object $jwtPayload */
        $jwtPayload = AuthContext::get();
        $userId     = (int) ($jwtPayload->sub ?? 0);

        try {
            $result = $this->authService->getMe($userId);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondNotFound($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // PUT /api/auth/me  [AUTH REQUIRED]
    // -------------------------------------------------------------------------

    /**
     * Met à jour le profil de l'utilisateur authentifié.
     *
     * Body JSON : { first_name?, last_name?, email?, avatar? }
     *
     * @return ResponseInterface
     */
    public function updateMe(): ResponseInterface
    {
        $rules = [
            'first_name' => 'permit_empty|max_length[100]',
            'last_name'  => 'permit_empty|max_length[100]',
            'email'      => 'permit_empty|valid_email|max_length[191]',
            'avatar'     => 'permit_empty|max_length[500]',
            'language'   => 'permit_empty|in_list[fr,en]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        /** @var object $jwtPayload */
        $jwtPayload = AuthContext::get();
        $userId     = (int) ($jwtPayload->sub ?? 0);

        try {
            $result = $this->authService->updateMe($userId, $this->request->getJSON(true) ?? []);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 422);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/switch-association  [AUTH REQUIRED]
    // -------------------------------------------------------------------------

    /**
     * Change l'association active et génère de nouveaux tokens scopés.
     *
     * Body JSON : { association_id, refresh_token }
     * Header   : Authorization: Bearer <access_token>
     *
     * @return ResponseInterface
     */
    public function switchAssociation(): ResponseInterface
    {
        $rules = [
            'association_id' => 'required|integer|greater_than[0]',
            'refresh_token'  => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body          = $this->request->getJSON(true) ?? [];
        $associationId = (int) ($body['association_id'] ?? 0);
        $refreshToken  = (string) ($body['refresh_token'] ?? '');

        $authHeader  = $this->request->getHeaderLine('Authorization');
        $accessToken = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $accessToken = substr($authHeader, 7);
        }

        /** @var object $jwtPayload */
        $jwtPayload = AuthContext::get();
        $userId     = (int) ($jwtPayload->sub ?? 0);
        $jti        = (string) ($jwtPayload->jti ?? '');
        $exp        = (int) ($jwtPayload->exp ?? 0);

        try {
            $result = $this->authService->switchAssociation(
                $userId,
                $associationId,
                $accessToken,
                $jti,
                $exp,
                $refreshToken
            );

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            return $this->respondForbidden($e->getMessage());
        }
    }
}
