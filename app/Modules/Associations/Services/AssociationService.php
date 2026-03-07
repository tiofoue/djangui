<?php

declare(strict_types=1);

namespace App\Modules\Associations\Services;

use App\Modules\Associations\Models\AssociationModel;
use App\Modules\Associations\Models\AssociationSettingModel;
use App\Modules\Plans\Services\PlanService;

/**
 * Service de gestion des associations, settings et workflows d'approbation.
 *
 * N'étend pas BaseService : opère sur plusieurs associations simultanément
 * (liste globale, admin, multi-tenant croisé).
 */
class AssociationService
{
    /**
     * Clés système autorisées (is_custom = 0).
     * Seules ces clés peuvent être envoyées avec is_custom=false par le client.
     * Un attaquant ne peut pas créer de nouvelles clés système arbitraires.
     *
     * @var list<string>
     */
    private const SYSTEM_SETTING_KEYS = [
        'timezone',
        'tontine_default_amount',
        'late_penalty_type',
        'late_penalty_value',
        'loan_max_rate',
        'loan_default_interest_type',
        'loan_max_duration_months',
        'loan_requires_guarantor',
        'solidarity_monthly_amount',
        'rotation_default_mode',
        'invitation_requires_approval',
        'loan_default_delay_days',
        'presence_amount',
        'savings_enabled',
        'cycle_start_month',
        'loan_interest_distribution',
    ];

    private AssociationModel $assocModel;
    private AssociationSettingModel $settingModel;
    private PlanService $planService;

