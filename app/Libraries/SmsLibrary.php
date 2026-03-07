<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Auth as AuthConfig;

/**
 * Bibliothèque SMS pour Djangui — intégration Africa's Talking.
 *
 * Gestion des OTP :
 * - Stockage en cache Redis du hash SHA-256 du code (jamais le code en clair)
 * - Compteur de tentatives avec blocage après otpMaxAttempts
 * - Rate limiting sur les renvois
 *
 * Configuration depuis .env :
 *   AT_USERNAME=sandbox
 *   AT_API_KEY=<votre_cle_api>
 */
class SmsLibrary
{
    /**
     * URL de l'API Africa's Talking pour l'envoi de SMS.
     */
    private const AT_SMS_URL = 'https://api.africastalking.com/version1/messaging';

    /**
     * @var AuthConfig
     */
    private AuthConfig $config;

    /**
     * @var string
     */
    private string $atUsername;

    /**
     * @var string
     */
    private string $atApiKey;

    public function __construct()
    {
        $this->config     = config(AuthConfig::class);
        $this->atUsername = (string) env('AT_USERNAME', '');
        $this->atApiKey   = (string) env('AT_API_KEY', '');
    }

    // -------------------------------------------------------------------------
    // Envoi SMS
    // -------------------------------------------------------------------------

    /**
     * Envoie un SMS via Africa's Talking.
     *
     * @param string $phone   Numéro destinataire au format E.164 (+237XXXXXXXXX)
     * @param string $message Corps du message (max 160 caractères recommandé)
     *
     * @return bool True si l'envoi a réussi (code HTTP 2xx)
     */
    public function sendSms(string $phone, string $message): bool
    {
        $curl = \Config\Services::curlrequest();

        try {
            $response = $curl->post(self::AT_SMS_URL, [
                'headers' => [
                    'apiKey'  => $this->atApiKey,
                    'Accept'  => 'application/json',
                ],
                'form_params' => [
                    'username' => $this->atUsername,
                    'to'       => $phone,
                    'message'  => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Exception $e) {
            log_message('error', '[SmsLibrary] Erreur envoi SMS à ' . $phone . ' : ' . $e->getMessage());

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // OTP
    // -------------------------------------------------------------------------

    /**
     * Génère et envoie un OTP de 6 chiffres par SMS.
     *
     * Le code généré est hashé (SHA-256) avant d'être stocké dans le cache Redis.
     * Clé cache : "otp:{purpose}:{phone}"  (ex: "otp:register:+237699000000")
     * TTL : Auth::$otpTtl secondes.
     *
     * @param string $phone    Numéro destinataire E.164
     * @param string $purpose  Contexte : 'register' | 'login' | 'reset'
     * @param string $language Langue du message SMS : 'fr' | 'en' (défaut : 'fr')
     *
     * @return string Le code OTP à 6 chiffres (à transmettre au client en dev, jamais en prod)
     */
    public function sendOtp(string $phone, string $purpose, string $language = 'fr'): string
    {
        $code     = sprintf('%06d', random_int(0, 999999));
        $codeHash = hash('sha256', $code);
        $cacheKey = "otp:{$purpose}:{$phone}";

        // Stocker le hash dans Redis (TTL = otpTtl)
        cache()->save($cacheKey, $codeHash, $this->config->otpTtl);

        // Réinitialiser le compteur de tentatives
        cache()->delete("otp:attempts:{$purpose}:{$phone}");

        $message = $this->buildOtpMessage($purpose, $code, $language);
        $this->sendSms($phone, $message);

        return $code;
    }

    /**
     * Vérifie un code OTP soumis par l'utilisateur.
     *
     * Gestion des tentatives :
     * - Incrémente le compteur à chaque tentative échouée
     * - Bloque pendant otpBlockDuration secondes après otpMaxAttempts échecs
     * - Supprime le code et le compteur si le code est valide
     *
     * Clé tentatives : "otp:attempts:{purpose}:{phone}"
     *
     * @param string $phone   Numéro E.164
     * @param string $purpose Contexte : 'register' | 'login' | 'reset'
     * @param string $code    Code saisi par l'utilisateur
     *
     * @throws \RuntimeException Si le compte est bloqué suite à trop de tentatives
     *
     * @return bool True si le code est valide
     */
    public function verifyOtp(string $phone, string $purpose, string $code): bool
    {
        $cacheKey    = "otp:{$purpose}:{$phone}";
        $attemptsKey = "otp:attempts:{$purpose}:{$phone}";

        // Vérifier si bloqué
        $attempts = (int) (cache()->get($attemptsKey) ?? 0);
        if ($attempts >= $this->config->otpMaxAttempts) {
            throw new \RuntimeException(
                'Trop de tentatives. Veuillez attendre avant de réessayer.'
            );
        }

        // Récupérer le hash stocké
        $storedHash = cache()->get($cacheKey);
        if ($storedHash === null) {
            return false; // OTP expiré ou inexistant
        }

        // Comparer le hash du code soumis
        $inputHash = hash('sha256', $code);
        if (!hash_equals((string) $storedHash, $inputHash)) {
            // Incrémenter le compteur de tentatives échouées
            $newAttempts = $attempts + 1;
            cache()->save($attemptsKey, $newAttempts, $this->config->otpBlockDuration);

            return false;
        }

        // Code valide — nettoyer le cache
        cache()->delete($cacheKey);
        cache()->delete($attemptsKey);

        return true;
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Construit le message SMS selon le contexte OTP et la langue de l'utilisateur.
     *
     * @param string $purpose  Contexte : 'register' | 'login' | 'reset'
     * @param string $code     Code OTP à inclure dans le message
     * @param string $language Langue du message : 'fr' | 'en' (défaut : 'fr')
     *
     * @return string Message SMS formaté dans la langue demandée
     */
    private function buildOtpMessage(string $purpose, string $code, string $language = 'fr'): string
    {
        $minutes = (int) round($this->config->otpTtl / 60);

        if ($language === 'en') {
            return match ($purpose) {
                'register' => "Djangui: Your verification code is {$code}. Valid for {$minutes} minutes.",
                'login'    => "Djangui: Your login code is {$code}. Valid for {$minutes} minutes.",
                'reset'    => "Djangui: Your reset code is {$code}. Valid for {$minutes} minutes.",
                default    => "Djangui: Your code is {$code}. Valid for {$minutes} minutes.",
            };
        }

        return match ($purpose) {
            'register' => "Djangui : Votre code de vérification est {$code}. Valable {$minutes} minutes.",
            'login'    => "Djangui : Votre code de connexion est {$code}. Valable {$minutes} minutes.",
            'reset'    => "Djangui : Votre code de réinitialisation est {$code}. Valable {$minutes} minutes.",
            default    => "Djangui : Votre code est {$code}. Valable {$minutes} minutes.",
        };
    }
}
