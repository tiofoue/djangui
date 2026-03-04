<?php

declare(strict_types=1);

namespace App\Common;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

/**
 * Contrôleur de base pour tous les modules HMVC de Djangui.
 *
 * Fournit des méthodes standardisées pour les réponses JSON.
 * Tous les contrôleurs API doivent étendre cette classe.
 *
 * Format succès  : { "status": "success", "data": ..., "message": "" }
 * Format erreur  : { "status": "error", "message": "", "errors": {} }
 * Format paginé  : { "status": "success", "data": [...], "meta": { ... } }
 */
abstract class BaseController extends ResourceController
{
    /**
     * Format de réponse par défaut : JSON.
     *
     * @var string
     */
    protected $format = 'json';

    // -------------------------------------------------------------------------
    // Réponses de succès
    // -------------------------------------------------------------------------

    /**
     * Réponse de succès générique (HTTP 200).
     *
     * @param mixed  $data    Données à retourner (array, object, null)
     * @param int    $status  Code HTTP (défaut 200)
     * @param string $message Message optionnel de contexte
     *
     * @return ResponseInterface
     */
    protected function respond(mixed $data, int $status = 200, string $message = ''): ResponseInterface
    {
        $payload = [
            'status'  => 'success',
            'data'    => $data,
            'message' => $message,
        ];

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }

    /**
     * Réponse de création réussie (HTTP 201).
     *
     * @param mixed  $data    Ressource créée
     * @param string $message Message optionnel
     *
     * @return ResponseInterface
     */
    protected function respondCreated(mixed $data, string $message = ''): ResponseInterface
    {
        return $this->respond($data, 201, $message);
    }

    /**
     * Réponse sans contenu (HTTP 204).
     * Utilisé après suppression ou mise à jour sans retour de données.
     *
     * @return ResponseInterface
     */
    protected function respondNoContent(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    // -------------------------------------------------------------------------
    // Réponses d'erreur
    // -------------------------------------------------------------------------

    /**
     * Réponse d'erreur générique.
     *
     * @param string  $message Message d'erreur principal
     * @param int     $status  Code HTTP (défaut 400)
     * @param mixed[] $errors  Détail des erreurs (champ => message)
     *
     * @return ResponseInterface
     */
    protected function respondError(string $message, int $status = 400, array $errors = []): ResponseInterface
    {
        $payload = [
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ];

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }

    /**
     * Réponse d'erreur de validation (HTTP 422 Unprocessable Entity).
     * Retourne le détail des champs invalides.
     *
     * @param mixed[] $errors  Tableau associatif champ => message d'erreur
     * @param string  $message Message général de validation
     *
     * @return ResponseInterface
     */
    protected function respondValidationError(
        array $errors,
        string $message = 'Validation failed'
    ): ResponseInterface {
        return $this->respondError($message, 422, $errors);
    }

    /**
     * Réponse non authentifié (HTTP 401 Unauthorized).
     * JWT absent, expiré ou invalide.
     *
     * @param string $message Message d'erreur
     *
     * @return ResponseInterface
     */
    protected function respondUnauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->respondError($message, 401);
    }

    /**
     * Réponse accès refusé (HTTP 403 Forbidden).
     * Utilisateur authentifié mais sans les permissions requises.
     *
     * @param string $message Message d'erreur
     *
     * @return ResponseInterface
     */
    protected function respondForbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return $this->respondError($message, 403);
    }

    /**
     * Réponse ressource introuvable (HTTP 404 Not Found).
     *
     * @param string $message Message d'erreur
     *
     * @return ResponseInterface
     */
    protected function respondNotFound(string $message = 'Resource not found'): ResponseInterface
    {
        return $this->respondError($message, 404);
    }

    /**
     * Réponse conflit de données (HTTP 409 Conflict).
     * Utilisé pour les doublons ou les conflits d'état (ex : membre déjà inscrit).
     *
     * @param string $message Message d'erreur
     *
     * @return ResponseInterface
     */
    protected function respondConflict(string $message): ResponseInterface
    {
        return $this->respondError($message, 409);
    }

    /**
     * Réponse trop de requêtes (HTTP 429 Too Many Requests).
     * Déclenché par le rate limiting.
     *
     * @param string $message Message d'erreur
     *
     * @return ResponseInterface
     */
    protected function respondTooManyRequests(string $message = 'Too many requests'): ResponseInterface
    {
        return $this->respondError($message, 429);
    }

    /**
     * Réponse quota de plan dépassé (HTTP 402 Payment Required).
     * Déclenché quand une limite du plan (ex : nb de membres) est atteinte.
     *
     * @param string $message Message d'erreur
     *
     * @return ResponseInterface
     */
    protected function respondQuotaExceeded(string $message = 'Plan limit reached'): ResponseInterface
    {
        return $this->respondError($message, 402);
    }

    // -------------------------------------------------------------------------
    // Réponse paginée
    // -------------------------------------------------------------------------

    /**
     * Réponse de liste paginée (HTTP 200).
     *
     * Format retourné :
     * {
     *   "status": "success",
     *   "data": [...],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 100,
     *     "last_page": 5
     *   }
     * }
     *
     * @param mixed[] $items       Éléments de la page courante
     * @param int     $total       Nombre total d'éléments (toutes pages)
     * @param int     $currentPage Numéro de la page courante (commence à 1)
     * @param int     $perPage     Nombre d'éléments par page
     *
     * @return ResponseInterface
     */
    protected function respondPaginated(
        array $items,
        int $total,
        int $currentPage,
        int $perPage
    ): ResponseInterface {
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $payload = [
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $currentPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
        ];

        return $this->response
            ->setStatusCode(200)
            ->setJSON($payload);
    }
}
