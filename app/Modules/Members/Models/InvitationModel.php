<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Common\BaseModel;

/**
 * Modèle de la table `invitations`.
 *
 * Gère les invitations à rejoindre une association.
 * La table a un `created_at` mais pas de `updated_at` ni `deleted_at`.
 */
class InvitationModel extends BaseModel
{
    /** @var string */
    protected $table = 'invitations';

    /** @var string */
    protected $primaryKey = 'id';

    /** @var string */
    protected $returnType = 'array';

    /** @var list<string> */
    protected $allowedFields = [
        'association_id',
        'invited_by',
        'phone',
        'email',
        'token',
        'role',
        'status',
        'expires_at',
        'created_at',
    ];

    /** @var bool — timestamps gérés manuellement (pas de updated_at) */
    protected $useTimestamps = false;

    /** @var bool — pas de soft-delete sur les invitations */
    protected $useSoftDeletes = false;

    // =========================================================================
    // Requêtes spécialisées
    // =========================================================================

    /**
     * Recherche une invitation par son token unique.
     * Non scopée — utilisée lors de l'acceptation publique (sans JWT).
     *
     * @param string $token Token d'invitation
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $row = $this->db->table('invitations')
            ->where('token', $token)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Vérifie si une invitation en attente (non expirée) existe déjà pour ce contact.
     * Évite les doublons d'invitation pour le même phone ou email.
     *
     * @param int         $associationId
     * @param string|null $phone
     * @param string|null $email
     *
     * @return bool
     */
    public function hasPendingInvitation(int $associationId, ?string $phone, ?string $email): bool
    {
        $builder = $this->db->table('invitations')
            ->where('association_id', $associationId)
            ->where('status', 'pending')
            ->where('expires_at >', gmdate('Y-m-d H:i:s'));

        if ($phone !== null) {
            $builder->where('phone', $phone);
        } elseif ($email !== null) {
            $builder->where('email', $email);
        } else {
            return false;
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Retourne la liste paginée des invitations d'une association,
     * enrichie du nom de l'invitant.
     *
     * @param int $associationId
     * @param int $limit
     * @param int $offset
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByAssociation(int $associationId, int $limit, int $offset): array
    {
        return $this->db->table('invitations i')
            ->select(
                'i.id, i.phone, i.email, i.role, i.status, i.expires_at, i.created_at,
                 u.first_name AS inviter_first_name, u.last_name AS inviter_last_name'
            )
            ->join('users u', 'u.id = i.invited_by', 'left')
            ->where('i.association_id', $associationId)
            ->orderBy('i.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Compte le total des invitations d'une association.
     *
     * @param int $associationId
     *
     * @return int
     */
    public function countByAssociation(int $associationId): int
    {
        return (int) $this->db->table('invitations')
            ->where('association_id', $associationId)
            ->countAllResults();
    }
}
