<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssociationMembersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Association d'appartenance
            'association_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Membre utilisateur
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Rôle effectif dans cette association
            'effective_role' => [
                'type'       => 'ENUM',
                'constraint' => ['president', 'treasurer', 'secretary', 'auditor', 'censor', 'member'],
                'null'       => false,
                'default'    => 'member',
            ],
            // Date d'adhésion effective
            'joined_at' => ['type' => 'DATETIME', 'null' => false],
            // Date de départ (NULL = toujours membre)
            'left_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            // Adhésion active
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 1],
        ]);

        $this->forge->addKey('id', true);
        // Un user ne peut avoir qu'une adhésion par association
        $this->forge->addUniqueKey(['association_id', 'user_id']);
        $this->forge->addKey('user_id');
        // Index composite pour filtrer les membres actifs d'une association
        $this->forge->addKey(['association_id', 'is_active']);
        // Index composite pour filtrer par rôle dans une association
        $this->forge->addKey(['association_id', 'effective_role']);

        // FK → associations (cascade suppression)
        $this->forge->addForeignKey('association_id', 'associations', 'id', 'CASCADE', 'CASCADE');
        // FK → users (cascade suppression)
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('association_members', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('association_members', true);
    }
}
