<?php

declare(strict_types=1);

namespace App\Modules\Plans\Models;

use App\Common\BaseModel;

/**
 * Modèle pour la table `plans`.
 * Table globale (non scopée par association_id).
 */
class PlanModel extends BaseModel
{
    protected $table      = 'plans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    /**
     * Table globale — pas de scope tenant automatique.
     *
     * @var bool
     */
    protected bool $scopedByAssociation = false;

    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    /**
     * Champs autorisés pour les insertions et mises à jour.
     *
     * @var list<string>
     */
    protected $allowedFields = [
        'name', 'label', 'price_monthly',
        'max_entities', 'max_members', 'max_tontines',
        'features', 'is_active',
    ];

    /**
     * Retourne tous les plans actifs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivePlans(): array
    {
        return $this->where('is_active', 1)->findAll();
    }

    /**
     * Recherche un plan par son nom technique.
     *
     * @param string $name Nom technique du plan (ex: 'free', 'pro')
     *
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }
}
