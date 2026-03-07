<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration du module Auth — JWT, OTP, Reset de mot de passe.
 *
 * Toutes les valeurs sensibles (jwtSecret) sont lues depuis le fichier .env.
 * Ne jamais commiter de secrets dans ce fichier.
 */
class Auth extends BaseConfig
{
    /**
     * Secret HMAC pour la signature des JWT.
     * Lire depuis .env : JWT_SECRET=<votre_secret_64_chars>
     *
     * @var string
     */
    public string $jwtSecret = '';

    /**
     * Durée de validité de l'access token JWT (secondes).
     * Défaut : 15 minutes.
     *
     * @var int
     */
    public int $accessTokenTtl = 900;

    /**
     * Durée de validité du refresh token (secondes).
     * Défaut : 7 jours.
     *
     * @var int
     */
    public int $refreshTokenTtl = 604800;

    /**
     * Durée de validité d'un OTP SMS (secondes).
     * Défaut : 10 minutes.
     *
     * @var int
     */
    public int $otpTtl = 600;

    /**
     * Nombre de chiffres du code OTP.
     *
     * @var int
     */
    public int $otpLength = 6;

    /**
     * Nombre maximal de tentatives OTP avant blocage du compte.
     *
     * @var int
     */
    public int $otpMaxAttempts = 5;

    /**
     * Durée du blocage après dépassement des tentatives OTP (secondes).
     * Défaut : 15 minutes.
     *
     * @var int
     */
    public int $otpBlockDuration = 900;

    /**
     * Durée de validité d'un token de réinitialisation de mot de passe (secondes).
     * Défaut : 30 minutes.
     *
     * @var int
     */
    public int $passwordResetTtl = 1800;

    /**
     * Algorithme de signature JWT.
     *
     * @var string
     */
    public string $jwtAlgorithm = 'HS256';

    /**
     * Initialise la configuration en lisant les variables d'environnement.
     */
    public function __construct()
    {
        parent::__construct();

        // Lire le secret JWT depuis .env (obligatoire en production)
        $secret = env('JWT_SECRET', '');
        if ($secret !== '') {
            $this->jwtSecret = (string) $secret;
        }
    }
}
