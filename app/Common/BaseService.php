<?php

declare(strict_types=1);

namespace App\Common;

/**
 * Classe de base abstraite pour tous les Services métier de Djangui.
 *
 * Chaque service est instancié avec l'`association_id` extrait du JWT,
 * garantissant l'isolation multi-tenant à la couche métier.
 *
 * Usage :
 *   class MemberService extends BaseService { ... }
 *   $service = new MemberService($associationId);
 */
abstract class BaseService
{
    /**
     * Identifiant de l'association courante (scope multi-tenant).
     * Toujours extrait du JWT authentifié, jamais du body de la requête.
     *
     * @var int
     */
    protected int $associationId;

    /**
     * Initialise le service avec le scope tenant de l'association courante.
     *
     * @param int $associationId Identifiant de l'association (extrait du JWT)
     */
    public function __construct(int $associationId)
    {
        $this->associationId = $associationId;
    }

    // -------------------------------------------------------------------------
    // Accesseurs et utilitaires communs
    // -------------------------------------------------------------------------

    /**
     * Retourne l'identifiant de l'association courante.
     *
     * @return int
     */
    protected function getAssociationId(): int
    {
        return $this->associationId;
    }

    /**
     * Calcule les métadonnées de pagination.
     *
     * Retourne un tableau structuré pour être passé directement à
     * BaseController::respondPaginated().
     *
     * @param int $total       Nombre total d'enregistrements (toutes pages)
     * @param int $currentPage Numéro de la page courante (commence à 1)
     * @param int $perPage     Nombre d'enregistrements par page
     *
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    protected function paginationMeta(int $total, int $currentPage, int $perPage): array
    {
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'current_page' => $currentPage,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => $lastPage,
        ];
    }

    /**
     * Retourne la date et l'heure courante au format MySQL, forcée en UTC.
     * Utilisé pour peupler manuellement les champs created_at / updated_at
     * lorsque l'insertion est faite hors du cycle automatique CI4.
     *
     * Utilise gmdate() pour garantir UTC indépendamment du timezone PHP serveur.
     * Règle CLAUDE.md : "Stockage DB : toujours UTC".
     *
     * @return string Date au format 'Y-m-d H:i:s' (UTC)
     */
    protected function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
