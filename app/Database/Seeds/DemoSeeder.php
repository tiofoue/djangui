<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder de démonstration — Sprint 1.
 *
 * Insère :
 *   - 4 plans SaaS (free, starter, pro, federation)
 *   - 1 super-admin + 5 membres (phones fictifs Cameroun)
 *   - 1 tontine_group  (auto-approuvé, status = active)
 *   - 1 association    (status = active)
 *   - 2 souscriptions  (plan pro, status = trial, 30 jours)
 *   - association_members : admin = president des deux entités,
 *                           5 membres dans les deux entités
 *   - association_settings : timezone Africa/Douala pour chaque entité
 *
 * Idempotent : tronque les tables dans l'ordre inverse des FK avant insertion.
 *
 * Utilisation :
 *   php spark db:seed DemoSeeder
 */
class DemoSeeder extends Seeder
{
    // =========================================================================
    // Point d'entrée
    // =========================================================================

    public function run(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->truncateTables();

        $planIds   = $this->seedPlans($now);
        $userIds   = $this->seedUsers($now);
        $assocIds  = $this->seedAssociations($userIds['admin'], $now);
        $this->seedSubscriptions($assocIds, $planIds['pro'], $now);
        $this->seedAssociationMembers($assocIds, $userIds, $now);
        $this->seedAssociationSettings($assocIds);

        echo "DemoSeeder terminé.\n";
        echo "  Admin  : +237690000001 / admin@djangui.test  (mot de passe : Admin1234!)\n";
        echo "  Membres: +237690000002 … +237690000006\n";
    }

    // =========================================================================
    // Remise à zéro (ordre inverse FK)
    // =========================================================================

