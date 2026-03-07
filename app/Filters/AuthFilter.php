<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\JwtLibrary;
use App\Services\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtre d'authentification JWT pour Djangui.
 *
 * Appliqué sur les routes nécessitant une authentification.
 *
 * Processus before() :
 * 1. Extraire le Bearer token depuis l'en-tête Authorization
 * 2. Vérifier la signature JWT, l'expiration et la blacklist Redis
 * 3. Peupler $request->user avec le payload décodé
 * 4. Retourner 401 si invalide
 *
 * Le payload JWT est accessible dans les contrôleurs via :
 *   $this->request->user  (stdClass avec sub, uuid, association_id, role, etc.)
 */
class AuthFilter implements FilterInterface
{
    /**
     * Vérifie le JWT avant l'exécution du contrôleur.
     *
     * @param RequestInterface          $request  La requête entrante
     * @param array<string, mixed>|null $arguments Arguments du filtre (non utilisés)
     *
     * @return ResponseInterface|null Réponse 401 si non authentifié, null si OK
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        // Vérifier la présence et le format "Bearer <token>"
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Token d\'authentification manquant.',
                    'errors'  => [],
                ]);
        }

        $token = substr($authHeader, 7);

        if (trim($token) === '') {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Token d\'authentification vide.',
                    'errors'  => [],
                ]);
        }

        try {
            $jwtLib  = new JwtLibrary();
            $payload = $jwtLib->verifyAccessToken($token);

            // Stocker le payload via AuthContext (évite les propriétés dynamiques PHP 8.2+)
            // Accessible dans les contrôleurs via : AuthContext::get()
            AuthContext::set($payload);

            return null; // Continuer vers le contrôleur
        } catch (\RuntimeException $e) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Non authentifié : ' . $e->getMessage(),
                    'errors'  => [],
                ]);
        }
    }

    /**
     * No-op — aucun traitement après la réponse.
     *
     * @param RequestInterface          $request   La requête
     * @param ResponseInterface         $response  La réponse
     * @param array<string, mixed>|null $arguments Arguments du filtre
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Rien à faire après la réponse
    }
}
