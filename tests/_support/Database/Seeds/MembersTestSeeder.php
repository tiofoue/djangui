<?php

declare(strict_types=1);

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Fixture de test pour le module Members.
 *
 * Crée un jeu de données minimal et reproductible :
 *   - 1 plan pro
 *   - 6 utilisateurs : president, secretary, auditor, member, outsider, future_member
 *   - 1 association standard (type=association)
 *   - 1 tontine_group (type=tontine_group)
 *   - Membres dans les deux entités
 *
 * Les IDs auto-incrémentés sont récupérables via la DB après insertion.
 * Ce seeder est idempotent : tronque les tables avant chaque exécution.
 */
class MembersTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['association_settings', 'subscriptions', 'association_members', 'invitations', 'associations', 'plans', 'users'] as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Plan
        $this->db->table('plans')->insert([
            'name'          => 'pro',
            'label'         => 'Pro',
            'price_monthly' => '5000.00',
            'features'      => json_encode(['tontines', 'loans', 'solidarity', 'bureau']),
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $planId = $this->db->insertID();

        // Utilisateurs
        $hash  = password_hash('Test1234!', PASSWORD_BCRYPT);
        $users = [
            'president'     => ['+237610000001', 'Alice', 'President', null],
            'secretary'     => ['+237610000002', 'Bob',   'Secretary', 'bob@test.local'],
            'auditor'       => ['+237610000003', 'Carol', 'Auditor',   null],
            'member'        => ['+237610000004', 'Dan',   'Member',    null],
            'outsider'      => ['+237610000005', 'Eve',   'Outsider',  null],
            'future_member' => ['+237610000006', 'Frank', 'Future',    'frank@test.local'],
        ];

        $userIds = [];

        foreach ($users as $key => [$phone, $first, $last, $email]) {
            $this->db->table('users')->insert([
                'uuid'              => $this->uuid(),
                'first_name'        => $first,
                'last_name'         => $last,
                'phone'             => $phone,
                'email'             => $email,
                'password'          => $hash,
                'language'          => 'fr',
                'is_active'         => 1,
                'is_super_admin'    => 0,
                'phone_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $userIds[$key] = $this->db->insertID();
        }

        // Associations
        $this->db->table('associations')->insert([
            'uuid'        => $this->uuid(),
            'name'        => 'Association Test',
            'slug'        => 'association-test',
            'country'     => 'CM',
            'currency'    => 'XAF',
            'type'        => 'association',
            'status'      => 'active',
            'reviewed_by' => $userIds['president'],
            'reviewed_at' => $now,
            'created_by'  => $userIds['president'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $assocId = $this->db->insertID();

        $this->db->table('associations')->insert([
            'uuid'        => $this->uuid(),
            'name'        => 'Tontine Test',
            'slug'        => 'tontine-test',
            'country'     => 'CM',
            'currency'    => 'XAF',
            'type'        => 'tontine_group',
            'status'      => 'active',
            'reviewed_by' => $userIds['president'],
            'reviewed_at' => $now,
            'created_by'  => $userIds['president'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $tontineId = $this->db->insertID();

        // Souscriptions
        $trialEnd = gmdate('Y-m-d H:i:s', strtotime('+30 days'));

        foreach ([$assocId, $tontineId] as $aId) {
            $this->db->table('subscriptions')->insert([
                'association_id'       => $aId,
                'plan_id'              => $planId,
                'status'               => 'trial',
                'trial_ends_at'        => $trialEnd,
                'current_period_start' => $now,
                'current_period_end'   => $trialEnd,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }

        // Membres de l'association standard
        $assocMembers = [
            [$assocId, $userIds['president'], 'president'],
            [$assocId, $userIds['secretary'], 'secretary'],
            [$assocId, $userIds['auditor'],   'auditor'],
            [$assocId, $userIds['member'],    'member'],
        ];

        // Membres du tontine_group (rôles limités : president, treasurer, member)
        $tontineMembers = [
            [$tontineId, $userIds['president'], 'president'],
            [$tontineId, $userIds['member'],    'member'],
        ];

        foreach (array_merge($assocMembers, $tontineMembers) as [$aId, $uId, $role]) {
            $this->db->table('association_members')->insert([
                'association_id' => $aId,
                'user_id'        => $uId,
                'effective_role' => $role,
                'joined_at'      => $now,
                'is_active'      => 1,
            ]);
        }
    }

    private function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
