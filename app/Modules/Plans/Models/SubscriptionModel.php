<?php

declare(strict_types=1);

namespace App\Modules\Plans\Models;

use App\Common\BaseModel;

/**
 * Modèle pour la table `subscriptions`.
 * Une seule souscription par association (UNIQUE association_id).
 */
class SubscriptionModel extends BaseModel
{
    protected $table      = 'subscriptions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    /**
     * Lookup par association_id explicite — pas de scope automatique.
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
        'association_id', 'plan_id', 'status',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'payment_method', 'cancelled_at',
    ];

    /**
     * Retourne la souscription d'une association.
     *
     * @param int $associationId Identifiant de l'association
     *
     * @return array<string, mixed>|null
     */
    public function getForAssociation(int $associationId): ?array
    {
        return $this->where('association_id', $associationId)->first();
    }

    /**
     * Retourne la souscription avec les données du plan incluses.
     *
     * @param int $associationId Identifiant de l'association
     *
     * @return array<string, mixed>|null
     */
    public function getWithPlan(int $associationId): ?array
    {
        return $this->db->table('subscriptions s')
            ->select('s.*, p.name as plan_name, p.label as plan_label, p.max_members, p.max_tontines, p.max_entities, p.features')
            ->join('plans p', 'p.id = s.plan_id')
            ->where('s.association_id', $associationId)
            ->get()
            ->getRowArray() ?: null;
    }
}
