<?php

declare(strict_types=1);

namespace App\Modules\Members\Controllers;

use App\Common\BaseController;
use App\Modules\Members\Services\MemberService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur du tableau de bord personnel de l'utilisateur.
 *
 * Routes :
 *   GET /api/me/overview  → overview()
 */
class MeController extends BaseController
{
    // =========================================================================
    // GET /api/me/overview
    // =========================================================================

    /**
     * Retourne la vue d'ensemble cross-associations de l'utilisateur connecté.
     *
     * Données retournées :
     * - Liste de toutes ses associations avec son rôle
     * - Statistiques tontines / emprunts / solidarité par association
     *   (stubs = 0 jusqu'à l'implémentation des modules Sprint 2-4)
     * - Totaux agrégés
     *
     * @return ResponseInterface
     */
    public function overview(): ResponseInterface
    {
        $userId = (int) (AuthContext::get()?->sub ?? 0);

        // MemberService instancié avec 0 : getOverview() est cross-associations
        // et n'utilise pas $this->associationId.
        $service = new MemberService(0);
        $result  = $service->getOverview($userId);

        return $this->respond($result);
    }
}
