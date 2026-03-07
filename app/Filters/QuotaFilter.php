<?php

declare(strict_types=1);

namespace App\Filters;

use App\Modules\Plans\Services\PlanService;
use App\Services\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtre de vérification des quotas SaaS.
 *
 * Appliqué avant la création de ressources limitées par le plan.
 * Arguments : nom de la ressource à vérifier ('members', 'tontines', 'entities').
 *
 * Retourne HTTP 402 si le quota est atteint.
 *
 * Usage dans les routes :
 *   ['filter' => 'quota:members']
 *   ['filter' => 'quota:tontines']
 */
class QuotaFilter implements FilterInterface
{
    /**
     * Table utilisée pour compter les ressources par type.
     *
     * @var array<string, string>
     */
    private array $resourceTables = [
        'members'  => 'association_members',
        'tontines' => 'tontines',
        'entities' => 'associations',
    ];

    /**
     * Vérifie le quota avant l'exécution du contrôleur.
     *
     * Processus :
     * 1. Extraire association_id depuis le payload JWT (AuthContext)
     * 2. Extraire le nom de la ressource depuis $arguments[0]
     * 3. Compter les ressources actuelles en DB
     * 4. Appeler PlanService::checkQuota()
     * 5. Retourner HTTP 402 si le quota est dépassé
     *
     * @param RequestInterface          $request   La requête entrante
     * @param array<int, string>|null   $arguments Arguments du filtre : [0] = nom de la ressource
     *
     * @return ResponseInterface|null HTTP 402 si quota atteint, null sinon
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        // Récupérer le payload JWT (défini par AuthFilter en amont)
        $jwt = AuthContext::get();

        if ($jwt === null) {
            // AuthFilter doit avoir rejeté la requête avant — on laisse passer
            return null;
        }

        // Extraire l'association_id depuis l'URL (segment 3 : /api/associations/{id}/...)
        // On utilise l'URL et non le claim JWT association_id car le claim reflète la dernière
        // association active en session, pas nécessairement celle de la requête courante.
        $associationId = (int) $request->uri->getSegment(3);

        if ($associationId <= 0) {
            // Pas d'ID d'association dans l'URL — skip le quota
            return null;
        }

        // Extraire le nom de la ressource depuis les arguments du filtre
        $resource = isset($arguments[0]) ? trim((string) $arguments[0]) : '';

        if ($resource === '' || !isset($this->resourceTables[$resource])) {
            // Ressource inconnue — skip silencieusement
            return null;
        }

        // Compter les ressources actuelles en DB
        $currentCount = $this->countCurrentResources($associationId, $resource);

        // Vérifier le quota via PlanService
        $planService = new PlanService();

        if (!$planService->checkQuota($associationId, $resource, $currentCount)) {
            return service('response')
                ->setStatusCode(402)
                ->setJSON([
                    'status'  => 'error',
                    'message' => "Limite du plan atteinte pour la ressource '{$resource}'. "
                        . 'Veuillez upgrader votre abonnement.',
                    'errors'  => [
                        'resource'      => $resource,
                        'current_count' => $currentCount,
                    ],
                ]);
        }

        return null;
    }

    /**
     * No-op — aucun traitement après la réponse.
     *
     * @param RequestInterface          $request   La requête
     * @param ResponseInterface         $response  La réponse
     * @param array<int, string>|null   $arguments Arguments du filtre
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Rien à faire après la réponse
    }

    /**
     * Compte les ressources actives d'une association en DB.
     *
     * @param int    $associationId Identifiant de l'association
     * @param string $resource      Nom de la ressource ('members', 'tontines', 'entities')
     *
     * @return int Nombre de ressources actives
     */
    private function countCurrentResources(int $associationId, string $resource): int
    {
        $db    = \Config\Database::connect();
        $table = $this->resourceTables[$resource] ?? null;

        if ($table === null || !$db->tableExists($table)) {
            return 0;
        }

        $builder = $db->table($table)
            ->where('association_id', $associationId);

        // Filtres spécifiques selon la ressource
        switch ($resource) {
            case 'members':
                $builder->where('is_active', 1)
                    ->where('left_at IS NULL');
                break;

            case 'tontines':
                // Compter uniquement les tontines non supprimées et non clôturées
                if ($db->fieldExists('deleted_at', $table)) {
                    $builder->where('deleted_at IS NULL');
                }

                if ($db->fieldExists('status', $table)) {
                    $builder->whereNotIn('status', ['closed', 'cancelled']);
                }
                break;

            case 'entities':
                // 'entities' = sous-associations dont l'association courante est la fédération parente.
                // max_entities limite le nombre de membres-associations d'une fédération.
                // Pour une association simple, ce quota ne s'applique jamais (parent_id ne peut pas
                // correspondre à elle-même), donc countAllResults() retournera 0.
                $builder->where('parent_id', $associationId);

                if ($db->fieldExists('deleted_at', $table)) {
                    $builder->where('deleted_at IS NULL');
                }
                break;
        }

        return (int) $builder->countAllResults();
    }
}
