<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Common\BaseModel;

/**
 * Modèle de la table `association_members`.
 *
 * Gère les adhésions des utilisateurs aux associations.
 * Pas de soft-delete CI4 standard : utilise is_active=0 + left_at.
 * Pas de timestamps automatiques : la table n'a pas de created_at/updated_at.
 */
class AssociationMemberModel extends BaseModel
{
    /** @var string */
    protected $table = 'association_members';

    /** @var string */
    protected $primaryKey = 'id';

    /** @var string */
    protected $returnType = 'array';

    /** @var list<string> */
    protected $allowedFields = [
        'association_id',
        'user_id',
        'effective_role',
        'joined_at',
        'left_at',
        'is_active',
    ];

    /** @var bool — la table n'a pas de created_at / updated_at standard */
    protected $useTimestamps = false;

    /** @var bool — soft-delete géré manuellement via is_active + left_at */
    protected $useSoftDeletes = false;

    // =========================================================================
    // Requêtes de lecture (avec jointures)
    // =========================================================================

    /**
     * Retourne la liste paginée des membres actifs avec leurs informations utilisateur.
     *
     * @param int $associationId Identifiant de l'association
     * @param int $limit         Nombre d'enregistrements à retourner
     * @param int $offset        Décalage pour la pagination
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMembersWithUsers(int $associationId, int $limit, int $offset): array
    {
        return $this->db->table('association_members am')
            ->select(
                'am.id, am.user_id, am.effective_role, am.joined_at,
                 u.uuid, u.first_name, u.last_name, u.phone, u.email, u.avatar'
            )
            ->join('users u', 'u.id = am.user_id')
            ->where('am.association_id', $associationId)
            ->where('am.is_active', 1)
            ->where('u.deleted_at IS NULL')
            ->orderBy('am.joined_at', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Compte les membres actifs d'une association.
     *
     * @param int $associationId
     *
     * @return int
     */
    public function countActiveMembers(int $associationId): int
    {
        return (int) $this->db->table('association_members am')
            ->join('users u', 'u.id = am.user_id')
            ->where('am.association_id', $associationId)
            ->where('am.is_active', 1)
            ->where('u.deleted_at IS NULL')
            ->countAllResults();
    }

    /**
     * Retourne l'adhésion brute d'un utilisateur dans une association.
     * Retourne null si aucune entrée n'existe (même inactive).
     *
     * @param int $associationId
     * @param int $userId
     *
     * @return array<string, mixed>|null
     */
    public function findMembership(int $associationId, int $userId): ?array
    {
        $row = $this->db->table('association_members')
            ->where('association_id', $associationId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Retourne les informations complètes d'un membre actif (membership + utilisateur).
     *
     * @param int $associationId
     * @param int $userId
     *
     * @return array<string, mixed>|null
     */
    public function findMemberWithUser(int $associationId, int $userId): ?array
    {
        $row = $this->db->table('association_members am')
            ->select(
                'am.id, am.user_id, am.effective_role, am.joined_at,
                 u.uuid, u.first_name, u.last_name, u.phone, u.email, u.avatar'
            )
            ->join('users u', 'u.id = am.user_id')
            ->where('am.association_id', $associationId)
            ->where('am.user_id', $userId)
            ->where('am.is_active', 1)
            ->where('u.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Retourne toutes les associations actives d'un utilisateur, avec les détails.
     * Utilisé pour GET /api/me/overview (cross-associations).
     *
     * @param int $userId
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssociationsForUser(int $userId): array
    {
        return $this->db->table('association_members am')
            ->select(
                'am.association_id, am.effective_role, am.joined_at,
                 a.name, a.slug, a.type, a.status, a.logo'
            )
            ->join('associations a', 'a.id = am.association_id')
            ->where('am.user_id', $userId)
            ->where('am.is_active', 1)
            ->where('a.deleted_at IS NULL')
            ->orderBy('am.joined_at', 'ASC')
            ->get()
            ->getResultArray();
    }
}
