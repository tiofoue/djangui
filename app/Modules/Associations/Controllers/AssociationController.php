<?php

declare(strict_types=1);

namespace App\Modules\Associations\Controllers;

use App\Common\BaseController;
use App\Modules\Associations\Services\AssociationService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur des associations.
 *
 * Délègue toute la logique métier à AssociationService.
 * Gère la validation des entrées et le formatage des réponses JSON.
 *
 * Routes :
 *   GET    /api/associations               → getMine()
 *   POST   /api/associations               → create()
 *   GET    /api/associations/{id}          → getById()
 *   PUT    /api/associations/{id}          → update()
 *   DELETE /api/associations/{id}          → delete()
 *   GET    /api/associations/{id}/children → getChildren()
 *   GET    /api/admin/associations         → adminGetAll()
 *   GET    /api/admin/associations/pending → adminGetPending()
 *   PUT    /api/admin/associations/{id}/approve   → adminApprove()
 *   PUT    /api/admin/associations/{id}/reject    → adminReject()
 *   PUT    /api/admin/associations/{id}/suspend   → adminSuspend()
 *   PUT    /api/admin/associations/{id}/reinstate → adminReinstate()
 */
class AssociationController extends BaseController
{
    private AssociationService $service;

    public function __construct()
    {
        $this->service = new AssociationService();
    }

    // =========================================================================
    // GET /api/associations
    // =========================================================================

    /**
     * Retourne toutes les associations de l'utilisateur authentifié.
     *
     * @return ResponseInterface
     */
    public function getMine(): ResponseInterface
    {
        /** @var object $jwt */
        $jwt    = AuthContext::get();
        $userId = (int) ($jwt->sub ?? 0);

        $result = $this->service->getMine($userId);

        return $this->respond($result);
    }

    // =========================================================================
    // POST /api/associations
    // =========================================================================

    /**
     * Crée une nouvelle association.
     *
     * Body JSON : {
     *   name, type, country?, currency?, description?, slogan?,
     *   statutes_text?, statutes_file?, parent_id?, phone?, address?,
     *   bp?, tax_number?, auth_number?, logo?
     * }
     *
     * @return ResponseInterface
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'name'          => 'required|max_length[191]',
            'type'          => 'required|in_list[tontine_group,association,federation]',
            'country'       => 'permit_empty|exact_length[2]',
            'currency'      => 'permit_empty|exact_length[3]',
            'description'   => 'permit_empty',
            'slogan'        => 'permit_empty|max_length[255]',
            'logo'          => 'permit_empty|max_length[255]|regex_match[/^[a-zA-Z0-9_\-\.]{1,255}$/]',
            'statutes_text' => 'permit_empty',
            // Nom de fichier uniquement (pas de chemin) pour éviter la traversée de répertoire
            'statutes_file' => 'permit_empty|max_length[500]|regex_match[/^[a-zA-Z0-9_\-\.]{1,255}$/]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        /** @var object $jwt */
        $jwt    = AuthContext::get();
        $userId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->create($this->request->getJSON(true) ?? [], $userId);