    public function __construct()
    {
        $this->assocModel   = new AssociationModel();
        $this->settingModel = new AssociationSettingModel();
        $this->planService  = new PlanService();
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Génère un UUID v4.
     *
     * @return string UUID v4 au format standard (xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx)
     */
    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Génère un slug depuis un nom (translitération + normalisation).
     * Convertit les accents, passe en minuscules, remplace les espaces et
     * caractères spéciaux par des tirets.
     *
     * @param string $name Nom de l'association
     *
     * @return string Slug normalisé
     */
    private function generateSlug(string $name): string
    {
        // Table de translitération des caractères accentués courants
        $transliterations = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ß' => 'ss', 'ÿ' => 'y', 'œ' => 'oe', 'À' => 'a', 'Á' => 'a', 'Â' => 'a',
            'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a', 'Æ' => 'ae', 'Ç' => 'c', 'È' => 'e',
            'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i',
            'Ï' => 'i', 'Ð' => 'd', 'Ñ' => 'n', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o',
            'Õ' => 'o', 'Ö' => 'o', 'Ø' => 'o', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u',
            'Ü' => 'u', 'Ý' => 'y', 'Þ' => 'th', 'Œ' => 'oe',
        ];

        $slug = strtr($name, $transliterations);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Génère un slug unique en ajoutant un suffixe numérique si nécessaire.
     *
     * @param string   $name      Nom de l'association
     * @param int|null $excludeId ID à exclure lors de la vérification (pour update)
     *
     * @return string Slug unique
     */
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = $this->generateSlug($name);
        $slug     = $baseSlug;
        $counter  = 2;

        while ($this->assocModel->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Normalise une clé de setting à partir du label.
     * Algorithme : trim → lowercase → translitération accents →
     * [^a-z0-9]+ → _ → dédoublonnage _ → trim _.
     *
     * @param string $label Libellé original du setting
     *
     * @return string Clé normalisée (snake_case)
     */
    private function normalizeSettingKey(string $label): string
    {
        $transliterations = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u',
            'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe',
            'ß' => 'ss', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Ç' => 'c',
            'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Î' => 'i', 'Ï' => 'i',
            'Ñ' => 'n', 'Ô' => 'o', 'Ö' => 'o', 'Û' => 'u', 'Ü' => 'u', 'Œ' => 'oe',
        ];

        $key = trim($label);
        $key = strtr($key, $transliterations);
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        $key = preg_replace('/_+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return $key;
    }

    /**
     * Retourne la date et l'heure courante en UTC (format MySQL).
     *
     * @return string Date au format 'Y-m-d H:i:s' (UTC)
     */
    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Vérifie si un utilisateur est membre actif d'une association.
     * Retourne les données du membership ou null si non membre.
     *
     * @param int $associationId Identifiant de l'association
     * @param int $userId        Identifiant de l'utilisateur
     *
     * @return array<string, mixed>|null
     */
    private function checkMembership(int $associationId, int $userId): ?array
    {
        $db = \Config\Database::connect();

        $result = $db->table('association_members')
            ->where('association_id', $associationId)
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->where('left_at IS NULL')
            ->get()
            ->getRowArray();

        return $result ?: null;
    }

    /**
     * Vérifie que l'utilisateur est président de l'association.
     * Lève une RuntimeException si ce n'est pas le cas.
     *
     * @param int $associationId Identifiant de l'association
     * @param int $userId        Identifiant de l'utilisateur
     *
     * @throws \RuntimeException Si l'utilisateur n'est pas président
     *
     * @return void
     */
    private function checkPresidentAccess(int $associationId, int $userId): void
    {
        $membership = $this->checkMembership($associationId, $userId);

        if ($membership === null || $membership['effective_role'] !== 'president') {
            throw new \RuntimeException(
                'Accès refusé : seul le président de l\'association peut effectuer cette action.'
            );
        }
    }

    /**
     * Insère le créateur comme membre avec le rôle de président.
     *
     * @param int $associationId Identifiant de l'association créée
     * @param int $userId        Identifiant du créateur
     *
     * @return void
     */
    private function addCreatorAsMember(int $associationId, int $userId): void
    {
        $db = \Config\Database::connect();

        $db->table('association_members')->insert([
            'association_id'  => $associationId,
            'user_id'         => $userId,
            'effective_role'  => 'president',
            'joined_at'       => $this->now(),
            'is_active'       => 1,
        ]);
    }

    // =========================================================================
    // CRUD principal
    // =========================================================================

    /**
     * Crée une nouvelle association.
     *
     * Règles métier :
     * - tontine_group : status = 'active', créateur → président immédiatement
     * - association/federation : status = 'pending_review', statutes requis
     * - UUID v4 et slug unique générés automatiquement
     * - Souscription trial créée via PlanService
     *
     * @param array<string, mixed> $data            Données de l'association
     * @param int                  $createdByUserId Identifiant du créateur (extrait du JWT)
     *
     * @throws \InvalidArgumentException Si les statuts sont manquants pour association/federation
     *
     * @return array<string, mixed>
     */
    public function create(array $data, int $createdByUserId): array
    {
        // ── Rate-limiting : max 10 créations par utilisateur par heure (anti-spam)
        $rateLimitKey   = "assoc_create_rate:{$createdByUserId}";
        $creationsCount = (int) (cache()->get($rateLimitKey) ?? 0);

        if ($creationsCount >= 10) {
            throw new \RuntimeException(
                'Limite atteinte : vous ne pouvez créer que 10 associations par heure.'
            );
        }

        $type = (string) ($data['type'] ?? 'association');

        // Validation métier : statuts requis pour association et fédération
        if (in_array($type, ['association', 'federation'], true)) {
            $hasStatutes = !empty($data['statutes_text']) || !empty($data['statutes_file']);

            if (!$hasStatutes) {
                throw new \InvalidArgumentException(
                    'Les statuts (texte ou fichier) sont obligatoires pour une association ou fédération.'
                );
            }
        }

        // Validation métier : parent_id doit référencer une fédération active dont le créateur est membre
        if (!empty($data['parent_id'])) {
            $parentId = (int) $data['parent_id'];

            try {
                $parent = $this->findAssociationOrFail($parentId);
            } catch (\RuntimeException $e) {
                throw new \InvalidArgumentException('La fédération parente (parent_id) est introuvable.');
            }

            if ($parent['type'] !== 'federation') {
                throw new \InvalidArgumentException('parent_id doit référencer une association de type fédération.');
            }

            if ($parent['status'] !== 'active') {
                throw new \InvalidArgumentException('La fédération parente doit être active.');
            }

            if ($this->checkMembership($parentId, $createdByUserId) === null) {
                throw new \InvalidArgumentException('Vous devez être membre de la fédération parente.');
            }
        }

        $name = (string) ($data['name'] ?? '');
        $slug = $this->generateUniqueSlug($name);

        // Statut selon le type
        $status = ($type === 'tontine_group') ? 'active' : 'pending_review';

        $insertData = [
            'uuid'           => $this->generateUuid(),
            'name'           => $name,
            'slug'           => $slug,
            'type'           => $type,
            'status'         => $status,
            'created_by'     => $createdByUserId,
            'description'    => $data['description'] ?? null,
            'slogan'         => $data['slogan'] ?? null,
            'logo'           => $data['logo'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'address'        => $data['address'] ?? null,
            'bp'             => $data['bp'] ?? null,
            'tax_number'     => $data['tax_number'] ?? null,
            'auth_number'    => $data['auth_number'] ?? null,
            'country'        => $data['country'] ?? 'CM',
            'currency'       => $data['currency'] ?? 'XAF',
            'parent_id'      => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'statutes_text'  => $data['statutes_text'] ?? null,
            'statutes_file'  => $data['statutes_file'] ?? null,
        ];

        $db = \Config\Database::connect();
        $db->transStart();

        $id = $this->assocModel->insert($insertData, true);

        if ($id === false || $id === 0) {
            $db->transRollback();
            throw new \RuntimeException(
                'Erreur lors de la création de l\'association : '
                . implode(', ', $this->assocModel->errors())
            );
        }

        $associationId = (int) $id;

        // Ajouter le créateur comme président
        $this->addCreatorAsMember($associationId, $createdByUserId);

        // Créer la souscription trial gratuite
        $this->planService->createFreeTrial($associationId);

        $db->transComplete();

        if (!$db->transStatus()) {
            throw new \RuntimeException('Erreur lors de la création de l\'association (transaction échouée).');
        }

        // Incrémenter le compteur de rate-limiting (TTL = 1 heure)
        cache()->save($rateLimitKey, $creationsCount + 1, 3600);

        return $this->findAssociationOrFail($associationId);
    }

    /**
     * Retourne les détails d'une association (vérification d'appartenance pour non-admin).
     *
     * @param int $id     Identifiant de l'association
     * @param int $userId Identifiant de l'utilisateur authentifié
     *
     * @throws \RuntimeException Si l'association n'existe pas ou accès refusé
     *
     * @return array<string, mixed>
     */
    public function getById(int $id, int $userId): array
    {
        $association = $this->findAssociationOrFail($id);

        // Vérifier que l'utilisateur est membre
        $membership = $this->checkMembership($id, $userId);

        if ($membership === null) {
            throw new \RuntimeException('Accès refusé : vous n\'êtes pas membre de cette association.');
        }

        $association['your_role'] = $membership['effective_role'];

        return $association;
    }

    /**
     * Retourne toutes les associations dont l'utilisateur est membre actif.
     *
     * @param int $userId Identifiant de l'utilisateur authentifié
     *
     * @return array{associations: list<array<string, mixed>>}
     */
    public function getMine(int $userId): array
    {
        return ['associations' => $this->assocModel->findByUserId($userId)];
    }

    /**
     * Met à jour une association.
     * Seul le président ou un super_admin peut modifier.
     *
     * @param int                  $id            Identifiant de l'association
     * @param array<string, mixed> $data          Données à mettre à jour
     * @param int                  $userId        Identifiant de l'utilisateur authentifié
     * @param bool                 $isSuperAdmin  True si super_admin
     *
     * @throws \RuntimeException Si accès refusé ou association introuvable
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $userId, bool $isSuperAdmin): array
    {
        $this->findAssociationOrFail($id);

        if (!$isSuperAdmin) {
            $this->checkPresidentAccess($id, $userId);
        }

        $updateData = array_filter([
            'description'   => $data['description'] ?? null,
            'slogan'        => $data['slogan'] ?? null,
            'logo'          => $data['logo'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'address'       => $data['address'] ?? null,
            'bp'            => $data['bp'] ?? null,
            'tax_number'    => $data['tax_number'] ?? null,
            'auth_number'   => $data['auth_number'] ?? null,
            'country'       => $data['country'] ?? null,
            'currency'      => $data['currency'] ?? null,
            'statutes_text' => $data['statutes_text'] ?? null,
            'statutes_file' => $data['statutes_file'] ?? null,
        ], fn($v) => $v !== null);

        // Le nom peut être mis à jour — regénérer le slug si nécessaire
        if (!empty($data['name'])) {
            $updateData['name'] = $data['name'];
            $updateData['slug'] = $this->generateUniqueSlug($data['name'], $id);
        }

        if (!empty($updateData)) {
            $this->assocModel->update($id, $updateData);
        }

        return $this->findAssociationOrFail($id);
    }

    /**
     * Supprime (soft delete) une association.
     * Lève une exception si des tontines actives existent.
     *
     * @param int  $id           Identifiant de l'association
     * @param int  $userId       Identifiant de l'utilisateur authentifié
     * @param bool $isSuperAdmin True si super_admin
     *
     * @throws \RuntimeException Si accès refusé ou tontines actives détectées
     *
     * @return void
     */
    public function delete(int $id, int $userId, bool $isSuperAdmin): void
    {
        $this->findAssociationOrFail($id);

        if (!$isSuperAdmin) {
            $this->checkPresidentAccess($id, $userId);
        }

        // Vérifier l'absence de tontines actives si la table existe (Sprint 2+)
        $db = \Config\Database::connect();

        if ($db->tableExists('tontines')) {
            $activeTontines = $db->table('tontines')
                ->where('association_id', $id)
                ->whereIn('status', ['active', 'collecting'])
                ->where('deleted_at IS NULL')
                ->countAllResults();

            if ($activeTontines > 0) {
                throw new \RuntimeException(
                    'Impossible de supprimer : ' . $activeTontines
                    . ' tontine(s) active(s) en cours. Clôturez-les d\'abord.'
                );
            }
        }

        $this->assocModel->delete($id);
    }

    // =========================================================================
    // Workflows d'approbation (super_admin)
    // =========================================================================

    /**
     * Approuve une association en attente.
     *
     * @param int $id      Identifiant de l'association
     * @param int $adminId Identifiant de l'administrateur
     *
     * @throws \RuntimeException Si l'association n'est pas en statut pending_review
     *
     * @return array<string, mixed>
     */
    public function approve(int $id, int $adminId): array
    {
        $association = $this->findAssociationOrFail($id);

        if ($association['status'] !== 'pending_review') {
            throw new \RuntimeException(
                'Seules les associations en attente de revue peuvent être approuvées.'
            );
        }

        $this->assocModel->update($id, [
            'status'      => 'active',
            'reviewed_by' => $adminId,
            'reviewed_at' => $this->now(),
        ]);

        return $this->findAssociationOrFail($id);
    }

    /**
     * Rejette une association en attente avec un motif.
     *
     * @param int    $id      Identifiant de l'association
     * @param int    $adminId Identifiant de l'administrateur
     * @param string $reason  Motif du rejet
     *
     * @throws \RuntimeException Si l'association n'est pas en statut pending_review
     *
     * @return array<string, mixed>
     */
    public function reject(int $id, int $adminId, string $reason): array
    {
        $association = $this->findAssociationOrFail($id);

        if ($association['status'] !== 'pending_review') {
            throw new \RuntimeException(
                'Seules les associations en attente de revue peuvent être rejetées.'
            );
        }

        $this->assocModel->update($id, [
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => $adminId,
            'reviewed_at'      => $this->now(),
        ]);

        return $this->findAssociationOrFail($id);
    }

    /**
     * Suspend une association active.
     *
     * @param int $id      Identifiant de l'association
     * @param int $adminId Identifiant de l'administrateur
     *
     * @throws \RuntimeException Si l'association n'est pas active
     *
     * @return array<string, mixed>
     */
    public function suspend(int $id, int $adminId): array
    {
        $association = $this->findAssociationOrFail($id);

        if ($association['status'] !== 'active') {
            throw new \RuntimeException(
                'Seules les associations actives peuvent être suspendues.'
            );
        }

        $this->assocModel->update($id, [
            'status'      => 'suspended',
            'reviewed_by' => $adminId,
            'reviewed_at' => $this->now(),
        ]);

        return $this->findAssociationOrFail($id);
    }

    /**
     * Réactive une association suspendue ou rejetée.
     *
     * @param int $id      Identifiant de l'association
     * @param int $adminId Identifiant de l'administrateur
     *
     * @throws \RuntimeException Si l'association n'est pas suspendue ou rejetée
     *
     * @return array<string, mixed>
     */
    public function reinstate(int $id, int $adminId): array
    {
        $association = $this->findAssociationOrFail($id);

        if (!in_array($association['status'], ['suspended', 'rejected'], true)) {
            throw new \RuntimeException(
                'Seules les associations suspendues ou rejetées peuvent être réactivées.'
            );
        }

        $this->assocModel->update($id, [
            'status'           => 'active',
            'rejection_reason' => null,
            'reviewed_by'      => $adminId,
            'reviewed_at'      => $this->now(),
        ]);

        return $this->findAssociationOrFail($id);
    }

    // =========================================================================
    // Listes admin (super_admin)
    // =========================================================================

    /**
     * Retourne toutes les associations en attente de revue (paginé).
     *
     * @param int $page    Numéro de page (commence à 1)
     * @param int $perPage Nombre d'éléments par page
     *
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAllPending(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = $this->assocModel->countPending();
        $items  = $this->assocModel->findPending($perPage, $offset);

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * Retourne toutes les associations (paginé, super_admin).
     *
     * @param int $page    Numéro de page (commence à 1)
     * @param int $perPage Nombre d'éléments par page
     *
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = $this->assocModel->countAllActive();
        $items  = $this->assocModel->findAllPaginated($perPage, $offset);

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    // =========================================================================
    // Sous-associations (federation)
    // =========================================================================

    /**
     * Retourne les sous-associations d'une fédération.
     * L'utilisateur doit être membre de la fédération parente.
     *
     * @param int $federationId Identifiant de la fédération parente
     * @param int $userId       Identifiant de l'utilisateur authentifié
     *
     * @throws \RuntimeException Si l'association n'est pas une fédération ou accès refusé
     *
     * @return array{children: list<array<string, mixed>>}
     */
    public function getChildren(int $federationId, int $userId): array
    {
        $federation = $this->findAssociationOrFail($federationId);

        if ($federation['type'] !== 'federation') {
            throw new \RuntimeException('Cette association n\'est pas une fédération.');
        }

        $membership = $this->checkMembership($federationId, $userId);

        if ($membership === null) {
            throw new \RuntimeException(
                'Accès refusé : vous n\'êtes pas membre de cette fédération.'
            );
        }

        return ['children' => $this->assocModel->findChildren($federationId)];
    }

    // =========================================================================
    // Settings
    // =========================================================================

    /**
     * Retourne tous les settings d'une association.
     * L'utilisateur doit être membre de l'association.
     *
     * @param int $associationId Identifiant de l'association
     * @param int $userId        Identifiant de l'utilisateur authentifié
     *
     * @throws \RuntimeException Si accès refusé
     *
     * @return array{settings: list<array<string, mixed>>}
     */
    public function getSettings(int $associationId, int $userId): array
    {
        $this->findAssociationOrFail($associationId);

        $membership = $this->checkMembership($associationId, $userId);

        if ($membership === null) {
            throw new \RuntimeException('Accès refusé : vous n\'êtes pas membre de cette association.');
        }

        return ['settings' => $this->settingModel->getAllForAssociation($associationId)];
    }

    /**
     * Met à jour ou crée des settings pour une association.
     * Seul le président peut modifier les settings.
     * Les clés système (is_custom=0) peuvent être modifiées mais pas supprimées.
     * Les clés custom (is_custom=1) sont normalisées depuis le label.
     *
     * @param int                        $associationId Identifiant de l'association
     * @param list<array<string, mixed>> $settings      Liste de settings à créer/mettre à jour
     * @param int                        $userId        Identifiant de l'utilisateur authentifié
     *
     * @throws \RuntimeException Si accès refusé
     *
     * @return array{settings: list<array<string, mixed>>}
     */
    public function updateSettings(int $associationId, array $settings, int $userId): array
    {
        $this->findAssociationOrFail($associationId);
        $this->checkPresidentAccess($associationId, $userId);

        foreach ($settings as $entry) {
            $label    = (string) ($entry['label'] ?? '');
            $value    = isset($entry['value']) ? (string) $entry['value'] : null;
            $isCustom = (bool) ($entry['is_custom'] ?? true);

            // Si une clé est fournie explicitement et qu'il s'agit d'un setting système (is_custom=0)
            if (!$isCustom && !empty($entry['key'])) {
                $key = (string) $entry['key'];

                // Sécurité : seules les clés système prédéfinies sont acceptées avec is_custom=false.
                // Empêche la création/falsification de clés système arbitraires par le client.
                if (!in_array($key, self::SYSTEM_SETTING_KEYS, true)) {
                    continue; // Ignorer silencieusement les clés système non reconnues
                }
            } else {
                // Normaliser la clé depuis le label pour les settings custom
                $key = $this->normalizeSettingKey($label);
            }

            if ($key === '') {
                continue; // Ignorer les entrées sans clé valide
            }

            $this->settingModel->upsertSetting(
                $associationId,
                $key,
                $label,
                $value,
                $isCustom
            );
        }

        return ['settings' => $this->settingModel->getAllForAssociation($associationId)];
    }

    // =========================================================================
    // Helper interne
    // =========================================================================

    /**
     * Récupère une association par son ID ou lève une exception.
     *
     * @param int $id Identifiant de l'association
     *
     * @throws \RuntimeException Si l'association n'existe pas ou est supprimée
     *
     * @return array<string, mixed>
     */
    private function findAssociationOrFail(int $id): array
    {
        $association = $this->assocModel->find($id);

        if ($association === null) {
            throw new \RuntimeException('Association introuvable.');
        }

        // Conversion Entity → array si nécessaire
        if (method_exists($association, 'toPublicArray')) {
            return $association->toPublicArray();
        }

        if (method_exists($association, 'toArray')) {
            return $association->toArray();
        }

        return (array) $association;
    }
}
