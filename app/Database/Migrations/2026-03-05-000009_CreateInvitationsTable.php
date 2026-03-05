<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvitationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Association qui émet l'invitation
            'association_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Membre (bureau) qui a créé l'invitation
            'invited_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Téléphone de la personne invitée (optionnel si email fourni)
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            // Email de la personne invitée (optionnel si téléphone fourni)
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'default' => null],
            // Jeton d'invitation (unique, transmis par SMS ou email)
            'token' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            // Rôle proposé à l'invité
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['treasurer', 'secretary', 'auditor', 'member'],
                'null'       => false,
                'default'    => 'member',
            ],
            // État de l'invitation
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'accepted', 'expired'],
                'null'       => false,
                'default'    => 'pending',
            ],
            // Date d'expiration de l'invitation
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['token']);
        // Index composite pour lister les invitations en attente d'une association
        $this->forge->addKey(['association_id', 'status']);
        $this->forge->addKey('phone');
        $this->forge->addKey('email');
        $this->forge->addKey('expires_at');
        $this->forge->addKey('invited_by');

        // FK → associations (cascade suppression)
        $this->forge->addForeignKey('association_id', 'associations', 'id', 'CASCADE', 'CASCADE');
        // FK → users (protéger l'invitant contre la suppression)
        $this->forge->addForeignKey('invited_by', 'users', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('invitations', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('invitations', true);
    }
}
