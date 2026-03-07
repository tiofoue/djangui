<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Auth as AuthConfig;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Bibliothèque JWT pour Djangui.
 *
 * Gère la génération, la vérification et la révocation des tokens JWT
 * (access tokens) et des refresh tokens opaques (stockés en base).
 *
 * Sécurité :
 * - Access tokens blacklistés dans Redis (cache CI4) après logout/switch
 * - Refresh tokens stockés en DB par leur hash SHA-256 uniquement
 * - JTI (JWT ID) = bin2hex(random_bytes(16)) = 32 chars hexadécimaux
 */
class JwtLibrary
{
    /**
     * @var AuthConfig
     */
    private AuthConfig $config;

    public function __construct()
    {
        $this->config = config(AuthConfig::class);
    }

    // -------------------------------------------------------------------------
    // Access Token
    // -------------------------------------------------------------------------

    /**
     * Génère un access token JWT.
     *
     * Payload inclus : sub, uuid, phone, association_id, role,
     * is_super_admin, lang, jti, iat, exp.
     *
     * @param int         $userId        Identifiant DB de l'utilisateur
     * @param string      $userUuid      UUID public de l'utilisateur
     * @param string      $userPhone     Numéro de téléphone E.164
     * @param int|null    $associationId Association active (null si aucune)
     * @param string|null $role          Rôle dans l'association active (null si aucune)
     * @param bool        $isSuperAdmin  Flag super-admin
     * @param string      $userLanguage  Langue préférée de l'utilisateur : 'fr' | 'en' (claim `lang`)
     *
     * @return array{token: string, jti: string, expires_at: int}
     */
    public function generateAccessToken(
        int $userId,
        string $userUuid,
        string $userPhone,
        ?int $associationId,
        ?string $role,
        bool $isSuperAdmin,
        string $userLanguage = 'fr'
    ): array {
        $jti = bin2hex(random_bytes(16));
        $iat = time();
        $exp = $iat + $this->config->accessTokenTtl;

        $payload = [
            'sub'            => $userId,
            'uuid'           => $userUuid,
            'phone'          => $userPhone,
            'association_id' => $associationId,
            'role'           => $role,
            'is_super_admin' => $isSuperAdmin,
            'lang'           => $userLanguage,
            'jti'            => $jti,
            'iat'            => $iat,
            'exp'            => $exp,
        ];

        $token = JWT::encode($payload, $this->config->jwtSecret, $this->config->jwtAlgorithm);

        return [
            'token'      => $token,
            'jti'        => $jti,
            'expires_at' => $exp,
        ];
    }

    /**
     * Vérifie et décode un access token JWT.
     *
     * Contrôles effectués :
     * 1. Signature HMAC valide
     * 2. Token non expiré (exp claim)
     * 3. Token non blacklisté dans Redis
     *
     * @param string $token Token JWT brut (sans "Bearer ")
     *
     * @throws \RuntimeException Si le token est invalide, expiré ou blacklisté
     *
     * @return object Payload décodé (stdClass avec sub, uuid, association_id, role, etc.)
     */
    public function verifyAccessToken(string $token): object
    {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->config->jwtSecret, $this->config->jwtAlgorithm)
            );
        } catch (\Exception $e) {
            throw new \RuntimeException('Token invalide ou expiré : ' . $e->getMessage());
        }

        // Vérifier la blacklist Redis
        $jti = $decoded->jti ?? '';
        if ($jti !== '' && cache()->get("jwt:blacklist:{$jti}") !== null) {
            throw new \RuntimeException('Token révoqué.');
        }

        return $decoded;
    }

    /**
     * Blackliste un access token dans Redis.
     *
     * La clé Redis est "jwt:blacklist:{jti}" avec un TTL égal à la durée
     * restante du token (pour ne pas saturer Redis avec des clés mortes).
     *
     * @param string $jti       JWT ID (claim jti du payload)
     * @param int    $expiresAt Timestamp Unix d'expiration du token
     */
    public function blacklistAccessToken(string $jti, int $expiresAt): void
    {
        $ttl = $expiresAt - time();
        if ($ttl > 0) {
            cache()->save("jwt:blacklist:{$jti}", 1, $ttl);
        }
    }

    // -------------------------------------------------------------------------
    // Refresh Token
    // -------------------------------------------------------------------------

    /**
     * Génère un refresh token opaque et le persiste en base.
     *
     * Le token brut = bin2hex(random_bytes(32)) = 64 chars hexadécimaux.
     * En base, seul le hash SHA-256 est stocké.
     *
     * @param int    $userId Identifiant DB de l'utilisateur
     * @param string $jti    JTI de l'access token associé
     *
     * @return array{raw_token: string, token_hash: string, jti: string, expires_at: string}
     */
    public function generateRefreshToken(int $userId, string $jti): array
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $this->config->refreshTokenTtl);

        $db = \Config\Database::connect();
        $db->table('refresh_tokens')->insert([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'jti'        => $jti,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return [
            'raw_token'  => $rawToken,
            'token_hash' => $tokenHash,
            'jti'        => $jti,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Vérifie un refresh token brut.
     *
     * Recherche en base par hash SHA-256, vérifie que le token
     * n'est pas révoqué et n'est pas expiré.
     *
     * @param string $rawToken Token brut transmis par le client
     *
     * @return array<string, mixed>|null Enregistrement DB ou null si invalide
     */
    public function findValidRefreshToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);

        $db  = \Config\Database::connect();
        $row = $db->table('refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->where('revoked_at IS NULL')
            ->where('expires_at >', gmdate('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Révoque un refresh token en base (pose revoked_at = now UTC).
     *
     * @param string $tokenHash Hash SHA-256 du refresh token
     */
    public function revokeRefreshToken(string $tokenHash): void
    {
        $db = \Config\Database::connect();
        $db->table('refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->update(['revoked_at' => gmdate('Y-m-d H:i:s')]);
    }
}
