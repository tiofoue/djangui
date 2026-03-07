<?php

declare(strict_types=1);

namespace App\Modules\Members\Controllers;

use App\Common\BaseController;
use App\Modules\Members\Services\MemberService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur des membres d'une association.
 *
 * Routes :
 *   GET    /api/associations/{id}/members               → list()
 *   GET    /api/associations/{id}/members/{userId}      → show()
 *   PUT    /api/associations/{id}/members/{userId}/role → changeRole()
 *   DELETE /api/associations/{id}/members/{userId}      → remove()
 */
class MemberController extends BaseController
{
    // =========================================================================
    // GET /api/associations/{assocId}/members
    // =========================================================================

    /**
     * Retourne la liste paginée des membres actifs de l'association.
     * Accessible à tout membre actif.
     *
     * @param int $assocId Identifiant de l'association (paramètre de route)
     *
     * @return ResponseInterface
     */
    public function list(int $assocId): ResponseInterface
    {
        $userId    = (int) (AuthContext::get()?->sub ?? 0);
        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage   = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        try {
            $service = new MemberService($assocId);
            $result  = $service->getMembers($userId, $page, $perPage);

            return $this->respondPaginated($result['data'], $result['meta']['total'], $page, $perPage);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondNotFound('Association introuvable ou accès interdit.');
        }
    }

    // =========================================================================
    // GET /api/associations/{assocId}/members/{userId}
    // =========================================================================

    /**
     * Retourne les informations d'un membre spécifique.
     * Accessible à tout membre actif.
     *
     * @param int $assocId Identifiant de l'association
     * @param int $userId  Identifiant de l'utilisateur cible
     *
     * @return ResponseInterface
     */
    public function detail(int $assocId, int $userId): ResponseInterface
    {
        $requesterId = (int) (AuthContext::get()?->sub ?? 0);

        try {
            $service = new MemberService($assocId);
            $result  = $service->getMember($requesterId, $userId);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondNotFound('Membre introuvable.');
        }
    }

    // =========================================================================
    // PUT /api/associations/{assocId}/members/{userId}/role
    // =========================================================================

    /**
     * Modifie le rôle effectif d'un membre.
     * Réservé au président de l'association.
     *
     * Body JSON : { "role": "treasurer|secretary|auditor|censor|member" }
     *
     * @param int $assocId Identifiant de l'association
     * @param int $userId  Identifiant du membre cible
     *
     * @return ResponseInterface
     */
    public function changeRole(int $assocId, int $userId): ResponseInterface
    {
        $rules = [
            'role' => 'required|in_list[treasurer,secretary,auditor,censor,member]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $requesterId = (int) (AuthContext::get()?->sub ?? 0);
        $body        = $this->request->getJSON(true) ?? [];
        $newRole     = (string) ($body['role'] ?? '');

        try {
            $service = new MemberService($assocId);
            $result  = $service->changeRole($requesterId, $userId, $newRole);

            return $this->respond($result, 200, 'Rôle mis à jour avec succès.');
        } catch (\InvalidArgumentException $e) {
            return $this->respondValidationError(['role' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // DELETE /api/associations/{assocId}/members/{userId}
    // =========================================================================

    /**
     * Retire un membre de l'association (soft-delete).
     * Réservé au président de l'association.
     *
     * @param int $assocId Identifiant de l'association
     * @param int $userId  Identifiant du membre à retirer
     *
     * @return ResponseInterface
     */
    public function remove(int $assocId, int $userId): ResponseInterface
    {
        $requesterId = (int) (AuthContext::get()?->sub ?? 0);

        try {
            $service = new MemberService($assocId);
            $service->removeMember($requesterId, $userId);

            return $this->respondNoContent();
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            return $this->respondError($message, 422);
        }
    }
}
