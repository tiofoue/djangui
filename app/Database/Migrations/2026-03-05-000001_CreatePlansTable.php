<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlansTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Nom technique du plan (unique)
            'name' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            // Libellé affiché
            'label' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            // Prix mensuel
            'price_monthly' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => false, 'default' => '0.00'],
            // Nombre max d'entités (NULL = illimité)
            'max_entities' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Nombre max de membres (NULL = illimité)
            'max_members' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Nombre max de tontines (NULL = illimité)
            'max_tontines' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Fonctionnalités incluses (JSON)
            'features' => ['type' => 'JSON', 'null' => false],
            // Plan actif ou archivé
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['name']);

        $this->forge->createTable('plans', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('plans', true);
    }
}
