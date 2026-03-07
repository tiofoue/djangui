<?php

declare(strict_types=1);

namespace App\Modules\Members\Services;

use App\Common\BaseService;
use App\Modules\Members\Models\AssociationMemberModel;
use App\Modules\Members\Models\InvitationModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Service de gestion des membres et des invitations.
 *
 * Toute la logique métier du module Members est ici.
 * Scopé par association_id (hérité de BaseService).
 *
 * Règles métier clés :
 * - tontine_group : rôles limités à president / treasurer / member
 * - Invitation : phone OU email requis (pas les deux obligatoires)
 * - Invitation expire après 7 jours
 * - Seul le président peut changer les rôles et retirer des membres
 * - Le rôle president ne peut pas être modifié via ce service
 * - Soft-delete membre : is_active=0 + left_at (historique conservé)
 */
class MemberService extends BaseService
{
    private AssociationMemberModel $memberModel;
    private InvitationModel $invitationModel;
    private BaseConnection $db;

    /**
     * @param int $associationId Identifiant de l'association (extrait du JWT ou de la route)
     */
    public function __construct(int $associationId)
    {
        parent::__construct($associationId);
        $this->memberModel     = new AssociationMemberModel();
        $this->invitationModel = new InvitationModel();
        $this->db              = \Config\Database::connect();
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Retourne le rôle effectif du demandeur dans l'association courante.
     * Lève une RuntimeException si non membre actif.
     *
     * @param int $userId
     *
     * @return string Rôle effectif (president, treasurer, secretary, auditor, censor, member)
     *
     * @throws \RuntimeException
     */
    private function getEffectiveRole(int $userId): string
    {
        $membership = $this->memberModel->findMembership($this->associationId, $userId);

        if ($membership === null || (int) $membership['is_active'] !== 1) {
            throw new \RuntimeException('Accès refusé : vous n\'êtes pas membre actif de cette association.');
        }

        return (string) $membership['effective_role'];
    }

    /**
     * Vérifie si un rôle est suffisant pour le niveau requis.
     *
     * Hiérarchie :
     *   president(6) > treasurer(5) = secretary(5) > auditor(3) > censor(2) > member(1)
     *
     * @param string $userRole     Rôle de l'utilisateur
     * @param string $requiredRole Rôle minimum requis
     *
     * @return bool
     */
    private function hasRole(string $userRole, string $requiredRole): bool
    {
        $hierarchy = [
            'president' => 6,
            'treasurer' => 5,
            'secretary' => 5,
            'auditor'   => 3,
            'censor'    => 2,
            'member'    => 1,
        ];

        return ($hierarchy[$userRole] ?? 0) >= ($hierarchy[$requiredRole] ?? 0);
    }

    /**
     * Retourne le type d'entité de l'association courante.
     *
     * @return string tontine_group | association | federation
     *
     * @throws \RuntimeException Si l'association est introuvable
     */
    private function getAssociationType(): string
    {
        $row = $this->db->table('associations')
            ->select('type')
            ->where('id', $this->associationId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new \RuntimeException('Association introuvable.');
        }

        return (string) $row['type'];
    }

    /**
     * Génère un token d'invitation aléatoire sécurisé (64 chars hex).
     *
     * @return string
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    // =========================================================================
    // Membres — lecture
    // =========================================================================

    /**
     * Retourne la liste paginée des membres actifs de l'association.
     * Accessible à tout membre actif.
     *
     * @param int $requesterId Identifiant du demandeur (doit être membre actif)
     * @param int $page        Numéro de page (commence à 1)
     * @param int $perPage     Éléments par page
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     *
     * @throws \RuntimeException Si le demandeur n'est pas membre actif
     */
    public function getMembers(int $requesterId, int $page, int $perPage): array
    {
        $this->getEffectiveRole($requesterId);

        $offset = ($page - 1) * $perPage;
        $total  = $this->memberModel->countActiveMembers($this->associationId);
        $items  = $this->memberModel->getMembersWithUsers($this->associationId, $perPage, $offset);

        return [
            'data' => $items,
            'meta' => $this->paginationMeta($total, $page, $perPage),
        ];
    }

    /**
     * Retourne les informations d'un membre spécifique.
     * Accessible à tout membre actif.
     *
     * @param int $requesterId  Identifiant du demandeur
     * @param int $targetUserId Identifiant de l'utilisateur cible
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException Si non membre ou cible introuvable
     */
    public function getMember(int $requesterId, int $targetUserId): array
    {
        $this->getEffectiveRole($requesterId);

        $member = $this->memberModel->findMemberWithUser($this->associationId, $targetUserId);

        if ($member === null) {
            throw new \RuntimeException('Membre introuvable dans cette association.');
        }

        return $member;
    }

    // =========================================================================
    // Membres — écriture
    // =========================================================================

    /**
     * Modifie le rôle effectif d'un membre.
     * Réservé au président.
     *
     * Règles :
     * - Le demandeur ne peut pas modifier son propre rôle
     * - Le rôle president ne peut pas être attribué via ce endpoint
     * - Le rôle du president en place ne peut pas être modifié ici
     * - Pour tontine_group : seuls treasurer et member sont autorisés
     *
     * @param int    $requesterId  Identifiant du président
     * @param int    $targetUserId Identifiant du membre à modifier
     * @param string $newRole      Nouveau rôle souhaité
     *
     * @return array<string, mixed> Données du membre mises à jour
     *
     * @throws \RuntimeException        Si permissions insuffisantes ou règle violée
     * @throws \InvalidArgumentException Si le rôle est invalide pour ce type d'entité
     */
    public function changeRole(int $requesterId, int $targetUserId, string $newRole): array
    {
        $requesterRole = $this->getEffectiveRole($requesterId);

        if ($requesterRole !== 'president') {
            throw new \RuntimeException('Accès refusé : seul le président peut modifier les rôles.');
        }

        if ($requesterId === $targetUserId) {
            throw new \RuntimeException('Vous ne pouvez pas modifier votre propre rôle.');
        }

        if ($newRole === 'president') {
            throw new \InvalidArgumentException(
                'Le rôle président ne peut pas être attribué via cette opération.'
            );
        }

        $targetMembership = $this->memberModel->findMembership($this->associationId, $targetUserId);

        if ($targetMembership === null || (int) $targetMembership['is_active'] !== 1) {
            throw new \RuntimeException('Membre introuvable dans cette association.');
        }

        if ($targetMembership['effective_role'] === 'president') {
            throw new \RuntimeException(
                'Impossible de modifier le rôle du président via cette opération.'
            );
        }

        // Règle tontine_group : seuls treasurer et member sont autorisés
        $type = $this->getAssociationType();

        if ($type === 'tontine_group' && !in_array($newRole, ['treasurer', 'member'], true)) {
            throw new \InvalidArgumentException(
                'Pour un tontine_group, les rôles autorisés sont : treasurer et member.'
            );
        }

        $this->db->table('association_members')
            ->where('association_id', $this->associationId)
            ->where('user_id', $targetUserId)
            ->update(['effective_role' => $newRole]);

        $updated = $this->memberModel->findMemberWithUser($this->associationId, $targetUserId);

        if ($updated === null) {
            throw new \RuntimeException('Erreur lors de la récupération du membre mis à jour.');
        }

        return $updated;
    }

    /**
     * Retire un membre de l'association.
     * Réservé au président.
     *
     * Le retrait est un soft-delete : is_active=0, left_at=now().
     * L'historique est conservé indéfiniment.
     *
     * @param int $requesterId  Identifiant du président
     * @param int $targetUserId Identifiant du membre à retirer
     *
     * @throws \RuntimeException Si permissions insuffisantes ou règle violée
     */
    public function removeMember(int $requesterId, int $targetUserId): void
    {
        $requesterRole = $this->getEffectiveRole($requesterId);

        if ($requesterRole !== 'president') {
            throw new \RuntimeException('Accès refusé : seul le président peut retirer un membre.');
        }

        if ($requesterId === $targetUserId) {
            throw new \RuntimeException(
                'Vous ne pouvez pas vous retirer vous-même de l\'association.'
            );
        }

        $targetMembership = $this->memberModel->findMembership($this->associationId, $targetUserId);

        if ($targetMembership === null || (int) $targetMembership['is_active'] !== 1) {
            throw new \RuntimeException('Membre introuvable dans cette association.');
        }

        if ($targetMembership['effective_role'] === 'president') {
            throw new \RuntimeException(
                'Impossible de retirer le président via cette opération.'
            );
        }

        $this->db->table('association_members')
            ->where('association_id', $this->associationId)
            ->where('user_id', $targetUserId)
            ->update([
                'is_active' => 0,
                'left_at'   => $this->now(),
            ]);
    }

    // =========================================================================
    // Invitations
    // =========================================================================

    /**
     * Crée une invitation pour rejoindre l'association.
     * Accessible aux secrétaires et rôles supérieurs (treasurer, president).
     *
     * Règles :
     * - phone OU email obligatoire (pas les deux requis, mais au moins un)
     * - Pour tontine_group : seuls treasurer et member sont invitables
     * - Le rôle president ne peut pas être invité
     * - Pas de doublon d'invitation en attente pour le même contact
     * - Expiration automatique après 7 jours
     *
     * @param int                  $requesterId Identifiant du demandeur
     * @param array<string, mixed> $data        Données : phone?, email?, role?
     *
     * @return array<string, mixed> Données de l'invitation créée
     *
     * @throws \RuntimeException        Si permissions insuffisantes ou doublon
     * @throws \InvalidArgumentException Si validation des données échoue
     */
    public function invite(int $requesterId, array $data): array
    {
        $requesterRole = $this->getEffectiveRole($requesterId);

        if (!$this->hasRole($requesterRole, 'secretary')) {
            throw new \RuntimeException(
                'Accès refusé : rôle secrétaire ou supérieur requis pour inviter.'
            );
        }

        $phone = (isset($data['phone']) && $data['phone'] !== '') ? (string) $data['phone'] : null;
        $email = (isset($data['email']) && $data['email'] !== '') ? (string) $data['email'] : null;
        $role  = (string) ($data['role'] ?? 'member');

        if ($phone === null && $email === null) {
            throw new \InvalidArgumentException('Un téléphone ou un email est requis.');
        }

        if ($role === 'president') {
            throw new \InvalidArgumentException(
                'Le rôle président ne peut pas être attribué via invitation.'
            );
        }

        $type = $this->getAssociationType();

        if ($type === 'tontine_group' && !in_array($role, ['treasurer', 'member'], true)) {
            throw new \InvalidArgumentException(
                'Pour un tontine_group, les rôles invitables sont : treasurer et member.'
            );
        }

        if ($this->invitationModel->hasPendingInvitation($this->associationId, $phone, $email)) {
            throw new \RuntimeException(
                'Une invitation en attente existe déjà pour ce contact dans cette association.'
            );
        }

        $now       = $this->now();
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
        $token     = $this->generateToken();

        $invitationData = [
            'association_id' => $this->associationId,
            'invited_by'     => $requesterId,
            'phone'          => $phone,
            'email'          => $email,
            'token'          => $token,
            'role'           => $role,
            'status'         => 'pending',
            'expires_at'     => $expiresAt,
            'created_at'     => $now,
        ];

        $id = $this->invitationModel->insert($invitationData, true);

        // TODO Sprint 5 — Notifications : envoyer SMS via SmsLibrary si $phone !== null
        // TODO Sprint 5 — Notifications : envoyer email si $email !== null

        return array_merge(['id' => $id], $invitationData);
    }

    /**
     * Retourne la liste paginée des invitations de l'association.
     * Accessible aux secrétaires et rôles supérieurs.
     *
     * @param int $requesterId Identifiant du demandeur
     * @param int $page        Numéro de page
     * @param int $perPage     Éléments par page
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     *
     * @throws \RuntimeException Si permissions insuffisantes
     */
    public function listInvitations(int $requesterId, int $page, int $perPage): array
    {
        $requesterRole = $this->getEffectiveRole($requesterId);

        if (!$this->hasRole($requesterRole, 'secretary')) {
            throw new \RuntimeException(
                'Accès refusé : rôle secrétaire ou supérieur requis.'
            );
        }

        $offset = ($page - 1) * $perPage;
        $total  = $this->invitationModel->countByAssociation($this->associationId);
        $items  = $this->invitationModel->listByAssociation($this->associationId, $perPage, $offset);

        return [
            'data' => $items,
            'meta' => $this->paginationMeta($total, $page, $perPage),
        ];
    }

    /**
     * Annule une invitation en attente.
     * Accessible aux secrétaires et rôles supérieurs.
     *
     * @param int $requesterId   Identifiant du demandeur
     * @param int $invitationId  Identifiant de l'invitation
     *
     * @throws \RuntimeException Si permissions insuffisantes ou invitation introuvable/non annulable
     */
    public function cancelInvitation(int $requesterId, int $invitationId): void
    {
        $requesterRole = $this->getEffectiveRole($requesterId);

        if (!$this->hasRole($requesterRole, 'secretary')) {
            throw new \RuntimeException(
                'Accès refusé : rôle secrétaire ou supérieur requis.'
            );
        }

        $invitation = $this->db->table('invitations')
            ->where('id', $invitationId)
            ->where('association_id', $this->associationId)
            ->get()
            ->getRowArray();

        if ($invitation === null) {
            throw new \RuntimeException('Invitation introuvable.');
        }

        if ($invitation['status'] !== 'pending') {
            throw new \RuntimeException(
                'Seules les invitations en attente peuvent être annulées.'
            );
        }

        $this->db->table('invitations')
            ->where('id', $invitationId)
            ->update(['status' => 'expired']);
    }

    /**
     * Accepte une invitation par son token et crée l'adhésion.
     *
     * Pré-condition : l'invitation a déjà été vérifiée et chargée par le contrôleur.
     *
     * Logique :
     * - Vérifier que le token destinataire correspond à l'utilisateur JWT
     * - Si l'utilisateur est déjà membre actif → erreur
     * - Si l'utilisateur avait une adhésion inactive → réactivation
     * - Sinon → nouvelle adhésion
     * - Marquer l'invitation comme accepted
     * - Toutes les opérations DB sont enveloppées dans une transaction
     *
     * @param array<string, mixed> $invitation Données de l'invitation (depuis InvitationModel)
     * @param int                  $userId     Identifiant de l'utilisateur qui accepte
     *
     * @return array<string, mixed> Données d'adhésion créée
     *
     * @throws \RuntimeException Si l'invitation est invalide, expirée, non destinée à l'utilisateur
     *                           ou si l'utilisateur est déjà membre
     */
    public function acceptInvitation(array $invitation, int $userId): array
    {
        if ($invitation['status'] !== 'pending') {
            throw new \RuntimeException('Cette invitation a déjà été utilisée ou annulée.');
        }

        if (strtotime((string) $invitation['expires_at']) < time()) {
            $this->db->table('invitations')
                ->where('id', $invitation['id'])
                ->update(['status' => 'expired']);

            throw new \RuntimeException('Cette invitation a expiré.');
        }

        // Vérifier que l'invitation est bien destinée à cet utilisateur
        $invitedUser = $this->db->table('users')
            ->select('phone, email')
            ->where('id', $userId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($invitedUser !== null) {
            $phoneMatch = $invitation['phone'] !== null && $invitedUser['phone'] === $invitation['phone'];
            $emailMatch = $invitation['email'] !== null && $invitedUser['email'] === $invitation['email'];

            // Si l'invitation cible un contact spécifique, vérifier la correspondance
            if (($invitation['phone'] !== null || $invitation['email'] !== null) && !$phoneMatch && !$emailMatch) {
                throw new \RuntimeException('Ce token d\'invitation ne vous est pas destiné.');
            }
        }

        $existing = $this->memberModel->findMembership($this->associationId, $userId);

        if ($existing !== null && (int) $existing['is_active'] === 1) {
            throw new \RuntimeException('Vous êtes déjà membre actif de cette association.');
        }

        $now = $this->now();

        $this->db->transStart();

        if ($existing !== null) {
            // Réactivation d'un ancien membre
            $this->db->table('association_members')
                ->where('association_id', $this->associationId)
                ->where('user_id', $userId)
                ->update([
                    'effective_role' => $invitation['role'],
                    'is_active'      => 1,
                    'left_at'        => null,
                    'joined_at'      => $now,
                ]);
        } else {
            // Nouvelle adhésion
            $this->db->table('association_members')->insert([
                'association_id' => $this->associationId,
                'user_id'        => $userId,
                'effective_role' => $invitation['role'],
                'joined_at'      => $now,
                'is_active'      => 1,
            ]);
        }

        $this->db->table('invitations')
            ->where('id', $invitation['id'])
            ->update(['status' => 'accepted']);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException('Erreur lors de la création de l\'adhésion (transaction échouée).');
        }

        $member = $this->memberModel->findMemberWithUser($this->associationId, $userId);

        if ($member === null) {
            throw new \RuntimeException('Erreur lors de la création de l\'adhésion.');
        }

        return $member;
    }

    // =========================================================================
    // Vue d'ensemble cross-associations
    // =========================================================================

    /**
     * Retourne la vue d'ensemble de l'utilisateur sur toutes ses associations.
     * Endpoint GET /api/me/overview.
     *
     * Les données tontines/loans/solidarity sont des stubs (0) jusqu'à
     * l'implémentation des modules correspondants (Sprint 2, 3, 4).
     *
     * @param int $userId Identifiant de l'utilisateur connecté
     *
     * @return array{associations: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function getOverview(int $userId): array
    {
        $memberships  = $this->memberModel->getAssociationsForUser($userId);
        $associations = [];

        foreach ($memberships as $m) {
            $associations[] = [
                'association' => [
                    'id'   => $m['association_id'],
                    'name' => $m['name'],
                    'slug' => $m['slug'],
                    'type' => $m['type'],
                    'logo' => $m['logo'],
                ],
                'role'      => $m['effective_role'],
                'joined_at' => $m['joined_at'],
                // Stubs — à enrichir Sprint 2 (Tontines), Sprint 3 (Loans), Sprint 4 (Solidarity)
                'tontines'   => [
                    'active'            => 0,
                    'total_contributed' => 0,
                    'pending_to_receive' => 0,
                ],
                'loans' => [
                    'active_count'   => 0,
                    'total_debt'     => 0,
                    'next_repayment' => null,
                ],
                'solidarity' => [
                    'total_contributed' => 0,
                    'balance_fund'      => 0,
                ],
            ];
        }

        return [
            'associations' => $associations,
            'totals'       => [
                'total_contributed' => 0,
                'total_debt'        => 0,
                'net_position'      => 0,
            ],
        ];
    }
}