            return $this->respondCreated($result, 'Association créée avec succès.');
        } catch (\InvalidArgumentException $e) {
            return $this->respondValidationError(['general' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 422);
        }
    }

    // =========================================================================
    // GET /api/associations/{id}
    // =========================================================================

    /**
     * Retourne les détails d'une association.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function getById(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt    = AuthContext::get();
        $userId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->getById($id, $userId);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            // Retourner systématiquement 404 (not found) pour éviter l'énumération d'IDs :
            // un attaquant ne peut pas distinguer "n'existe pas" de "existe mais tu n'es pas membre".
            return $this->respondNotFound('Association introuvable.');
        }
    }

    // =========================================================================
    // PUT /api/associations/{id}
    // =========================================================================

    /**
     * Met à jour une association.
     *
     * Body JSON : champs optionnels parmi name, description, slogan, logo,
     *             phone, address, bp, tax_number, auth_number, country,
     *             currency, statutes_text, statutes_file
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function update(int $id): ResponseInterface
    {
        $rules = [
            'name'          => 'permit_empty|max_length[191]',
            'country'       => 'permit_empty|exact_length[2]',
            'currency'      => 'permit_empty|exact_length[3]',
            'description'   => 'permit_empty',
            'slogan'        => 'permit_empty|max_length[255]',
            'logo'          => 'permit_empty|max_length[255]|regex_match[/^[a-zA-Z0-9_\-\.]{1,255}$/]',
            'statutes_text' => 'permit_empty',
            'statutes_file' => 'permit_empty|max_length[500]|regex_match[/^[a-zA-Z0-9_\-\.]{1,255}$/]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        /** @var object $jwt */
        $jwt          = AuthContext::get();
        $userId       = (int) ($jwt->sub ?? 0);
        $isSuperAdmin = (bool) ($jwt->is_super_admin ?? false);

        try {
            $result = $this->service->update($id, $this->request->getJSON(true) ?? [], $userId, $isSuperAdmin);

            return $this->respond($result, 200, 'Association mise à jour.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // DELETE /api/associations/{id}
    // =========================================================================

    /**
     * Supprime (soft delete) une association.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function delete(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt          = AuthContext::get();
        $userId       = (int) ($jwt->sub ?? 0);
        $isSuperAdmin = (bool) ($jwt->is_super_admin ?? false);

        try {
            $this->service->delete($id, $userId, $isSuperAdmin);

            return $this->respondNoContent();
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 409);
        }
    }

    // =========================================================================
    // GET /api/associations/{id}/children
    // =========================================================================

    /**
     * Retourne les sous-associations d'une fédération.
     *
     * @param int $id Identifiant de la fédération
     *
     * @return ResponseInterface
     */
    public function getChildren(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt    = AuthContext::get();
        $userId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->getChildren($id, $userId);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // GET /api/admin/associations  [SUPER ADMIN]
    // =========================================================================

    /**
     * Retourne toutes les associations (liste admin paginée).
     *
     * @return ResponseInterface
     */
    public function adminGetAll(): ResponseInterface
    {
        /** @var object $jwt */
        $jwt = AuthContext::get();

        if (!(bool) ($jwt->is_super_admin ?? false)) {
            return $this->respondForbidden('Accès réservé aux administrateurs système.');
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        $result = $this->service->getAll($page, $perPage);

        return $this->respondPaginated($result['data'], $result['meta']['total'], $page, $perPage);
    }

    // =========================================================================
    // GET /api/admin/associations/pending  [SUPER ADMIN]
    // =========================================================================

    /**
     * Retourne les associations en attente de revue (liste admin paginée).
     *
     * @return ResponseInterface
     */
    public function adminGetPending(): ResponseInterface
    {
        /** @var object $jwt */
        $jwt = AuthContext::get();

        if (!(bool) ($jwt->is_super_admin ?? false)) {
            return $this->respondForbidden('Accès réservé aux administrateurs système.');
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        $result = $this->service->getAllPending($page, $perPage);

        return $this->respondPaginated($result['data'], $result['meta']['total'], $page, $perPage);
    }

    // =========================================================================
    // PUT /api/admin/associations/{id}/approve  [SUPER ADMIN]
    // =========================================================================

    /**
     * Approuve une association en attente de revue.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function adminApprove(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt = AuthContext::get();

        if (!(bool) ($jwt->is_super_admin ?? false)) {
            return $this->respondForbidden('Accès réservé aux administrateurs système.');
        }

        $adminId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->approve($id, $adminId);

            return $this->respond($result, 200, 'Association approuvée.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // PUT /api/admin/associations/{id}/reject  [SUPER ADMIN]
    // =========================================================================

    /**
     * Rejette une association en attente de revue.
     *
     * Body JSON : { reason }
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function adminReject(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt = AuthContext::get();

        if (!(bool) ($jwt->is_super_admin ?? false)) {
            return $this->respondForbidden('Accès réservé aux administrateurs système.');
        }

        $rules = ['reason' => 'required|max_length[1000]'];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $body    = $this->request->getJSON(true) ?? [];
        $reason  = (string) ($body['reason'] ?? '');
        $adminId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->reject($id, $adminId, $reason);

            return $this->respond($result, 200, 'Association rejetée.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // PUT /api/admin/associations/{id}/suspend  [SUPER ADMIN]
    // =========================================================================

    /**
     * Suspend une association active.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function adminSuspend(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt = AuthContext::get();

        if (!(bool) ($jwt->is_super_admin ?? false)) {
            return $this->respondForbidden('Accès réservé aux administrateurs système.');
        }

        $adminId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->suspend($id, $adminId);

            return $this->respond($result, 200, 'Association suspendue.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // PUT /api/admin/associations/{id}/reinstate  [SUPER ADMIN]
    // =========================================================================

    /**
     * Réactive une association suspendue ou rejetée.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function adminReinstate(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt = AuthContext::get();

        if (!(bool) ($jwt->is_super_admin ?? false)) {
            return $this->respondForbidden('Accès réservé aux administrateurs système.');
        }

        $adminId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->reinstate($id, $adminId);

            return $this->respond($result, 200, 'Association réactivée.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            return $this->respondError($message, 422);
        }
    }
}
