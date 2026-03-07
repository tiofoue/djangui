<?php

declare(strict_types=1);

namespace App\Modules\Associations\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Entité Association.
 * Casts automatiques pour is_active, parent_id, etc.
 * toPublicArray() retire les champs internes (reviewed_by, deleted_at).
 */
class AssociationEntity extends Entity
{
    /**
     * Casts de colonnes pour la conversion automatique des types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id'        => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * Champs masqués lors de la conversion en tableau.
     *
     * @var list<string>
     */
    protected $hiddenFields = ['deleted_at'];

    /**
     * Retourne true si l'association est active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->attributes['status'] === 'active';
    }

    /**
     * Retourne les données publiques de l'association (sans deleted_at).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $data = $this->toArray();
        unset($data['deleted_at']);

        return $data;
    }
}
