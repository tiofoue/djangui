<?php

declare(strict_types=1);

namespace App\Modules\Associations\Models;

use App\Common\BaseModel;
use App\Modules\Associations\Entities\AssociationEntity;

/**
 * Modèle pour la table `associations`.
 * Table globale (non scopée par association_id).
 */
class AssociationModel extends BaseModel
{
    protected $table      = 'associations';
    protected $primaryKey = 'id';
    protected $returnType = AssociationEntity::class;

    /**
     * Table globale — pas de scope tenant automatique.
     *
     * @var bool
     */
    protected bool $scopedByAssociation = false;

    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    /**
     * Champs autorisés pour les insertions et mises à jour.
     *
     * @var list<string>
     */
    protected $allowedFields = [
        'uuid', 'name', 'slug', 'description', 'slogan', 'logo',
        'phone', 'address', 'bp', 'tax_number', 'auth_number',
        'country', 'currency', 'type', 'parent_id',
        'statutes_text', 'statutes_file', 'status',
        'rejection_reason', 'reviewed_by', 'reviewed_at', 'created_by',
    ];

    /**
     * Règles de validation du modèle.
     *
     * @var array<string, string>
     */
    protected $validationRules = [
        'name'     => 'required|max_length[191]',
        'slug'     => 'required|max_length[191]|is_unique[associations.slug,id,{id}]',
        'type'     => 'required|in_list[tontine_group,association,federation]',
        'country'  => 'permit_empty|exact_length[2]',
        'currency' => 'permit_empty|exact_length[3]',
    ];

    // -------------------------------------------------------------------------
    // Méthodes de recherche
    // -------------------------------------------------------------------------

    /**
     * Recherche une association par son slug.
     *
     * @param string $slug Slug unique
     *
     * @return AssociationEntity|null
     */
    public function findBySlug(string $slug): ?AssociationEntity
    {
        /** @var AssociationEntity|null */
        return $this->where('slug', $slug)->first();
    }

    /**
     * Vérifie si un slug est déjà utilisé (optionnellement en excluant un id).
     *
     * @param string   $slug      Slug à tester
     * @param int|null $excludeId ID à exclure (pour les mises à jour)
     *
     * @return bool
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $builder = $this->where('slug', $slug);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Retourne toutes les associations d'un utilisateur via association_members.
     *
     * @param int $userId Identifiant de l'utilisateur
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId): array
    {
        return $this->db->table('associations a')
            ->select('a.*, am.effective_role, am.joined_at')
            ->join('association_members am', 'am.association_id = a.id')
            ->where('am.user_id', $userId)
            ->where('am.is_active', 1)
            ->where('am.left_at IS NULL')
            ->where('a.deleted_at IS NULL')
            ->orderBy('am.joined_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Retourne les sous-associations d'une fédération.
     *
     * @param int $parentId Identifiant de la fédération parente
     *
     * @return array<int, array<string, mixed>>
     */
    public function findChildren(int $parentId): array
    {
        // $useSoftDeletes = true : CI4 ajoute automatiquement la clause deleted_at IS NULL
        return $this->where('parent_id', $parentId)
            ->findAll();
    }

    /**
     * Retourne les associations en attente de revue (pagination).
     *
     * @param int $limit  Nombre d'enregistrements par page
     * @param int $offset Décalage pour la pagination
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPending(int $limit, int $offset): array
    {
        // $useSoftDeletes = true : CI4 ajoute automatiquement la clause deleted_at IS NULL
        return $this->where('status', 'pending_review')
            ->orderBy('created_at', 'ASC')
            ->findAll($limit, $offset);
    }

    /**
     * Compte les associations en attente de revue.
     *
     * @return int
     */
    public function countPending(): int
    {
        // $useSoftDeletes = true : CI4 ajoute automatiquement la clause deleted_at IS NULL
        return $this->where('status', 'pending_review')
            ->countAllResults();
    }

    /**
     * Retourne toutes les associations non supprimées (pagination).
     *
     * @param int $limit  Nombre d'enregistrements par page
     * @param int $offset Décalage pour la pagination
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllPaginated(int $limit, int $offset): array
    {
        // $useSoftDeletes = true : CI4 ajoute automatiquement la clause deleted_at IS NULL
        return $this->orderBy('created_at', 'DESC')
            ->findAll($limit, $offset);
    }

    /**
     * Compte toutes les associations non supprimées (soft-deleted exclues).
     *
     * Nommée countAllActive() pour éviter un conflit avec Model::countAll()
     * du framework qui, lui, compte TOUTES les lignes (deleted incluses).
     *
     * @return int
     */
    public function countAllActive(): int
    {
        // $useSoftDeletes = true : CI4 ajoute automatiquement la clause deleted_at IS NULL
        return $this->countAllResults();
    }
}
