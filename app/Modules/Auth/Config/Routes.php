<?php

declare(strict_types=1);

/**
 * Routes du module Auth — Djangui.
 *
 * Préfixe : /api/auth/
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

$routes->group('api', static function ($routes): void {
    // ------------------------------------------------------------------
    // Endpoints publics (pas d'authentification requise)
    // ------------------------------------------------------------------

    /** Inscription d'un nouveau compte */
    $routes->post('auth/register', 'Auth\Controllers\AuthController::register');

    /** Vérification de l'OTP d'activation */
    $routes->post('auth/verify-phone', 'Auth\Controllers\AuthController::verifyPhone');

    /** Renvoi d'un OTP SMS */
    $routes->post('auth/resend-otp', 'Auth\Controllers\AuthController::resendOtp');

    /** Connexion standard (identifiant + mot de passe) */
    $routes->post('auth/login', 'Auth\Controllers\AuthController::login');

    /** Demande d'OTP de connexion (sans mot de passe) */
    $routes->post('auth/login/otp', 'Auth\Controllers\AuthController::requestLoginOtp');

    /** Vérification de l'OTP de connexion */
    $routes->post('auth/login/otp/verify', 'Auth\Controllers\AuthController::verifyLoginOtp');

    /** Rafraîchissement des tokens (rotation refresh token) */
    $routes->post('auth/refresh', 'Auth\Controllers\AuthController::refreshToken');

    /** Demande de réinitialisation de mot de passe */
    $routes->post('auth/forgot-password', 'Auth\Controllers\AuthController::forgotPassword');

    /** Réinitialisation du mot de passe avec token */
    $routes->post('auth/reset-password', 'Auth\Controllers\AuthController::resetPassword');

    // ------------------------------------------------------------------
    // Endpoints protégés (filtre 'auth' — JWT obligatoire)
    // ------------------------------------------------------------------

    /** Déconnexion — blacklist + révocation refresh token */
    $routes->post('auth/logout', 'Auth\Controllers\AuthController::logout', ['filter' => 'auth']);

    /** Profil de l'utilisateur courant */
    $routes->get('auth/me', 'Auth\Controllers\AuthController::me', ['filter' => 'auth']);

    /** Mise à jour du profil de l'utilisateur courant */
    $routes->put('auth/me', 'Auth\Controllers\AuthController::updateMe', ['filter' => 'auth']);

    /** Switch d'association active — génère de nouveaux tokens scopés */
    $routes->post('auth/switch-association', 'Auth\Controllers\AuthController::switchAssociation', ['filter' => 'auth']);
});
