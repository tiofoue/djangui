<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Contexte d'authentification — partage le payload JWT décodé entre
 * AuthFilter et les contrôleurs sans recourir aux propriétés dynamiques
 * (dépréciées en PHP 8.2, supprimées en PHP 9.0).
 *
 * Usage :
 *   // Dans AuthFilter::before()
 *   AuthContext::set($decodedPayload);
 *
 *   // Dans AuthController
 *   $user = AuthContext::get();
 */
class AuthContext
{
    /**
     * Payload JWT de la requête courante (null si non authentifié).
     *
     * @var object|null
     */
    private static ?object $payload = null;

    /**
     * Stocke le payload JWT décodé pour la durée de la requête.
     *
     * @param object $payload Payload JWT décodé (stdClass avec sub, uuid, association_id, role, etc.)
     */
    public static function set(object $payload): void
    {
        self::$payload = $payload;
    }

    /**
     * Retourne le payload JWT de la requête courante.
     *
     * @return object|null Null si aucun token valide n'a été traité par AuthFilter
     */
    public static function get(): ?object
    {
        return self::$payload;
    }

    /**
     * Réinitialise le contexte (utile pour les tests).
     */
    public static function reset(): void
    {
        self::$payload = null;
    }
}
