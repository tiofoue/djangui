<?php

declare(strict_types=1);

namespace App\Modules\Plans\Controllers;

use App\Common\BaseController;
use App\Modules\Plans\Services\PlanService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur Plans & Souscriptions.
 *
 * Délègue toute la logique métier à PlanService.
 *
 * Routes :
 *   GET    /api/plans                              → getPlans()
 *   GET    /api/associations/{id}/subscription     → getSubscription()
 *   POST   /api/associations/{id}/subscription     → subscribe()
 *   DELETE /api/associations/{id}/subscription     → cancel()
 */
class SubscriptionController extends BaseController
{
    private PlanService $planService;

    public function __construct()
    {
        $this->planService = new PlanService();
    }

    // =========================================================================
    // GET /api/plans  (public — pas d'auth)
    // =========================================================================

    /**
     * Retourne la liste publique des plans actifs.
     *
     * @return ResponseInterface
     */
    public function getPlans(): ResponseInterface
    {
        $result = $this->planService->getPlans();

        return $this->respond($result);
    }

    // =========================================================================
    // GET /api/associations/{id}/subscription  [AUTH REQUIRED]
    // =========================================================================

    /**
     * Retourne la souscription courante d'une association.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function getSubscription(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt          = AuthContext::get();
        $userId       = (int) ($jwt->sub ?? 0);
        $isSuperAdmin = (bool) ($jwt->is_super_admin ?? false);

        try {
            $result = $this->planService->getSubscription($id, $userId, $isSuperAdmin);

            return $this->respond(['subscription' => $result]);
        } catch (\RuntimeException $e) {
            return $this->respondForbidden($e->getMessage());
        }
    }

    // =========================================================================
    // POST /api/associations/{id}/subscription  [AUTH REQUIRED]
    // =========================================================================

    /**
     * Souscrit une association à un plan.
     *
     * Body JSON : { plan_name }
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function subscribe(int $id): ResponseInterface
    {
        $rules = [
            'plan_name' => 'required|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondValidationError($this->validator->getErrors());
        }

        /** @var object $jwt */
        $jwt          = AuthContext::get();
        $userId       = (int) ($jwt->sub ?? 0);
        $isSuperAdmin = (bool) ($jwt->is_super_admin ?? false);

        $body     = $this->request->getJSON(true) ?? [];
        $planName = (string) ($body['plan_name'] ?? '');

        try {
            $result = $this->planService->subscribe($id, $planName, $userId, $isSuperAdmin);

            return $this->respondCreated(['subscription' => $result], 'Souscription activée.');
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 422);
        }
    }

    // =========================================================================
    // DELETE /api/associations/{id}/subscription  [AUTH REQUIRED]
    // =========================================================================

    /**
     * Annule la souscription d'une association.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function cancel(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt          = AuthContext::get();
        $userId       = (int) ($jwt->sub ?? 0);
        $isSuperAdmin = (bool) ($jwt->is_super_admin ?? false);

        try {
            $this->planService->cancel($id, $userId, $isSuperAdmin);

            return $this->respondNoContent();
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage(), 422);
        }
    }
}
