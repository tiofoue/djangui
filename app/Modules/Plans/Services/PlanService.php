<?php

declare(strict_types=1);

namespace App\Modules\Plans\Services;

use App\Modules\Plans\Models\PlanModel;
use App\Modules\Plans\Models\SubscriptionModel;

/**
 * Service Plans & Souscriptions.
 *
 * Gère les abonnements et la vérification des quotas SaaS.
 * N'étend pas BaseService car opère sur des ressources globales.
 */
class PlanService
{
    private PlanModel $planModel;
    private SubscriptionModel $subscriptionModel;

    public function __construct()
    {
        $this->planModel         = new PlanModel();
        $this->subscriptionModel = new SubscriptionModel();
    }

    /**
     * Retourne tous les plans actifs (liste publique).
     *
     * @return array{plans: list<array<string, mixed>>}
     */
    public function getPlans(): array
    {
        return ['plans' => $this->planModel->getActivePlans()];
    }

    /**
     * Retourne la souscription courante d'une association avec données du plan.
     *
     * Contrôle d'accès : l'utilisateur doit être membre actif de l'association,
     * sauf si super_admin.
     *
     * @param int  $associationId Identifiant de l'association
     * @param int  $userId        Identifiant de l'utilisateur authentifié
     * @param bool $isSuperAdmin  True si super_admin
     *
     * @throws \RuntimeException Si accès refusé ou aucune souscription n'existe
     *
     * @return array<string, mixed>
     */
    public function getSubscription(int $associationId, int $userId, bool $isSuperAdmin = false): array
    {
        if (!$isSuperAdmin) {
            $this->requireMembership($associationId, $userId);
        }

        $sub = $this->subscriptionModel->getWithPlan($associationId);

        if ($sub === null) {
            throw new \RuntimeException('Aucune souscription trouvée pour cette association.');
        }

        return $sub;
    }

    /**
     * Souscrit une association à un plan (création ou mise à jour).
     *
     * Contrôle d'accès : seul le président ou un super_admin peut souscrire.
     *
     * @param int    $associationId Identifiant de l'association
     * @param string $planName      Nom technique du plan (ex: 'free', 'pro')
     * @param int    $userId        Identifiant de l'utilisateur authentifié
     * @param bool   $isSuperAdmin  True si super_admin
     *
     * @throws \RuntimeException Si accès refusé ou plan invalide
     *
     * @return array<string, mixed>
     */
    public function subscribe(int $associationId, string $planName, int $userId = 0, bool $isSuperAdmin = false): array
    {
        if (!$isSuperAdmin) {
            $this->requirePresident($associationId, $userId);
        }

        $plan = $this->planModel->findByName($planName);

        if ($plan === null || !(bool) $plan['is_active']) {
            throw new \RuntimeException("Plan '{$planName}' introuvable ou inactif.");
        }

        $now       = gmdate('Y-m-d H:i:s');
        $periodEnd = gmdate('Y-m-d H:i:s', strtotime('+30 days'));
        $existing  = $this->subscriptionModel->getForAssociation($associationId);

        $data = [
            'plan_id'              => (int) $plan['id'],
            'status'               => 'active',
            'current_period_start' => $now,
            'current_period_end'   => $periodEnd,
            'cancelled_at'         => null,
        ];

        if ($existing !== null) {
            $this->subscriptionModel->update((int) $existing['id'], $data);
        } else {
            $data['association_id'] = $associationId;
            $data['trial_ends_at']  = null;
            $this->subscriptionModel->insert($data);
        }

        return $this->subscriptionModel->getWithPlan($associationId) ?? [];
    }

    /**
     * Annule la souscription d'une association.
     *
     * Contrôle d'accès : seul le président ou un super_admin peut annuler.
     *
     * @param int  $associationId Identifiant de l'association
     * @param int  $userId        Identifiant de l'utilisateur authentifié
     * @param bool $isSuperAdmin  True si super_admin
     *
     * @throws \RuntimeException Si accès refusé ou aucune souscription active
     *
     * @return void
     */
    public function cancel(int $associationId, int $userId = 0, bool $isSuperAdmin = false): void
    {
        if (!$isSuperAdmin) {
            $this->requirePresident($associationId, $userId);
        }

        $sub = $this->subscriptionModel->getForAssociation($associationId);

        if ($sub === null || $sub['status'] === 'cancelled') {
            throw new \RuntimeException('Aucune souscription active à annuler.');
        }

        $this->subscriptionModel->update((int) $sub['id'], [
            'status'       => 'cancelled',
            'cancelled_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Crée la souscription trial gratuite (plan 'free') à la création d'une association.
     * Appelé automatiquement par AssociationService::create().
     *
     * @param int $associationId Identifiant de l'association nouvellement créée
     *
     * @return void
     */
    public function createFreeTrial(int $associationId): void
    {
        $free = $this->planModel->findByName('free');

        if ($free === null) {
            return; // Plan free non seédé — skip silencieusement
        }

        $now = gmdate('Y-m-d H:i:s');

        $this->subscriptionModel->insert([
            'association_id'       => $associationId,
            'plan_id'              => (int) $free['id'],
            'status'               => 'trial',
            'trial_ends_at'        => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
            'current_period_start' => $now,
            'current_period_end'   => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
    }

    /**
     * Vérifie si une association peut encore créer une ressource selon son plan.
     *
     * @param int    $associationId Identifiant de l'association
     * @param string $resource      Type de ressource : 'members' | 'tontines' | 'entities'
     * @param int    $currentCount  Nombre actuel de ressources
     *
     * @return bool True si le quota n'est pas atteint
     */
    public function checkQuota(int $associationId, string $resource, int $currentCount): bool
    {
        $sub = $this->subscriptionModel->getWithPlan($associationId);

        if ($sub === null) {
            return true; // Pas de souscription = pas de limite appliquée
        }

        $limitKey = match ($resource) {
            'members'  => 'max_members',
            'tontines' => 'max_tontines',
            'entities' => 'max_entities',
            default    => null,
        };

        if ($limitKey === null || $sub[$limitKey] === null) {
            return true; // NULL = illimité
        }

        return $currentCount < (int) $sub[$limitKey];
    }

    // =========================================================================
    // Helpers privés — contrôle d'accès
    // =========================================================================

    /**
     * Vérifie que l'utilisateur est membre actif de l'association.
     *
     * @param int $associationId Identifiant de l'association
     * @param int $userId        Identifiant de l'utilisateur
     *
     * @throws \RuntimeException Si l'utilisateur n'est pas membre
     */
    private function requireMembership(int $associationId, int $userId): void
    {
        $db = \Config\Database::connect();

        $count = $db->table('association_members')
            ->where('association_id', $associationId)
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->where('left_at IS NULL')
            ->countAllResults();

        if ($count === 0) {
            throw new \RuntimeException('Accès refusé : vous n\'êtes pas membre de cette association.');
        }
    }

    /**
     * Vérifie que l'utilisateur est président actif de l'association.
     *
     * @param int $associationId Identifiant de l'association
     * @param int $userId        Identifiant de l'utilisateur
     *
     * @throws \RuntimeException Si l'utilisateur n'est pas président
     */
    private function requirePresident(int $associationId, int $userId): void
    {
        $db = \Config\Database::connect();

        $count = $db->table('association_members')
            ->where('association_id', $associationId)
            ->where('user_id', $userId)
            ->where('effective_role', 'president')
            ->where('is_active', 1)
            ->where('left_at IS NULL')
            ->countAllResults();

        if ($count === 0) {
            throw new \RuntimeException(
                'Accès refusé : seul le président de l\'association peut effectuer cette action.'
            );
        }
    }
}
