<?php

declare(strict_types=1);

namespace App\Modules\Auth\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Entité utilisateur pour le module Auth.
 *
 * Gère le hachage des mots de passe (bcrypt) et masque les
 * champs sensibles dans les représentations publiques.
 */
class UserEntity extends Entity
{
    /**
     * Casts de types automatiques appliqués lors de l'hydratation.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id'             => 'integer',
        'is_active'      => 'boolean',
        'is_super_admin' => 'boolean',
    ];

    /**
     * Champs cachés dans toArray() / toJson().
     *
     * @var list<string>
     */
    protected $hiddenFields = [
        'password',
        'deleted_at',
    ];

    // -------------------------------------------------------------------------
    // Gestion du mot de passe
    // -------------------------------------------------------------------------

    /**
     * Hache un mot de passe en clair avec bcrypt et le persiste dans l'entité.
     *
     * Appelé automatiquement par CI4 Entity lors de l'affectation du champ
     * 'password' (setPassword('...')).
     *
     * @param string $plain Mot de passe en clair (min 8 caractères)
     *
     * @return static
     */
    public function setPassword(string $plain): static
    {
        $this->attributes['password'] = password_hash($plain, PASSWORD_BCRYPT);

        return $this;
    }

    /**
     * Vérifie qu'un mot de passe en clair correspond au hash bcrypt stocké.
     *
     * @param string $plain Mot de passe en clair soumis par l'utilisateur
     *
     * @return bool True si le mot de passe est correct
     */
    public function verifyPassword(string $plain): bool
    {
        $hash = $this->attributes['password'] ?? '';

        return password_verify($plain, (string) $hash);
    }

    // -------------------------------------------------------------------------
    // Représentation publique
    // -------------------------------------------------------------------------

    /**
     * Retourne les données de l'utilisateur sans les champs sensibles.
     *
     * Champs exclus : password, deleted_at.
     * Utilisé dans les réponses API (profil, tokens, etc.).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $data = $this->toArray();

        unset($data['password'], $data['deleted_at']);

        return $data;
    }
}
