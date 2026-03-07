<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Common\BaseModel;
use App\Modules\Auth\Entities\UserEntity;

/**
 * Modèle pour la table `users`.
 *
 * Table globale (non scopée par association_id) car un utilisateur
 * peut appartenir à plusieurs associations simultanément.
 *
 * Le scoping multi-tenant est géré au niveau du module Association
 * via la table de jointure `association_members`.
 */
class UserModel extends BaseModel
{
    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var string
     */
    protected $returnType = UserEntity::class;

    /**
     * Table globale — pas de scoping par association_id.
     *
     * @var bool
     */
    protected bool $scopedByAssociation = false;

    /**
     * @var bool
     */
    protected $useSoftDeletes = true;

    /**
     * @var bool
     */
    protected $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected $allowedFields = [
        'uuid',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'avatar',
        'language',
        'is_active',
        'is_super_admin',
        'phone_verified_at',
        'email_verified_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $validationRules = [
        'first_name' => 'required|max_length[100]',
        'last_name'  => 'required|max_length[100]',
        'phone'      => 'required|max_length[20]|is_unique[users.phone,id,{id}]',
        'email'      => 'permit_empty|valid_email|max_length[191]|is_unique[users.email,id,{id}]',
        'password'   => 'required|min_length[8]',
        'language'   => 'permit_empty|in_list[fr,en]',
    ];

    /**
     * @var array<string, string>
     */
    protected $validationMessages = [
        'phone' => [
            'is_unique' => 'Ce numéro de téléphone est déjà utilisé.',
        ],
        'email' => [
            'is_unique'    => 'Cette adresse email est déjà utilisée.',
            'valid_email'  => 'L\'adresse email n\'est pas valide.',
        ],
    ];

    // -------------------------------------------------------------------------
    // Méthodes de recherche
    // -------------------------------------------------------------------------

    /**
     * Recherche un utilisateur par son numéro de téléphone.
     *
     * @param string $phone Numéro au format E.164 (+237XXXXXXXXX)
     *
     * @return UserEntity|null Entité utilisateur ou null si non trouvé
     */
    public function findByPhone(string $phone): ?UserEntity
    {
        /** @var UserEntity|null */
        return $this->where('phone', $phone)->first();
    }

    /**
     * Recherche un utilisateur par son adresse email.
     *
     * @param string $email Adresse email
     *
     * @return UserEntity|null Entité utilisateur ou null si non trouvé
     */
    public function findByEmail(string $email): ?UserEntity
    {
        /** @var UserEntity|null */
        return $this->where('email', $email)->first();
    }

    /**
     * Recherche un utilisateur par téléphone ou email.
     *
     * La recherche par téléphone est prioritaire : si l'identifiant
     * correspond à un numéro existant, on retourne cet utilisateur.
     * Sinon on tente une recherche par email.
     *
     * @param string $identifier Numéro de téléphone ou adresse email
     *
     * @return UserEntity|null Entité utilisateur ou null si non trouvé
     */
    public function findByPhoneOrEmail(string $identifier): ?UserEntity
    {
        $user = $this->findByPhone($identifier);
        if ($user !== null) {
            return $user;
        }

        return $this->findByEmail($identifier);
    }
}
