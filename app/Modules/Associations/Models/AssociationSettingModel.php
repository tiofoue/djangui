<?php

declare(strict_types=1);

namespace App\Modules\Associations\Models;

use App\Common\BaseModel;

/**
 * Modèle pour la table `association_settings`.
 *
 * PAS de updated_at ni deleted_at (design intentionnel).
 * Les opérations de mise à jour passent par upsertSetting().
 */
class AssociationSettingModel extends BaseModel
{
    protected $table      = 'association_settings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    /**
     * Scopé par association_id — isolation multi-tenant.
     *
     * @var bool
     */
    protected bool $scopedByAssociation = true;

    /**
     * Pas de timestamps automatiques (created_at géré manuellement).
     *
     * @var bool
     */
    protected $useTimestamps = false;

    /**
     * Pas de soft delete sur cette table.
     *
     * @var bool
     */
    protected $useSoftDeletes = false;

    /**
     * Champs autorisés pour les insertions et mises à jour.
     *
     * @var list<string>
     */
    protected $allowedFields = [
        'association_id', 'key', 'label', 'value', 'is_custom', 'created_at',
    ];

    /**
     * Règles de validation du modèle.
     *
     * @var array<string, string>
     */
    protected $validationRules = [
        'key'   => 'required|max_length[100]',
        'value' => 'permit_empty',
    ];

    // -------------------------------------------------------------------------
    // Méthodes spécialisées
    // -------------------------------------------------------------------------

    /**
     * Retourne tous les settings d'une association.
     *
     * @param int $associationId Identifiant de l'association
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllForAssociation(int $associationId): array
    {
        return $this->forAssociation($associationId)
            ->orderBy('is_custom', 'ASC')
            ->orderBy('key', 'ASC')
            ->findAll();
    }

    /**
     * Retourne un setting par clé pour une association.
     *
     * @param int    $associationId Identifiant de l'association
     * @param string $key           Clé du setting
     *
     * @return array<string, mixed>|null
     */
    public function getByKey(int $associationId, string $key): ?array
    {
        return $this->forAssociation($associationId)
            ->where($this->table . '.key', $key)
            ->first();
    }

    /**
     * Insert ou met à jour un setting (upsert par association_id + key).
     * Utilise une vérification d'existence puis INSERT ou UPDATE.
     *
     * @param int         $associationId Identifiant de l'association
     * @param string      $key           Clé normalisée
     * @param string      $label         Libellé original
     * @param string|null $value         Valeur
     * @param bool        $isCustom      true si clé personnalisée
     *
     * @return void
     */
    public function upsertSetting(
        int $associationId,
        string $key,
        string $label,
        ?string $value,
        bool $isCustom = true
    ): void {
        $existing = $this->getByKey($associationId, $key);

        if ($existing !== null) {
            $this->db->table($this->table)
                ->where('association_id', $associationId)
                ->where('key', $key)
                ->update(['label' => $label, 'value' => $value]);
        } else {
            $this->db->table($this->table)->insert([
                'association_id' => $associationId,
                'key'            => $key,
                'label'          => $label,
                'value'          => $value,
                'is_custom'      => (int) $isCustom,
                'created_at'     => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Supprime un setting personnalisé (is_custom = 1).
     * Les clés système (is_custom = 0) ne peuvent pas être supprimées.
     *
     * @param int    $associationId Identifiant de l'association
     * @param string $key           Clé à supprimer
     *
     * @return bool True si supprimé, false si clé système ou inexistante
     */
    public function deleteCustom(int $associationId, string $key): bool
    {
        $setting = $this->getByKey($associationId, $key);

        if ($setting === null || !(bool) $setting['is_custom']) {
            return false;
        }

        $this->db->table($this->table)
            ->where('association_id', $associationId)
            ->where('key', $key)
            ->delete();

        return true;
    }
}