    private function truncateTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'association_settings',
            'subscriptions',
            'association_members',
            'invitations',
            'associations',
            'plans',
            'users',
        ] as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    // =========================================================================
    // Plans SaaS
    // =========================================================================

    /**
     * Insère les 4 plans et retourne un tableau name → id.
     *
     * @param string $now Horodatage UTC courant
     *
     * @return array<string, int>
     */
    private function seedPlans(string $now): array
    {
        $plans = [
            [
                'name'          => 'free',
                'label'         => 'Gratuit',
                'price_monthly' => '0.00',
                'max_entities'  => 1,
                'max_members'   => 15,
                'max_tontines'  => 1,
                'features'      => json_encode(['tontines']),
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'starter',
                'label'         => 'Starter',
                'price_monthly' => '2000.00',
                'max_entities'  => 1,
                'max_members'   => 50,
                'max_tontines'  => 3,
                'features'      => json_encode(['tontines', 'loans', 'solidarity', 'documents']),
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'pro',
                'label'         => 'Pro',
                'price_monthly' => '5000.00',
                'max_entities'  => 3,
                'max_members'   => null,
                'max_tontines'  => null,
                'features'      => json_encode([
                    'tontines', 'loans', 'solidarity', 'documents',
                    'bureau', 'elections', 'reports',
                ]),
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'federation',
                'label'         => 'Fédération',
                'price_monthly' => '15000.00',
                'max_entities'  => null,
                'max_members'   => null,
                'max_tontines'  => null,
                'features'      => json_encode([
                    'tontines', 'loans', 'solidarity', 'documents',
                    'bureau', 'elections', 'reports', 'federation',
                ]),
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        $this->db->table('plans')->insertBatch($plans);

        $rows = $this->db->table('plans')->select('id, name')->get()->getResultArray();
        $ids  = [];

        foreach ($rows as $row) {
            $ids[$row['name']] = (int) $row['id'];
        }

        return $ids;
    }

    // =========================================================================
    // Utilisateurs
    // =========================================================================

    /**
     * Insère l'admin et 5 membres. Retourne un tableau label → user_id.
     *
     * @param string $now Horodatage UTC courant
     *
     * @return array<string, int>
     */
    private function seedUsers(string $now): array
    {
        $password = password_hash('Admin1234!', PASSWORD_BCRYPT);

        $users = [
            // Super-admin plateforme
            [
                'uuid'              => $this->uuid(),
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'phone'             => '+237690000001',
                'email'             => 'admin@djangui.test',
                'password'          => $password,
                'language'          => 'fr',
                'is_active'         => 1,
                'is_super_admin'    => 1,
                'phone_verified_at' => $now,
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            // Membres démonstration
            [
                'uuid'              => $this->uuid(),
                'first_name'        => 'Hermann',
                'last_name'         => 'Tiofoue',
                'phone'             => '+237690000002',
                'email'             => 'hermann@djangui.test',
                'password'          => $password,
                'language'          => 'fr',
                'is_active'         => 1,
                'is_super_admin'    => 0,
                'phone_verified_at' => $now,
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'uuid'              => $this->uuid(),
                'first_name'        => 'Marie',
                'last_name'         => 'Nguemo',
                'phone'             => '+237690000003',
                'email'             => null,
                'password'          => $password,
                'language'          => 'fr',
                'is_active'         => 1,
                'is_super_admin'    => 0,
                'phone_verified_at' => $now,
                'email_verified_at' => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'uuid'              => $this->uuid(),
                'first_name'        => 'Paul',
                'last_name'         => 'Essama',
                'phone'             => '+237690000004',
                'email'             => null,
                'password'          => $password,
                'language'          => 'fr',
                'is_active'         => 1,
                'is_super_admin'    => 0,
                'phone_verified_at' => $now,
                'email_verified_at' => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'uuid'              => $this->uuid(),
                'first_name'        => 'Claudine',
                'last_name'         => 'Biyong',
                'phone'             => '+237690000005',
                'email'             => null,
                'password'          => $password,
                'language'          => 'en',
                'is_active'         => 1,
                'is_super_admin'    => 0,
                'phone_verified_at' => $now,
                'email_verified_at' => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'uuid'              => $this->uuid(),
                'first_name'        => 'Jean-Pierre',
                'last_name'         => 'Mvondo',
                'phone'             => '+237690000006',
                'email'             => null,
                'password'          => $password,
                'language'          => 'fr',
                'is_active'         => 1,
                'is_super_admin'    => 0,
                'phone_verified_at' => $now,
                'email_verified_at' => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
        ];

        $this->db->table('users')->insertBatch($users);

        // Récupère les IDs dans l'ordre d'insertion (par phone)
        $rows = $this->db->table('users')
            ->select('id, phone')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $ids = ['admin' => 0, 'members' => []];

        foreach ($rows as $row) {
            if ($row['phone'] === '+237690000001') {
                $ids['admin'] = (int) $row['id'];
            } else {
                $ids['members'][] = (int) $row['id'];
            }
        }

        return $ids;
    }

    // =========================================================================
    // Associations
    // =========================================================================

    /**
     * Insère 1 tontine_group et 1 association.
     * Retourne un tableau label → association_id.
     *
     * @param int    $adminId Identifiant du super-admin créateur
     * @param string $now     Horodatage UTC courant
     *
     * @return array<string, int>
     */
    private function seedAssociations(int $adminId, string $now): array
    {
        $associations = [
            [
                'uuid'       => $this->uuid(),
                'name'       => 'Tontine Demo',
                'slug'       => 'tontine-demo',
                'description' => 'Groupe de tontine de démonstration — Sprint 1',
                'country'    => 'CM',
                'currency'   => 'XAF',
                'type'       => 'tontine_group',
                // tontine_group auto-approuvé
                'status'     => 'active',
                'reviewed_by' => $adminId,
                'reviewed_at' => $now,
                'created_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid'       => $this->uuid(),
                'name'       => 'Association Demo',
                'slug'       => 'association-demo',
                'description' => 'Association de démonstration — Sprint 1',
                'country'    => 'CM',
                'currency'   => 'XAF',
                'type'       => 'association',
                'status'     => 'active',
                'reviewed_by' => $adminId,
                'reviewed_at' => $now,
                'created_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->db->table('associations')->insertBatch($associations);

        $rows = $this->db->table('associations')
            ->select('id, slug')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $ids = [];

        foreach ($rows as $row) {
            $ids[$row['slug']] = (int) $row['id'];
        }

        return $ids;
    }

    // =========================================================================
    // Souscriptions
    // =========================================================================

    /**
     * Insère 2 souscriptions plan Pro en trial (30 jours).
     *
     * @param array<string, int> $assocIds Identifiants associations (slug → id)
     * @param int                $planId   Identifiant du plan Pro
     * @param string             $now      Horodatage UTC courant
     */
    private function seedSubscriptions(array $assocIds, int $planId, string $now): void
    {
        $trialEnd = gmdate('Y-m-d H:i:s', strtotime('+30 days'));

        $subscriptions = [];

        foreach ($assocIds as $assocId) {
            $subscriptions[] = [
                'association_id'       => $assocId,
                'plan_id'              => $planId,
                'status'               => 'trial',
                'trial_ends_at'        => $trialEnd,
                'current_period_start' => $now,
                'current_period_end'   => $trialEnd,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        $this->db->table('subscriptions')->insertBatch($subscriptions);
    }

    // =========================================================================
    // Membres des associations
    // =========================================================================

    /**
     * Insère les membres : admin = president, 5 utilisateurs = member.
     * Les deux entités (tontine_group + association) reçoivent les mêmes membres.
     *
     * @param array<string, int>   $assocIds Identifiants associations (slug → id)
     * @param array<string, mixed> $userIds  Tableau {admin: int, members: int[]}
     * @param string               $now      Horodatage UTC courant
     */
    private function seedAssociationMembers(array $assocIds, array $userIds, string $now): void
    {
        $rows = [];

        foreach ($assocIds as $assocId) {
            // Admin = président
            $rows[] = [
                'association_id' => $assocId,
                'user_id'        => $userIds['admin'],
                'effective_role' => 'president',
                'joined_at'      => $now,
                'left_at'        => null,
                'is_active'      => 1,
            ];

            // 5 membres standards
            foreach ($userIds['members'] as $memberId) {
                $rows[] = [
                    'association_id' => $assocId,
                    'user_id'        => $memberId,
                    'effective_role' => 'member',
                    'joined_at'      => $now,
                    'left_at'        => null,
                    'is_active'      => 1,
                ];
            }
        }

        $this->db->table('association_members')->insertBatch($rows);
    }

    // =========================================================================
    // Paramètres des associations
    // =========================================================================

    /**
     * Insère les settings système par défaut pour chaque association.
     *
     * @param array<string, int> $assocIds Identifiants associations (slug → id)
     */
    private function seedAssociationSettings(array $assocIds): void
    {
        $rows = [];

        foreach ($assocIds as $assocId) {
            $rows[] = [
                'association_id' => $assocId,
                'key'            => 'timezone',
                'label'          => 'Fuseau horaire',
                'value'          => 'Africa/Douala',
                'is_custom'      => 0,
            ];
            $rows[] = [
                'association_id' => $assocId,
                'key'            => 'rotation_default_mode',
                'label'          => 'Mode de rotation par défaut',
                'value'          => 'random',
                'is_custom'      => 0,
            ];
            $rows[] = [
                'association_id' => $assocId,
                'key'            => 'late_penalty_type',
                'label'          => 'Type de pénalité retard',
                'value'          => 'percentage_per_month',
                'is_custom'      => 0,
            ];
            $rows[] = [
                'association_id' => $assocId,
                'key'            => 'late_penalty_value',
                'label'          => 'Valeur de la pénalité retard',
                'value'          => '0.05',
                'is_custom'      => 0,
            ];
        }

        $this->db->table('association_settings')->insertBatch($rows);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Génère un UUID v4 aléatoire.
     *
     * @return string UUID au format xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    private function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
