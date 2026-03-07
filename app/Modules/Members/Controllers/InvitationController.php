<?php

declare(strict_types=1);

namespace App\Modules\Members\Controllers;

use App\Common\BaseController;
use App\Modules\Members\Models\InvitationModel;
use App\Modules\Members\Services\MemberService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur des invitations.
 *
 * Routes :
 *   POST   /api/associations/{id}/invitations              → create()
 *   GET    /api/associations/{id}/invitations              → list()
 *   DELETE /api/associations/{id}/invitations/{invitId}   → cancel()
 *   POST   /api/invitations/{token}/accept                 → accept()  [public]
 */
class InvitationController extends BaseController
{
    // =========================================================================
    // POST /api/associations/{assocId}/invitations
    // =========================================================================

    /**
     * Crée une invitation pour rejoindre l'association.
     * Accessible aux secrétaires et rôles supérieurs.
     *
     * Body JSON : {
     *   phone?  : string  (téléphone format +237XXXXXXXXX)
     *   email?  : string  (adresse email)
     *   role?   : string  (treasurer|secretary|auditor|member — défaut: member)
     * }
     * Contrainte Service : phone OU email requis (au moins un).
     *
     * @param int $assocId Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function create(int $assocId): ResponseInterface
    {
        $rules = [
            'phone' => 'permit_empty|max_length[20]',
            'email' => 'permit_empty|valid_email|max_length[191]',
            'role'  => 'permit_empty|in_list[treasurer,secretary,auditor,member]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        $requesterId = (int) (AuthContext::get()?->sub ?? 0);
        $body        = $this->request->getJSON(true) ?? [];

        try {
            $service = new MemberService($assocId);
            $result  = $service->invite($requesterId, $body);

            return $this->respondCreated($result, 'Invitation créée avec succès.');
        } catch (\InvalidArgumentException $e) {
            return $this->respondValidationError(['general' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 409);
        }
    }

    // =========================================================================
    // GET /api/associations/{assocId}/invitations
    // =========================================================================

    /**
     * Retourne la liste paginée des invitations de l'association.
     * Accessible aux secrétaires et rôles supérieurs.
     *
     * @param int $assocId Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function list(int $assocId): ResponseInterface
    {
        $requesterId = (int) (AuthContext::get()?->sub ?? 0);
        $page        = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage     = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        try {
            $service = new MemberService($assocId);
            $result  = $service->listInvitations($requesterId, $page, $perPage);

            return $this->respondPaginated($result['data'], $result['meta']['total'], $page, $perPage);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // DELETE /api/associations/{assocId}/invitations/{invitId}
    // =========================================================================

    /**
     * Annule une invitation en attente.
     * Accessible aux secrétaires et rôles supérieurs.
     *
     * @param int $assocId   Identifiant de l'association
     * @param int $invitId   Identifiant de l'invitation
     *
     * @return ResponseInterface
     */
    public function cancel(int $assocId, int $invitId): ResponseInterface
    {
        $requesterId = (int) (AuthContext::get()?->sub ?? 0);

        try {
            $service = new MemberService($assocId);
            $service->cancelInvitation($requesterId, $invitId);

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

    // =========================================================================
    // POST /api/invitations/{token}/accept  [auth filter requis — JWT obligatoire]
    // =========================================================================

    /**
     * Accepte une invitation via son token.
     * L'utilisateur doit être authentifié (filtre auth JWT requis).
     *
     * Processus :
     * 1. Vérifier que le token existe en base
     * 2. Vérifier statut pending et non expiré
     * 3. Déléguer à MemberService::acceptInvitation()
     *
     * @param string $token Token d'invitation (64 chars hex)
     *
     * @return ResponseInterface
     */
    public function accept(string $token): ResponseInterface
    {
        // Nettoyage préventif du token (évite injections via l'URL)
        $token = preg_replace('/[^a-f0-9]/i', '', $token) ?? '';

        if (strlen($token) !== 64) {
            return $this->respondNotFound('Token d\'invitation invalide.');
        }

        $userId = (int) (AuthContext::get()?->sub ?? 0);

        if ($userId === 0) {
            return $this->respondUnauthorized('Authentification requise pour accepter une invitation.');
        }

        // Charger l'invitation pour obtenir l'association_id (nécessaire à MemberService)
        $invitationModel = new InvitationModel();
        $invitation      = $invitationModel->findByToken($token);

        if ($invitation === null) {
            return $this->respondNotFound('Invitation introuvable ou invalide.');
        }

        try {
            $service = new MemberService((int) $invitation['association_id']);
            $result  = $service->acceptInvitation($invitation, $userId);

            return $this->respondCreated($result, 'Invitation acceptée. Vous êtes maintenant membre.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'expiré')) {
                return $this->respondError($message, 410); // Gone
            }

            if (str_contains($message, 'déjà membre')) {
                return $this->respondConflict($message);
            }

            return $this->respondError($message, 422);
        }
    }
}
