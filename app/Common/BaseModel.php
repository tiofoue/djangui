<?php

declare(strict_types=1);

namespace App\Common;

use CodeIgniter\Model;

/**
 * Modèle de base pour tous les modèles HMVC de Djangui.
 *
 * Fournit le scoping multi-tenant automatique par `association_id`.
 *
 * RÈGLE ABSOLUE : Ne JAMAIS contourner ce scope.
 * L'`association_id` doit TOUJOURS être extrait du JWT (token authentifié),
 * jamais depuis le body de la requête ou un paramètre utilisateur.
 *
 * Usage standard :
 *   $model->forAssociation($assocId)->findAll();
 *   $model->forAssociation($assocId)->find($id);
 */
abstract class BaseModel extends Model
{
    /**
     * Activation des timestamps automatiques (created_at / updated_at).
     *
     * @var bool
     */
    protected $useTimestamps = true;

    /**
     * Activation de la suppression douce (soft delete).
     *
     * @var bool
     */
    protected $useSoftDeletes = true;

    /**
     * Colonne utilisée pour la suppression douce.
     *
     * @var string
     */
    protected $deletedField = 'deleted_at';

    /**
     * Identifiant de l'association courante pour le scoping multi-tenant.
     * Null signifie qu'aucun scope n'a encore été défini (erreur en production).
     *
     * @var int|null
     */
    protected ?int $associationId = null;

    /**
     * Indique si ce modèle est scopé par association_id.
     * Mettre à false pour les tables globales (ex : users, plans).
     *
     * @var bool
     */
    protected bool $scopedByAssociation = true;

    // -------------------------------------------------------------------------
    // Gestion du scope tenant
    // -------------------------------------------------------------------------

    /**
     * Définit l'association courante pour le scoping multi-tenant.
     * Toutes les requêtes suivantes seront filtrées par cet `association_id`.
     *
     * @param int $associationId Identifiant de l'association (extrait du JWT)
     *
     * @return static
     */
    public function setAssociationId(int $associationId): static
    {
        $this->associationId = $associationId;

        return $this;
    }

    /**
     * Alias fluent pour setAssociationId().
     * Permet une syntaxe plus lisible : $model->forAssociation($id)->findAll()
     *
     * @param int $associationId Identifiant de l'association (extrait du JWT)
     *
     * @return static
     */
    public function forAssociation(int $associationId): static
    {
        return $this->setAssociationId($associationId);
    }

    /**
     * Applique le filtre WHERE association_id si le modèle est scopé par tenant.
     * Lève une RuntimeException si le scope est attendu mais non défini,
     * afin d'éviter toute fuite de données inter-tenant silencieuse.
     *
     * @throws \RuntimeException Si scopedByAssociation=true et associationId non défini
     *
     * @return static
     */
    protected function applyTenantScope(): static
    {
        if (! $this->scopedByAssociation) {
            return $this;
        }

        if ($this->associationId === null) {
            throw new \RuntimeException(
                static::class . '::applyTenantScope() — associationId non défini. '
                . 'Appeler forAssociation($id) avant toute requête.'
            );
        }

        $this->where($this->table . '.association_id', $this->associationId);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Override des méthodes de lecture CI4 avec scope tenant
    // -------------------------------------------------------------------------

    /**
     * Récupère un enregistrement par son ID, scopé par association_id.
     *
     * @param int|string|int[]|string[]|null $id Identifiant(s) à rechercher
     *
     * @return array<string, mixed>|object|null Enregistrement ou null si non trouvé
     */
    public function find($id = null): mixed
    {
        $this->applyTenantScope();

        return parent::find($id);
    }

    /**
     * Récupère tous les enregistrements, scopés par association_id.
     *
     * @param int $limit  Nombre maximum d'enregistrements (0 = illimité)
     * @param int $offset Décalage pour la pagination
     *
     * @return array<int, array<string, mixed>|object> Liste des enregistrements
     */
    public function findAll(int $limit = 0, int $offset = 0): array
    {
        $this->applyTenantScope();

        return parent::findAll($limit, $offset);
    }
}
