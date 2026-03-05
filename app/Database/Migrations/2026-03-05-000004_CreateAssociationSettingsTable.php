<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssociationSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Association propriétaire du paramètre
            'association_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Clé du paramètre (mot réservé SQL, géré par CI4 DbForge)
            'key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            // Libellé lisible du paramètre
            'label' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            // Valeur stockée en texte
            'value' => ['type' => 'TEXT', 'null' => true, 'default' => null],
            // Paramètre personnalisé (1) ou système (0)
            'is_custom' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 0],
            // Pas de updated_at ni deleted_at (voulu)
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('association_id');
        // Unicité de la clé par association
        $this->forge->addUniqueKey(['association_id', 'key']);

        // FK → associations (cascade suppression)
        $this->forge->addForeignKey('association_id', 'associations', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('association_settings', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('association_settings', true);
    }
}
